<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->string('source', 40)->nullable()->after('text');
            $table->string('short_link_code', 32)->nullable()->after('source');
            $table->index(['user_id', 'source']);
            $table->index(['short_link_code']);
        });
    }

    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'source']);
            $table->dropIndex(['short_link_code']);
            $table->dropColumn(['source', 'short_link_code']);
        });
    }
};

