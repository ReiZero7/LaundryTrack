-- LaundryTrack PR #5: expand ONLY the orders.status ENUM.
-- VERIFIED against production column information supplied by the project owner:
-- laundry__orders_tbl.status: ENUM, NOT NULL DEFAULT 'received'
-- laundry__status_history_tbl.old_status: VARCHAR(20) NULL
-- laundry__status_history_tbl.new_status: VARCHAR(20) NOT NULL
-- Therefore DO NOT change either history column. VARCHAR(20) already fits 'requested'.
--
-- IMPORTANT:
-- 1. Back up the database to SQL and check the backup before running.
-- 2. Run this ONLY in the target LaundryTrack database (expected: s68160395).
-- 3. Recommended: test on a database copy first.
-- 4. MySQL ALTER TABLE auto-commits; this whole migration is NOT atomic.
-- 5. Apply ONCE. Deploy PR #5 PHP code only AFTER this succeeds.
--
-- Preflight (read-only):
-- SELECT DATABASE();
-- SHOW COLUMNS FROM laundry__orders_tbl LIKE 'status';
-- SHOW COLUMNS FROM laundry__status_history_tbl WHERE Field IN ('old_status', 'new_status');
-- SELECT status, COUNT(*) FROM laundry__orders_tbl GROUP BY status;
--
-- Use the actual existing ENUM list instead of guessing from truncated phpMyAdmin
-- output. If status isn't the expected NOT NULL ENUM with default 'received', the
-- dynamically constructed SQL is NULL and PREPARE fails before any write.
SET @status_enum_type = (
  SELECT COLUMN_TYPE
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'laundry__orders_tbl'
    AND COLUMN_NAME = 'status'
    AND DATA_TYPE = 'enum'
    AND IS_NULLABLE = 'NO'
    AND COLUMN_DEFAULT = 'received'
);
SET @expand_requested_status = IF(
  @status_enum_type IS NOT NULL
    AND LEFT(@status_enum_type, 5) = 'enum('
    AND LOCATE('''requested''', @status_enum_type) = 0,
  CONCAT(
    'ALTER TABLE `laundry__orders_tbl` MODIFY COLUMN `status` ENUM(''requested'',',
    SUBSTRING(@status_enum_type, 6),
    ' NOT NULL DEFAULT ''received'''
  ),
  NULL
);
-- If this PREPARE fails, STOP. Do not run the backfill below.
PREPARE add_requested_status FROM @expand_requested_status;
EXECUTE add_requested_status;
DEALLOCATE PREPARE add_requested_status;

-- Historical PR #4 customer-created requests left in 'received' may be
-- corrected to 'requested' only if their initial history row explicitly says
-- they were submitted through the self-order website. Staff-created orders,
-- advanced orders and actual handoffs must be left unchanged.
-- Both changes are transactionally grouped; the preceding ALTER is not.
START TRANSACTION;

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

COMMIT;

-- Postflight (read-only):
-- SHOW COLUMNS FROM laundry__orders_tbl LIKE 'status';
-- SHOW COLUMNS FROM laundry__status_history_tbl WHERE Field IN ('old_status','new_status');
-- SELECT status,COUNT(*) FROM laundry__orders_tbl GROUP BY status;
