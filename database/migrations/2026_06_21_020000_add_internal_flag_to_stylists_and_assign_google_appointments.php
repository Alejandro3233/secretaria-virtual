<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stylists', function (Blueprint $table): void {
            $table->boolean('is_internal')->default(false)->after('is_active');
        });

        DB::table('clinics')->pluck('id')->each(function (int $clinicId): void {
            $stylistId = DB::table('stylists')
                ->where('clinic_id', $clinicId)
                ->where('is_internal', true)
                ->where('name', 'Google')
                ->value('id');

            if (! $stylistId) {
                $stylistId = DB::table('stylists')->insertGetId([
                    'clinic_id' => $clinicId,
                    'name' => 'Google',
                    'specialty' => 'Control interno de Google Calendar',
                    'work_days' => json_encode(['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday']),
                    'work_starts_at' => '00:00:00',
                    'work_ends_at' => '23:59:00',
                    'is_active' => true,
                    'is_internal' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('appointments')
                ->where('clinic_id', $clinicId)
                ->where('source', 'google_calendar')
                ->update(['stylist_id' => $stylistId, 'updated_at' => now()]);
        });
    }

    public function down(): void
    {
        DB::table('stylists')->where('is_internal', true)->where('name', 'Google')->pluck('id')
            ->each(fn (int $stylistId) => DB::table('appointments')->where('stylist_id', $stylistId)->update(['stylist_id' => null]));
        DB::table('stylists')->where('is_internal', true)->where('name', 'Google')->delete();

        Schema::table('stylists', function (Blueprint $table): void {
            $table->dropColumn('is_internal');
        });
    }
};
