<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinic_request_metrics', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('duration_ms');
            $table->unsignedBigInteger('memory_bytes')->default(0);
            $table->unsignedBigInteger('disk_bytes')->default(0);
            $table->timestamp('recorded_at');

            $table->index(['clinic_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinic_request_metrics');
    }
};
