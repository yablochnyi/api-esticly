<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promo_codes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id'); // organization id
            $table->string('code', 40);
            $table->string('type', 16); // percent|fixed
            $table->decimal('amount', 10, 2);
            $table->unsignedBigInteger('service_id')->nullable();
            $table->date('valid_until')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['user_id', 'code']);
            $table->index(['user_id']);
            $table->index(['service_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promo_codes');
    }
};

