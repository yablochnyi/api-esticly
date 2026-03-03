<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visit_reminder_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visit_id')->constrained('visits')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete(); // recipient (owner or staff-user)
            $table->unsignedInteger('offset_min');
            $table->timestamp('due_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->string('status', 32)->default('sent'); // sent|failed
            $table->string('error', 255)->nullable();
            $table->timestamps();

            $table->unique(['visit_id', 'user_id', 'offset_min']);
            $table->index(['user_id', 'sent_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visit_reminder_deliveries');
    }
};

