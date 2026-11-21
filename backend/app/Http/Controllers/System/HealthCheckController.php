<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class HealthCheckController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'cache' => $this->checkCache(),
            'storage' => $this->checkStorage(),
        ];

        $allHealthy = ! in_array('failed', $checks, true);

        return response()->json([
            'status' => $allHealthy ? 'healthy' : 'degraded',
            'checks' => $checks,
            'timestamp' => now()->utc()->toIso8601String(),
            'request_id' => $request->attributes->get('request_id'),
        ], $allHealthy ? 200 : 503);
    }

    private function checkDatabase(): string
    {
        try {
            DB::select('SELECT 1');

            return 'ok';
        } catch (Throwable) {
            return 'failed';
        }
    }

    private function checkCache(): string
    {
        try {
            $probeKey = 'xhopy_health_probe_'.Str::random(12);
            Cache::put($probeKey, 'ok', 10);
            $value = Cache::get($probeKey);
            Cache::forget($probeKey);

            return $value === 'ok' ? 'ok' : 'failed';
        } catch (Throwable) {
            return 'failed';
        }
    }

    private function checkStorage(): string
    {
        try {
            $storageDir = storage_path('app');

            return is_dir($storageDir) && is_writable($storageDir) ? 'ok' : 'failed';
        } catch (Throwable) {
            return 'failed';
        }
    }
}
