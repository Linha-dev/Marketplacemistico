import { normalizePaymentStatus } from '../../../services/payments/payment-status-machine.js';
import { processRefundForPayment } from '../../../services/payments/refund-service.js';
import { recordAuditLog } from '../../../services/audit/audit-service.js';
import {
  CANCEL_ALLOWED_STATUSES,
  RETURN_ALLOWED_STATUSES,
  VALID_STATUSES,
  sanitizeOrderCreationPayload,
  sanitizeOrderId,
  sanitizePagination,
  sanitizePostSalePayload,
  sanitizeProductItems,
  sanitizeStatusPayload
} from './schemas.js';
import {
  countOrdersByBuyerId,
  decreaseProductStock,
  findLatestPaymentForOrder,
  findLockedProductsByIds,
  findOrderBasicById,
  findOrderByIdForUser,
  findOrderForPostSale,
  findOrderItems,
  findOrderWithItemsById,
  findOrdersByBuyerId,
  findSellerByUserId,
  findSellerOrderStatus,
  insertOrder,
  insertOrderItem,
  insertOrderPostSaleEvent,
  runInTransaction,
  updateOrderPostSale,
  updateOrderStatus
} from './repository.js';

function createBusinessError(code, message) {
  const error = new Error(message);
  error.code = code;
  return error;
}

export async function listOrdersForBuyer(userId, query) {
  const { page, limit, offset } = sanitizePagination(query);
  const total = await countOrdersByBuyerId(userId);
  const orders = await findOrdersByBuyerId(userId, limit, offset);

  return {
    orders,
    pagination: {
      page,
      limit,
      total,
      totalPages: Math.ceil(total / limit)
    }
  };
}

export async function createOrder(userId, body) {
  const payload = sanitizeOrderCreationPayload(body);

  if (!Array.isArray(payload.items) || payload.items.length === 0) {
    throw createBusinessError('VALIDATION_ERROR', 'Itens do pedido sao obrigatorios');
  }

  const sanitizedItems = sanitizeProductItems(payload.items);
  const productIds = sanitizedItems.map(i => i.productId).filter(Boolean);
  if (productIds.length !== sanitizedItems.length) {
    throw createBusinessError('VALIDATION_ERROR', 'IDs de produtos invalidos');
  }

  const uniqueProductIds = [...new Set(productIds)];

  return runInTransaction(async (tx) => {
    const lockedProductsResult = await findLockedProductsByIds(tx, uniqueProductIds);
    const lockedProducts = lockedProductsResult.rows;

    if (lockedProducts.length !== uniqueProductIds.length) {
      throw createBusinessError('NOT_FOUND', 'Um ou mais produtos nao foram encontrados');
    }

    const sellerIds = new Set(lockedProducts.map((product) => product.seller_id));
    if (sellerIds.size !== 1) {
      throw createBusinessError('MULTI_SELLER_NOT_ALLOWED', 'No MVP, o carrinho aceita produtos de apenas um vendedor por vez');
    }

    const sellerId = lockedProducts[0].seller_id;
    const sellerUserId = lockedProducts[0].seller_user_id;
    if (userId === sellerUserId) {
      throw createBusinessError('FORBIDDEN', 'Vendedor nao pode comprar seus proprios produtos');
    }

    const productById = new Map(lockedProducts.map((product) => [product.id, product]));
    const orderItems = sanitizedItems.map((item) => {
      const product = productById.get(item.productId);

      if (!product) {
        throw createBusinessError('NOT_FOUND', `Produto ${item.productId} nao encontrado`);
      }

      if (!product.publicado) {
        throw createBusinessError('PRODUCT_UNAVAILABLE', `Produto ${product.id} nao esta disponivel`);
      }

      return {
        product,
        quantidade: item.quantidade,
        preco: parseFloat(product.preco),
        nameSnapshot: product.nome,
        weightSnapshot: product.weight_kg,
        dimensionSnapshot: {
          height_cm: product.height_cm,
          width_cm: product.width_cm,
          length_cm: product.length_cm
        }
      };
    });

    const requestedStockByProduct = new Map();
    for (const item of orderItems) {
      const currentQty = requestedStockByProduct.get(item.product.id) || 0;
      requestedStockByProduct.set(item.product.id, currentQty + item.quantidade);
    }

    for (const [productId, requestedQty] of requestedStockByProduct.entries()) {
      const product = productById.get(productId);
      if (sanitizeOrderId(product.estoque) < requestedQty) {
        throw createBusinessError('INSUFFICIENT_STOCK', `Estoque insuficiente para produto ${productId}`);
      }
    }

    const itemsSubtotal = orderItems.reduce((sum, item) => sum + item.preco * item.quantidade, 0);
    const grandTotal = Math.max(0, itemsSubtotal + payload.shippingTotal - payload.discountTotal);

    const orderInsert = await insertOrder(tx, {
      buyerId: userId,
      sellerId,
      grandTotal,
      itemsSubtotal,
      shippingTotal: payload.shippingTotal,
      discountTotal: payload.discountTotal,
      shippingQuoteId: payload.shippingQuoteId,
      shippingAddressSnapshot: payload.shippingAddressSnapshot,
      billingAddressSnapshot: payload.billingAddressSnapshot
    });
    const orderId = orderInsert.rows[0].id;

    for (const item of orderItems) {
      await insertOrderItem(tx, {
        orderId,
        sellerId,
        productId: item.product.id,
        quantidade: item.quantidade,
        preco: item.preco,
        nameSnapshot: item.nameSnapshot,
        weightSnapshot: item.weightSnapshot,
        dimensionSnapshot: item.dimensionSnapshot
      });
    }

    for (const [productId, requestedQty] of requestedStockByProduct.entries()) {
      const stockUpdate = await decreaseProductStock(tx, requestedQty, productId);
      if (stockUpdate.rowCount === 0) {
        throw createBusinessError('INSUFFICIENT_STOCK', `Estoque insuficiente para produto ${productId}`);
      }
    }

    const orderQuery = await findOrderWithItemsById(tx, orderId);
    return orderQuery.rows[0];
  });
}

