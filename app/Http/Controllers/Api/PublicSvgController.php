<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class PublicSvgController extends Controller
{
    /**
     * Provide raw SVG markup with explicit CORS headers for frontend color manipulation.
     * Prevents browser CORS blocks when fetching assets cross-origin (e.g., ayohadir.id -> api.ayohadir.id).
     */
    public function show(Request $request): Response
    {
        $url = (string) $request->query('url', '');
        if (empty($url)) {
            return response('URL is required', Response::HTTP_BAD_REQUEST);
        }

        // 1. Check if the URL points to internal storage (/storage/...)
        $cleanPath = $url;
        if (preg_match('#/storage/(.+)#', $url, $matches)) {
            $cleanPath = $matches[1];
        }

        // Strip any query parameters or hash
        $cleanPath = explode('?', $cleanPath)[0];
        $cleanPath = explode('#', $cleanPath)[0];

        // Security check: Must end with .svg and prevent directory traversal
        if (str_contains($cleanPath, '..')) {
            return response('Invalid path', Response::HTTP_BAD_REQUEST);
        }

        // 2. Direct read from local public disk if it's a storage path
        if (Storage::disk('public')->exists($cleanPath)) {
            $content = Storage::disk('public')->get($cleanPath);
            return response($content, Response::HTTP_OK, [
                'Content-Type' => 'image/svg+xml; charset=utf-8',
                'Access-Control-Allow-Origin' => '*',
                'Access-Control-Allow-Methods' => 'GET, OPTIONS',
                'Cache-Control' => 'public, max-age=31536000, immutable',
            ]);
        }

        // 3. Fallback: Fetch external safe URL if full HTTP/HTTPS URL
        if (filter_var($url, FILTER_VALIDATE_URL)) {
            try {
                $response = Http::timeout(6)->get($url);
                if ($response->successful()) {
                    $body = $response->body();
                    // Verify it is actually SVG
                    if (str_contains($body, '<svg')) {
                        return response($body, Response::HTTP_OK, [
                            'Content-Type' => 'image/svg+xml; charset=utf-8',
                            'Access-Control-Allow-Origin' => '*',
                            'Access-Control-Allow-Methods' => 'GET, OPTIONS',
                            'Cache-Control' => 'public, max-age=31536000, immutable',
                        ]);
                    }
                }
            } catch (\Throwable $e) {
                // Ignore and fall through to 404
            }
        }

        return response('SVG asset not found', Response::HTTP_NOT_FOUND);
    }
}
