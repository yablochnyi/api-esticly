# OTP Turnstile rollout

## Scope

- New phone numbers must solve Cloudflare Turnstile before either Twilio channel is called.
- Existing User phone numbers, active non-deleted staff with base_access, and configured review numbers retain the existing login flow. Existing numbers still have App Check and send limits; this exemption is not an anti-fraud guarantee.
- Feature flag: `OTP_CAPTCHA_ENABLED` (default false for a coordinated rollout).
- Enforcing the flag blocks new-number SMS requests from old app versions too. Do not trust a client version header or make CAPTCHA optional for older clients.
- No changes to billing, active sessions, OTP verification, country restrictions, or paid-send limits.

## Configuration

1. In Cloudflare Turnstile create a Managed widget, restricted to the hostname of APP_URL (production: esticly.com). Pre-clearance is not required. No DNS/proxy migration is needed.
2. Configure TURNSTILE_SITE_KEY and TURNSTILE_SECRET_KEY in the server environment. Never commit the secret or send it in chat. Never use Cloudflare's always-pass testing keys in production.
3. APP_URL must be the canonical HTTPS origin serving the API and the hosted challenge page. Siteverify's hostname is checked against it.
4. OTP_CACHE_STORE must be a shared, lock-capable cache, such as Redis or the existing database cache. All workers must share APP_KEY and cache. Cache/Cloudflare errors fail closed for new numbers.

## Request flow

1. App sends POST /api/mobile/auth/send-otp with a fresh limited-use App Check token.
2. If CAPTCHA is needed, API returns 403/captcha_required with a random 64-hex challenge ID and same-origin challenge URL. No SMS budget is reserved and Twilio is not contacted.
3. Flutter displays the hosted challenge in a WebView, in the chosen app language. The page contains the public site key, never the secret or phone number.
4. App repeats send-otp once with captcha_id, captcha_token, and a NEW limited-use App Check token (the first token was consumed even though the API returned 403).
5. Server validates the cached 5-minute challenge's phone fingerprint. A shared lock and consumption before Siteverify prevent concurrent/replayed uses. It checks success, hostname, action=otp_send, and cdata=challenge ID.
6. Existing phone/IP send limits run, then at most one provider request. A valid CAPTCHA does not bypass a pause or rate limit. Provider/network errors never cause automatic send retries.

Closing the WebView cancels the send. Each resend needs a fresh challenge. An invalid, consumed, or expired challenge returns captcha_invalid; missing keys/provider/cache failures return captcha_unavailable. Flutter releases its loading state and shows localized errors. Raw CAPTCHA tokens and secrets are not included in server diagnostic events.

## Deployment and verification

- Back up the affected production files and environment. Deploy the controller/service/view/route/config changes with the flag off first; refresh Laravel config/route/view caches using the existing deployment process.
- The API code is baked into its Docker image. Rebuild the image from the updated host source for a durable deployment; a container-only copy is not durable.
- Build and test BOTH Android and iOS with the new WebView dependency and fresh-token flow. This is not delivered by changing only the server.
- Enable the flag once live keys and the client are ready. Enabling it sooner intentionally prevents new registrations on existing store builds; obtain explicit approval for such a temporary closure.
- Test using live CAPTCHA and an authorized test number: initial send, retry/cooldown, cancellation, expired challenge, offline/timeout, existing user, active staff and review login. Check public-page HTTPS, nonce CSP and Cloudflare frame loading in real WebViews.
- Confirm direct requests lacking CAPTCHA cannot send on new numbers. Do not provide a production bypass for testing. Sandbox unit tests mock Siteverify and Twilio and do not send paid SMS.
- Rollback requires deliberate consideration: disabling the flag reopens new-number SMS without CAPTCHA. Prefer keeping new registrations closed during an incident over silently failing open.

## Remaining defenses

CAPTCHA raises the cost of automation but is not sufficient alone. Keep Fraud Guard, add agreed destination-country and emergency spend/volume limits separately, and monitor accepted-send/verification ratios. CAPTCHA cannot repair Twilio account/credential errors such as 20003.

Sources: https://developers.cloudflare.com/turnstile/get-started/mobile-implementation/ and https://developers.cloudflare.com/turnstile/get-started/server-side-validation/
