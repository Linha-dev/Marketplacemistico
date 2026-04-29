-- Add shipping dimension columns to products if they do not exist.
-- These columns were defined in the initial schema but may be absent
-- in databases deployed before the schema was updated.
ALTER TABLE products
  ADD COLUMN IF NOT EXISTS weight_kg NUMERIC(10,3),
  ADD COLUMN IF NOT EXISTS height_cm NUMERIC(10,2),
  ADD COLUMN IF NOT EXISTS width_cm NUMERIC(10,2),
  ADD COLUMN IF NOT EXISTS length_cm NUMERIC(10,2),
  ADD COLUMN IF NOT EXISTS insurance_value NUMERIC(10,2) DEFAULT 0;

-- Add snapshot columns to order_items for the same reason.
ALTER TABLE order_items
  ADD COLUMN IF NOT EXISTS unit_price DECIMAL(10,2),
  ADD COLUMN IF NOT EXISTS name_snapshot VARCHAR(255),
  ADD COLUMN IF NOT EXISTS weight_snapshot NUMERIC(10,3),
  ADD COLUMN IF NOT EXISTS dimension_snapshot_json JSONB;
