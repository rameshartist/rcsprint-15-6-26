ALTER TABLE custom_quote_requests ADD COLUMN IF NOT EXISTS is_seen TINYINT(1) NOT NULL DEFAULT 0 AFTER approved_at;
ALTER TABLE custom_quote_requests ADD COLUMN IF NOT EXISTS customer_update_pending TINYINT(1) NOT NULL DEFAULT 0 AFTER is_seen;
ALTER TABLE custom_quote_requests ADD COLUMN IF NOT EXISTS customer_update_type VARCHAR(80) NULL AFTER customer_update_pending;
ALTER TABLE custom_quote_requests ADD COLUMN IF NOT EXISTS customer_update_at DATETIME NULL AFTER customer_update_type;
CREATE INDEX IF NOT EXISTS idx_custom_quote_unseen ON custom_quote_requests (is_seen, created_at);
