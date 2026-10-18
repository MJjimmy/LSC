# MariaDB to native Railway MySQL cutover

The native `MySQL` service is kept on its existing image so Railway can retain its built-in database interface. The owner explicitly accepted the reported image vulnerabilities (4 Critical, 45 High advisories in the reviewed `mysql:8` image); this is not a clean security verdict.

## Preservation boundary

- Keep MariaDB, its volume, and the website upload volume. Do not delete, reset, or replay the fresh-install schema against existing data.
- Use `bin/transfer-to-mysql.php` only as a private CLI task. It has no public HTTP endpoint and refuses execution without an explicit environment confirmation.
- First use `LSC_TRANSFER_CONFIRM=inspect-only`. This reads only table names and row counts; no passwords, hashes, messages or user details are logged.
- Before copying, enable `LSC_DB_CUTOVER_MAINTENANCE=1` on the web service and verify app GET/POST requests return 503 while `/healthz` remains healthy. Wait for the previous serving deployment to be removed.
- Use `LSC_TRANSFER_CONFIRM=copy-preserve-source` only in that maintenance window. The source is read-locked, not modified. Existing differing target tables/data cause a refusal; nothing is dropped, truncated or overwritten.
- On a fresh target, copy actual `SHOW CREATE TABLE` definitions and every record with explicit IDs. Preserve hashes, roles, nulls, unicode, file paths and auto-increment state. Verify normalized columns/indexes/foreign keys, row counts and length-prefixed whole-row SHA256 fingerprints for every table before allowing application cutover.
- Use a database-scoped `lsc_user` account on MySQL, not root. Its password is a provider reference to the existing private application secret, not a value committed to the repository. Source and target remain private. No new TCP proxy is required.
- After the verified copy, point only the website's `MYSQL*` variables at MySQL, clear the one-time command and all temporary source/root references, and disable maintenance. The original MariaDB credentials remain available for rollback.

## Failure / rollback

If inspection or copy fails, keep the website on MariaDB. A failed copy can leave newly-created empty/incomplete target tables because MySQL DDL is not transactional. Do not retry by dropping tables. Investigate and obtain approval before any cleanup or target replacement. An identical completed transfer is verified safely on retry.

Before accepting new writes on MySQL, rollback is simply restoring the website's private MariaDB references and disabling maintenance. Once new writes have been accepted on MySQL, do not switch back without reconciling those writes.

## Tests

`tests/mysql_transfer_runtime_test.py` creates isolated, disposable source/target containers on a new internal Docker network. It tests read-only inspection, real MariaDB-to-MySQL transfer, data/schema/ID/auto-increment preservation, safe identical retries, conflicting-target refusal and missing-confirmation refusal. No cloud credentials or existing Docker volumes are used.

`tests/cutover_gate_runtime_test.py` proves the maintenance flag stops web GET/POST requests before database access while allowing health checks and CLI tasks. The flag is off by default.
