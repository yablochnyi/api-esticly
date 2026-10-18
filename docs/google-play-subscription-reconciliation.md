# Google Play subscription recovery

## Delivery

Google Play publishes RTDN to `projects/esticly-491412/topics/esticly-play-rtdn`.
The push subscription is `esticly-play-rtdn-push` and must not expire due to
inactivity. Keep the standard Pub/Sub envelope (no payload unwrapping), a
120-second acknowledgement deadline, and seven-day message retention.
Prefer exponential retry backoff.

The HTTPS receiver is `/api/mobile/billing/google/rtdn`. Its authentication
secret must match `GOOGLE_PLAY_RTDN_TOKEN`. Never copy the complete push URL
into documentation, support messages, or logs. Rotate both ends together.
The current receiver validates its shared secret; enabling Pub/Sub OIDC
alone does not replace this validation.

Send a Play Console test notification and confirm the corresponding
`google_play_rtdn_test_notification` event on the server. Console publication
success alone is not proof of delivery. A test event does not exercise a real
renewal or prove that access was updated.

Temporary lookup failures and purchases not yet associated with an account
return non-2xx so Pub/Sub retries. Purchase ownership can be recovered from
the account identifier returned by the authenticated Google API. Do not
trust account identifiers from a push payload.

## Entitlements and reconciliation

All known eligible purchases are considered. Active, grace-period, and
canceled-but-unexpired purchases grant access; hold, paused, pending, and
expired purchases do not. A linked replacement prevents resurrection of the
old purchase. Independent active Pro and Basic purchases retain Pro until
it expires, then fall back to Basic. Synchronization never cancels a plan.

The scheduler runs `billing:sync-google` every fifteen minutes as a fallback
for missing notifications. It refreshes all reconcilable purchase tokens,
not just the row with the latest stored expiration. One failed lookup does
not prevent the remaining purchases from being checked. Tokens whose stored
expiry is older than sixty days are excluded.

For an authorized support check:

```sh
php artisan billing:sync-google --user=175 --force
```

Omit `--user` to reconcile all known Google subscription owners. This fetches
store truth and acknowledges eligible purchases; it does not initiate a
charge, refund, cancellation, or free entitlement grant. Newly discovered
orders are imported into payment statistics; the hourly analytics job
remains the fallback for order lookup failures.

## Android release validation

The server fix does not replace the installed Android purchase flow. Release
the matching mobile fix after testing with Google Play license testers:

- first purchase and restore of an already-owned plan;
- Basic to Pro replacement and deferred Pro to Basic replacement;
- stale server expiration, account hold, wrong Play account, and query failure;
- multiple independent purchases (stop and request review, do not buy again);
- RTDN before mobile verification, duplicate delivery, and failed delivery retry;
- renewal outside the app followed by access and payment-statistics checks.

Local HTTP mocks cover these server transitions but do not substitute for
Play Billing testing. Never charge a customer's account as a test.
