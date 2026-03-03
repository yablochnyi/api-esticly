<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visits', function (Blueprint $table) {
            $table->unsignedBigInteger('promo_code_id')->nullable()->after('service_id');
            $table->string('promo_code', 40)->nullable()->after('promo_code_id');
            $table->decimal('promo_discount', 10, 2)->default(0)->after('price'); // absolute discount

            $table->index(['promo_code_id']);
            $table->index(['promo_code']);
        });
    }

    public function down(): void
    {
        Schema::table('visits', function (Blueprint $table) {
            $table->dropIndex(['promo_code_id']);
            $table->dropIndex(['promo_code']);
            $table->dropColumn(['promo_code_id', 'promo_code', 'promo_discount']);
        });
    }
};

