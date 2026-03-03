<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('launch_waitlist_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->string('email', 190);
            $table->string('phone', 40);
            $table->string('locale', 10)->default('en');
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamp('subscribed_at');
            $table->timestamps();

            $table->unique(['email', 'phone']);
            $table->index('locale');
            $table->index('subscribed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('launch_waitlist_subscriptions');
    }
};

