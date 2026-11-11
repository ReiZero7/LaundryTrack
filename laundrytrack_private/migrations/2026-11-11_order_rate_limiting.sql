-- LaundryTrack PR #6: persistent order submission rate limiting.
-- Requires MySQL/InnoDB. Apply once in the configured LaundryTrack DB.
-- No existing users or orders are modified.
-- Keep the table and the private RATE_LIMIT_SECRET in place before deploying
-- the updated PHP actions/bootstrap files.
CREATE TABLE laundry__order_rate_limits_tbl (
    scope VARCHAR(24) NOT NULL,
    identity_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    window_started_at BIGINT UNSIGNED NOT NULL,
    attempt_count INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (scope, identity_hash),
    KEY idx_order_rate_limit_window (window_started_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Verify after installation:
-- SHOW COLUMNS FROM laundry__order_rate_limits_tbl;
-- SHOW INDEX FROM laundry__order_rate_limits_tbl;
--
-- The table stores only HMAC-SHA256 identity digests, never raw IP addresses.
-- Optional maintenance, when suitable and after a backup:
-- DELETE FROM laundry__order_rate_limits_tbl
-- WHERE window_started_at < UNIX_TIMESTAMP() - 2592000;
