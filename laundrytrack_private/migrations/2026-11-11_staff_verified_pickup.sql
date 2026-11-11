-- Staff-verified pickup workflow. Safe only after a verified backup exists.
-- Run once on s68160395 BEFORE deploying pickup-security PHP changes.
-- This creates a new table and does not rewrite existing order statuses.
CREATE TABLE IF NOT EXISTS laundry__pickup_requests_tbl (
  order_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
  requested_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  requested_source ENUM('guest','member') NOT NULL,
  requested_by BIGINT UNSIGNED NOT NULL,
  confirmed_at TIMESTAMP NULL DEFAULT NULL,
  confirmed_by BIGINT UNSIGNED NULL,
  KEY idx_pickup_requested_at (requested_at),
  KEY idx_pickup_confirmed_at (confirmed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
