-- Reverse: drop the columns added by 0010_add_shipping_columns.up.sql.
ALTER TABLE order_items
  DROP COLUMN IF EXISTS dimension_snapshot_json,
  DROP COLUMN IF EXISTS weight_snapshot,
  DROP COLUMN IF EXISTS name_snapshot,
  DROP COLUMN IF EXISTS unit_price;

ALTER TABLE products
  DROP COLUMN IF EXISTS insurance_value,
  DROP COLUMN IF EXISTS length_cm,
  DROP COLUMN IF EXISTS width_cm,
  DROP COLUMN IF EXISTS height_cm,
  DROP COLUMN IF EXISTS weight_kg;
