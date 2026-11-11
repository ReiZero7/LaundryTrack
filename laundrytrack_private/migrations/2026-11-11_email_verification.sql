-- PR #8: Verified-email login for new customer accounts.
-- IMPORTANT: BACKUP FIRST. Apply once, before deploying the new PHP.
-- Existing accounts (including admins) are grandfathered so previously
-- working logins are not unexpectedly disabled.
ALTER TABLE laundry__users_tbl
  ADD COLUMN email_verified_at DATETIME NULL DEFAULT NULL;

UPDATE laundry__users_tbl
SET email_verified_at = UTC_TIMESTAMP()
WHERE email IS NOT NULL AND email_verified_at IS NULL;

-- Store only SHA-256 verification token hashes, never raw tokens.
-- No FK here: existing installations may use different user/order id widths.
-- All ownership checks and order transfers are performed transactionally.
CREATE TABLE laundry__email_verifications_tbl (
  user_id BIGINT UNSIGNED NOT NULL,
  token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  expires_at DATETIME NOT NULL,
  sent_at DATETIME NOT NULL,
  send_count INT UNSIGNED NOT NULL DEFAULT 1,
  send_window_started_at DATETIME NOT NULL,
  claimed_order_id BIGINT UNSIGNED NULL,
  claimed_customer_id BIGINT UNSIGNED NULL,
  claimed_token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
  PRIMARY KEY (user_id),
  UNIQUE KEY uq_email_verification_hash (token_hash),
  UNIQUE KEY uq_email_claimed_order (claimed_order_id),
  KEY idx_email_verification_expiry (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Verify:
-- SHOW COLUMNS FROM laundry__users_tbl LIKE 'email_verified_at';
-- SHOW COLUMNS FROM laundry__email_verifications_tbl;
-- SELECT role, COUNT(*) FROM laundry__users_tbl
-- WHERE email IS NOT NULL AND email_verified_at IS NULL GROUP BY role;
