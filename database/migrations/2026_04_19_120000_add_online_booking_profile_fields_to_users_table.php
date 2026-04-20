<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('booking_phone', 50)->nullable()->after('booking_slug');
            $table->string('booking_instagram')->nullable()->after('booking_phone');
            $table->string('booking_tiktok')->nullable()->after('booking_instagram');
            $table->string('booking_telegram')->nullable()->after('booking_tiktok');
            $table->string('booking_whatsapp')->nullable()->after('booking_telegram');
            $table->string('booking_viber')->nullable()->after('booking_whatsapp');
            $table->text('booking_bio')->nullable()->after('booking_viber');
            $table->json('booking_specialties')->nullable()->after('booking_bio');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'booking_phone',
                'booking_instagram',
                'booking_tiktok',
                'booking_telegram',
                'booking_whatsapp',
                'booking_viber',
                'booking_bio',
                'booking_specialties',
            ]);
        });
    }
};
