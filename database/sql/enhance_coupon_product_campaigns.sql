CREATE TABLE IF NOT EXISTS coupon_products (
  coupon_id INT UNSIGNED NOT NULL,
  product_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (coupon_id, product_id),
  KEY idx_coupon_products_product (product_id),
  CONSTRAINT fk_coupon_products_coupon FOREIGN KEY (coupon_id) REFERENCES coupons(id) ON DELETE CASCADE,
  CONSTRAINT fk_coupon_products_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE coupons ADD COLUMN IF NOT EXISTS popup_enabled TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE coupons ADD COLUMN IF NOT EXISTS popup_image VARCHAR(500) NULL;
