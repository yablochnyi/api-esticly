<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('short_links', function (Blueprint $table) {
            $table->unsignedBigInteger('click_count')->default(0)->after('target_path');
            $table->timestamp('last_clicked_at')->nullable()->after('click_count');
        });
    }

    public function down(): void
    {
        Schema::table('short_links', function (Blueprint $table) {
            $table->dropColumn(['click_count', 'last_clicked_at']);
        });
    }
};

