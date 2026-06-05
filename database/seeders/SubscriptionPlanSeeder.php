<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SubscriptionPlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Basico',
                'slug' => 'basico',
                'monthly_price_cents' => 4900,
                'monthly_appointments_limit' => 150,
                'monthly_voice_minutes_limit' => 0,
                'monthly_sms_limit' => 50,
                'users_limit' => 2,
                'features' => json_encode(['citas', 'clientes', 'servicios', 'email']),
            ],
            [
                'name' => 'Profesional',
                'slug' => 'profesional',
                'monthly_price_cents' => 9900,
                'monthly_appointments_limit' => 500,
                'monthly_voice_minutes_limit' => 600,
                'monthly_sms_limit' => 500,
                'users_limit' => 6,
                'features' => json_encode(['twilio_voice', 'sms', 'google_calendar', 'estilistas']),
            ],
            [
                'name' => 'Salon Plus',
                'slug' => 'clinica-plus',
                'monthly_price_cents' => 19900,
                'monthly_appointments_limit' => null,
                'monthly_voice_minutes_limit' => 2000,
                'monthly_sms_limit' => 2000,
                'users_limit' => null,
                'features' => json_encode(['twilio_voice', 'sms', 'google_calendar', 'reportes', 'multi_salon']),
            ],
        ];

        foreach ($plans as $plan) {
            DB::table('subscription_plans')->updateOrInsert(
                ['slug' => $plan['slug']],
                array_merge($plan, [
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }
    }
}
