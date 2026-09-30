<?php

namespace App\Support\Health;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Probes every dependency the application needs to serve requests.
 * Failure details are logged, never returned: the result only says
 * up/down with a latency, so it is safe to expose publicly.
 */
final class HealthChecker
{
    /**
     * @return array<string, array{status: 'up'|'down', latency_ms: float}>
     */
    public function run(): array
    {
        $checks = [
            'database' => $this->probe('database', fn () => DB::select('select 1')),
            'cache' => $this->probe('cache', function (): void {
                $key = 'health:'.Str::random(8);
                Cache::put($key, true, 10);

                if (Cache::pull($key) !== true) {
                    throw new RuntimeException('Cache round-trip failed.');
                }
            }),
            'storage' => $this->probe('storage', function (): void {
                $path = 'health/'.Str::random(8);
                Storage::disk('local')->put($path, 'ok');
                Storage::disk('local')->delete($path);
            }),
        ];

        if ($this->usesRedis()) {
            $checks['redis'] = $this->probe('redis', fn () => Redis::connection()->ping());
        }

        return $checks;
    }

    /**
     * @return array{status: 'up'|'down', latency_ms: float}
     */
    private function probe(string $name, callable $probe): array
    {
        $start = hrtime(true);

        try {
            $probe();
            $status = 'up';
        } catch (Throwable $e) {
            Log::error('health.check_failed', ['check' => $name, 'exception' => $e::class, 'message' => $e->getMessage()]);
            $status = 'down';
        }

        return ['status' => $status, 'latency_ms' => round((hrtime(true) - $start) / 1e6, 2)];
    }

    private function usesRedis(): bool
    {
        return config('cache.default') === 'redis'
            || config('queue.default') === 'redis'
            || config('session.driver') === 'redis';
    }
}
