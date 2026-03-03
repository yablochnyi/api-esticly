<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_time_offs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->date('date'); // org-local date, stored as one row per day
            $table->string('type', 20); // day_off | vacation
            $table->string('note', 255)->nullable();
            $table->timestamps();

            $table->unique(['staff_id', 'date']);
            $table->index(['staff_id', 'type', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_time_offs');
    }
};

