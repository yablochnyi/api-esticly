<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('users', 'timezone')) {
            return;
        }

        DB::table('users')
            ->where('timezone', 'Europe/Kiev')
            ->update(['timezone' => 'Europe/Kyiv']);
    }

    public function down(): void
    {
        // Intentionally no-op: Europe/Kyiv is the canonical timezone name.
    }
};
