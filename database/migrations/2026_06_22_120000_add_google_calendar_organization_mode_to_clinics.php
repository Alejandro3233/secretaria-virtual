<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clinics', function (Blueprint $table): void {
            $table->string('google_calendar_organization_mode')->nullable()->after('google_calendar_summary');
        });

        DB::table('clinics')
            ->whereNotNull('google_connected_at')
            ->update(['google_calendar_organization_mode' => 'existing']);
    }

    public function down(): void
    {
        Schema::table('clinics', function (Blueprint $table): void {
            $table->dropColumn('google_calendar_organization_mode');
        });
    }
};
