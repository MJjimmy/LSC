# UI fix

Branch: `ui-fix`.

## Website

- The group header provides a **Leave group** confirmation form for members.
- The server removes only the signed-in user's membership in that group. Creators cannot leave their own groups; the disabled button explains this.
- Shared tasks, files, uploaded bytes and chat history are retained. The member's stale join request for that group is cleared in the same transaction. Other group memberships are unchanged.
- Dashboard assignments from a former group are no longer exposed to the leaving member. Existing site-administrator oversight permissions remain unchanged.
- CSRF, active-account checks and creator protection remain server-enforced, including forged form submissions.
- All website layouts use light appearance before first paint. Existing saved dark preferences are replaced without clearing cookies, sessions or unrelated settings. The dark-mode control is removed.

## Focused verification

The existing combined workflow executes `node tests/light-theme-test.cjs` and the bounded, isolated `tests/final-auth-runner.py ui-fix.php`, followed by the existing authentication, administrator and Python regressions. The PHP fixture uses actual production classes over owned synthetic SQLite data; only existing MySQL ensure-table DDL is substituted. It is not a live MySQL concurrency or production-account test.

The runtime/database image job remains paused on the production deployment branch. No phone rehearsal, installation or data clearing is part of this change.
