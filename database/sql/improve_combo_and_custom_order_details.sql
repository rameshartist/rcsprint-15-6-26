ALTER TABLE combo_offers ADD COLUMN show_title TINYINT(1) NOT NULL DEFAULT 1, ADD COLUMN show_badge TINYINT(1) NOT NULL DEFAULT 1, ADD COLUMN show_cta TINYINT(1) NOT NULL DEFAULT 1, ADD COLUMN show_short_description TINYINT(1) NOT NULL DEFAULT 1, ADD COLUMN show_description TINYINT(1) NOT NULL DEFAULT 1;
ALTER TABLE combo_offer_custom_items ADD COLUMN quantity INT NOT NULL DEFAULT 1 AFTER item_name;
ALTER TABLE custom_quote_requests ADD COLUMN product_image VARCHAR(500) NULL AFTER product_name;
ALTER TABLE order_items ADD COLUMN custom_product_image VARCHAR(500) NULL AFTER custom_quote_id;
