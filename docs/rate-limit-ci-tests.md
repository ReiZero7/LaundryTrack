# CI regression tests for LaundryTrack Rate Limiting

This change adds **only tests and GitHub Actions automation**; it does not change production code, MySQL data or the `RATE_LIMIT_SECRET` already installed on Angsila.

## Safety boundaries

- Tests run in an ephemeral GitHub Actions MySQL 8.0 service, database **`laundrytrack_ci`**.
- The test entrypoint refuses to run unless `APP_ENV=ci`, `DB_NAME=laundrytrack_ci` and MySQL's current database is exactly `laundrytrack_ci`.
- A new random test-only HMAC key is generated inside the CI job. No production passwords or API secrets are used.
- Test IP addresses use the reserved TEST-NET ranges.
- Do **not** copy the CI SQL fixture or invoke the test file from Angsila. It deliberately resets its test counters.
- These tests exercise the real `order_rate_limit_allowed()` helper and rate-limit table (no real orders, users, or web requests are created).

## Automated assertions

1. Guest submissions 1–8 accepted in 15 minutes; the 9th rejected without incrementing counters.
2. Spoofed `X-Forwarded-For` does not allow quota bypass.
3. Different server-observed IP has an independent guest quota.
4. Member submissions 1–10 accepted; the 11th rejected.
5. Different member ID has an independent quota.
6. The guest 24h ceiling is 30; the 31st is rejected.
7. The member 24h ceiling is 50; the 51st is rejected.
8. Expired short/daily windows restart their counters.
9. Invalid server-observed IP, weak HMAC key and missing database table fail closed.
10. The workflow separately syntax-checks all PHP files.

## How to check

1. Open this PR's **Checks** tab.
2. Wait for **PHP syntax lint** and **Isolated MySQL rate-limit integration**.
3. Both should show green. If GitHub Actions are disabled on the repository, enable them deliberately from repo Settings, or inspect the code and run on a separate isolated CI runner.
4. PR merge should be considered only after CI succeeded; no production migration or redeployment is needed for this test-only PR.

**Not covered:** concurrent-request stress testing, actual SMTP/email verification and full end-to-end browser UI tests. Run these separately before heavy public traffic.
