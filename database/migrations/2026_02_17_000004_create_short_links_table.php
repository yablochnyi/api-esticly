<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('short_links', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id'); // organization id
            $table->string('key', 80); // e.g. review
            $table->string('code', 32); // /s/{code}
            $table->string('target_path', 255); // e.g. /r/{slug}
            $table->timestamps();

            $table->unique(['code']);
            $table->unique(['user_id', 'key']);
            $table->index(['user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('short_links');
    }
};

