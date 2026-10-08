ALTER TABLE orders ADD COLUMN IF NOT EXISTS customer_update_pending TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE orders ADD COLUMN IF NOT EXISTS customer_update_type VARCHAR(80) NULL AFTER customer_update_pending;
ALTER TABLE orders ADD COLUMN IF NOT EXISTS customer_update_at DATETIME NULL AFTER customer_update_type;
ALTER TABLE orders ADD COLUMN IF NOT EXISTS admin_update_pending TINYINT(1) NOT NULL DEFAULT 0 AFTER customer_update_at;
ALTER TABLE orders ADD COLUMN IF NOT EXISTS admin_update_type VARCHAR(80) NULL AFTER admin_update_pending;
ALTER TABLE orders ADD COLUMN IF NOT EXISTS admin_update_at DATETIME NULL AFTER admin_update_type;

CREATE INDEX IF NOT EXISTS idx_orders_customer_update ON orders (customer_update_pending, customer_update_at);
CREATE INDEX IF NOT EXISTS idx_orders_admin_update ON orders (admin_update_pending, admin_update_at);
