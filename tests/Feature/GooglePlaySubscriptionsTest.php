<?php

namespace Tests\Feature;

use App\Models\Subscription;
use App\Models\SubscriptionTransaction;
use App\Models\User;
use App\Services\BillingPaymentImporter;
use App\Support\GooglePlaySubscriptions as Play;
use App\Support\GoogleServiceAccount;
use App\Support\OrgSubscription;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class GooglePlaySubscriptionsTest extends TestCase
{
    private array $snapshots = [];

    private array $orders = [];

    private array $savedEnv = [];

    private array $savedStatic = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 9, 28)->setTime(18, 0));
        foreach (['GOOGLE_PLAY_PACKAGE_NAME' => 'com.example.test', 'GOOGLE_PLAY_RTDN_TOKEN' => 'test-rtdn'] as $key => $value) {
            $this->savedEnv[$key] = Env::getRepository()->get($key);
            Env::getRepository()->set($key, $value);
        }
        foreach (['cachedCreds' => ['client_email' => 'test@example.test', 'private_key' => 'unused', 'project_id' => 'test'],
            'cachedTokens' => [sha1(Play::SCOPE) => ['token' => 'fake', 'expires_at' => time() + 3600]]] as $name => $value) {
            $property = new \ReflectionProperty(GoogleServiceAccount::class, $name);
            $this->savedStatic[$name] = $property->getValue();
            $property->setValue(null, $value);
        }
        Http::preventStrayRequests();
        Http::fake(['https://androidpublisher.googleapis.com/*' => function ($request) {
            if (str_contains($request->url(), '/subscriptionsv2/tokens/')) {
                $token = rawurldecode(basename($request->url()));

                return isset($this->snapshots[$token]) ? Http::response($this->snapshots[$token]) : Http::response([], 503);
            }
            if (str_contains($request->url(), '/orders/')) {
                $id = rawurldecode(basename($request->url()));

                return isset($this->orders[$id]) ? Http::response($this->orders[$id]) : Http::response([], 503);
            }

            return Http::response([], 503);
        }]);
        $this->mock(BillingPaymentImporter::class)->shouldReceive('refreshGoogleSubscription')->andReturnNull();
        Schema::create('users', function (Blueprint $t) {
            $t->id();
            $t->string('name')->nullable();
            $t->string('subscription_plan')->nullable();
            $t->string('subscription_provider')->nullable();
            $t->timestamp('subscription_ends_at')->nullable();
            $t->timestamp('registered_at')->nullable();
            $t->timestamps();
        });
        foreach (['2026_03_25_000002_create_subscriptions_table.php', '2026_03_25_000003_create_subscription_transactions_table.php'] as $migration) {
            (require database_path('migrations/'.$migration))->up();
        }
        User::forceCreate(['id' => 1, 'registered_at' => now()->subMonths(2)]);
        User::forceCreate(['id' => 2, 'registered_at' => now()->subMonths(2)]);
    }

    protected function tearDown(): void
    {
        foreach ($this->savedEnv as $key => $value) {
            if ($value === null) {
                Env::getRepository()->clear($key);
            } else {
                Env::getRepository()->set($key, $value);
            }
        }
        foreach ($this->savedStatic as $name => $value) {
            (new \ReflectionProperty(GoogleServiceAccount::class, $name))->setValue(null, $value);
        }
        parent::tearDown();
    }

    private function snapshot(string $plan = 'basic', string $state = 'ACTIVE', string $end = '2026-10-27T17:05:16Z'): array
    {
        return ['subscriptionState' => 'SUBSCRIPTION_STATE_'.$state, 'startTime' => '2026-08-27T17:05:16Z',
            'acknowledgementState' => 'ACKNOWLEDGEMENT_STATE_ACKNOWLEDGED',
            'lineItems' => [['productId' => 'esticly_'.$plan.'_monthly', 'expiryTime' => $end, 'latestSuccessfulOrderId' => 'GPA.test.'.$plan]]];
    }

    private function sync(string $token, string $plan = 'basic'): Subscription
    {
        return Play::syncPurchase(User::findOrFail(1), 'esticly_'.$plan.'_monthly', $token);
    }

    private function accessStatus(): array
    {
        return OrgSubscription::status(User::findOrFail(1));
    }

    public function test_expired_pro_cannot_overwrite_paid_basic_in_either_order(): void
    {
        $this->snapshots['basic'] = $this->snapshot();
        $this->snapshots['pro'] = $this->snapshot('pro', 'EXPIRED', '2026-09-28T13:11:02Z');
        $this->sync('basic');
        $this->sync('pro', 'pro');
        $this->assertSame('basic', User::find(1)->subscription_plan);
        $this->assertTrue($this->accessStatus()['can_create_visits']);
        $this->sync('pro', 'pro');
        $this->sync('basic');
        $this->assertSame('basic', $this->accessStatus()['subscription_plan']);
        $this->assertSame(2, SubscriptionTransaction::count());
    }

    public function test_pro_expiry_falls_back_to_still_paid_basic_without_waiting_for_a_job(): void
    {
        $this->snapshots['basic'] = $this->snapshot();
        $this->snapshots['pro'] = $this->snapshot('pro', 'ACTIVE', '2026-09-29T18:00:00Z');
        $this->sync('pro', 'pro');
        $this->sync('basic');
        $this->assertSame('pro', $this->accessStatus()['subscription_plan']);
        $this->travel(2)->days();
        $this->assertSame('basic', $this->accessStatus()['subscription_plan']);
        $this->assertTrue($this->accessStatus()['can_create_visits']);
        $this->assertFalse($this->accessStatus()['can_manage_staff']);
    }

    public function test_only_entitled_google_states_grant_access_even_with_a_future_expiry(): void
    {
        foreach (['ACTIVE' => true, 'IN_GRACE_PERIOD' => true, 'CANCELED' => true,
            'ON_HOLD' => false, 'PAUSED' => false, 'PENDING' => false, 'EXPIRED' => false] as $state => $active) {
            $this->snapshots['token'] = $this->snapshot('basic', $state);
            $this->sync('token');
            $this->assertSame($active, $this->accessStatus()['can_create_visits'], $state);
        }
    }

    public function test_reconciliation_checks_every_purchase_not_just_latest_stored_end(): void
    {
        $this->snapshots['basic'] = $this->snapshot('basic', 'EXPIRED', '2026-08-27T17:05:16Z');
        $this->snapshots['pro'] = $this->snapshot('pro', 'ON_HOLD', '2026-09-28T13:11:02Z');
        $this->sync('basic');
        $this->sync('pro', 'pro');
        $this->snapshots['basic'] = $this->snapshot();
        Play::syncLatestForOrg(User::find(1));
        $this->assertSame('basic', $this->accessStatus()['subscription_plan']);
        $this->assertTrue($this->accessStatus()['can_create_visits']);
    }

    public function test_one_failed_lookup_does_not_prevent_other_purchase_recovery(): void
    {
        $this->snapshots['pro'] = $this->snapshot('pro', 'EXPIRED', '2026-09-20T00:00:00Z');
        $this->snapshots['basic'] = $this->snapshot('basic', 'EXPIRED', '2026-09-20T00:00:00Z');
        $this->sync('pro', 'pro');
        $this->sync('basic');
        unset($this->snapshots['pro']);
        $this->snapshots['basic'] = $this->snapshot();
        try {
            Play::syncLatestForOrg(User::find(1));
            $this->fail('Incomplete reconciliation must be reported');
        } catch (\RuntimeException $e) {
            $this->assertSame('google_play_reconcile_incomplete', $e->getMessage());
        }
        $this->assertTrue($this->accessStatus()['can_create_visits']);
    }

    public function test_linked_replacement_does_not_resurrect_old_pro_on_late_callback(): void
    {
        $this->snapshots['old'] = $this->snapshot('pro');
        $this->sync('old', 'pro');
        $this->snapshots['new'] = $this->snapshot() + ['linkedPurchaseToken' => 'old'];
        $this->sync('new');
        $this->sync('old', 'pro');
        $this->assertSame('basic', $this->accessStatus()['subscription_plan']);
    }

    public function test_verified_product_not_requested_product_determines_deferred_entitlement(): void
    {
        $this->snapshots['token'] = $this->snapshot();
        $s = $this->sync('token', 'pro');
        $this->assertSame('basic', $s->plan_code);
        $this->assertSame('basic', $this->accessStatus()['subscription_plan']);
    }

    public function test_deferred_two_item_purchase_uses_current_entitlement_before_and_after_switch(): void
    {
        $old = $this->snapshot('pro')['lineItems'][0];
        $old['deferredItemReplacement'] = ['productId' => 'esticly_basic_6months'];
        $next = ['productId' => 'esticly_basic_6months'];
        foreach ([[$next, $old], [$old, $next]] as $items) {
            $this->snapshots['deferred'] = array_replace($this->snapshot(), ['lineItems' => $items]);
            foreach (['esticly_pro_monthly', 'esticly_basic_6months'] as $requested) {
                $s = Play::syncPurchase(User::findOrFail(1), $requested, 'deferred');
                $this->assertSame('esticly_pro_monthly', $s->product_id);
                $this->assertSame('pro', $this->accessStatus()['subscription_plan']);
                $this->assertSame('esticly_pro_monthly', Play::resolveProductId($this->snapshots['deferred']));
            }
        }

        unset($old['deferredItemReplacement']);
        $old['expiryTime'] = '2026-09-28T17:00:00Z';
        $next['expiryTime'] = '2027-03-28T17:00:00Z';
        foreach ([[$next, $old], [$old, $next]] as $items) {
            $this->snapshots['deferred']['lineItems'] = $items;
            foreach (['esticly_pro_monthly', 'esticly_basic_6months'] as $requested) {
                $s = Play::syncPurchase(User::findOrFail(1), $requested, 'deferred');
                $this->assertSame('esticly_basic_6months', $s->product_id);
                $this->assertSame('basic', $this->accessStatus()['subscription_plan']);
                $this->assertTrue($this->accessStatus()['can_create_visits']);
                $this->assertSame('esticly_basic_6months', Play::resolveProductId($this->snapshots['deferred']));
            }
        }
    }

    public function test_longer_period_keeps_verified_expiry_and_survives_a_late_monthly_callback(): void
    {
        foreach (['6months' => '2027-03-28T18:00:00Z', 'yearly' => '2027-09-28T18:00:00Z'] as $period => $end) {
            $old = 'monthly-'.$period;
            $new = 'long-'.$period;
            $this->snapshots[$old] = $this->snapshot();
            $this->sync($old);
            $snapshot = $this->snapshot('basic', 'ACTIVE', $end);
            $snapshot['lineItems'][0]['productId'] = 'esticly_basic_'.$period;
            $snapshot['linkedPurchaseToken'] = $old;
            $this->snapshots[$new] = $snapshot;
            $subscription = Play::syncPurchase(User::findOrFail(1), 'esticly_basic_'.$period, $new);
            $this->assertSame('esticly_basic_'.$period, $subscription->product_id);
            $this->assertSame(strtotime($end), $subscription->ends_at->timestamp);
            $this->sync($old);
            $this->assertSame('esticly_basic_'.$period, $this->accessStatus()['subscription_product_id']);
            $this->assertTrue($this->accessStatus()['can_create_visits']);
        }
    }

    public function test_purchase_cannot_be_reassigned_to_another_user(): void
    {
        $this->snapshots['token'] = $this->snapshot();
        $this->sync('token');
        try {
            Play::syncPurchase(User::find(2), 'esticly_basic_monthly', 'token');
            $this->fail('Purchase ownership must be preserved');
        } catch (\RuntimeException $e) {
            $this->assertSame('google_play_purchase_owned_by_another_account', $e->getMessage());
        }
        $this->assertSame(1, Subscription::first()->user_id);
    }

    public function test_google_expiration_preserves_active_apple_access(): void
    {
        User::find(1)->forceFill(['subscription_provider' => 'app_store', 'subscription_plan' => 'pro', 'subscription_ends_at' => now()->addMonth()])->save();
        $this->snapshots['token'] = $this->snapshot('basic', 'EXPIRED', '2026-09-20T00:00:00Z');
        $this->sync('token');
        $this->assertSame('app_store', User::find(1)->subscription_provider);
        $this->assertTrue($this->accessStatus()['can_manage_staff']);
    }

    public function test_periodic_command_recovers_a_missed_renewal_and_respects_freshness(): void
    {
        $this->snapshots['token'] = $this->snapshot('basic', 'EXPIRED', '2026-09-20T00:00:00Z');
        $this->sync('token');
        $this->assertFalse(Play::shouldSyncOrg(User::find(1)));
        $this->travel(16)->minutes();
        $this->assertTrue(Play::shouldSyncOrg(User::find(1)));
        $this->snapshots['token'] = $this->snapshot();
        $this->assertSame(0, Artisan::call('billing:sync-google'));
        $this->assertTrue($this->accessStatus()['can_create_visits']);
    }

    public function test_rtdn_lookup_failure_is_retryable_and_authentication_is_required(): void
    {
        $payload = ['message' => ['messageId' => 'test', 'data' => base64_encode(json_encode([
            'packageName' => 'com.example.test', 'subscriptionNotification' => ['purchaseToken' => 'missing', 'notificationType' => 2],
        ]))]];
        $this->postJson('/api/mobile/billing/google/rtdn', $payload)->assertForbidden();
        $this->postJson('/api/mobile/billing/google/rtdn?token=test-rtdn', $payload)->assertStatus(503);
    }

    public function test_rtdn_recovers_purchase_when_mobile_verification_never_reached_server(): void
    {
        $this->snapshots['new'] = $this->snapshot() + ['externalAccountIdentifiers' => [
            'obfuscatedExternalAccountId' => '00000000-6573-4000-8000-000000000001',
        ]];
        $payload = ['message' => ['messageId' => 'recovered', 'data' => base64_encode(json_encode([
            'packageName' => 'com.example.test', 'subscriptionNotification' => ['purchaseToken' => 'new', 'notificationType' => 4],
        ]))]];
        $this->postJson('/api/mobile/billing/google/rtdn?token=test-rtdn', $payload)->assertOk();
        $this->assertTrue($this->accessStatus()['can_create_visits']);
        $this->assertSame(1, Subscription::count());
        $this->postJson('/api/mobile/billing/google/rtdn?token=test-rtdn', $payload)->assertOk();
        $this->assertSame(1, SubscriptionTransaction::count());
    }

    public function test_new_purchase_account_binding_must_match_authenticated_user(): void
    {
        $this->snapshots['new'] = $this->snapshot() + ['externalAccountIdentifiers' => [
            'obfuscatedExternalAccountId' => '00000000-6573-4000-8000-000000000002',
        ]];
        $this->expectExceptionMessage('google_play_purchase_owned_by_another_account');
        $this->sync('new');
    }

    public function test_on_hold_requires_existing_purchase_even_when_paid_access_has_expired(): void
    {
        $this->snapshots['token'] = $this->snapshot('basic', 'ON_HOLD', '2026-09-27T00:00:00Z');
        $this->sync('token');
        $this->assertFalse($this->accessStatus()['can_create_visits']);
        $this->assertTrue(Play::requiresExistingPurchase(User::find(1)));
    }

    public function test_subscription_sync_imports_successful_payment_immediately(): void
    {
        (require database_path('migrations/2026_09_21_120000_create_subscription_payments_table.php'))->up();
        $this->app->instance(BillingPaymentImporter::class, new BillingPaymentImporter);
        $this->snapshots['token'] = $this->snapshot();
        $this->orders['GPA.test.basic'] = [
            'orderId' => 'GPA.test.basic', 'state' => 'PROCESSED',
            'total' => ['currencyCode' => 'UAH', 'units' => '474', 'nanos' => 990000000],
            'orderHistory' => ['processedEvent' => ['eventTime' => '2026-09-28T15:56:58Z']],
        ];
        $this->sync('token');
        $payment = \App\Models\SubscriptionPayment::firstOrFail();
        $this->assertSame('paid', $payment->state);
        $this->assertSame(474990000, $payment->amount_micros);
        $this->assertSame('2026-09-28', $payment->paid_at->toDateString());
    }
}
