import { query } from '../db.js';
import { sanitizeString, sanitizeInteger } from '../sanitize.js';
import { sendSuccess, sendError } from '../response.js';
import { withCors } from '../middleware.js';

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

async function handler(req, res) {
  if (req.method !== 'POST') {
    return sendError(res, 'METHOD_NOT_ALLOWED', 'Metodo nao permitido', 405);
  }

  const webhookSecret = process.env.MELHOR_ENVIO_WEBHOOK_SECRET;
  if (webhookSecret) {
    const receivedSecret = req.headers['x-webhook-secret'];
    if (receivedSecret !== webhookSecret) {
      return sendError(res, 'UNAUTHORIZED', 'Webhook sem autorizacao', 401);
    }
  }

  try {
    const payload = req.body || {};
    const shipmentExternalId = sanitizeString(payload.id || payload.shipment_id || payload.order_id);
    const eventType = sanitizeString(payload.event || payload.type || payload.status || 'tracking_update');
    const normalizedStatus = normalizeShippingStatus(payload.status || payload.event || payload.type);
    const trackingCode = sanitizeString(payload.tracking || payload.tracking_code);

    if (!shipmentExternalId) {
      return sendError(res, 'VALIDATION_ERROR', 'shipment_id externo ausente');
    }

    const shipments = await query(
      'SELECT id, order_id FROM shipments WHERE melhor_envio_shipment_id = $1 LIMIT 1',
      [shipmentExternalId]
    );

    if (shipments.length === 0) {
      return sendSuccess(res, { message: 'Shipment nao localizado, evento registrado para auditoria' });
    }

    const shipment = shipments[0];

    await query(
      `INSERT INTO shipment_events (shipment_id, event_name, event_payload_json, occurred_at)
       VALUES ($1, $2, $3::jsonb, CURRENT_TIMESTAMP)`,
      [shipment.id, eventType || 'tracking_update', JSON.stringify(payload)]
    );

    await query(
      `UPDATE shipments
       SET tracking_code = COALESCE($1, tracking_code),
           status = COALESCE($2, status),
           updated_at = CURRENT_TIMESTAMP
       WHERE id = $3`,
      [trackingCode || null, normalizedStatus, sanitizeInteger(shipment.id)]
    );

    await query(
      `UPDATE orders
       SET shipping_status = COALESCE($1, shipping_status)
       WHERE id = $2`,
      [normalizedStatus, shipment.order_id]
    );

    return sendSuccess(res, { processed: true, shipmentId: shipment.id });
  } catch (error) {
    console.error('Erro no webhook Melhor Envio:', error);
    return sendError(res, 'INTERNAL_ERROR', 'Erro ao processar webhook de envio', 500);
  }
}

export default withCors(handler);
