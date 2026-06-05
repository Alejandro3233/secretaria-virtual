<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stylists', function (Blueprint $table) {
            $table->foreignId('service_id')->nullable()->after('clinic_id')->constrained()->nullOnDelete();
            $table->json('work_days')->nullable()->after('specialty');
            $table->time('work_starts_at')->nullable()->after('work_days');
            $table->time('work_ends_at')->nullable()->after('work_starts_at');
        });
    }

    public function down(): void
    {
        Schema::table('stylists', function (Blueprint $table) {
            $table->dropConstrainedForeignId('service_id');
            $table->dropColumn(['work_days', 'work_starts_at', 'work_ends_at']);
        });
    }
};
