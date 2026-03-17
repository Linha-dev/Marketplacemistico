-- Normalize legacy statuses before adding constraints
UPDATE orders
SET status = 'pendente'
WHERE status IS NULL OR BTRIM(status) = '';

UPDATE orders
SET payment_status = 'pending'
WHERE payment_status IS NULL OR BTRIM(payment_status) = '';

UPDATE orders
SET shipping_status = 'pending'
WHERE shipping_status IS NULL OR BTRIM(shipping_status) = '';

UPDATE payments
SET status = 'pending'
WHERE status IS NULL OR BTRIM(status) = '';

UPDATE shipments
SET status = 'pending'
WHERE status IS NULL OR BTRIM(status) = '';

-- Keep only one payment row per provider charge id for unique index creation
WITH ranked_payments AS (
  SELECT id,
         ROW_NUMBER() OVER (PARTITION BY provider, provider_charge_id ORDER BY id DESC) AS rn
  FROM payments
  WHERE provider_charge_id IS NOT NULL
)
UPDATE payments p
SET provider_charge_id = NULL
FROM ranked_payments r
WHERE p.id = r.id
  AND r.rn > 1;

-- Keep only one shipment row per external id before unique index creation
WITH ranked_shipments AS (
  SELECT id,
         ROW_NUMBER() OVER (PARTITION BY melhor_envio_shipment_id ORDER BY id DESC) AS rn
  FROM shipments
  WHERE melhor_envio_shipment_id IS NOT NULL
)
UPDATE shipments s
SET melhor_envio_shipment_id = NULL
FROM ranked_shipments r
WHERE s.id = r.id
  AND r.rn > 1;

-- Deduplicate webhook events for idempotency index
WITH ranked_events AS (
  SELECT id,
         ROW_NUMBER() OVER (PARTITION BY provider, external_id, event_type ORDER BY id DESC) AS rn
  FROM webhook_events
  WHERE external_id IS NOT NULL AND event_type IS NOT NULL
)
DELETE FROM webhook_events w
USING ranked_events r
WHERE w.id = r.id
  AND r.rn > 1;

ALTER TABLE orders
  ALTER COLUMN status SET NOT NULL;

ALTER TABLE orders
  DROP CONSTRAINT IF EXISTS chk_orders_status_allowed;
ALTER TABLE orders
  ADD CONSTRAINT chk_orders_status_allowed
  CHECK (status IN ('pendente', 'confirmado', 'enviado', 'entregue', 'cancelado')) NOT VALID;

ALTER TABLE orders
  DROP CONSTRAINT IF EXISTS chk_orders_payment_status_allowed;
ALTER TABLE orders
  ADD CONSTRAINT chk_orders_payment_status_allowed
  CHECK (payment_status IN ('pending', 'approved', 'failed', 'refunded', 'partially_refunded', 'cancelled')) NOT VALID;

ALTER TABLE orders
  DROP CONSTRAINT IF EXISTS chk_orders_shipping_status_allowed;
ALTER TABLE orders
  ADD CONSTRAINT chk_orders_shipping_status_allowed
  CHECK (shipping_status IN ('pending', 'label_generated', 'posted', 'in_transit', 'delivered', 'cancelled', 'returned')) NOT VALID;

ALTER TABLE orders
  DROP CONSTRAINT IF EXISTS chk_orders_totals_non_negative;
ALTER TABLE orders
  ADD CONSTRAINT chk_orders_totals_non_negative
  CHECK (
    total >= 0
    AND items_subtotal >= 0
    AND shipping_total >= 0
    AND discount_total >= 0
    AND grand_total >= 0
  ) NOT VALID;

ALTER TABLE payments
  DROP CONSTRAINT IF EXISTS chk_payments_status_allowed;
ALTER TABLE payments
  ADD CONSTRAINT chk_payments_status_allowed
  CHECK (status IN ('pending', 'approved', 'failed', 'refunded', 'partially_refunded', 'cancelled')) NOT VALID;

ALTER TABLE payments
  DROP CONSTRAINT IF EXISTS chk_payments_amount_positive;
ALTER TABLE payments
  ADD CONSTRAINT chk_payments_amount_positive
  CHECK (amount > 0) NOT VALID;

