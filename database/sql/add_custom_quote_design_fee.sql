ALTER TABLE custom_quote_requests
  ADD COLUMN design_fee DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER quoted_amount;
