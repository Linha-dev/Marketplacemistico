ALTER TABLE orders DROP CONSTRAINT IF EXISTS chk_orders_status_allowed;
ALTER TABLE orders DROP CONSTRAINT IF EXISTS chk_orders_payment_status_allowed;
ALTER TABLE orders DROP CONSTRAINT IF EXISTS chk_orders_shipping_status_allowed;
ALTER TABLE orders DROP CONSTRAINT IF EXISTS chk_orders_totals_non_negative;
ALTER TABLE orders ALTER COLUMN status DROP NOT NULL;

ALTER TABLE payments DROP CONSTRAINT IF EXISTS chk_payments_status_allowed;
ALTER TABLE payments DROP CONSTRAINT IF EXISTS chk_payments_amount_positive;
ALTER TABLE payments DROP CONSTRAINT IF EXISTS chk_payments_order_id_present;

ALTER TABLE shipping_quotes DROP CONSTRAINT IF EXISTS chk_shipping_quotes_price_non_negative;
ALTER TABLE shipping_quotes DROP CONSTRAINT IF EXISTS chk_shipping_quotes_delivery_time_non_negative;

ALTER TABLE shipments DROP CONSTRAINT IF EXISTS chk_shipments_status_allowed;
ALTER TABLE shipments DROP CONSTRAINT IF EXISTS chk_shipments_provider_allowed;

ALTER TABLE order_items DROP CONSTRAINT IF EXISTS chk_order_items_positive_values;

ALTER TABLE payment_splits DROP CONSTRAINT IF EXISTS chk_payment_splits_amounts_non_negative;
ALTER TABLE payment_splits DROP CONSTRAINT IF EXISTS chk_payment_splits_status_allowed;

ALTER TABLE manual_payouts DROP CONSTRAINT IF EXISTS chk_manual_payouts_amounts_non_negative;
ALTER TABLE manual_payouts DROP CONSTRAINT IF EXISTS chk_manual_payouts_status_allowed;

DROP INDEX IF EXISTS idx_payments_provider_charge;
CREATE INDEX IF NOT EXISTS idx_payments_provider_charge ON payments(provider, provider_charge_id);

DROP INDEX IF EXISTS idx_webhook_events_unique;
CREATE INDEX IF NOT EXISTS idx_webhook_events_unique ON webhook_events(provider, external_id, event_type);

DROP INDEX IF EXISTS idx_shipments_external_unique;
