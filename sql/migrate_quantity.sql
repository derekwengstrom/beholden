-- Run once on an existing installation after backing up the database.
-- Existing products start with quantity 0 until edited in the CMS.
ALTER TABLE products ADD COLUMN quantity INT UNSIGNED NOT NULL DEFAULT 0 AFTER price;
