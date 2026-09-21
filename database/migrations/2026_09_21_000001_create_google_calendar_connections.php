<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('google_calendar_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('status')->default('disconnected')->index();
            $table->string('google_subject')->nullable();
            $table->text('email')->nullable();
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->string('calendar_id')->nullable();
            $table->string('state_hash', 64)->nullable()->unique();
            $table->timestamp('state_expires_at')->nullable();
            $table->uuid('confirmation_id')->nullable();
            $table->string('locale', 5)->default('en');
            $table->unsignedBigInteger('sync_cursor')->default(0);
            $table->timestamp('last_synced_at')->nullable();
            $table->string('last_error')->nullable();
            $table->timestamps();
        });
        Schema::create('google_calendar_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('connection_id')->constrained('google_calendar_connections')->cascadeOnDelete();
            // Keep the mapping after a visit is deleted, until Google confirms deletion.
            $table->unsignedBigInteger('visit_id');
            $table->string('event_id', 64);
            $table->string('payload_hash', 64)->nullable();
            $table->timestamps();
            $table->unique(['connection_id', 'visit_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('google_calendar_events');
        Schema::dropIfExists('google_calendar_connections');
    }
};
