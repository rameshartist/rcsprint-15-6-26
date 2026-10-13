ALTER TABLE orders ADD COLUMN checkout_group_id VARCHAR(80) NULL AFTER custom_quote_id;
CREATE INDEX idx_orders_checkout_group ON orders (checkout_group_id);
