-- Additive per-order-service work completion log.
-- The original order_items remain authoritative for service, quantity, unit price and total.
-- Work completion exists only when a staff member marks a service line complete.
CREATE TABLE IF NOT EXISTS laundry__order_item_work_tbl (
  order_item_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
  completed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  completed_by BIGINT UNSIGNED NOT NULL,
  KEY idx_order_item_work_staff (completed_by),
  CONSTRAINT fk_order_item_work_item FOREIGN KEY (order_item_id)
    REFERENCES laundry__order_items_tbl(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
