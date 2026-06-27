<?php

namespace App\Http\Middleware;

use App\Models\Clinic;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class RecordClinicRequestUsage
{
    public function handle(Request $request, Closure $next): Response
    {
        $startedAt = hrtime(true);
        $startingMemory = memory_get_usage(true);
        $response = $next($request);

        $clinicId = $this->clinicId($request);

        if ($clinicId && ! $request->is('costos-salones*')) {
            $responseBytes = (int) ($response->headers->get('Content-Length') ?: 0);

            if ($responseBytes === 0 && method_exists($response, 'getContent')) {
                $content = $response->getContent();
                $responseBytes = is_string($content) ? strlen($content) : 0;
            }

            DB::table('clinic_request_metrics')->insert([
                'clinic_id' => $clinicId,
                'duration_ms' => max(1, (int) round((hrtime(true) - $startedAt) / 1_000_000)),
                'memory_bytes' => max(0, memory_get_peak_usage(true) - $startingMemory),
                // Approximation of disk/data activity: request plus generated response.
                'disk_bytes' => max(0, (int) $request->server('CONTENT_LENGTH', 0)) + $responseBytes,
                'recorded_at' => now(),
            ]);
        }

        return $response;
    }

    private function clinicId(Request $request): ?int
    {
        $userClinicId = $request->user()?->clinics()->value('clinics.id');

        if ($userClinicId) {
            return (int) $userClinicId;
        }

        foreach ($request->route()?->parameters() ?? [] as $parameter) {
            if ($parameter instanceof Clinic) {
                return (int) $parameter->id;
            }

            if (is_object($parameter) && isset($parameter->clinic_id)) {
                return (int) $parameter->clinic_id;
            }
        }

        return null;
    }
}
