<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('facility_resources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('capacity')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::table('services', function (Blueprint $table) {
            $table->foreignId('facility_resource_id')->nullable()->after('clinic_id')->constrained()->nullOnDelete();
            $table->unsignedInteger('resource_units')->default(1)->after('facility_resource_id');
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropConstrainedForeignId('facility_resource_id');
            $table->dropColumn('resource_units');
        });
        Schema::dropIfExists('facility_resources');
    }
};
