# LSC runtime replacement — verified locally

This supersedes the runtime described in `RAILPACK_REVIEW.md`. The application code and database are not rewritten or deleted. The replacement uses a dedicated **Dockerfile.railway**, not the old local Dockerfile and not the earlier Railpack image.

## What changed

- Maintained Chainguard PHP-FPM and NGINX bases are pinned by digest in `Dockerfile.railway`.
- PHP runs as UID/GID 65532. NGINX and PHP-FPM are supervised by the s6 tools already supplied in the vendor image. No PHP development server, host-installed tools, package-manager installation, or custom-built security library is used.
- The FPM listener is a private Unix socket. Only `/app/public` is served. Health passes through both NGINX and PHP-FPM; it does not check the database.
- The validated `PORT` is used at startup. Runtime state is confined to `/tmp/lsc`; immutable service scripts work even when `/tmp` is mounted noexec. PHP environment variables reach FPM workers without being written into the generated server configuration.
- Dockerfile-specific build exclusions leave the original local `.dockerignore` and Docker files alone. Source copying is explicit, excluding secret environment files, database exports, existing uploaded data and private documents.

## Resolution of the three earlier findings

- **CVE-2026-86140 / CVE-2026-74860:** the replacement loads libxml **2.15.4**, from vendor package `libxml2 2.15.4-r1`, rather than the previous Debian 2.9.14 library. This release includes the relevant upstream fixes.
- **CVE-2026-85091:** the replacement uses vendor package `zlib 1.3.2.1_rc20260921-r0`. Wolfi's package recipe pins source commit `767c4c947852e143f582c85f14cf573411df1b35`. GitHub's upstream comparison confirms that source includes fix commit `df84af25dc1942490e1d1c899a07619152a46148` (23 commits ahead, none behind).
- No warnings were hidden, no vulnerable libraries were manually deleted, and no VEX suppression was added.

Primary evidence:
- https://raw.githubusercontent.com/wolfi-dev/os/main/zlib.yaml
- https://github.com/madler/zlib/compare/df84af25dc1942490e1d1c899a07619152a46148...767c4c947852e143f582c85f14cf573411df1b35
- https://security-tracker.debian.org/tracker/CVE-2026-86140
- https://security-tracker.debian.org/tracker/CVE-2026-74860
- https://edu.chainguard.dev/chainguard/containers/migration/migration-guides/php/

## Verification

The exact rebuilt image tested and scanned is `sha256:71f395b574aaed72ea2ee5acb96d934da23128212513037f13321372264187b4`; the all-severity Docker Scout report is `D:\SecurityReview\lsc-runtime-fixed-release.sarif`. At verification it indexed 118 packages and found **zero known vulnerabilities**. This is a scan snapshot, not a guarantee of application security or future package safety.

Reproduce with installed Docker and Python:

```text
python tests/runtime_config_test.py
docker build --platform linux/amd64 --network none -f Dockerfile.railway -t lsc-runtime-fixed:review .
python tests/runtime_smoke_test.py
```

The smoke test uses a unique disposable container, no published ports, no external network, no capabilities, a read-only root filesystem, bounded temporary storage and no actual provider/database credentials. It verifies eight HTTP paths, required PHP modules, PHP syntax, NGINX recovery under supervision, graceful stop and cleanup. Do not point it at a live website or an existing container.

## Railway deployment settings — not applied by this fix

When deployment is authorised, connect the intended GitHub branch and set `RAILWAY_DOCKERFILE_PATH=Dockerfile.railway`. Select Dockerfile builds if the dashboard requests the builder. Remove any old PHP development-server start-command override: leave the start override empty so the image entrypoint is used. Do not let the service fall back to the earlier Railpack configuration.

Set the health path to `/healthz`. Set the normal `MYSQLHOST`, `MYSQLPORT`, `MYSQLDATABASE`, `MYSQLUSER`, `MYSQLPASSWORD` and HTTPS `APP_URL` values privately in Railway. Production/debug-off defaults are already supplied by the image. Never paste actual database credentials into source, documentation or chat.

A persistent volume is still required at `/app/storage/uploads`; ensure UID 65532 can write it. Check the database, designated staging accounts, login/settings, upload/download and persistence before release. Keep live mail/SMS workers disabled until separately configured and verified. Existing databases/volumes must not be deleted or reinitialised as part of this runtime change. Backups, restore tests, provider configuration and broader application security remain separate release gates.

No Railway service or main-branch deployment is changed by building or testing this replacement.
