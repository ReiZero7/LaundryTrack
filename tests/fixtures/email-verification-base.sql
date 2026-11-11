-- Disposable GitHub Actions schema ONLY. Never run on Angsila.
CREATE TABLE laundry__users_tbl (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NULL UNIQUE,
    phone VARCHAR(20) NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin','customer') NOT NULL DEFAULT 'customer'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE laundry__orders_tbl (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    customer_id BIGINT UNSIGNED NOT NULL,
    tracking_token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
    status ENUM('requested','received','washing','drying','ready','picked_up','cancelled') NOT NULL DEFAULT 'requested'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Ensure migration preserves existing members and admins.
INSERT INTO laundry__users_tbl(id,full_name,email,password_hash,role)
VALUES
    (1,'Old customer','old@example.test','test-hash','customer'),
    (2,'Old admin','old-admin@example.test','test-hash','admin'),
    (3,'Walk-in guest',NULL,'test-hash','customer');
