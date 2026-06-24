<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clinics', function (Blueprint $table): void {
            $table->timestamp('google_ever_synced_at')->nullable()->after('google_last_synced_at');
        });

        DB::table('clinics')->get(['id', 'google_last_synced_at'])->each(function (object $clinic): void {
            $firstImportedAt = DB::table('appointments')
                ->where('clinic_id', $clinic->id)
                ->where('source', 'google_calendar')
                ->min('created_at');

            $everSyncedAt = $clinic->google_last_synced_at ?: $firstImportedAt;
            if ($everSyncedAt) {
                DB::table('clinics')->where('id', $clinic->id)->update([
                    'google_ever_synced_at' => $everSyncedAt,
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('clinics', function (Blueprint $table): void {
            $table->dropColumn('google_ever_synced_at');
        });
    }
};
