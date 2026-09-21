<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 32);
            $table->string('external_id');
            $table->string('plan_code', 16)->nullable();
            $table->string('environment', 16)->default('unknown');
            $table->string('state', 32)->default('unknown');
            $table->bigInteger('amount_micros')->nullable();
            $table->string('currency', 8)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('period_ends_at')->nullable();
            $table->timestamp('checked_at')->nullable();
            $table->string('lookup_error', 64)->nullable();
            $table->timestamps();
            $table->unique(['provider', 'external_id']);
            $table->index(['environment', 'paid_at']);
            $table->index(['user_id', 'period_ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_payments');
    }
};
