<?php

namespace App\Services;

use App\Models\Clinic;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ClinicResolver
{
    public function currentOrCreate(User $user): Clinic
    {
        if ($clinic = $user->primaryClinic()) {
            return $clinic;
        }

        return DB::transaction(function () use ($user): Clinic {
            $plan = SubscriptionPlan::query()
                ->where('slug', 'profesional')
                ->first();

            $clinic = Clinic::create([
                'subscription_plan_id' => $plan?->id,
                'name' => 'Salon de '.$user->name,
                'email' => $user->email,
                'country_code' => 'US',
                'subscription_status' => 'trial',
            ]);

            DB::table('clinic_users')->insert([
                'clinic_id' => $clinic->id,
                'user_id' => $user->id,
                'role' => $user->is_super_admin ? 'super_admin' : 'owner',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $clinic;
        });
    }
}
