-- Run once after backing up your existing database.
CREATE TABLE rental_orders (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 order_number VARCHAR(32) NOT NULL UNIQUE,
 customer_name VARCHAR(200) NOT NULL,
 customer_email VARCHAR(254) NOT NULL,
 customer_phone VARCHAR(50) NOT NULL,
 customer_notes TEXT NULL,
 pickup_date DATE NOT NULL,
 return_date DATE NOT NULL,
 status ENUM('reserved','out','returned','cancelled') NOT NULL DEFAULT 'reserved',
 total DECIMAL(12,2) NOT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 checked_out_at DATETIME NULL,
 checked_in_at DATETIME NULL,
 cancelled_at DATETIME NULL,
 KEY idx_dates_status (status,pickup_date,return_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE rental_order_items (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 order_id BIGINT UNSIGNED NOT NULL,
 product_id BIGINT UNSIGNED NULL,
 product_name VARCHAR(200) NOT NULL,
 product_size VARCHAR(100) NOT NULL DEFAULT '',
 product_color VARCHAR(100) NOT NULL DEFAULT '',
 product_material VARCHAR(100) NOT NULL DEFAULT '',
 quantity INT UNSIGNED NOT NULL,
 unit_price DECIMAL(10,2) NOT NULL,
 KEY idx_order (order_id),
 KEY idx_product (product_id),
 CONSTRAINT fk_rental_order FOREIGN KEY (order_id) REFERENCES rental_orders(id),
 CONSTRAINT fk_rental_product FOREIGN KEY (product_id) REFERENCES products(productId) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
