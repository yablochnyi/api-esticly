<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visit_agreements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visit_id')->constrained('visits')->cascadeOnDelete();
            $table->foreignId('service_id')->nullable()->constrained('services')->nullOnDelete();
            $table->longText('agreement_text')->nullable(); // snapshot at signing time
            $table->string('signature_path')->nullable(); // stored in public disk
            $table->timestamp('signed_at')->nullable();
            $table->timestamps();

            $table->unique('visit_id');
            $table->index(['service_id', 'signed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visit_agreements');
    }
};

