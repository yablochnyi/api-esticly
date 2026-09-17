# App Check protection for OTP

## Deployment contract

- Firebase project: `esticly` / `725624745298`.
- Android: Play Integrity, Firebase app `1:725624745298:android:1e5c5292ccde1bb3c2de8b`.
- iOS: App Attest, Firebase app `1:725624745298:ios:63d7fc410b69b9c1c2de8b`.
- Both clients use package/bundle ID `com.esticly.app`.
- Mobile 2.0.8 (33) sends a fresh limited-use token in `X-Firebase-AppCheck` for
  each initial OTP send or resend. There is no automatic retry after an SMS call.
- Only POST `/api/mobile/auth/send-otp` is protected. Existing authenticated
  sessions, OTP verification, push, billing and store webhooks are unchanged.

Production enforcement is mandatory, including review accounts. Setting
`APP_CHECK_ENFORCE=false` only works with `APP_ENV=local` or `testing`.
Older mobile versions cannot request OTP once this backend is deployed. Existing
logged-in users remain logged in. Coordinate the store release and communicate
the required update; never add an unprotected legacy send endpoint.

The host nginx OTP pause is separate and is NOT removed by this change. It
returns `503 otp_paused` before Laravel. Keep it until the owner explicitly
authorizes testing/resuming SMS. No production deployment is performed by editing
these files.

## Backend configuration

The verified public project/app identifiers above are the config defaults. For
the production Docker environment, verify these in `.env.docker`:

```dotenv
APP_ENV=production
APP_CHECK_ENFORCE=true
APP_CHECK_CACHE_STORE=database
FIREBASE_PROJECT_NUMBER=725624745298
FIREBASE_APP_CHECK_APP_IDS=1:725624745298:android:1e5c5292ccde1bb3c2de8b,1:725624745298:ios:63d7fc410b69b9c1c2de8b
OTP_IP_HOURLY_LIMIT=5
```

Run `composer install` (or rebuild the existing Docker image) so the locked
`firebase/php-jwt` dependency is installed. A deploy without vendor updates will
fail closed. The normal entrypoint runs migrations. Cache/cache_locks tables must
exist, and all workers must use the same database cache. Redis is supported if it
is persistent, shared and configured not to evict replay records.

Do NOT run `cache:clear`, `optimize:clear`, flush Redis or change cache prefixes
while live: that removes both replay reservations and OTP limits. Container
startup no longer flushes the data cache. Configuration/view caches may be cleared.

## Verification and replay protection

JWT verification uses Google's HTTPS JWKS endpoint, RS256 only, required JWT type,
project issuer/audience, expiry/issued time and an allowlist of the two app IDs.
Public keys are cached for six hours. Unknown-key refreshes are serialized and
limited to once a minute; Google/cache failure rejects the request without SMS.
An App Store Connect key or Firebase service-account private key is NOT needed.

After verification, an atomic shared-cache reservation consumes the token's `jti`
(scoped to app/issuer and stored as a SHA-256 digest). Tokens without `jti` are
rejected: clients must use `getLimitedUseToken()`. The reservation survives until
after expiration and is not released on validation/provider failure. This is
local replay protection for this API, NOT Firebase's Node.js consume-token API.
Any other service accepting these tokens must share equivalent replay state.
App Check is not proof of a human, nor a stable device identifier: automation
using genuine apps/devices can still obtain fresh tokens. Existing IP/phone
limits and Twilio protections remain necessary. No global SMS cap was added.

Responses: `403 app_check_required` for missing/invalid/used tokens;
`503 app_check_unavailable` for configuration, cache or key-fetch failures.
Audit events: `otp_app_check_verified`, `otp_app_check_rejected`,
`otp_app_check_unavailable`. Tokens and provider exception messages are not logged.

## Test checklist and rollout

1. Run `php vendor/bin/phpunit tests/Feature/OtpAppCheckTest.php tests/Feature/OtpSendProtectionTest.php`.
   All Twilio/Google HTTP calls in these tests are mocked; they never send SMS.
2. Deploy the API to a staging backend with its own Twilio test setup first, or
   deploy behind the existing nginx production pause. Do not use a production
   bypass to support old clients or debug builds.
3. Install the Android AAB via Google Play internal testing (not a sideloaded APK).
   The App Check SHA-256 must match Play's app signing key, not the upload key.
4. Install the iOS build through TestFlight on a real supported iPhone. App Attest
   entitlement must be `production`; regenerate provisioning profiles if Xcode
   requests this after enabling the capability.
5. For end-to-end staging tests build with an explicit HTTPS staging API_BASE_URL.
   Store builds must use the production default and must NOT contain localhost.
6. Verify accepted Android and iOS attestations in backend logs, successful
   initial send/resend, working code confirmation and readable localized errors.
   With the nginx pause still in place, OTP returns `otp_paused` and no Laravel
   attestation event: this is not an end-to-end App Check success.
7. Invalid, expired, other-app and replayed tokens must not call Twilio. Test a
   replay from a different IP/phone; verify the sixth fresh token from one IP
   still hits the existing five-per-hour limit. Use mocks for abuse tests.
8. Resume actual production OTP ONLY after explicit owner approval and successful
   testing/release coordination. Do not temporarily remove the App Check guard.

For local simulator work only: use a separate Firebase test project, register its
debug token there, set `--dart-define=APP_CHECK_DEBUG=true` in a debug build and
point the app/backend at that test environment. Never register debug tokens in
the production Firebase project or share them in logs/chat. Release/profile builds
ignore this flag. App Check cannot distinguish a registered production debug
token from other valid project tokens at our JWT layer.

Official references:
- https://firebase.google.com/docs/app-check/flutter/default-providers
- https://firebase.google.com/docs/app-check/custom-resource-backend
