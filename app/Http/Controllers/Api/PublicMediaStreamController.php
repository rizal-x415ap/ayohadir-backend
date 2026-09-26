<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class PublicMediaStreamController extends Controller
{
    /**
     * Provide binary media file streaming (video/audio) with explicit CORS headers.
     * Prevents browser CORS blocks when downloading assets cross-origin (ayohadir.id -> api.ayohadir.id).
     */
    public function stream(Request $request): Response
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

        // Strip query params or hash
        $cleanPath = explode('?', $cleanPath)[0];
        $cleanPath = explode('#', $cleanPath)[0];

        // Security check: prevent directory traversal
        if (str_contains($cleanPath, '..')) {
            return response('Invalid path', Response::HTTP_BAD_REQUEST);
        }

        // 2. Direct read from local public disk
        if (Storage::disk('public')->exists($cleanPath)) {
            $fullPath = Storage::disk('public')->path($cleanPath);
            $mimeType = Storage::disk('public')->mimeType($cleanPath) ?: 'video/mp4';

            $response = new BinaryFileResponse($fullPath, 200, [
                'Content-Type' => $mimeType,
                'Access-Control-Allow-Origin' => '*',
                'Access-Control-Allow-Methods' => 'GET, HEAD, OPTIONS',
                'Access-Control-Allow-Headers' => '*',
                'Access-Control-Expose-Headers' => 'Content-Length, Content-Range, Accept-Ranges',
                'Accept-Ranges' => 'bytes',
                'Cache-Control' => 'public, max-age=31536000, immutable',
            ]);
            $response->prepare($request);
            return $response;
        }

        return response('File not found', Response::HTTP_NOT_FOUND);
    }
}
