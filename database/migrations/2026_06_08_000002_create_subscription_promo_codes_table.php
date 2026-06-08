<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_promo_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->unsignedSmallInteger('duration_months');
            $table->unsignedInteger('max_uses')->nullable();
            $table->unsignedInteger('used_count')->default(0);
            $table->boolean('active')->default(true);
            $table->string('blogger_name', 120)->nullable();
            $table->text('note')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['active', 'expires_at']);
        });

        Schema::create('subscription_promo_code_redemptions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('subscription_promo_code_id');
            $table->foreignId('org_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('access_until');
            $table->timestamps();

            $table
                ->foreign('subscription_promo_code_id', 'sub_promo_redemptions_code_fk')
                ->references('id')
                ->on('subscription_promo_codes')
                ->cascadeOnDelete();
            $table->unique(['subscription_promo_code_id', 'org_id'], 'subscription_promo_redemptions_code_org_unique');
            $table->index(['org_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_promo_code_redemptions');
        Schema::dropIfExists('subscription_promo_codes');
    }
};
