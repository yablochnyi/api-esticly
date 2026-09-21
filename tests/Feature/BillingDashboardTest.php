<?php

namespace Tests\Feature;

use App\Filament\Pages\Dashboard;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Models\User;
use App\Services\BillingDashboardReport;
use App\Services\BillingPaymentImporter;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BillingDashboardTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('k', 32))]);
        $this->travelTo(CarbonImmutable::parse('2026-09-21T12:00:00Z'));
        Http::preventStrayRequests();
        Schema::create('users', function (Blueprint $t) {
            $t->id();
            $t->string('name')->nullable();
            $t->string('company_name')->nullable();
            $t->string('email')->nullable();
            $t->string('password')->nullable();
            $t->unsignedBigInteger('organization_id')->nullable();
            $t->unsignedBigInteger('staff_id')->nullable();
            $t->timestamps();
        });
        foreach ([
            '2025_12_24_130308_create_permission_tables.php',
            '2026_02_16_000003_create_device_tokens_table.php',
            '2026_03_25_000002_create_subscriptions_table.php',
            '2026_03_25_000003_create_subscription_transactions_table.php',
            '2026_09_21_120000_create_subscription_payments_table.php',
        ] as $migration) {
            (require database_path('migrations/'.$migration))->up();
        }
        DB::table('users')->insert([
            ['id' => 1, 'name' => 'Owner', 'company_name' => 'Example salon', 'email' => 'owner@example.test'],
            ['id' => 2, 'name' => 'Admin', 'company_name' => 'Admin', 'email' => 'admin@example.test'],
        ]);
        Role::create(['name' => 'superadmin', 'guard_name' => 'web']);
        User::find(2)->assignRole('superadmin');
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_apple_history_is_deduplicated_and_refunds_are_not_erased(): void
    {
        $subscription = $this->subscription();
        $transaction = $this->appleTransaction();
        $snapshot = ['environment' => 'Production', 'history' => ['signedTransactions' => [$this->jws($transaction)]], 'latest_transaction' => $transaction];
        $importer = new BillingPaymentImporter;
        $importer->importAppleSnapshot($subscription, $snapshot);
        $importer->importAppleSnapshot($subscription, $snapshot);
        $this->assertSame(1, SubscriptionPayment::count());
        $this->assertSame(39990000, SubscriptionPayment::first()->amount_micros);
        $transaction['revocationDate'] = now()->getTimestampMs();
        $importer->importAppleSnapshot($subscription, ['latest_transaction' => $transaction]);
        $importer->importAppleSnapshot($subscription, $snapshot);
        $this->assertSame('refunded', SubscriptionPayment::first()->state);
        $transaction['transactionId'] = 'other';
        $transaction['originalTransactionId'] = 'different-owner';
        $importer->importAppleSnapshot($subscription, ['latest_transaction' => $transaction]);
        $this->assertSame(1, SubscriptionPayment::count());
    }

    public function test_google_uses_order_money_and_processing_date_not_recurring_price_or_original_start(): void
    {
        $subscription = $this->subscription(['provider' => 'google_play', 'meta' => []]);
        $importer = new BillingPaymentImporter;
        $snapshot = ['startTime' => '2026-01-01T00:00:00Z', 'latestOrderId' => 'GPA.example..1',
            'lineItems' => [['expiryTime' => '2026-10-01T00:00:00Z', 'autoRenewingPlan' => ['recurringPrice' => ['units' => '999']]]]];
        $importer->discoverGoogleOrder($subscription, $snapshot);
        $importer->discoverGoogleOrder($subscription, $snapshot);
        $payment = SubscriptionPayment::first();
        $this->assertNull($payment->amount_micros);
        $this->assertNull($payment->paid_at);
        $importer->importGoogleOrder($payment, [
            'orderId' => 'GPA.example..1', 'state' => 'PROCESSED',
            'createTime' => '2026-08-01T00:00:00Z',
            'orderHistory' => ['processedEvent' => ['eventTime' => '2026-09-01T00:00:00Z']],
            'total' => ['currencyCode' => 'UAH', 'units' => '474', 'nanos' => 990000000],
        ]);
        $this->assertSame(1, SubscriptionPayment::count());
        $this->assertSame(474990000, $payment->fresh()->amount_micros);
        $this->assertSame('2026-09-01', $payment->fresh()->paid_at->toDateString());
        $this->expectException(\RuntimeException::class);
        $importer->importGoogleOrder($payment, ['orderId' => 'wrong']);
    }

    public function test_current_payers_require_valid_dates_production_and_positive_confirmed_payment(): void
    {
        $active = $this->subscription();
        $this->payment($active);
        $this->payment($active, ['external_id' => 'renewal']);
        $expired = $this->subscription(['purchase_token' => 'expired', 'ends_at' => now()->subDay()]);
        $this->payment($expired, ['external_id' => 'expired-payment', 'period_ends_at' => now()->subDay()]);
        $test = $this->subscription(['user_id' => 2, 'purchase_token' => 'sandbox', 'meta' => ['environment' => 'Sandbox']]);
        $this->payment($test, ['external_id' => 'test-payment', 'environment' => 'test']);
        $report = app(BillingDashboardReport::class)->build(['period' => 'all']);
        $this->assertSame(1, $report['paying']);
        $this->assertSame(1, $report['ios']);
        $this->assertSame(1, $report['ever']);
        $this->assertSame(3, $report['total_payments']);
        $this->assertSame(1, $report['test_payments']);
        $this->assertSame(119970000, $report['all_totals']['PLN']);
        $active->update(['status' => 'cancelled']);
        $this->assertSame(0, app(BillingDashboardReport::class)->build()['paying']);
    }

    public function test_month_boundaries_use_warsaw_and_currencies_are_separate(): void
    {
        $s = $this->subscription();
        $this->payment($s, ['paid_at' => '2026-08-31T22:30:00Z']);
        $this->payment($s, ['external_id' => 'older', 'paid_at' => '2026-08-31T21:59:59Z', 'currency' => 'EUR', 'amount_micros' => 9990000]);
        $report = app(BillingDashboardReport::class)->build(['period' => '2026-09']);
        $this->assertSame(1, $report['period_payments']);
        $this->assertSame(['PLN' => 39990000], $report['totals']);
        $this->assertSame(['EUR' => 9990000, 'PLN' => 39990000], $report['all_totals']);
        $this->assertSame(1, $report['months']->first()['count']);
        $this->assertSame(0, $report['months']->first()['new']);
    }

    public function test_free_unknown_refunded_and_grace_access_are_not_current_payers(): void
    {
        $s = $this->subscription();
        $this->payment($s, ['amount_micros' => 0]);
        $this->assertSame(0, app(BillingDashboardReport::class)->build()['paying']);
        SubscriptionPayment::query()->update(['amount_micros' => null]);
        $this->assertSame(1, app(BillingDashboardReport::class)->build()['unknown']);
        SubscriptionPayment::query()->update(['amount_micros' => 39990000, 'state' => 'refunded']);
        $report = app(BillingDashboardReport::class)->build();
        $this->assertSame(0, $report['paying']);
        $this->assertSame(1, $report['refunds']);
        $this->assertSame(1, $report['ever']);
        $s->update(['status' => 'grace_period']);
        SubscriptionPayment::query()->update(['state' => 'paid']);
        $this->assertSame(0, app(BillingDashboardReport::class)->build()['paying']);
        $this->assertSame(1, app(BillingDashboardReport::class)->build()['grace']);
    }

    public function test_platform_plan_and_customer_search_filters(): void
    {
        $s = $this->subscription(['provider' => 'google_play', 'meta' => [], 'plan_code' => 'pro', 'status' => 'cancelled']);
        $this->payment($s, ['plan_code' => 'pro']);
        $service = app(BillingDashboardReport::class);
        $this->assertSame(1, $service->build(['provider' => 'google_play', 'plan' => 'pro'])['paying']);
        $this->assertSame(0, $service->build(['provider' => 'app_store'])['paying']);
        $this->assertSame(0, $service->build(['plan' => 'basic'])['paying']);
        $this->assertCount(1, $service->build(['search' => 'example salon'])['customers']);
        $this->assertCount(0, $service->build(['customers' => 'inactive'])['customers']);
        $this->assertCount(0, $service->build(['search' => 'not found'])['customers']);
        $this->assertSame(1, $service->build(['period' => ['unexpected'], 'provider' => ['bad'], 'search' => []])['paying']);
    }

    public function test_failed_google_lookup_is_visible_and_does_not_change_entitlements(): void
    {
        $s = $this->subscription(['provider' => 'google_play', 'meta' => ['latestOrderId' => 'GPA.unavailable', 'lineItems' => [['expiryTime' => '2026-10-01T00:00:00Z']]]]);
        $before = $s->fresh()->getAttributes();
        $importer = new class extends BillingPaymentImporter
        {
            public function fetchGoogleOrder(string $orderId): array
            {
                throw new \RuntimeException('sensitive response must not be stored');
            }
        };
        $result = $importer->refresh();
        $this->assertSame(1, $result['lookup_errors']);
        $this->assertSame('google_order_lookup_failed', SubscriptionPayment::first()->lookup_error);
        $this->assertSame($before, $s->fresh()->getAttributes());
        $this->assertSame(0, $importer->refresh()['lookup_errors']);
        $this->assertSame(1, $importer->refresh(true)['lookup_errors']);
    }

    public function test_dashboard_and_livewire_are_superadmin_only(): void
    {
        $this->get('/admin')->assertRedirect();
        $this->actingAs(User::find(1))->get('/admin')->assertForbidden();
        Livewire::actingAs(User::find(1))->test(Dashboard::class)->assertForbidden();
        $this->actingAs(User::find(2))->get('/admin')->assertOk();
        $page = Livewire::actingAs(User::find(2))->test(Dashboard::class)->assertSuccessful();
        User::find(2)->removeRole('superadmin');
        $this->actingAs(User::find(2)->fresh());
        $page->call('$refresh')->assertForbidden();
    }

    public function test_dashboard_renders_data_empty_states_filters_and_pagination(): void
    {
        $s = $this->subscription();
        for ($i = 0; $i < 17; $i++) {
            $this->payment($s, ['external_id' => 'payment-'.$i]);
        }
        Livewire::actingAs(User::find(2))->test(Dashboard::class)
            ->assertSee('Example salon')->assertSee('39,99 PLN')
            ->set('filters.provider', 'google_play')->assertSee('Заказы не найдены')
            ->set('filters.provider', null)->call('setPage', 2, 'paymentsPage')->assertSee('39,99 PLN')
            ->set('filters.search', 'no-match')->assertSee('Подписчики не найдены');
    }

    private function subscription(array $attributes = []): Subscription
    {
        return Subscription::create(array_merge([
            'user_id' => 1, 'provider' => 'app_store', 'purchase_token' => 'original',
            'product_id' => 'esticly_basic_monthly', 'plan_code' => 'basic', 'status' => 'active',
            'ends_at' => '2026-10-01', 'meta' => ['environment' => 'Production'],
        ], $attributes));
    }

    private function payment(Subscription $s, array $attributes = []): SubscriptionPayment
    {
        return SubscriptionPayment::create(array_merge([
            'subscription_id' => $s->id, 'user_id' => $s->user_id, 'provider' => $s->provider,
            'external_id' => 'payment', 'plan_code' => 'basic', 'environment' => 'production',
            'state' => 'paid', 'amount_micros' => 39990000, 'currency' => 'PLN',
            'paid_at' => '2026-09-01', 'period_ends_at' => '2026-10-01',
        ], $attributes));
    }

    private function appleTransaction(): array
    {
        return ['transactionId' => 'transaction', 'originalTransactionId' => 'original',
            'productId' => 'esticly_basic_monthly', 'environment' => 'Production',
            'price' => 39990, 'currency' => 'PLN',
            'purchaseDate' => CarbonImmutable::parse('2026-09-01')->getTimestampMs(),
            'expiresDate' => CarbonImmutable::parse('2026-10-01')->getTimestampMs()];
    }

    private function jws(array $payload): string
    {
        return 'header.'.rtrim(strtr(base64_encode(json_encode($payload)), '+/', '-_'), '=').'.signature';
    }
}
