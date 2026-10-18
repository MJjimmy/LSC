# LSC Railpack review — historical candidate

> Superseded by `RUNTIME_FIX.md` and the dedicated `Dockerfile.railway`. The three findings below describe the old candidate, not the replacement runtime.

## Scope

This branch prepares the existing PHP application for Railpack's FrankenPHP runtime. It does not rewrite LSC, replace the database, enable notification providers, or deploy a Railway service.

The branch is based on GitHub main at `f7a822863b2afdc959159faa86ea09f4bbb699bf`. The separate review checkout is `D:\LSC-Railpack`. The user's canonical project remains `C:\Users\Jimmy\Desktop\LSC`; its unrelated edits and local Docker files are preserved. Only the new deployment configuration, regression test, workflow and this guide are copied there. Do not replace the Desktop application with this checkout or upload all its untracked Docker files.

## Configuration

- `composer.json`: detects PHP 8.4 and declares PDO MySQL, mbstring, curl and fileinfo. No third-party application packages, Composer scripts or plugins are enabled.
- `railpack.json`: selects PHP, sets production defaults, excludes secrets, uploaded data and development artifacts, and uses Railpack's production start script.
- `Caddyfile`: serves only `/app/public`, listens on Railway's `PORT`, blocks dotfiles, retains the application 404 page and serves `/healthz` without needing database access.
- `php.ini`: hides PHP details/errors and bounds memory, request duration and upload sizes.
- Old `Procfile`, `nixpacks.toml` and `railway.json` overrides are removed from this review branch. They would otherwise select the old builder or PHP development server. Existing local Docker files are not removed.

Railway's current documentation deprecates `railway.json`/`railway.toml` for new services. This review deliberately does not add an infrastructure-as-code provisioning script or apply any cloud configuration. When deployment is approved, select Railpack in the service settings, leave build/start overrides empty, and let Railpack use the repository configuration. Do not merge this branch into a branch connected to Railway until the release gate below is resolved.

## Required later, before deployment

In Railway's private service variables set `MYSQLHOST`, `MYSQLPORT`, `MYSQLDATABASE`, `MYSQLUSER`, `MYSQLPASSWORD` and an HTTPS `APP_URL`. Keep `APP_ENV=production` and `APP_DEBUG=0`. Never commit actual secrets or reuse development example passwords. Use a dedicated non-root database account and private database networking.

Mount persistent storage at `/app/storage/uploads` and verify its permissions. Back up the database and uploaded files and test restoration. Import the appropriate schema/migrations manually into the chosen new or existing database after reviewing them; this configuration does not run destructive SQL or automatic migrations. Keep OAuth, email/SMS workers and paid/live delivery disabled until separately configured and tested.

Set the Railway liveness path to `/healthz` and allow the port provided by Railway. This endpoint proves only that the server is responding. It does NOT prove database readiness, registration/login, persistence or provider connectivity. Verify those paths with a designated staging database before releasing.

## Verification performed

- Five offline deployment-contract tests pass: `python tests/railpack_config_test.py`.
- A Railpack 0.42.0 candidate image was built: `sha256:d2ee9ccd6f41107857e1690ae5cb4ca33d671c8eddeb12abd3a29b49831739c2`.
- During runtime testing the candidate's health route incorrectly reached the missing-file rewrite. The corrected Caddyfile places liveness first inside the ordered route. It was mounted read-only into that exact candidate for verification; the final corrected configuration has NOT been rebuilt into a newly scanned image.
- With that corrected configuration, eight HTTP paths were checked inside a disposable container: the landing page and liveness return 200; six non-public/missing paths return 404. No PHP version header was exposed. The container had no external network, public ports, real credentials or database connection, ran with UID 65532, a read-only filesystem and bounded temporary storage, and was removed after testing.
- All 76 application PHP files present in that candidate passed syntax lint. Registration, authenticated database operations, uploads across redeploys and SSO were not tested in this review.

## Security / release gate

**Not approved for live deployment.** Docker Scout's final candidate-image report contains **0 Critical and 3 High** findings:

| Finding | Package | Review status |
| --- | --- | --- |
| CVE-2026-86140 | libxml2 | Vendor flags XML validation buffer overflow; applicability to LSC is not established. |
| CVE-2026-74860 | libxml2 | Advisory requires Python SAX bindings; do not mark resolved without confirming package/runtime applicability. |
| CVE-2026-85091 | zlib | Advisory version range and distro package classification need reconciliation; not treated as cleared. |

No exploit against LSC was demonstrated. These are not malware detections. The report is `D:\SecurityReview\lsc-railpack-final.sarif`. The user authorised publishing configuration on a review branch only, with Railway deployment blocked. Resolve or explicitly approve the remaining findings, rebuild from the final source, rescan that exact image and complete staging acceptance before merge/release. No scan or local test in this guide certifies the website as production-ready.

## Primary references checked

- Railpack PHP documentation/source at the reviewed release: https://github.com/railwayapp/railpack/blob/v0.42.0/docs/src/content/docs/languages/php.md
- Railpack configuration and exclusions: https://github.com/railwayapp/railpack/tree/v0.42.0/docs/src/content/docs/config
- Railway configuration guidance: https://docs.railway.com/config-as-code
- Debian advisories: https://security-tracker.debian.org/tracker/CVE-2026-86140 , https://security-tracker.debian.org/tracker/CVE-2026-74860 , https://security-tracker.debian.org/tracker/CVE-2026-85091
