# OTP send protection

The mobile send-OTP endpoint allows at most **5 provider send attempts per IP in
any rolling 60-minute window**. Phone numbers share the IP allowance. Existing
route-level per-phone and per-minute limits still apply. There is no new global
send limit, country block, CAPTCHA, or global provider-failure circuit breaker.

Reservations are taken under a cache lock before contacting Twilio. Failures and
timeouts consume a reservation because delivery or billing may be uncertain.
Concurrent requests share the same allowance. Cache failure or lock contention
returns 503 rather than allowing unmetered sends. A blocked request returns 429
with `ok: false`, `code: otp_rate_limited`, and `Retry-After` / `retry_after`.

When Verify is configured, rejection or failure never triggers a second send
through Twilio Messages. Messages is used only when Verify is not configured.
Provider failure returns 503, never `ok: true`. A successful response means the
provider accepted the request, not that the handset received the SMS.

## Deployment

1. Deploy the changed application files using the normal backend release process.
   This Docker project copies code into its image: rebuild/recreate the API
   service; updating the host checkout alone does not update the running API.
2. `OTP_IP_HOURLY_LIMIT=5` is the default. `OTP_CACHE_STORE` defaults to
   `CACHE_STORE`. Use persistent shared Redis or database storage in production;
   file cache is suitable only for workers sharing the same filesystem.
3. Regenerate cached configuration if the deployment uses it. Do **not** flush
   application cache as part of deployment: this resets active OTP allowances.
   Cache store/prefix changes and cache loss also reset existing allowances.
4. Verify the running image's `config('otp.ip_hourly_limit')` is 5 and the cache
   store is persistent. Existing attempts before this release are not backfilled.
5. Keep the API reachable only through the trusted reverse proxy. On the current
   host, nginx appends `$remote_addr` to `X-Forwarded-For`; a test covers a forged
   prefix followed by this trusted address. Recheck this if the proxy topology
   changes. Do not expose the internal API port publicly.
6. Monitor `otp_send_result` and `otp_guard_unavailable`. The former records
   request ID, resolved IP, masked phone, keyed phone fingerprint and outcome.
   OTP values, provider exception text and full phone numbers are not logged by
   this send handler. Requests rejected by validation or existing route throttles
   still appear in access logs, but do not reach this handler's audit event.

Configured App Store/Google Play review phones keep their existing no-SMS path
and do not consume the paid-send allowance. No database migration or mobile
release is required. Existing mobile versions display their existing error UI
on HTTP 429/503; no new countdown UI is added.

## Limits of this protection

This does not prove a requester is human. Different real IPs each receive their
own allowance, and people behind a shared Wi-Fi/mobile gateway share an allowance.
It is not a monetary cap; SMS prices depend on destination. It protects only OTP
sends, not appointment reminders or marketing SMS. Keep Twilio Fraud Guard on.
An inactive Twilio account must be resolved separately before SMS can work.

## Tests

Run `php vendor/bin/phpunit tests/Feature/OtpSendProtectionTest.php`. The provider
HTTP client is mocked: tests do not send SMS or access production data.
