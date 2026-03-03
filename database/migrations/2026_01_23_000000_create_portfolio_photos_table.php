<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portfolio_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete(); // organization owner
            $table->foreignId('staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->string('path');
            $table->string('caption', 255)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'id']);
            $table->index(['user_id', 'staff_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portfolio_photos');
    }
};