ALTER TABLE payments
  DROP CONSTRAINT IF EXISTS chk_payments_order_id_present;
ALTER TABLE payments
  ADD CONSTRAINT chk_payments_order_id_present
  CHECK (order_id IS NOT NULL) NOT VALID;

ALTER TABLE shipping_quotes
  DROP CONSTRAINT IF EXISTS chk_shipping_quotes_price_non_negative;
ALTER TABLE shipping_quotes
  ADD CONSTRAINT chk_shipping_quotes_price_non_negative
  CHECK (price >= 0 AND COALESCE(custom_price, 0) >= 0) NOT VALID;

ALTER TABLE shipping_quotes
  DROP CONSTRAINT IF EXISTS chk_shipping_quotes_delivery_time_non_negative;
ALTER TABLE shipping_quotes
  ADD CONSTRAINT chk_shipping_quotes_delivery_time_non_negative
  CHECK (delivery_time IS NULL OR delivery_time >= 0) NOT VALID;

ALTER TABLE shipments
  DROP CONSTRAINT IF EXISTS chk_shipments_status_allowed;
ALTER TABLE shipments
  ADD CONSTRAINT chk_shipments_status_allowed
  CHECK (status IN ('pending', 'label_generated', 'posted', 'in_transit', 'delivered', 'cancelled', 'returned')) NOT VALID;

ALTER TABLE shipments
  DROP CONSTRAINT IF EXISTS chk_shipments_provider_allowed;
ALTER TABLE shipments
  ADD CONSTRAINT chk_shipments_provider_allowed
  CHECK (provider IN ('melhor_envio', 'manual', 'custom')) NOT VALID;

ALTER TABLE order_items
  DROP CONSTRAINT IF EXISTS chk_order_items_positive_values;
ALTER TABLE order_items
  ADD CONSTRAINT chk_order_items_positive_values
  CHECK (quantidade > 0 AND preco_unitario >= 0 AND COALESCE(unit_price, 0) >= 0) NOT VALID;

ALTER TABLE payment_splits
  DROP CONSTRAINT IF EXISTS chk_payment_splits_amounts_non_negative;
ALTER TABLE payment_splits
  ADD CONSTRAINT chk_payment_splits_amounts_non_negative
  CHECK (
    gross_amount >= 0
    AND platform_fee_amount >= 0
    AND gateway_fee_amount >= 0
    AND operational_fee_amount >= 0
    AND seller_net_amount >= 0
  ) NOT VALID;

ALTER TABLE payment_splits
  DROP CONSTRAINT IF EXISTS chk_payment_splits_status_allowed;
ALTER TABLE payment_splits
  ADD CONSTRAINT chk_payment_splits_status_allowed
  CHECK (status IN ('pending', 'ready', 'paid', 'failed', 'cancelled')) NOT VALID;

ALTER TABLE manual_payouts
  DROP CONSTRAINT IF EXISTS chk_manual_payouts_amounts_non_negative;
ALTER TABLE manual_payouts
  ADD CONSTRAINT chk_manual_payouts_amounts_non_negative
  CHECK (amount >= 0 AND fee_amount >= 0) NOT VALID;

ALTER TABLE manual_payouts
  DROP CONSTRAINT IF EXISTS chk_manual_payouts_status_allowed;
ALTER TABLE manual_payouts
  ADD CONSTRAINT chk_manual_payouts_status_allowed
  CHECK (status IN ('pending', 'processing', 'paid', 'failed', 'rejected', 'cancelled')) NOT VALID;

DROP INDEX IF EXISTS idx_payments_provider_charge;
CREATE UNIQUE INDEX IF NOT EXISTS idx_payments_provider_charge
  ON payments(provider, provider_charge_id)
  WHERE provider_charge_id IS NOT NULL;

DROP INDEX IF EXISTS idx_webhook_events_unique;
CREATE UNIQUE INDEX IF NOT EXISTS idx_webhook_events_unique
  ON webhook_events(provider, external_id, event_type)
  WHERE external_id IS NOT NULL AND event_type IS NOT NULL;

CREATE UNIQUE INDEX IF NOT EXISTS idx_shipments_external_unique
  ON shipments(melhor_envio_shipment_id)
  WHERE melhor_envio_shipment_id IS NOT NULL;
