<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('smart_push_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('org_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 40);
            $table->date('local_date');
            $table->string('title', 160);
            $table->text('body');
            $table->json('data')->nullable();
            $table->string('status', 24)->default('pending');
            $table->timestamp('sent_at')->nullable();
            $table->string('error', 255)->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'type', 'local_date'], 'smart_push_user_type_date_unique');
            $table->index(['org_id', 'local_date']);
            $table->index(['status']);
        });

        $this->backfillMissingLanguageCodes();
    }

    public function down(): void
    {
        Schema::dropIfExists('smart_push_deliveries');
    }

    private function backfillMissingLanguageCodes(): void
    {
        if (!Schema::hasColumn('users', 'language_code') || !Schema::hasColumn('users', 'timezone')) {
            return;
        }

        $cases = [
            'uk' => ['Europe/Kyiv', 'Europe/Kiev'],
            'pl' => ['Europe/Warsaw'],
            'cs' => ['Europe/Prague'],
            'de' => ['Europe/Berlin', 'Europe/Vienna', 'Europe/Zurich'],
            'fr' => ['Europe/Paris'],
            'it' => ['Europe/Rome'],
            'es' => ['Europe/Madrid'],
            'pt' => ['Europe/Lisbon'],
        ];

        foreach ($cases as $language => $timezones) {
            DB::table('users')
                ->where(function ($query) {
                    $query->whereNull('language_code')->orWhere('language_code', '');
                })
                ->whereIn('timezone', $timezones)
                ->update(['language_code' => $language]);
        }

        DB::table('users')
            ->where(function ($query) {
                $query->whereNull('language_code')->orWhere('language_code', '');
            })
            ->update(['language_code' => 'en']);
    }
};
