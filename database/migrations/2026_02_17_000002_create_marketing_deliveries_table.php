<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketing_deliveries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id'); // organization id
            $table->unsignedBigInteger('visit_id');
            $table->string('automation_key', 80);

            $table->string('to_phone', 64)->nullable();
            $table->string('status', 20)->default('pending'); // pending|sent|failed|skipped
            $table->text('error')->nullable();
            $table->timestamp('sent_at')->nullable();

            $table->timestamps();

            $table->unique(['user_id', 'visit_id', 'automation_key']);
            $table->index(['user_id', 'automation_key']);
            $table->index(['visit_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_deliveries');
    }
};

