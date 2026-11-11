-- IMPORTANT: review the real column definitions before running.
-- This migration assumes the initial PR #4 schema uses ENUM states listed below.
-- Run SHOW COLUMNS on production first and adjust if original types differ.
-- Back up the MySQL database and test on a copy. MySQL ALTER TABLE auto-commits.
--
-- Preflight:
-- SHOW COLUMNS FROM laundry__orders_tbl LIKE 'status';
-- SHOW COLUMNS FROM laundry__status_history_tbl LIKE 'old_status';
-- SHOW COLUMNS FROM laundry__status_history_tbl LIKE 'new_status';
-- SELECT status,COUNT(*) FROM laundry__orders_tbl GROUP BY status;
--
-- FIRST: widen both order and history status columns. Keep existing enum values.
ALTER TABLE laundry__orders_tbl
  MODIFY COLUMN status
  ENUM('requested','received','washing','drying','ready','picked_up','cancelled')
  NOT NULL DEFAULT 'received';

ALTER TABLE laundry__status_history_tbl
  MODIFY COLUMN old_status
  ENUM('requested','received','washing','drying','ready','picked_up','cancelled')
  NULL DEFAULT NULL,
  MODIFY COLUMN new_status
  ENUM('requested','received','washing','drying','ready','picked_up','cancelled')
  NOT NULL;

-- SECOND: Historical PR #4 self-service requests that remain in the initial
-- received state and whose first history entry is explicitly self-service.
-- Admin-created orders, orders already in washing/ready/picked_up, and history
-- representing actual physical receipt are NOT rewritten.
-- Only records originally created via self-service are eligible.
UPDATE laundry__status_history_tbl h
INNER JOIN laundry__orders_tbl o ON o.id=h.order_id
INNER JOIN laundry__users_tbl cu ON cu.id=o.created_by
SET h.new_status='requested'
WHERE o.status='received'
  AND cu.role='customer'
  AND h.old_status IS NULL
  AND h.new_status='received'
  AND h.note LIKE 'ลูกค้าสร้างออร์เดอร์ผ่านเว็บไซต์%';

UPDATE laundry__orders_tbl o
INNER JOIN laundry__users_tbl cu ON cu.id=o.created_by
INNER JOIN laundry__status_history_tbl h ON h.order_id=o.id
SET o.status='requested'
WHERE o.status='received'
  AND cu.role='customer'
  AND h.old_status IS NULL
  AND h.new_status='requested'
  AND h.note LIKE 'ลูกค้าสร้างออร์เดอร์ผ่านเว็บไซต์%';

-- Verification:
-- SELECT status, COUNT(*) FROM laundry__orders_tbl GROUP BY status;
-- SELECT old_status, new_status, COUNT(*) FROM laundry__status_history_tbl
-- GROUP BY old_status, new_status;
