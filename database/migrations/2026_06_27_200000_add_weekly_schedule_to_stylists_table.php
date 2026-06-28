<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stylists', fn (Blueprint $table) => $table->json('weekly_schedule')->nullable()->after('break_ends_at'));
    }

    public function down(): void
    {
        Schema::table('stylists', fn (Blueprint $table) => $table->dropColumn('weekly_schedule'));
    }
};
