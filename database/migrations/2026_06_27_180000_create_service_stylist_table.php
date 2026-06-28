<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_stylist', function (Blueprint $table): void {
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stylist_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['service_id', 'stylist_id']);
        });

        DB::table('stylists')->whereNotNull('service_id')->orderBy('id')->each(function (object $stylist): void {
            DB::table('service_stylist')->insertOrIgnore([
                'service_id' => $stylist->service_id,
                'stylist_id' => $stylist->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_stylist');
    }
};
