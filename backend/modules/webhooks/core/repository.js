import { query } from '../../../db.js';
import { sanitizeInteger } from '../../../sanitize.js';

export async function findShipmentByExternalId(shipmentExternalId) {
  return query('SELECT id, order_id FROM shipments WHERE melhor_envio_shipment_id = $1 LIMIT 1', [shipmentExternalId]);
}

export async function insertShipmentEvent(shipmentId, eventName, payload) {
  return query(
    `INSERT INTO shipment_events (shipment_id, event_name, event_payload_json, occurred_at)
     VALUES ($1, $2, $3::jsonb, CURRENT_TIMESTAMP)`,
    [shipmentId, eventName || 'tracking_update', JSON.stringify(payload)]
  );
}

export async function updateShipmentTracking(shipmentId, trackingCode, normalizedStatus) {
  return query(
    `UPDATE shipments
     SET tracking_code = COALESCE($1, tracking_code),
         status = COALESCE($2, status),
         updated_at = CURRENT_TIMESTAMP
     WHERE id = $3`,
    [trackingCode || null, normalizedStatus, sanitizeInteger(shipmentId)]
  );
}

export async function updateOrderShippingStatus(orderId, normalizedStatus) {
  return query(
    `UPDATE orders
     SET shipping_status = COALESCE($1, shipping_status)
     WHERE id = $2`,
    [normalizedStatus, orderId]
  );
}
