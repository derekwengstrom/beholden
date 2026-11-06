-- Run ONCE against the existing database before deploying the updated PHP files.
-- Backup the database first.
ALTER TABLE products
  ADD COLUMN size VARCHAR(100) NOT NULL DEFAULT '' AFTER description,
  ADD COLUMN color VARCHAR(100) NOT NULL DEFAULT '' AFTER size,
  ADD COLUMN material VARCHAR(100) NOT NULL DEFAULT '' AFTER color;
ALTER TABLE rental_order_items
  ADD COLUMN product_size VARCHAR(100) NOT NULL DEFAULT '' AFTER product_name,
  ADD COLUMN product_color VARCHAR(100) NOT NULL DEFAULT '' AFTER product_size,
  ADD COLUMN product_material VARCHAR(100) NOT NULL DEFAULT '' AFTER product_color;
