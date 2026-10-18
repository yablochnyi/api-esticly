# Google deferred replacements: 2026-09-29

Account 152's license-test chain reached the API via RTDN: Basic monthly,
Pro monthly, then a deferred replacement to Basic six months. Subscription 22
was acknowledged and switched at the accelerated test renewal. No purchases
were deleted or canceled during this fix.

The mobile listener previously ignored callbacks containing the old SKU and
the replacement token. Build 43 accepts that callback only for a deferred
replacement of the known purchase, rejects the old token replay, verifies it
with the server, and displays a scheduled-change message if the old entitlement
is still effective. Error/cancellation events without a SKU are also handled.
Store launch and callback waiting share a bounded timeout.

The API now chooses the dated entitlement with the latest expiry rather than
the requested SKU or first line item. A future replacement without expiry must
not grant access early; an expired old line must not override an active new one.
Both item orders and both requested SKUs are covered by regression tests.

## Deployment

Only `app/Support/GooglePlaySubscriptions.php` was updated, in both host source
and the running `beautycrm_api` container. Original SHA-256:
`f99245493263fed83e63b40a5515ef1becde8dcbda956176211e3057c8fbe1f4`.
Backup: `/root/esticly-google-deferred-20260929/backup.php`.
PHP was gracefully reloaded; Horizon restarted and reported running.
`https://esticly.com/up` returned 200. The deployed resolver selected Basic six
months from account 152's two-item stored Google snapshot.

The Docker image was NOT rebuilt. Rebuild it from the updated host source before
recreating the container, or the image's older code could return.
No App Check, OTP or subscription data changes were made by this deployment.

## Verification and remaining checks

- API billing tests: 31 passed, 173 assertions.
- Android artifact: 2.0.10 (43), production API and normal App Check.
- Re-test Basic monthly -> Pro monthly -> Basic six months through Play's
  license-testing track; scheduled changes must close the spinner while keeping
  the paid current entitlement until renewal. Reopen/restore after renewal.
- iOS Pro six months -> Basic six months has not been reproduced or verified
  by this fix; it still needs a separate TestFlight check.

Reference: https://developer.android.com/google/play/billing/subscriptions#deferred
