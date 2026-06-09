<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->boolean('reminder_call_enabled')->default(false)->after('google_sync_error');
            $table->boolean('reminder_sms_enabled')->default(false)->after('reminder_call_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn(['reminder_call_enabled', 'reminder_sms_enabled']);
        });
    }
};
