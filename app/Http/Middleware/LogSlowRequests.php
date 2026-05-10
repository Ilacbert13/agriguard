<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Logs requests that exceed a configurable wall-clock or DB-time threshold.
 *
 * Goals:
 *  - Surface slow controllers, slow N+1 query bursts, and slow external API calls in the logs.
 *  - Stay cheap for fast requests — only counts queries via a lightweight DB listener,
 *    and only emits a Log line when over the threshold.
 *  - Skip noisy non-page assets (the front controller, healthchecks).
 *
 * Configuration via env (no service-config file required):
 *   - PERF_LOG_SLOW_REQUEST_MS (default 1500) — wall-clock threshold (ms)
 *   - PERF_LOG_QUERY_COUNT (default 60)       — query count threshold per request
 *   - PERF_LOG_ENABLED (default true)         — set false to disable entirely
 */
class LogSlowRequests
{
    public function handle(Request $request, Closure $next): Response
    {
        // Synthetic Laravel route /up may still run behind nginx; skip DB instrumentation.
        if ($request->path() === 'up') {
            return $next($request);
        }

        if (! filter_var(env('PERF_LOG_ENABLED', true), FILTER_VALIDATE_BOOLEAN)) {
            return $next($request);
        }

        $thresholdMs = (int) env('PERF_LOG_SLOW_REQUEST_MS', 1500);
        $queryThreshold = (int) env('PERF_LOG_QUERY_COUNT', 60);

        $startedAt = microtime(true);
        $queryCount = 0;
        $queryTimeMs = 0.0;

        // Lightweight listener: increment counters only — no per-query logs.
        DB::listen(static function ($query) use (&$queryCount, &$queryTimeMs): void {
            $queryCount++;
            $queryTimeMs += (float) $query->time;
        });

        $response = $next($request);

        $elapsedMs = (microtime(true) - $startedAt) * 1000.0;

        if ($elapsedMs >= $thresholdMs || $queryCount >= $queryThreshold) {
            $route = optional($request->route())->getName() ?: $request->route()?->uri();
            Log::warning('perf.slow_request', [
                'method' => $request->getMethod(),
                'path' => $request->path(),
                'route' => $route,
                'status' => $response->getStatusCode(),
                'elapsed_ms' => round($elapsedMs, 1),
                'query_count' => $queryCount,
                'query_time_ms' => round($queryTimeMs, 1),
                'user_id' => optional($request->user())->getAuthIdentifier(),
                'memory_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 1),
            ]);
        }

        return $response;
    }
}
