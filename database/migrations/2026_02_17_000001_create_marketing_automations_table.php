<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketing_automations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id'); // organization id
            $table->string('key', 80);
            $table->boolean('enabled')->default(false);
            $table->unsignedInteger('delay_min')->default(0);
            $table->text('template')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'key']);
            $table->index(['user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_automations');
    }
};

