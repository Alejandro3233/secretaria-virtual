<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->unsignedInteger('monthly_price_cents');
            $table->string('stripe_price_id')->nullable();
            $table->unsignedInteger('monthly_appointments_limit')->nullable();
            $table->unsignedInteger('monthly_voice_minutes_limit')->nullable();
            $table->unsignedInteger('monthly_sms_limit')->nullable();
            $table->unsignedInteger('users_limit')->nullable();
            $table->json('features')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('clinics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_plan_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('twilio_phone_number')->nullable();
            $table->string('address')->nullable();
            $table->string('timezone')->default('America/New_York');
            $table->string('stripe_customer_id')->nullable()->index();
            $table->string('stripe_subscription_id')->nullable()->index();
            $table->string('subscription_status')->default('trial');
            $table->timestamp('subscription_renews_at')->nullable();
            $table->string('google_calendar_id')->nullable();
            $table->string('google_calendar_summary')->nullable();
            $table->text('google_access_token')->nullable();
            $table->text('google_refresh_token')->nullable();
            $table->timestamp('google_token_expires_at')->nullable();
            $table->string('google_sync_token')->nullable();
            $table->timestamp('google_connected_at')->nullable();
            $table->timestamp('google_last_synced_at')->nullable();
            $table->timestamps();
        });

        Schema::create('clinic_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role')->default('staff');
            $table->timestamps();
            $table->unique(['clinic_id', 'user_id']);
        });

        Schema::create('stylists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('specialty')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->string('first_name');
            $table->string('last_name')->nullable();
            $table->string('phone')->index();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->string('notification_preference')->default('both');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('client_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('hair_type')->nullable();
            $table->string('preferred_stylist')->nullable();
            $table->string('color_formula')->nullable();
            $table->text('allergies')->nullable();
            $table->text('beauty_notes')->nullable();
            $table->timestamps();
        });

        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('duration_minutes')->default(30);
            $table->unsignedInteger('price_cents')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('stylist_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->string('status')->default('pending');
            $table->string('priority')->default('normal');
            $table->string('source')->default('web');
            $table->string('reason')->nullable();
            $table->string('chair_station')->nullable();
            $table->unsignedInteger('deposit_cents')->nullable();
            $table->text('client_comments')->nullable();
            $table->text('internal_notes')->nullable();
            $table->string('google_calendar_id')->nullable();
            $table->string('google_calendar_event_id')->nullable()->index();
            $table->timestamp('google_synced_at')->nullable();
            $table->string('google_sync_status')->default('pending');
            $table->text('google_sync_error')->nullable();
            $table->timestamps();
        });

        Schema::create('appointment_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action');
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->timestamps();
        });

        Schema::create('call_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('appointment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('twilio_call_sid')->nullable()->index();
            $table->string('from_phone')->index();
            $table->string('to_phone')->nullable();
            $table->string('status')->default('received');
            $table->string('intent')->nullable();
            $table->text('transcript')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('appointment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('channel');
            $table->string('event');
            $table->string('recipient');
            $table->string('status')->default('pending');
            $table->string('provider_message_id')->nullable();
            $table->text('body')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('call_logs');
        Schema::dropIfExists('appointment_activity_logs');
        Schema::dropIfExists('appointments');
        Schema::dropIfExists('services');
        Schema::dropIfExists('client_preferences');
        Schema::dropIfExists('clients');
        Schema::dropIfExists('stylists');
        Schema::dropIfExists('clinic_users');
        Schema::dropIfExists('clinics');
        Schema::dropIfExists('subscription_plans');
    }
};
