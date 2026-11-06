-- Run once against your EXISTING database. Do not re-import schema.sql.
CREATE TABLE IF NOT EXISTS product_photos (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 product_id BIGINT UNSIGNED NOT NULL,
 photo_key VARCHAR(512) NOT NULL,
 sort_order INT UNSIGNED NOT NULL DEFAULT 0,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_photo_key (photo_key),
 KEY idx_product_order (product_id, sort_order, id),
 CONSTRAINT fk_product_photos_product FOREIGN KEY (product_id) REFERENCES products(productId) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Copy original single photos into the new gallery, without duplicates.
INSERT INTO product_photos (product_id, photo_key, sort_order)
SELECT p.productId, p.photo_key, 0
FROM products p
WHERE p.photo_key IS NOT NULL AND p.photo_key <> ''
ON DUPLICATE KEY UPDATE photo_key = VALUES(photo_key);
-- Leave products.photo_key intact for compatibility with older code.
