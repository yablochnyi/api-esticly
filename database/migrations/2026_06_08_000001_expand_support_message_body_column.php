<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE support_messages MODIFY body MEDIUMTEXT NOT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE support_messages MODIFY body TEXT NOT NULL');
    }
};