export async function getOrderById(userId, rawId) {
  const orderId = sanitizeOrderId(rawId);
  if (!orderId) {
    throw createBusinessError('INVALID_ID', 'ID inválido');
  }

  const orders = await findOrderByIdForUser(orderId, userId);
  if (orders.length === 0) {
    throw createBusinessError('NOT_FOUND', 'Pedido não encontrado ou sem permissão');
  }

  const items = await findOrderItems(orderId);
  return { ...orders[0], items };
}

export async function patchOrderStatus(userId, rawOrderId, body) {
  const orderId = sanitizeOrderId(rawOrderId);
  if (!orderId) {
    throw createBusinessError('INVALID_ID', 'ID invalido');
  }

  const status = sanitizeStatusPayload(body);
  if (!VALID_STATUSES.includes(status)) {
    throw createBusinessError('VALIDATION_ERROR', `Status invalido. Valores aceitos: ${VALID_STATUSES.join(', ')}`);
  }

  const sellers = await findSellerByUserId(userId);
  if (sellers.length === 0) {
    throw createBusinessError('FORBIDDEN', 'Acesso restrito a vendedores');
  }
  const sellerId = sellers[0].id;

  const existing = await findSellerOrderStatus(orderId, sellerId);
  if (existing.length === 0) {
    throw createBusinessError('NOT_FOUND', 'Pedido nao encontrado ou sem permissao');
  }

  const beforeOrder = existing[0];
  const result = await updateOrderStatus(orderId, sellerId, status);
  const updated = result[0];

  await recordAuditLog({
    actorUserId: userId,
    action: 'order.status_changed',
    resourceType: 'order',
    resourceId: orderId,
    before: {
      status: beforeOrder.status,
      shipping_status: beforeOrder.shipping_status,
      payment_status: beforeOrder.payment_status
    },
    after: {
      status: updated.status,
      shipping_status: updated.shipping_status,
      payment_status: updated.payment_status
    },
    metadata: {
      updated_by_seller_id: sellerId
    }
  });

  return updated;
}

export async function createPostSale(userId, rawOrderId, body) {
  const orderId = sanitizeOrderId(rawOrderId);
  const { action, reason } = sanitizePostSalePayload(body);

  if (!orderId) {
    throw createBusinessError('VALIDATION_ERROR', 'id do pedido invalido');
  }

  if (!['cancel', 'return_request'].includes(action)) {
    throw createBusinessError('VALIDATION_ERROR', 'action deve ser cancel ou return_request');
  }

  return runInTransaction(async (tx) => {
    const orderResult = await findOrderForPostSale(tx, orderId);

    if (orderResult.rows.length === 0) {
      throw createBusinessError('NOT_FOUND', 'Pedido nao encontrado');
    }

    const order = orderResult.rows[0];
    if (order.comprador_id !== userId) {
      throw createBusinessError('FORBIDDEN', 'Apenas o comprador pode solicitar cancelamento/devolucao');
    }

    const previousStatus = order.status;
    const previousShippingStatus = order.shipping_status;

    let nextStatus = order.status;
    let nextShippingStatus = order.shipping_status;

    if (action === 'cancel') {
      if (!CANCEL_ALLOWED_STATUSES.has(order.status)) {
        throw createBusinessError('CANCEL_NOT_ALLOWED', 'Cancelamento permitido apenas antes do envio');
      }
      nextStatus = 'cancelado';
      nextShippingStatus = 'cancelled';
    }

    if (action === 'return_request') {
      if (!RETURN_ALLOWED_STATUSES.has(order.status)) {
        throw createBusinessError('RETURN_NOT_ALLOWED', 'Devolucao permitida apenas para pedido entregue');
      }
      nextStatus = 'devolvido';
      nextShippingStatus = 'returned';
    }

    await updateOrderPostSale(tx, orderId, nextStatus, nextShippingStatus);

    const paymentResult = await findLatestPaymentForOrder(tx, orderId);

    let refund = null;
    if (paymentResult.rows.length > 0) {
      const payment = paymentResult.rows[0];
      const normalizedPaymentStatus = normalizePaymentStatus(payment.status);

      if (['approved', 'partially_refunded'].includes(normalizedPaymentStatus)) {
        const refundResult = await processRefundForPayment({
          tx,
          payment,
          requestedAmount: null,
          reason: action === 'cancel' ? 'cancelamento_pedido' : 'devolucao_pedido',
          requestedByUserId: userId
        });
        refund = refundResult.refund;
      }
    }

    await insertOrderPostSaleEvent(tx, {
      orderId,
      action,
      previousStatus,
      nextStatus,
      previousShippingStatus,
      nextShippingStatus,
      reason,
      userId,
      refundId: refund?.id || null,
      metadata: { payment_status_before: order.payment_status }
    });

    await recordAuditLog({
      db: tx,
      actorUserId: userId,
      action: `order.post_sale.${action}`,
      resourceType: 'order',
      resourceId: orderId,
      before: {
        status: previousStatus,
        shipping_status: previousShippingStatus,
        payment_status: order.payment_status
      },
      after: {
        status: nextStatus,
        shipping_status: nextShippingStatus
      },
      metadata: {
        reason: reason || null,
        refund_id: refund?.id || null
      }
    });

    const finalOrderResult = await findOrderBasicById(tx, orderId);

    return {
      order: finalOrderResult.rows[0],
      refund: refund || null,
      action
    };
  });
}
