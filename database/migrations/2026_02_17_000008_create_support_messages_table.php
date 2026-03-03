<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('thread_id');
            $table->unsignedBigInteger('org_id'); // organization id (users.id)
            $table->string('sender_type', 16); // user|support
            $table->unsignedBigInteger('sender_user_id')->nullable(); // for user sender (owner/staff)
            $table->text('body');
            $table->timestamp('read_at_support')->nullable();
            $table->timestamp('read_at_user')->nullable();
            $table->timestamps();

            $table->index(['thread_id', 'id']);
            $table->index(['org_id', 'id']);
            $table->index(['sender_type']);
            $table->index(['read_at_support']);
            $table->index(['read_at_user']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_messages');
    }
};

