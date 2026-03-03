<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();

            // Tenant / organization (users.id)
            $table->unsignedBigInteger('org_id')->nullable();

            // Actor (who made the change)
            $table->unsignedBigInteger('actor_user_id')->nullable();
            $table->unsignedBigInteger('actor_staff_id')->nullable();
            $table->string('actor_type', 16)->nullable(); // owner|staff|system
            $table->string('actor_email', 191)->nullable();
            $table->string('actor_name', 191)->nullable();

            // What changed
            $table->string('action', 16); // created|updated|deleted|restored|custom
            $table->string('auditable_type', 191)->nullable(); // FQCN
            $table->unsignedBigInteger('auditable_id')->nullable();
            $table->string('event', 64)->nullable(); // optional custom event name

            // Encrypted JSON payloads (casts encrypt/decrypt automatically)
            $table->longText('old_values')->nullable();
            $table->longText('new_values')->nullable();
            $table->json('changed_keys')->nullable(); // non-sensitive list for quick filtering

            // Request context (optional)
            $table->string('source', 32)->nullable(); // api_mobile|filament|job|console|system
            $table->string('ip', 64)->nullable();
            $table->string('method', 12)->nullable();
            $table->string('url', 2048)->nullable();
            $table->string('user_agent', 1024)->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->index(['org_id', 'created_at']);
            $table->index(['actor_user_id', 'created_at']);
            $table->index(['auditable_type', 'auditable_id']);
            $table->index(['action', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};

