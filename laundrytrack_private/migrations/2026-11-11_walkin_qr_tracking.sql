-- LaundryTrack: add walk-in customers and QR-scoped order links.
-- BACK UP MySQL before running. Apply ONCE on the database configured for this site.
-- Existing registered customers keep their email and password.
-- MySQL unique indexes permit multiple NULL email addresses.
ALTER TABLE laundry__users_tbl
    MODIFY COLUMN email VARCHAR(150) NULL DEFAULT NULL;

-- Only a SHA-256 token hash is kept in the database, never the secret URL token.
ALTER TABLE laundry__orders_tbl
    ADD COLUMN tracking_token_hash CHAR(64) NULL DEFAULT NULL,
    ADD UNIQUE KEY uniq_laundry_tracking_token (tracking_token_hash);
