# Apple subscription periods deployment, 2026-09-29

## Incident

TestFlight build 2.0.10 (42) could purchase `esticly_basic_6months`, but the
production API rejected verification with `app_store_unknown_product`.
The deployed Apple product map still recognized monthly products only.

## Scoped deployment

Updated the server working tree and running `beautycrm_api` container:

- `app/Support/AppStoreSubscriptions.php`: all six Apple products and latest
  applicable transaction selection within the original transaction chain.
- `app/Support/OrgSubscription.php`: expose the actual current store product.
- `app/Services/BillingPaymentImporter.php`: reuse the Apple product map.

Host and container originals matched before deployment. A hash guard prevented
overwriting files changed since inspection. Originals are retained on the server
under `/root/esticly-billing-fix-20260929/backup`. PHP was gracefully reloaded and
Horizon restarted. No database, OTP, credentials, or Google billing code changed.
The Docker image was not rebuilt; rebuild from the updated server working tree
before recreating the container from an image.

## Verification

- Local `SubscriptionPeriodsTest` and `BillingDashboardTest`: 14 tests passed,
  94 assertions.
- Server PHP syntax and deployed file hashes passed.
- Read-only App Store Server API lookup confirmed the failed purchase is a
  Sandbox `esticly_basic_6months` transaction for the expected bundle.
- All six Apple product IDs map to their correct tiers.
- Public mobile version endpoint returned HTTP 200; Horizon is running.

No manual entitlement update or new purchase was performed. Complete the
end-to-end check with Restore Purchases in TestFlight and verify the success log
and updated profile. Existing manually granted access was left unchanged.
