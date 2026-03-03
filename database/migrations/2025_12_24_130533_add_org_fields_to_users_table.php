<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone')->unique()->after('id');

            $table->string('company_name')->nullable();
            $table->string('address')->nullable();
            $table->text('description')->nullable();

            $table->string('currency_code', 3)->nullable();
            $table->json('schedule')->nullable();

            $table->string('logo_path')->nullable();

            $table->timestamp('phone_verified_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('phone');
            $table->dropColumn('company_name');
            $table->dropColumn('address');
            $table->dropColumn('description');
            $table->dropColumn('currency_code');
            $table->dropColumn('schedule');
            $table->dropColumn('logo_path');
            $table->dropColumn('phone_verified_at');
        });
    }
};
