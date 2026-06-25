<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('product_onboarding_completed_at')->nullable()->after('registered_at');
        });

        // Product onboarding is only for registrations completed after this rollout.
        DB::table('users')
            ->whereNotNull('registered_at')
            ->update(['product_onboarding_completed_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('product_onboarding_completed_at');
        });
    }
};
