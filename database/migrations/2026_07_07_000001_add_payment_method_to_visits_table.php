<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visits', function (Blueprint $table) {
            $table->string('payment_method', 16)->default('cash')->after('price');
            $table->index(['user_id', 'payment_method', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::table('visits', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'payment_method', 'starts_at']);
            $table->dropColumn('payment_method');
        });
    }
};
