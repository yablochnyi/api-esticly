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
        Schema::create('visits', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete(); // organization
            $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();
            $table->foreignId('staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->foreignId('client_id')->nullable()->constrained('clients')->nullOnDelete();

            // для "разового клиента" (может быть null/null)
            $table->string('client_name')->nullable();
            $table->string('client_phone')->nullable();

            $table->dateTime('starts_at');
            $table->dateTime('ends_at');

            $table->unsignedInteger('duration_min')->default(0);
            $table->decimal('price', 10, 2)->nullable();

            $table->string('status')->default('pending');

            $table->timestamps();

            $table->index(['user_id', 'starts_at']);
            $table->index(['staff_id', 'starts_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('visits');
    }
};
