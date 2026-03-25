<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained('subscriptions')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 32);
            $table->string('event_type', 32);
            $table->string('product_id', 128)->nullable();
            $table->string('provider_transaction_id', 255)->nullable();
            $table->string('provider_order_id', 255)->nullable();
            $table->string('purchase_token', 255)->nullable();
            $table->bigInteger('amount_micros')->nullable();
            $table->string('currency_code', 16)->nullable();
            $table->timestamp('period_start_at')->nullable();
            $table->timestamp('period_end_at')->nullable();
            $table->timestamp('occurred_at')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'provider']);
            $table->index(['subscription_id', 'event_type']);
            $table->index(['provider', 'provider_order_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_transactions');
    }
};
