<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('users')) {
            return;
        }

        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE users MODIFY company_name TEXT NULL');
            DB::statement('ALTER TABLE users MODIFY address TEXT NULL');
            return;
        }

        Schema::table('users', function ($table) {
            $table->text('company_name')->nullable()->change();
            $table->text('address')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('users')) {
            return;
        }

        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE users MODIFY company_name VARCHAR(255) NULL');
            DB::statement('ALTER TABLE users MODIFY address VARCHAR(255) NULL');
            return;
        }

        Schema::table('users', function ($table) {
            $table->string('company_name', 255)->nullable()->change();
            $table->string('address', 255)->nullable()->change();
        });
    }
};
