import { sanitizeInteger, sanitizeNumber, sanitizeString } from '../../../sanitize.js';

export const VALID_STATUSES = ['pendente', 'confirmado', 'enviado', 'entregue', 'cancelado', 'devolvido'];
export const CANCEL_ALLOWED_STATUSES = new Set(['pendente', 'confirmado']);
export const RETURN_ALLOWED_STATUSES = new Set(['entregue']);

export function sanitizePagination(query = {}) {
  const page = Math.max(1, sanitizeInteger(query.page) || 1);
  const limit = Math.min(100, Math.max(1, sanitizeInteger(query.limit) || 20));
  return { page, limit, offset: (page - 1) * limit };
}

export function sanitizeOrderId(rawId) {
  return sanitizeInteger(rawId);
}

export function sanitizeOrderCreationPayload(body = {}) {
  const shippingTotal = sanitizeNumber(body.shipping_total);
  const discountTotal = sanitizeNumber(body.discount_total);

  return {
    items: body.items,
    shippingQuoteId: sanitizeInteger(body.shipping_quote_id),
    shippingTotal: shippingTotal !== null && shippingTotal >= 0 ? shippingTotal : 0,
    discountTotal: discountTotal !== null && discountTotal >= 0 ? discountTotal : 0,
    shippingAddressSnapshot: body.shipping_address_snapshot,
    billingAddressSnapshot: body.billing_address_snapshot
  };
}

export function sanitizePostSalePayload(body = {}) {
  return {
    action: sanitizeString(body.action || '').toLowerCase(),
    reason: sanitizeString(body.reason || '')
  };
}

export function sanitizeStatusPayload(body = {}) {
  return sanitizeString(body.status);
}

export function sanitizeProductItems(items = []) {
  return items.map((item) => ({
    productId: sanitizeInteger(item.product_id),
    quantidade: Math.max(1, sanitizeInteger(item.quantidade) || 1)
  }));
}
