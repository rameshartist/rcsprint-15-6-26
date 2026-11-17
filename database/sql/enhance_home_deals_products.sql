ALTER TABLE home_deals ADD COLUMN IF NOT EXISTS product_id INT UNSIGNED NULL AFTER deal_type;
CREATE INDEX IF NOT EXISTS idx_home_deals_product ON home_deals(product_id);
