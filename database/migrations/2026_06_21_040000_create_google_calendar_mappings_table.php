<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('google_calendar_mappings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stylist_id')->nullable()->constrained()->nullOnDelete();
            $table->string('google_calendar_id');
            $table->string('google_calendar_name');
            $table->string('access_role')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_enabled')->default(true);
            $table->boolean('is_available')->default(true);
            $table->timestamp('last_detected_at')->nullable();
            $table->timestamps();

            $table->unique(['clinic_id', 'google_calendar_id']);
            $table->index(['clinic_id', 'is_enabled', 'is_available']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('google_calendar_mappings');
    }
};
