<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureValidOrigin
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Skip for webhook callbacks and health checks
        if ($request->is('api/v1/payment/duitku/callback') || $request->is('up') || $request->is('api/v1/health')) {
            return $next($request);
        }

        // Only enforce for API and Sanctum routes
        if (!$request->is('api/*') && !$request->is('sanctum/*')) {
            return $next($request);
        }

        $origin = $request->header('Origin');
        $referer = $request->header('Referer');

        // If neither Origin nor Referer is present (CLI, tests, cURL, server-to-server), proceed
        if (!$origin && !$referer) {
            return $next($request);
        }

        $allowedOrigins = array_filter([
            rtrim(env('FRONTEND_URL', 'http://localhost:5173'), '/'),
            'https://ayohadir.id',
            'https://www.ayohadir.id',
            'http://localhost:5173',
            'http://localhost:8000',
            'http://127.0.0.1:5173',
            'http://127.0.0.1:8000',
        ]);

        if ($origin) {
            $cleanOrigin = rtrim($origin, '/');
            if (!in_array($cleanOrigin, $allowedOrigins, true)) {
                return response()->json([
                    'error' => [
                        'code' => 'FORBIDDEN',
                        'message' => 'Origin not allowed.',
                    ],
                ], 403);
            }
        }

        if (!$origin && $referer) {
            $refererScheme = parse_url($referer, PHP_URL_SCHEME);
            $refererHost = parse_url($referer, PHP_URL_HOST);
            $refererPort = parse_url($referer, PHP_URL_PORT);
            if ($refererScheme && $refererHost) {
                $refererOrigin = $refererScheme . '://' . $refererHost . ($refererPort ? ':' . $refererPort : '');
                if (!in_array($refererOrigin, $allowedOrigins, true)) {
                    return response()->json([
                        'error' => [
                            'code' => 'FORBIDDEN',
                            'message' => 'Referer origin not allowed.',
                        ],
                    ], 403);
                }
            }
        }

        return $next($request);
    }
}
