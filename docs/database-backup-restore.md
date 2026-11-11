# LaundryTrack — Backup & Restore Runbook (PR #10)

## Scope and safety

- The **production site on Angsila is NOT modified** by this PR. This PR adds a CLI-only backup script, one GitHub Actions restoration test, and this runbook.
- The script only **reads** the configured MySQL database using `mysqldump`; it cannot restore, drop, update or delete database rows.
- Backups contain private customer data (names, phone numbers, passwords **hashes**, QR token **hashes**, orders and rate-limit identities). **Never upload a backup to GitHub, public_html or this chat.**
- Keep backups private, preferably in encrypted off-server storage accessible only to the project owner. Backups stored only on Angsila are not a complete disaster recovery strategy.
- The helper stores DB credentials only in a temporary private MySQL option file (`0600`) and removes it even if the dump fails. It does not embed credentials in process arguments. Files and checksums are created `0600` inside directory `0700`.
- `--single-transaction` provides a consistent snapshot for InnoDB tables during ordinary writes but not concurrent DDL; create the backup during a quiet maintenance period. MySQL privileges and host `mysqldump` availability may restrict this.

## 1. Angsila preflight (read-only)

SSH to Angsila and run:

```bash
command -v mysqldump || echo 'NO MYSQLDUMP'
php -r 'echo "PHP CLI ", PHP_VERSION, PHP_EOL; echo extension_loaded("pdo_mysql") ? "PDO MYSQL OK\n" : "PDO MYSQL MISSING\n";'
test -r ~/laundrytrack_private/.env && echo 'PRIVATE CONFIG ACCESS OK'
```

If `mysqldump` is absent or blocked, do **not** improvise by changing server packages or running `sudo`. Use the university administrator's supported export process or phpMyAdmin **Export → SQL** for an appropriately private copy and verify restore on an isolated database.

## 2. Install the CLI-only backup script

**After PR #10 is merged into main:** retrieve just the script using a verified checkout (or `git pull` in your clone) and copy it into `~/laundrytrack_private/scripts/`. Keep `.env` and live site outside the repository.

Example from a new checkout (you may use an existing clone instead):

```bash
cd ~
git clone --depth=1 https://github.com/ReiZero7/LaundryTrack.git laundrytrack_backup_tool
php -l ~/laundrytrack_backup_tool/laundrytrack_private/scripts/backup_database.php
mkdir -p ~/laundrytrack_private/scripts
cp ~/laundrytrack_backup_tool/laundrytrack_private/scripts/backup_database.php ~/laundrytrack_private/scripts/
php ~/laundrytrack_private/scripts/backup_database.php
```

Expected output includes `BACKUP OK`, the selected DB name `s68160395`, a path under `~/laundrytrack_backups/mysql/`, byte size, and SHA256 checksum. If it fails, it reports `BACKUP FAILED`; do **not** treat it as a complete backup.

The script is safe to run multiple times: it produces a new timestamped file, never overwrites an old dump, and does not change the application or DB schema.

## 3. Confirm file integrity (read-only)

```bash
cd ~/laundrytrack_backups/mysql
ls -lh -- *.sql *.sha256
sha256sum --check -- *.sha256
ls -ld ~/laundrytrack_backups ~/laundrytrack_backups/mysql
```

Expected checksum: `OK`. **Do not paste database contents or a backup file into ChatGPT.** File names, file sizes and `OK` results are sufficient when requesting help.

## 4. Verify recovery — ISOLATED database ONLY

Never import the backup over `s68160395` while the production site is active.

A restore verification requires MySQL permissions to create an entirely separate disposable test schema (for example, `laundrytrack_restore_test`), or a separately provisioned staging server. On managed university hosting, a student account might not have this permission; ask the hosting administrator rather than assuming it.

Safe manual procedure after the test schema has been created by its administrator:

1. Confirm the **target database name is NOT `s68160395`** and its tables can be discarded.
2. Use the supported MySQL client or phpMyAdmin **Import** to import the backup **only into the disposable test schema**.
3. Compare table counts for `laundry__users_tbl`, `laundry__orders_tbl`, `laundry__order_items_tbl`, `laundry__status_history_tbl`, and `laundry__order_rate_limits_tbl`.
4. Confirm selected synthetic or agreed-on record counts; do not print names, phone numbers or credentials in reports.
5. Keep the restored database private and delete it when finished, subject to institutional retention policies.

The GitHub Actions workflow `.github/workflows/database-backup-restore.yml` performs the same **backup → SHA256 → restore → compare** procedure on completely synthetic data in `laundrytrack_ci` and `laundrytrack_restore_ci`. It **does not** prove a real production snapshot has been restored successfully; that remains a separate manual acceptance criterion.

## 5. Operational policy

- Keep at least one **encrypted off-server copy** in addition to the protected Angsila copy.
- Re-test restore after major schema updates or before high-risk changes.
- Agree a sensible backup cadence and retention period for actual use; make sure backups do not indefinitely retain customer personal data.
- No email verification is needed: user chose not to adopt abandoned PR #8.
