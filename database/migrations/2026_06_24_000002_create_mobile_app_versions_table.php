<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mobile_app_versions', function (Blueprint $table) {
            $table->id();
            $table->string('platform', 16)->unique();
            $table->string('latest_version', 32);
            $table->string('minimum_version', 32);
            $table->string('store_url', 500);
            $table->boolean('enabled')->default(true);
            $table->timestamps();
        });

        DB::table('mobile_app_versions')->insert([
            [
                'platform' => 'ios',
                'latest_version' => '1.0.3',
                'minimum_version' => '1.0.0',
                'store_url' => 'https://apps.apple.com/app/esticly-salon-crm/id6761251722',
                'enabled' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'platform' => 'android',
                'latest_version' => '1.0.0',
                'minimum_version' => '1.0.0',
                'store_url' => 'https://play.google.com/store/apps/details?id=com.esticly.app',
                'enabled' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('mobile_app_versions');
    }
};
