import { sanitizeBoolean, sanitizeInteger, sanitizeString } from '../../../sanitize.js';

export function sanitizeRetryLimit(body = {}) {
  return Math.min(100, Math.max(1, sanitizeInteger(body.limit) || 10));
}

export function sanitizeReprocessPayload(body = {}, query = {}) {
  return {
    eventId: sanitizeInteger(body.event_id || query.event_id),
    force: sanitizeBoolean(body.force || query.force)
  };
}

export function sanitizeWebhookSecretHeader(headers = {}) {
  return sanitizeString(headers['x-webhook-secret']);
}

export function normalizeShippingStatus(rawStatus) {
  const value = sanitizeString(rawStatus || '').toLowerCase();

  if (!value) return 'pending';
  if (['pending', 'label_generated', 'posted', 'in_transit', 'delivered', 'cancelled', 'returned'].includes(value)) {
    return value;
  }
  if (value.includes('entreg') || value.includes('delivered')) return 'delivered';
  if (value.includes('transit') || value.includes('transito')) return 'in_transit';
  if (value.includes('post') || value.includes('ship') || value.includes('enviad')) return 'posted';
  if (value.includes('label') || value.includes('etiqueta') || value.includes('ready')) return 'label_generated';
  if (value.includes('cancel')) return 'cancelled';
  if (value.includes('return') || value.includes('devol')) return 'returned';
  return 'pending';
}

export function extractMelhorEnvioPayload(body = {}) {
  return {
    shipmentExternalId: sanitizeString(body.id || body.shipment_id || body.order_id),
    eventType: sanitizeString(body.event || body.type || body.status || 'tracking_update'),
    normalizedStatus: normalizeShippingStatus(body.status || body.event || body.type),
    trackingCode: sanitizeString(body.tracking || body.tracking_code)
  };
}
