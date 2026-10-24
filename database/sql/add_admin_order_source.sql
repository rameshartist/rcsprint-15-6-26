ALTER TABLE orders ADD COLUMN order_source VARCHAR(30) NOT NULL DEFAULT 'customer' AFTER checkout_group_id;
ALTER TABLE orders ADD COLUMN created_by_admin_id INT UNSIGNED NULL AFTER order_source;
CREATE INDEX idx_orders_source ON orders (order_source, created_at);
