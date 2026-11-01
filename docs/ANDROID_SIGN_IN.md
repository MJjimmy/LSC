# Android Google sign-in handoff

This uses the **same PHP backend, Google web OAuth client and existing identities**. There is no native Google SDK, replacement backend or additional configuration mode. Email delivery and queue activation flags remain unchanged.

## Wire contract

1. The embedded login/register Google partial recognizes `?app=android` or the existing `$_SESSION['android_client']` marker. It shows **Continue with Google** linking to the fixed `lscapp://google` trigger rather than posting Google OAuth inside the WebView. Desktop retains its existing form.
2. Native intercepts that exact trigger. Generate independent cryptographically random 32-byte values and lowercase-hex encode them as `verifier` and `state` (64 characters each). Keep the verifier private in native pending-request state. Compute `challenge = SHA256hex(verifier)` over the 64-character ASCII verifier, **not decoded bytes**, yielding lowercase 64-hex.
3. Open the system browser at the application's exact configured HTTPS origin and path:
   ```text
   /app-auth.php?action=start&challenge=<64hex>&state=<64hex>
   ```
   Native must not accept an arbitrary host, port, scheme or redirect target from web content. Use the same fixed backend origin as the WebView.
4. GET creates a browser-session-private intent with random `intent_id` and a **600-second** deadline. A minimal document immediately submits its private form, without showing another LSC sign-in screen or asking for another click. The POST still requires that session's CSRF token and current intent id. Only script-disabled browsers see a fallback button. Merely visiting start/complete while already signed into the website never issues a handoff code.
5. POST starts the existing Google OAuth flow, with `next=app-auth.php?action=complete`, `link_user_id=null`, and the private intent tag. The registered Google callback **stays `/oauth.php?provider=google`**. Existing state, Google PKCE, browser-user binding and provider identity verification still apply. Only after a successful Google exchange and `OAuthAccounts::resolve` does the callback log in and mark the corresponding intent completed. No implicit email linking or role changes are introduced; new Google identities still use the existing student defaults.
6. GET complete requires the completed intent's user id to equal the current browser auth id and an active account. It consumes that intent once and persists only `SHA256hex(code)`, native challenge, authoritative user id and a **120-second** expiry. The no-store/no-referrer response is a **303 redirect directly to the fixed native target**, with no LSC success page or manual Return to LSC link:
   ```text
   lscapp://auth?code=<64hex>&state=<64hex>
   ```
   No Google token, access token, password, session id or verifier is put in this link. An Android/browser permission prompt may still require user approval; the website must not bypass device policy.
7. Native accepts only the exact `lscapp://auth` target, with no alternate user-info/port/path/fragment and exactly one well-formed code and state. Compare state against a still-pending request (maximum 600 seconds since native start), atomically claim that pending request, and reject missing/mismatched/expired/replayed callbacks. State matching is native's responsibility: the exchange body intentionally does not include state.
8. Call `WebView.postUrl` on the **fixed original backend HTTPS URL**, never a URL from the callback:
   ```text
   /app-auth.php?action=exchange
   ```
   UTF-8 form body:
   ```text
   code=<64hex>&verifier=<64hex>
   ```
   Let the WebView store its own HttpOnly session cookie and follow the **303** to `dashboard.php`. Do not copy browser cookies or use a native HTTP client for the exchange. Discard native pending state/verifier when claimed; failed/expired handoffs require a new attempt.

The exchange intentionally has no browser-session CSRF token because the fresh WebView proves possession of the single-use code plus its private verifier. It is IP-rate-limited to 30 requests per 600-second window using the existing DB limiter (browser actions: 120/600). It locks the code/user lookup transactionally, constant-time compares SHA256hex(verifier) to the saved challenge, checks expiry/active account, and atomically deletes the code. It invokes existing `Auth::login` with the **stored database role**, sets `android_client=true`, and resets session age/activity. Wrong verifier does not consume a legitimate code. Inactive, expired, unknown and replayed codes all fail with the same sanitized message. Infrastructure failures are also sanitized. The existing proxy-IP trust configuration is unchanged.

A custom scheme is not exclusive ownership of the link; PKCE prevents another app intercepting a code from redeeming it without the private verifier. Native must still validate the exact target/state, keep the verifier out of logs/intents/URLs and restrict WebView navigation to the configured backend. Never log exchange bodies, completion links or OAuth callback queries in application/analytics logs.

## Schema and deployment

Before serving the new route, run the existing CLI-only preparation command in the deployment environment:

```sh
php bin/prepare-provider-schema.php
```

It additively creates `app_auth_codes` if absent. It infers the existing `users.user_id` integer/signedness, uses InnoDB, and verifies metadata for exact ASCII `ascii_bin` `CHAR(64)` hash/challenge columns, non-null matching user id, non-null unsigned bigint Unix expiry, exact full primary key, expiry index and users foreign key. Existing incompatible schemas fail closed; it never drops/resets/repairs existing tables or data. Existing provider and queue checks remain. Expired rows are rejected, not automatically redeemed or renewed; operational retention cleanup may delete expired rows separately if needed. Successful redemption deletes its row.

Keep the existing `APP_URL`, Google client variables and registered callback. No new secrets are required. This document does not claim the migration or deployment has been run.

## Offline verification

```sh
php tests/app-sign-in.php
php tests/google-sign-in.php
```

Requires PHP with `pdo_sqlite` and sessions (the existing Google suite additionally uses `mbstring`). The new suite uses only an in-memory SQLite database, session state, synthetic users and obviously fake provider settings. It never loads `.env`, uses the live database, sends email, creates live accounts or calls Google. It covers browser-login bypass prevention, current/private intent and state checks, cross-session/unknown/replayed/expired intents, one-time completion, code hashing/expiry, incorrect verifier, inactive/unknown/expired/replayed codes and role-preserving WebView login. Source-wiring and template checks supplement these tests; they do not establish HTTP headers, live Google behavior or MySQL concurrency.

**Verification boundary:** PHP is not installed locally; use the existing hosted PHP and isolated runtime CI, not local Docker or new installations. The HTTP fixture exercises automatic-start markup, CSRF refusal, a trusted callback hook in its disposable session, the real native 303 redirect and verifier-bound exchange/replay refusal. It does not contact Google, prove a real consent round trip, or prove the installed phone/browser will allow automatic external navigation. Verify that last step on the user's phone; no new APK is needed when the existing native return handler is already installed.
