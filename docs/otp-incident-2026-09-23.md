# OTP incident: 2026-09-23

## Verified observations

Read-only production log snapshot: 2026-09-23 20:49 Europe/Warsaw.
Window: September 22 and 23, Europe/Warsaw (UTC+02:00).

- 264 HTTP send-OTP requests, 70 distinct source IPs.
- September 22: 172 requests, 115 provider-accepted sends.
- September 23: 92 requests, 32 provider-accepted sends.
- Country lookup: 54 Pakistan IPs, 233 requests, 144 provider-accepted sends.
- Provider acceptance does not establish delivery, billing, or malicious intent
  for every individual request. IP geolocation is not proof of the sender's
  physical location; VPNs and proxies can change it.
- Active provider: Twilio Verify. Paid sending remains disabled. The two existing
  review-number exceptions and App Check remain unchanged.
- Read-only Twilio Verify service fetch returned error 20003. Its Fraud Guard
  settings could not be verified. Do not assume the protection is disabled.

## Applied containment

Host nginx blocks POST requests to `/api/mobile/auth/send-otp`, including normalized
URL, trailing-slash, and `index.php` variants, for Pakistan IPv4/IPv6 ranges.
The response is HTTP 403 JSON with `code: otp_region_restricted`.
Other API routes, appointments, calendar callbacks, and SSH are not country-blocked.

Configuration on the production host:

- `/etc/nginx/conf.d/esticly-otp-geo.conf`: source-address classification and route map.
- `/etc/nginx/esticly-geo/pk-cidrs.conf`: 1,170 IPv4 and 568 IPv6 prefixes.
- `/etc/nginx/fastpanel2-sites/esticly_com_usr/esticly.com.includes`: OTP rejection.
- `/var/www/esticly_com_usr/data/logs/esticly.com-otp-geo-blocked.log`: blocked requests.
- `/root/esticly-otp-geo-20260923/`: staging, test scripts, and `site.before` backup.

The API port is bound to `127.0.0.1:8000`, not a public interface. Classification
uses nginx `$remote_addr`; only the existing explicitly trusted Cloudflare networks
can supply forwarded client addresses. A forged X-Forwarded-For sent directly to
the origin does not change the country decision.

Source: [IP Geolocation by DB-IP](https://db-ip.com), Country Lite September 2026,
[CC BY 4.0](https://creativecommons.org/licenses/by/4.0/). The downloaded CSV's
decompressed SHA-1 matched the publisher's value:
`2d99eae57714d39e7670a12985ae3083f8df807b`.
Only validated IP ranges were converted into nginx configuration.

This is a monthly database snapshot, not an automatically updated service.
Refresh the country list monthly, preserving attribution, validating the generated
prefixes, running isolated tests and `nginx -t`, then reloading nginx. An update
failure must leave the previous valid list in place. Geo-IP coverage is imperfect
and cannot prevent an attacker using an address outside Pakistan.

## Verification and rollback

87 isolated nginx tests passed: all 70 observed IPs, IPv6, URL variants, and
unaffected appointment/calendar routes. The test listener trusted a special
header on localhost only and was stopped afterward. That trust is NOT present
in production. Production `nginx -t` passed, nginx was reloaded, `/up` returned
200, and an empty OTP request still required App Check. No SMS was sent.

To roll back this country rule only, restore `site.before` to the site include,
remove the dedicated `esticly-otp-geo.conf` and `pk-cidrs.conf`, run `nginx -t`,
and reload nginx. Do not modify the global OTP pause or review-phone configuration.
These are host-level changes; rebuilding the API image will not install them on
a replacement server. Preserve the files and attribution in infrastructure backups.

## Proposed next steps, not yet implemented

1. Resolve Twilio account/API authorization and inspect the correct Verify service.
   Confirm Fraud Guard is enabled. Start with Standard; consider Max during active
   abuse with a support path for false positives. Do not bypass RiskCheck, broadly
   safe-list prefixes, or fall back to Programmable Messaging after a rejection.
2. Configure Verify Geo Permissions from the real customer destination countries.
   These are recipient-number countries, NOT request-IP countries. Do not infer
   business markets from UI translation languages or IP geolocation.
3. Before restoring paid OTP, add an atomic global attempt/spend budget and a
   provider-failure circuit breaker. Reserve before calling Twilio and account for
   uncertain timeouts. Alert below the cap; stop automatically at the cap.
   Twilio usage alerts alone are not a hard stop. Agree thresholds against normal
   traffic and destination prices, rather than promising an exact monetary ceiling
   based only on send counts.
4. Keep the deployed one-attempt-per-phone-per-60-seconds limit and five-attempts-
   per-IP-per-rolling-hour limit. Propose five paid attempts per phone per day,
   with support recovery and progressive retry delays after repeated failures.
   Test concurrent requests, shared IPs, cache failures, and number normalization.
5. Add server-verified CAPTCHA for suspicious traffic and installation-bound,
   cryptographically proven request identity in the mobile apps. App Check and
   consumed limited-use tokens remain required; a client-supplied device ID alone
   is not a trustworthy device ban. Reinstall/reset can change installation identity.
6. Consider paid Lookup only after cheaper gates and budgets. SMS Pumping Risk
   should be checked live, not cached or bulk-replayed against old traffic. Count
   Lookup expense in the budget. Evaluate line-type rules before excluding VoIP
   customers; do not deny unknown types without a fallback policy.
7. Restore sending in a controlled test, check provider delivery and successful
   OTP verification, then expand. Track request-to-verified-login conversion,
   country/network, cost, and false positives without logging OTPs or secrets.

## Official references

- [Verify Fraud Guard](https://www.twilio.com/docs/verify/preventing-toll-fraud/sms-fraud-guard)
- [Verify Geo Permissions](https://www.twilio.com/docs/verify/preventing-toll-fraud/verify-geo-permissions)
- [Preventing toll fraud](https://www.twilio.com/docs/verify/preventing-toll-fraud)
- [Permission denied 20003](https://www.twilio.com/docs/api/errors/20003)
- [SMS Pumping Risk](https://www.twilio.com/docs/lookup/v2-api/sms-pumping-risk)
- [Turnstile server validation](https://developers.cloudflare.com/turnstile/get-started/server-side-validation/)

CAPTCHA is an additional layer, not a substantiated guarantee to stop 95% of bots.
Programmable Messaging Pumping Protection is a different product and may carry
additional charges; enabling it is not the appropriate first step for this active
Verify integration.
