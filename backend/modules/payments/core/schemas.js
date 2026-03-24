import { sanitizeInteger, sanitizeNumber, sanitizeString } from '../../../sanitize.js';

export function sanitizeCreatePayload(body = {}) {
  return {
    orderId: sanitizeInteger(body.order_id),
    paymentMethod: sanitizeString(body.payment_method || 'pix').toLowerCase()
  };
}

export function sanitizeRefundPayload(body = {}) {
  const paymentId = sanitizeInteger(body.payment_id);
  const orderId = sanitizeInteger(body.order_id);
  const reason = sanitizeString(body.reason || 'refund_total');
  const requestedAmountRaw = body.amount;

  const requestedAmount =
    requestedAmountRaw === undefined || requestedAmountRaw === null
      ? null
      : sanitizeNumber(requestedAmountRaw);

  return { paymentId, orderId, reason, requestedAmount };
}
