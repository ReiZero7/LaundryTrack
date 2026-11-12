-- Additive per-service work tracking. No existing orders or customer records are changed.
-- CI uses only disposable laundrytrack_ci. On Angsila, run guarded PHP script,
-- not this SQL by hand, after a verified database backup.
CREATE TABLE IF NOT EXISTS laundry__order_item_progress_tbl (
    order_item_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
    work_status ENUM('pending','working','done') NOT NULL DEFAULT 'pending',
    updated_at TIMESTAMP NULL DEFAULT NULL,
    updated_by BIGINT UNSIGNED NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Mark completed historical orders' service work as done; all other services
-- start as pending for explicit staff verification. Existing tracked progress
-- is never overwritten. This statement only writes to the new tracking table.
INSERT INTO laundry__order_item_progress_tbl (order_item_id,work_status)
SELECT oi.id, IF(o.status IN ('ready','picked_up'),'done','pending')
FROM laundry__order_items_tbl oi
JOIN laundry__orders_tbl o ON o.id=oi.order_id
ON DUPLICATE KEY UPDATE order_item_id=order_item_id;
