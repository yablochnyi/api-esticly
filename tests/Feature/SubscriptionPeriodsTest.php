<?php

namespace Tests\Feature;

use App\Models\Subscription;
use App\Models\User;
use App\Support\AppStoreSubscriptions as Apple;
use App\Support\GooglePlaySubscriptions as Play;
use App\Support\OrgSubscription;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SubscriptionPeriodsTest extends TestCase
{
    public function test_both_stores_map_all_periods_to_the_same_access_tiers(): void
    {
        foreach (['basic', 'pro'] as $plan) {
            foreach (['monthly', '6months', 'yearly'] as $period) {
                $id = "esticly_{$plan}_{$period}";
                $this->assertSame($plan, Apple::planCodeForProductId($id));
                $this->assertSame($plan, Play::planCodeForProductId($id));
            }
        }
        $this->assertNull(Apple::planCodeForProductId('esticly_basic_weekly'));
        $this->assertNull(Play::planCodeForProductId('esticly_pro_fake'));
    }

    public function test_apple_history_selects_new_period_instead_of_restored_old_monthly_product(): void
    {
        $monthly = ['productId' => 'esticly_basic_monthly', 'purchaseDate' => 1000, 'originalTransactionId' => 'root', 'transactionId' => 'old'];
        $annual = ['productId' => 'esticly_basic_yearly', 'purchaseDate' => 2000, 'originalTransactionId' => 'root', 'transactionId' => 'new'];
        $this->assertSame($annual, Apple::currentTransactionFromHistory([$monthly, $annual], 'old'));
        $this->assertSame($annual, Apple::currentTransactionFromHistory([$annual, $monthly], 'root'));
        $this->assertSame($monthly, Apple::currentTransactionFromHistory([$annual + ['isUpgraded' => true], $monthly], 'root'));
        $refunded = $annual + ['revocationDate' => 3000];
        $this->assertSame($refunded, Apple::currentTransactionFromHistory([$monthly, $refunded], 'root'));
        $other = array_replace($annual, ['originalTransactionId' => 'other', 'purchaseDate' => 4000]);
        $this->assertSame($annual, Apple::currentTransactionFromHistory([$other, $annual, $monthly], 'old'));
    }

    public function test_status_returns_the_actual_product_not_an_inferred_monthly_plan(): void
    {
        Schema::create('users', function (Blueprint $t) {
            $t->id(); $t->string('subscription_plan')->nullable();
            $t->string('subscription_provider')->nullable(); $t->timestamp('subscription_ends_at')->nullable();
            $t->timestamp('registered_at')->nullable(); $t->timestamps();
        });
        (require database_path('migrations/2026_03_25_000002_create_subscriptions_table.php'))->up();
        foreach (['app_store', 'google_play'] as $provider) {
            $u = User::forceCreate(['registered_at' => now()->subYear(), 'subscription_plan' => 'basic',
                'subscription_provider' => $provider, 'subscription_ends_at' => now()->addYear()]);
            Subscription::forceCreate(['user_id' => $u->id, 'provider' => $provider,
                'product_id' => 'esticly_basic_yearly', 'plan_code' => 'basic', 'status' => 'active',
                'purchase_token' => $provider.'-test', 'ends_at' => now()->addYear()]);
            $state = OrgSubscription::status($u);
            $this->assertSame('esticly_basic_yearly', $state['subscription_product_id']);
            $this->assertTrue($state['can_create_visits']);
            $this->assertFalse($state['can_manage_staff']);
        }

        $granted = User::forceCreate(['registered_at' => now()->subYear(),
            'subscription_plan' => 'basic', 'subscription_provider' => 'app_store',
            'subscription_ends_at' => now()->addMonths(2)]);
        Subscription::forceCreate(['user_id' => $granted->id, 'provider' => 'app_store',
            'product_id' => 'esticly_basic_monthly', 'plan_code' => 'basic', 'status' => 'expired',
            'purchase_token' => 'expired-before-grant', 'ends_at' => now()->subMonth()]);
        $grantedState = OrgSubscription::status($granted);
        $this->assertTrue($grantedState['subscription_active']);
        $this->assertTrue($grantedState['can_create_visits']);
        $this->assertNull($grantedState['subscription_product_id']);
        $this->assertFalse(Play::requiresExistingPurchase($granted));
        $this->assertTrue($granted->fresh()->subscription_ends_at->isFuture());
    }
}
