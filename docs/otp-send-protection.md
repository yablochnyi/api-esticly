# OTP send protection

The mobile send-OTP endpoint allows at most **2 provider send attempts per IP and
2 per normalized phone number in any rolling 60-minute window**. Phone numbers share the IP allowance. Existing
route-level per-phone and per-minute limits still apply. A normalized phone number
can reserve a paid send only once every **60 seconds**, across IPs and devices.
The phone lock wraps the IP reservation; rejected retries do not consume IP slots.
There is also a global limit of **20 provider send attempts per calendar day**, resetting
at midnight in `Europe/Warsaw` (including daylight-saving changes). It covers all
new phones/IPs and both Twilio channels. Existing users and active, non-deleted
staff with base access bypass the daily budget, but retain phone/IP hourly limits
and cooldowns. Registration is looked up server-side, never accepted from a request.
`OTP_REGISTERED_ONLY=true` blocks paid sends to all other numbers, independently
of CAPTCHA and the daily budget. `OTP_CAPTCHA_ENABLED=false` disables challenges.
CAPTCHA is documented in [the Turnstile runbook](otp-captcha.md). There is no global
provider-failure circuit breaker. A separate host nginx rule blocks Pakistan
IP ranges on this endpoint as of 2026-09-23; see
[the incident runbook](otp-incident-2026-09-23.md).

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
2. `OTP_IP_HOURLY_LIMIT=2` and `OTP_PHONE_HOURLY_LIMIT=2` are the defaults. `OTP_CACHE_STORE` defaults to
   `CACHE_STORE`. Use persistent shared Redis or database storage in production;
   file cache is suitable only for workers sharing the same filesystem.
3. Regenerate cached configuration if the deployment uses it. Do **not** flush
   application cache as part of deployment: this resets active OTP allowances.
   Cache store/prefix changes and cache loss also reset existing allowances.
   Set `OTP_GLOBAL_DAILY_LIMIT=20` and `OTP_DAILY_TIMEZONE=Europe/Warsaw`.
   The shared counter is `otp-send:global-day:YYYY-MM-DD`, protected by
   `otp-send:global-day:lock`. Backfill the current day from `provider_accepted`
   and `provider_failed` audit events before enabling the new guard; never give
   a fresh allowance mid-day. Missing/incomplete history requires conservatively
   exhausting today's budget. A zero daily limit disables paid OTP for new numbers.
   CAPTCHA/validation/phone/IP rejections do not consume daily slots. Failed or
   uncertain provider calls do, and restarting the API does not reset the counter.
4. Verify the running image's `config('otp.ip_hourly_limit')` and
   `config('otp.phone_hourly_limit')` are 2 and the cache store is persistent.
   Existing IP reservations are preserved when reducing the limit. The new phone
   hourly counter must be backfilled from provider-attempt audit events during a
   paused rollout if paid sends occurred in the preceding hour.
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
and do not consume the paid-send allowance or phone cooldown. Setting
`OTP_SENDING_ENABLED=false` pauses all paid sends while preserving these explicitly
configured review numbers and App Check enforcement. No database migration or
mobile release is required for server enforcement. The updated mobile countdown
starts at 60 seconds and respects `retry_after` on HTTP 429; distributing that UI
change requires a new mobile release. Existing builds still receive HTTP 429.

For a targeted real-SMS exception during a pause, configure
`OTP_PAUSED_ALLOWED_PHONE_HASHES` with comma-separated SHA-256 hashes of canonical
E.164 phone numbers (including `+`). This does not use a fixed review code or
bypass App Check, phone cooldown, IP limits, or Twilio verification. All other
numbers remain paused. Remove the exception when it is no longer needed.

## Limits of this protection

This does not prove a requester is human. Different real IPs each receive their
own allowance, and people behind a shared Wi-Fi/mobile gateway share an allowance.
It is not a monetary cap; SMS prices depend on destination. It protects only OTP
sends, not appointment reminders or marketing SMS. Keep Twilio Fraud Guard on.
An inactive Twilio account must be resolved separately before SMS can work.

## Tests

Run `php vendor/bin/phpunit tests/Feature/OtpSendProtectionTest.php`. The provider
HTTP client is mocked: tests do not send SMS or access production data.
