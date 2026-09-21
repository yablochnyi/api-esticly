# Billing dashboard

`/admin` is restricted to the existing `superadmin` gate, including Livewire updates.
The page uses a separate `subscription_payments` projection. It never changes
subscription status, user entitlements, store orders, or OTP configuration.

## Refresh and deploy

1. Deploy the API image and run `php artisan migrate --force`.
2. Run `php artisan billing:refresh-analytics` to populate historical snapshots.
3. The existing Laravel scheduler runs this command hourly. New Google orders
   are fetched immediately in that run; previously known orders refresh daily.
   `--force` refreshes all known Google orders. This requires the existing Play
   service account to have permission to read financial order information.

Google errors preserve previously fetched facts, record a generic error flag,
and return a nonzero command status. No tokens or raw API responses are copied
to the analytics table. Dashboard requests never call external store APIs.

## Definitions

- Current payers: unique subscription owners with an unexpired production
  store subscription and a positive, non-fully-refunded payment whose service
  period is still valid. Google cancellation of auto-renewal does not remove
  access before expiry. Apple revocation, grace periods, on-hold, sandbox,
  free trials and manual grants are not current payers.
- Where a user has overlapping store subscriptions, current platform/plan
  uses the paid subscription with the latest end date, avoiding double counts.
- Historical payments are deduplicated by provider and transaction/order ID.
  Subscription verification events are not themselves payments.
- Apple amounts are transaction `price` in milliunits converted to micros.
  Only already persisted App Store server snapshots are decoded; this is not
  an endpoint accepting or validating client-supplied signed transactions.
- Google amounts and timestamps come from Orders `total` and
  `orderHistory.processedEvent.eventTime`. Neither subscription `startTime`
  nor `recurringPrice` is a reliable renewal charge/date.
- Totals are original customer charges, before refunds, store commissions
  and tax deductions, grouped by currency. They are not net revenue or bank
  proceeds. Refund-related order states are visible separately.
- Missing amounts/dates remain unknown and are excluded from charged totals.
  History is limited to order IDs and Apple history preserved in our database;
  it is not a complete reconciliation of App Store / Play financial reports.
- Month boundaries use Europe/Warsaw, converted to UTC before comparison.
  Current subscription counters are independent of the payment period filter.
- Device platforms and activity come from push-token registrations. They are
  not a list of hardware models or a reliable last-login timestamp.

## Verification

`php artisan test --filter=BillingDashboardTest`

Coverage includes deduplication, sandbox, refunds, unknown amounts, stale
active statuses, Google auto-renewal cancellation, grace, timezone boundaries,
currency separation, lookup failures, filtering, pagination and authorization.
