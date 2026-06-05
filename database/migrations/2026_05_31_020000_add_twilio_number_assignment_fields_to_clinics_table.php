<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clinics', function (Blueprint $table): void {
            $table->string('country_code', 2)->default('US')->after('phone');
            $table->string('twilio_phone_sid')->nullable()->after('twilio_phone_number');
            $table->string('twilio_number_status')->default('pending')->after('twilio_phone_sid');
            $table->text('twilio_number_error')->nullable()->after('twilio_number_status');
            $table->timestamp('twilio_number_assigned_at')->nullable()->after('twilio_number_error');
        });
    }

    public function down(): void
    {
        Schema::table('clinics', function (Blueprint $table): void {
            $table->dropColumn([
                'country_code',
                'twilio_phone_sid',
                'twilio_number_status',
                'twilio_number_error',
                'twilio_number_assigned_at',
            ]);
        });
    }
};
