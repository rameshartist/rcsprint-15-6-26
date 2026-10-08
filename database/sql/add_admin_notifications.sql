CREATE TABLE IF NOT EXISTS admin_notifications (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  admin_id INT UNSIGNED NOT NULL,
  event_key VARCHAR(190) NOT NULL,
  type VARCHAR(40) NOT NULL,
  title VARCHAR(160) NOT NULL,
  message VARCHAR(500) NOT NULL,
  action_url VARCHAR(500) NOT NULL,
  source_created_at DATETIME NULL,
  read_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_admin_notification_event (admin_id,event_key),
  KEY idx_admin_notification_feed (admin_id,read_at,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
