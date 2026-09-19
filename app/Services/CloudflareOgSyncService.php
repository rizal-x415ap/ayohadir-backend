<?php

namespace App\Services;

use App\Http\Controllers\Api\PublicOgController;
use App\Models\Template;
use App\Models\Wedding;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CloudflareOgSyncService
{
    protected PublicOgController $ogController;

    public function __construct(PublicOgController $ogController)
    {
        $this->ogController = $ogController;
    }

    /**
     * Send payload to Cloudflare Pages edge sync endpoint.
     */
    public function pushToKv(string $key, array $data): bool
    {
        $endpoint = env('CLOUDFLARE_KV_SYNC_URL', 'https://ayohadir.id/_edge/sync-og');

        try {
            $response = Http::timeout(6)->post($endpoint, [
                'key' => $key,
                'data' => $data,
            ]);

            if ($response->successful()) {
                return true;
            }

            Log::warning("Cloudflare KV sync returned {$response->status()}: " . $response->body());
            return false;
        } catch (\Throwable $e) {
            Log::warning("Cloudflare KV sync failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Sync a single template to Cloudflare KV.
     */
    public function syncTemplate(Template $template): bool
    {
        $request = Request::create("/templates/{$template->slug}", 'GET');
        $data = $this->ogController->resolveTemplateOgData($request, $template->slug);

        if (!$data) {
            return false;
        }

        return $this->pushToKv("template:{$template->slug}", $data);
    }

    /**
     * Sync a single wedding invitation to Cloudflare KV.
     */
    public function syncWedding(Wedding $wedding): bool
    {
        $request = Request::create("/{$wedding->slug}", 'GET');
        $data = $this->ogController->resolveWeddingOgData($request, $wedding->slug);

        if (!$data) {
            return false;
        }

        return $this->pushToKv("wedding:{$wedding->slug}", $data);
    }
}
