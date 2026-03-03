<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->softDeletes();
            $table->timestamp('anonymized_at')->nullable()->after('deleted_at');
            $table->foreignId('anonymized_by_user_id')
                ->nullable()
                ->after('anonymized_at')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropConstrainedForeignId('anonymized_by_user_id');
            $table->dropColumn('anonymized_at');
            $table->dropSoftDeletes();
        });
    }
};

