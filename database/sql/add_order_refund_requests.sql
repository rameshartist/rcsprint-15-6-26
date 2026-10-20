ALTER TABLE orders
  ADD COLUMN refund_requested_at DATETIME NULL AFTER admin_update_at,
  ADD COLUMN refund_request_note TEXT NULL AFTER refund_requested_at;
