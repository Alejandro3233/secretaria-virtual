<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stylists', function (Blueprint $table): void {
            $table->time('break_starts_at')->nullable()->after('work_ends_at');
            $table->time('break_ends_at')->nullable()->after('break_starts_at');
        });
    }

    public function down(): void
    {
        Schema::table('stylists', function (Blueprint $table): void {
            $table->dropColumn(['break_starts_at', 'break_ends_at']);
        });
    }
};
