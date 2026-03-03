<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('booking_slug', 32)->nullable()->unique();
            $table->boolean('online_booking_enabled')->default(false);
            $table->boolean('online_booking_whitelist_only')->default(false);
            $table->unsignedSmallInteger('online_booking_period_days')->default(90);
            $table->boolean('online_booking_auto_confirm')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['booking_slug']);
            $table->dropColumn([
                'booking_slug',
                'online_booking_enabled',
                'online_booking_whitelist_only',
                'online_booking_period_days',
                'online_booking_auto_confirm',
            ]);
        });
    }
};


