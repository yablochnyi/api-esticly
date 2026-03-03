<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dsar_operations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('org_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('actor_staff_id')->nullable();
            $table->foreignId('client_id')->nullable()->constrained('clients')->nullOnDelete();

            // export_client | anonymize_client | export_all_clients | export_org
            $table->string('type', 32);
            // pending | running | done | failed
            $table->string('status', 16)->default('pending');

            $table->string('language_code', 8)->nullable();
            $table->string('target_email', 191)->nullable();
            $table->string('payload_path', 255)->nullable();
            $table->string('payload_sha256', 64)->nullable();
            $table->unsignedBigInteger('payload_bytes')->nullable();
            $table->text('error_message')->nullable();
            $table->json('meta')->nullable();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['org_id', 'type', 'created_at']);
            $table->index(['org_id', 'status', 'created_at']);
            $table->index(['client_id', 'type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dsar_operations');
    }
};

