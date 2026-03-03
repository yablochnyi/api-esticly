<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_threads', function (Blueprint $table) {
            // allow multiple tickets per org
            $table->dropUnique(['org_id']);

            $table->string('subject', 120)->nullable()->after('org_id');
            $table->string('status', 16)->default('open')->after('subject'); // open|closed
            $table->unsignedBigInteger('created_by_user_id')->nullable()->after('status');
            $table->timestamp('closed_at')->nullable()->after('created_by_user_id');

            $table->index(['org_id', 'status']);
            $table->index(['status']);
            $table->index(['created_by_user_id']);
        });
    }

    public function down(): void
    {
        Schema::table('support_threads', function (Blueprint $table) {
            $table->dropIndex(['org_id', 'status']);
            $table->dropIndex(['status']);
            $table->dropIndex(['created_by_user_id']);

            $table->dropColumn(['subject', 'status', 'created_by_user_id', 'closed_at']);

            $table->unique(['org_id']);
        });
    }
};

