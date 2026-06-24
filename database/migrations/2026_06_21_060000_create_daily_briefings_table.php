<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_briefings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('briefing_date');
            $table->text('message');
            $table->timestamp('played_at')->nullable();
            $table->timestamps();

            $table->unique(['clinic_id', 'user_id', 'briefing_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_briefings');
    }
};
