import { sendError, sendSuccess } from '../../../response.js';
import { createPayment, createRefund } from './service.js';

function statusForCode(code) {
  if (code === 'NOT_FOUND') return 404;
  if (code === 'METHOD_NOT_ALLOWED') return 405;
  if ([
    'VALIDATION_ERROR',
    'INVALID_PAYMENT_STATUS',
    'NO_REFUNDABLE_BALANCE',
    'INVALID_REFUND_AMOUNT',
    'REFUND_AMOUNT_EXCEEDS_BALANCE',
    'UNSUPPORTED_PROVIDER',
    'INVALID_PAYMENT_STATUS_TRANSITION'
  ].includes(code)) return 400;
  return 500;
}

function handleError(res, error, fallbackMessage) {
  const code = error.code || 'INTERNAL_ERROR';
  return sendError(res, code, error.message || fallbackMessage, statusForCode(code));
}

export async function createPaymentController(req, res) {
  try {
    if (req.method !== 'POST') {
      return sendError(res, 'METHOD_NOT_ALLOWED', 'Metodo nao permitido', 405);
    }

    const payload = await createPayment(req.user.id, req.body, req.correlationId || null);
    return sendSuccess(res, payload, 201);
  } catch (error) {
    return handleError(res, error, 'Erro ao criar cobranca de pagamento');
  }
}

export async function createRefundController(req, res) {
  try {
    if (req.method !== 'POST') {
      return sendError(res, 'METHOD_NOT_ALLOWED', 'Metodo nao permitido', 405);
    }

    const payload = await createRefund(req.user.id, req.body);
    return sendSuccess(res, payload, 201);
  } catch (error) {
    return handleError(res, error, 'Erro ao processar refund');
  }
}
