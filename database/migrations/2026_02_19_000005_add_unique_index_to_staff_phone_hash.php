<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('staff', 'phone_hash')) {
            return;
        }

        $dups = DB::table('staff')
            ->select('phone_hash')
            ->whereNotNull('phone_hash')
            ->groupBy('phone_hash')
            ->havingRaw('COUNT(*) > 1')
            ->limit(1)
            ->pluck('phone_hash');

        if ($dups->isNotEmpty()) {
            throw new RuntimeException('Cannot add unique index staff.phone_hash: duplicate staff phone numbers detected.');
        }

        Schema::table('staff', function (Blueprint $table) {
            $table->unique('phone_hash', 'staff_phone_hash_unique');
        });
    }

    public function down(): void
    {
        try {
            DB::statement('ALTER TABLE staff DROP INDEX staff_phone_hash_unique');
        } catch (\Throwable) {
            // ignore
        }
    }
};
