<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

class HealthCheckController extends Controller
{
    /**
     * Return backend, database, and cache health status.
     */
    public function __invoke(): JsonResponse
    {
        $dbStatus = 'ONLINE';
        try {
            DB::connection()->getPdo();
        } catch (Throwable $e) {
            $dbStatus = 'OFFLINE';
        }

        $cacheStatus = 'ONLINE';
        try {
            Cache::put('health_ping', 'pong', 10);
            $cached = Cache::get('health_ping');
            if ($cached !== 'pong') {
                $cacheStatus = 'DEGRADED';
            }
        } catch (Throwable $e) {
            $cacheStatus = 'OFFLINE';
        }

        $isHealthy = ($dbStatus === 'ONLINE');

        return response()->json([
            'data' => [
                'status' => $isHealthy ? 'healthy' : 'unhealthy',
                'app' => config('app.name', 'Ayo Hadir'),
                'environment' => config('app.env', 'production'),
                'version' => app()->version(),
                'database' => $dbStatus,
                'cache' => $cacheStatus,
                'timestamp' => now()->toIso8601String(),
            ],
            'meta' => [
                'timestamp' => now()->toIso8601String(),
            ],
        ], $isHealthy ? 200 : 503);
    }
}
