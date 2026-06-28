<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table): void {
            $table->timestamp('marketing_email_consent_at')->nullable();
            $table->timestamp('marketing_sms_consent_at')->nullable();
        });

        Schema::create('flash_campaigns', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->unsignedTinyInteger('discount_percent');
            $table->unsignedInteger('original_price_cents')->nullable();
            $table->unsignedInteger('discounted_price_cents')->nullable();
            $table->string('segment');
            $table->json('channels');
            $table->string('subject')->nullable();
            $table->text('message');
            $table->timestamp('expires_at');
            $table->string('status')->default('draft')->index();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();
        });

        Schema::create('flash_campaign_recipients', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('flash_campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->uuid('token')->unique();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('email_status')->nullable();
            $table->string('sms_status')->nullable();
            $table->string('email_provider_id')->nullable();
            $table->string('sms_provider_id')->nullable()->index();
            $table->text('email_error')->nullable();
            $table->text('sms_error')->nullable();
            $table->timestamp('email_sent_at')->nullable();
            $table->timestamp('sms_sent_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamps();
            $table->unique(['flash_campaign_id', 'client_id']);
        });

        Schema::table('appointments', function (Blueprint $table): void {
            $table->foreignId('flash_campaign_recipient_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('campaign_discount_percent')->nullable();
            $table->unsignedInteger('campaign_price_cents')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('flash_campaign_recipient_id');
            $table->dropColumn(['campaign_discount_percent', 'campaign_price_cents']);
        });
        Schema::dropIfExists('flash_campaign_recipients');
        Schema::dropIfExists('flash_campaigns');
        Schema::table('clients', function (Blueprint $table): void {
            $table->dropColumn(['marketing_email_consent_at', 'marketing_sms_consent_at']);
        });
    }
};
