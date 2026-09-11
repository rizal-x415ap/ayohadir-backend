<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\MediaResource;
use App\Models\Media;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class GlobalAssetController extends Controller
{
    /**
     * List global design and audio assets available for weddings.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Media::globalAssets();

        if ($request->filled('type')) {
            $query->where('type', $request->query('type'));
        }

        if ($request->filled('exclude_type')) {
            $query->where('type', '!=', $request->query('exclude_type'));
        }

        if ($request->boolean('exclude_music')) {
            $query->where(function ($q) {
                $q->where('category', '!=', 'music')
                  ->orWhereNull('category');
            })->where('type', '!=', 'audio');
        }

        if ($request->filled('category')) {
            if ($request->query('category') === 'music') {
                $query->where(function ($q) {
                    $q->where('category', 'music')
                      ->orWhere('type', 'audio');
                });
            } elseif ($request->query('category') === 'video') {
                $query->where(function ($q) {
                    $q->where('category', 'video')
                      ->orWhere('type', 'video');
                });
            } elseif ($request->query('category') === 'gif') {
                $query->where(function ($q) {
                    $q->where('category', 'gif')
                      ->orWhere('mime_type', 'image/gif')
                      ->orWhere('path', 'like', '%.gif')
                      ->orWhere('filename', 'like', '%.gif');
                });
            } else {
                $query->where('category', $request->query('category'));
            }
        }

        if ($request->filled('search')) {
            $search = $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('artist', 'like', "%{$search}%")
                  ->orWhere('filename', 'like', "%{$search}%")
                  ->orWhere('category', 'like', "%{$search}%");
            });
        }

        $perPage = min($request->integer('per_page', 50), 100);
        $assets = $query->latest('id')->paginate($perPage);

        return MediaResource::collection($assets);
    }
}
