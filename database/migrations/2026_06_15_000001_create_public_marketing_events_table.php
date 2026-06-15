<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('public_marketing_events', function (Blueprint $table) {
            $table->id();
            $table->string('event', 40);
            $table->string('locale', 10)->nullable();
            $table->string('path', 255)->nullable();
            $table->string('page_url', 1000)->nullable();
            $table->string('referrer', 1000)->nullable();
            $table->string('utm_source', 120)->nullable();
            $table->string('utm_medium', 120)->nullable();
            $table->string('utm_campaign', 190)->nullable();
            $table->string('utm_content', 190)->nullable();
            $table->string('utm_term', 190)->nullable();
            $table->string('fbclid_hash', 64)->nullable();
            $table->string('gclid_hash', 64)->nullable();
            $table->string('visitor_id_hash', 64)->nullable();
            $table->string('ip_hash', 64)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at')->useCurrent();
            $table->timestamps();

            $table->index(['event', 'occurred_at']);
            $table->index(['utm_campaign', 'utm_content']);
            $table->index('visitor_id_hash');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('public_marketing_events');
    }
};
