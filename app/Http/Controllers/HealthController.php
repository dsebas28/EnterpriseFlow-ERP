<?php

namespace App\Http\Controllers;

use App\Support\Health\HealthChecker;
use Illuminate\Http\JsonResponse;

/**
 * Readiness probe for load balancers, Docker and uptime monitors: 200 when
 * every dependency is up, 503 otherwise. Laravel's `/up` remains as a pure
 * liveness probe (the process answers).
 */
class HealthController extends Controller
{
    /**
     * Application health.
     *
     * @unauthenticated
     */
    public function __invoke(HealthChecker $health): JsonResponse
    {
        $checks = $health->run();
        $healthy = collect($checks)->every(fn (array $check) => $check['status'] === 'up');

        return response()->json([
            'status' => $healthy ? 'ok' : 'degraded',
            'app' => ['name' => config('app.name'), 'environment' => app()->environment()],
            'checks' => $checks,
            'checked_at' => now()->toIso8601String(),
        ], $healthy ? 200 : 503, ['Cache-Control' => 'no-store']);
    }
}
