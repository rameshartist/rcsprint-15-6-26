ALTER TABLE user_addresses ADD COLUMN IF NOT EXISTS address_type VARCHAR(20) NOT NULL DEFAULT 'home' AFTER label;
ALTER TABLE user_addresses ADD COLUMN IF NOT EXISTS is_default_shipping TINYINT(1) NOT NULL DEFAULT 0 AFTER is_default;
ALTER TABLE user_addresses ADD COLUMN IF NOT EXISTS is_default_billing TINYINT(1) NOT NULL DEFAULT 0 AFTER is_default_shipping;
ALTER TABLE user_addresses ADD COLUMN IF NOT EXISTS legal_name VARCHAR(180) NULL AFTER business_name;
ALTER TABLE user_addresses ADD COLUMN IF NOT EXISTS gst_no VARCHAR(30) NULL AFTER legal_name;
UPDATE user_addresses SET is_default_shipping=is_default WHERE is_default=1 AND is_default_shipping=0;
