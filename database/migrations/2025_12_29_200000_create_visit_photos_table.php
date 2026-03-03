<?php

use App\Models\Visit;
use App\Models\VisitPhoto;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visit_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visit_id')->constrained('visits')->cascadeOnDelete();
            $table->string('kind', 16); // before|after
            $table->string('path');
            $table->timestamps();

            $table->index(['visit_id', 'kind']);
        });

        // Backfill legacy single-photo columns into the new table.
        Visit::query()
            ->whereNotNull('photo_before_path')
            ->select(['id', 'photo_before_path'])
            ->chunkById(200, function ($rows) {
                foreach ($rows as $v) {
                    VisitPhoto::query()->create([
                        'visit_id' => $v->id,
                        'kind' => 'before',
                        'path' => $v->photo_before_path,
                    ]);
                }
            });

        Visit::query()
            ->whereNotNull('photo_after_path')
            ->select(['id', 'photo_after_path'])
            ->chunkById(200, function ($rows) {
                foreach ($rows as $v) {
                    VisitPhoto::query()->create([
                        'visit_id' => $v->id,
                        'kind' => 'after',
                        'path' => $v->photo_after_path,
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('visit_photos');
    }
};


