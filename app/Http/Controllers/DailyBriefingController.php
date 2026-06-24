<?php

namespace App\Http\Controllers;

use App\Services\ClinicResolver;
use App\Services\DailyBriefingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DailyBriefingController extends Controller
{
    public function played(Request $request, ClinicResolver $clinics, DailyBriefingService $briefings): JsonResponse
    {
        $clinic = $clinics->currentOrCreate($request->user());
        $briefings->markPlayed($clinic, $request->user());

        return response()->json(['ok' => true]);
    }
}
