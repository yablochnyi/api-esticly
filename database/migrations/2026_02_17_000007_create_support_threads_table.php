<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_threads', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('org_id'); // organization id (users.id)
            $table->timestamp('last_message_at')->nullable();
            $table->string('last_message_preview', 255)->nullable();
            $table->unsignedInteger('unread_for_support')->default(0);
            $table->unsignedInteger('unread_for_user')->default(0);
            $table->timestamps();

            $table->unique(['org_id']);
            $table->index(['org_id']);
            $table->index(['last_message_at']);
            $table->index(['unread_for_support']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_threads');
    }
};

