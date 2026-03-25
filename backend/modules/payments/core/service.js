import { sanitizeNumber } from '../../../sanitize.js';
import { sanitizeCreatePayload, sanitizeRefundPayload } from './schemas.js';
import {
  findBuyerById,
  findOrderForPayment,
  findRefundablePayment,
  insertManualPayout,
  insertPayment,
  insertPaymentSplit,
  runInTransaction
} from './repository.js';

function createBusinessError(code, message) {
  const error = new Error(message);
  error.code = code;
  return error;
}

export async function createPayment(userId, body, correlationId = null) {
  const [{ createEfiCharge }, { normalizePaymentStatus }, { recordPaymentLedgerEntries }, { incrementMetric }, { logError, logInfo }] = await Promise.all([
    import('../../../services/payments/efi-service.js'),
    import('../../../services/payments/payment-status-machine.js'),
    import('../../../services/finance/ledger-service.js'),
    import('../../../observability/metrics-store.js'),
    import('../../../observability/logger.js')
  ]);

  incrementMetric('payments.create.request.total');

  try {
    const { orderId, paymentMethod } = sanitizeCreatePayload(body);

    if (!orderId) {
      throw createBusinessError('VALIDATION_ERROR', 'order_id obrigatorio');
    }

    const orders = await findOrderForPayment(orderId, userId);
    if (orders.length === 0) {
      throw createBusinessError('NOT_FOUND', 'Pedido nao encontrado');
    }

    const buyers = await findBuyerById(userId);
    if (buyers.length === 0) {
      throw createBusinessError('NOT_FOUND', 'Comprador nao encontrado');
    }

    const order = orders[0];
    const amount = sanitizeNumber(order.grand_total || order.total) || 0;

    const charge = await createEfiCharge({
      order: { ...order, amount },
      buyer: buyers[0],
      seller: order,
      paymentMethod
    });

    const normalizedStatus = normalizePaymentStatus(charge.status);

    const paymentResult = await insertPayment({
      orderId,
      providerChargeId: charge.providerChargeId,
      paymentMethod: charge.paymentMethod,
      status: normalizedStatus,
      amount,
      rawResponse: charge.raw,
      paidAt: normalizedStatus === 'approved' ? new Date().toISOString() : null
    });

    const payment = paymentResult[0];

    const commissionRate = sanitizeNumber(order.commission_rate) ?? 0.12;
    const manualFeeRate = sanitizeNumber(order.manual_payout_fee_rate) ?? 0;
    const platformFeeAmount = amount * commissionRate;
    const operationalFeeAmount = charge.splitMode === 'manual' ? amount * manualFeeRate : 0;
    const sellerNetAmount = Math.max(0, amount - platformFeeAmount - operationalFeeAmount);

    await insertPaymentSplit({
      paymentId: payment.id,
      sellerId: order.seller_id,
      splitMode: charge.splitMode,
      grossAmount: amount,
      platformFeeAmount,
      operationalFeeAmount,
      sellerNetAmount,
      efiPayeeCodeSnapshot: charge.splitRecipientCode || null,
      status: normalizedStatus === 'approved' ? 'ready' : 'pending'
    });

    if (normalizedStatus === 'approved') {
      await recordPaymentLedgerEntries({
        orderId,
        paymentId: payment.id,
        grossAmount: amount,
        platformFeeAmount,
        sellerNetAmount,
        splitMode: charge.splitMode
      });
    }

    if (charge.splitMode === 'manual') {
      await insertManualPayout({
        sellerId: order.seller_id,
        orderId,
        amount: sellerNetAmount,
        feeAmount: operationalFeeAmount
      });
    }

    incrementMetric('payments.create.success.total');

    return {
      payment,
      pixQrCode: charge.pixQrCode,
      pixCopyPaste: charge.pixCopyPaste,
      splitMode: charge.splitMode
    };
  } catch (error) {
    incrementMetric('payments.create.error.total');
    logError('payments.create.error', error, {
      correlation_id: correlationId
    });
    throw error;
  } finally {
    logInfo('payments.create.completed', {
      correlation_id: correlationId
    });
  }
}

export async function createRefund(userId, body) {
  const { paymentId, orderId, reason, requestedAmount } = sanitizeRefundPayload(body);

  if (!paymentId && !orderId) {
    throw createBusinessError('VALIDATION_ERROR', 'payment_id ou order_id obrigatorio');
  }

  if (requestedAmount !== null && requestedAmount <= 0) {
    throw createBusinessError('VALIDATION_ERROR', 'amount deve ser maior que zero');
  }

  const { processRefundForPayment } = await import('../../../services/payments/refund-service.js');

  return runInTransaction(async (tx) => {
    const paymentResult = await findRefundablePayment(tx, paymentId || orderId, userId, Boolean(paymentId));

    if (paymentResult.rows.length === 0) {
      throw createBusinessError('NOT_FOUND', 'Pagamento nao encontrado ou sem permissao');
    }

    const payment = paymentResult.rows[0];
    const refundResult = await processRefundForPayment({
      tx,
      payment,
      requestedAmount,
      reason,
      requestedByUserId: userId
    });

    return {
      refund: refundResult.refund,
      payment: {
        id: payment.id,
        order_id: payment.order_id,
        status: refundResult.paymentStatus
      },
      refundable_before: refundResult.refundableBefore,
      refundable_after: refundResult.refundableAfter,
      provider: refundResult.provider
    };
  });
}
