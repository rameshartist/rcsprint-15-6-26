-- Normalize previously issued public document identifiers to the current no-hyphen format.
-- Rows that would collide with an existing identifier are deliberately left unchanged.
UPDATE orders source_row
LEFT JOIN orders collision
  ON collision.order_id = REPLACE(source_row.order_id, '-', '')
 AND collision.id <> source_row.id
SET source_row.order_id = REPLACE(source_row.order_id, '-', '')
WHERE source_row.order_id LIKE '%-%' AND collision.id IS NULL;

UPDATE custom_quote_requests source_row
LEFT JOIN custom_quote_requests collision
  ON collision.request_code = REPLACE(source_row.request_code, '-', '')
 AND collision.id <> source_row.id
SET source_row.request_code = REPLACE(source_row.request_code, '-', '')
WHERE source_row.request_code LIKE '%-%' AND collision.id IS NULL;
