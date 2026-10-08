ALTER TABLE products
  ADD COLUMN IF NOT EXISTS video_url VARCHAR(500) NULL,
  ADD COLUMN IF NOT EXISTS video_path VARCHAR(500) NULL,
  ADD COLUMN IF NOT EXISTS show_delivery_info TINYINT(1) NOT NULL DEFAULT 1,
  ADD COLUMN IF NOT EXISTS delivery_info VARCHAR(255) NULL DEFAULT 'Delivery in 3 - 5 Working Days',
  ADD COLUMN IF NOT EXISTS show_free_delivery_info TINYINT(1) NOT NULL DEFAULT 1,
  ADD COLUMN IF NOT EXISTS free_delivery_info VARCHAR(255) NULL DEFAULT 'Free Delivery on Orders Above ₹999';

ALTER TABLE combo_offers
  ADD COLUMN IF NOT EXISTS video_url VARCHAR(500) NULL,
  ADD COLUMN IF NOT EXISTS video_path VARCHAR(500) NULL,
  ADD COLUMN IF NOT EXISTS show_delivery_info TINYINT(1) NOT NULL DEFAULT 1,
  ADD COLUMN IF NOT EXISTS delivery_info VARCHAR(255) NULL DEFAULT 'Delivery in 3 - 5 Working Days',
  ADD COLUMN IF NOT EXISTS show_free_delivery_info TINYINT(1) NOT NULL DEFAULT 1,
  ADD COLUMN IF NOT EXISTS free_delivery_info VARCHAR(255) NULL DEFAULT 'Free Delivery on Orders Above ₹999';

CREATE TABLE IF NOT EXISTS combo_offer_images (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  combo_offer_id INT UNSIGNED NOT NULL,
  image_path VARCHAR(500) NOT NULL,
  alt_text VARCHAR(255) NULL,
  is_primary TINYINT(1) NOT NULL DEFAULT 0,
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_combo_images (combo_offer_id,is_primary,sort_order),
  CONSTRAINT fk_combo_images_offer FOREIGN KEY (combo_offer_id) REFERENCES combo_offers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
