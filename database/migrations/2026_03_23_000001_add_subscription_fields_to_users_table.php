<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'registered_at')) {
                $table->timestamp('registered_at')->nullable()->after('phone_verified_at');
            }
            if (!Schema::hasColumn('users', 'subscription_plan')) {
                $table->string('subscription_plan', 16)->nullable()->after('registered_at');
            }
            if (!Schema::hasColumn('users', 'subscription_ends_at')) {
                $table->timestamp('subscription_ends_at')->nullable()->after('subscription_plan');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'subscription_ends_at')) {
                $table->dropColumn('subscription_ends_at');
            }
            if (Schema::hasColumn('users', 'subscription_plan')) {
                $table->dropColumn('subscription_plan');
            }
            if (Schema::hasColumn('users', 'registered_at')) {
                $table->dropColumn('registered_at');
            }
        });
    }
};
