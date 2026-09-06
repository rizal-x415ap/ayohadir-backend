<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateMediaMetadataRequest;
use App\Http\Requests\UploadMediaRequest;
use App\Http\Resources\MediaResource;
use App\Models\Media;
use App\Services\MediaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AdminAssetController extends Controller
{
    public function __construct(
        protected MediaService $mediaService
    ) {}

    /**
     * List admin global studio design assets.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        if (!$request->user()->isAdmin()) {
            abort(403, 'Akses khusus administrator.');
        }

        $query = Media::globalAssets();

        if ($request->filled('type')) {
            $query->where('type', $request->query('type'));
        }

        if ($request->filled('category')) {
            if ($request->query('category') === 'music') {
                $query->where(function ($q) {
                    $q->where('category', 'music')
                      ->orWhere('type', 'audio');
                });
            } else {
                $query->where('category', $request->query('category'));
            }
        }

        if ($request->filled('search')) {
            $search = $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('filename', 'like', "%{$search}%")
                  ->orWhere('title', 'like', "%{$search}%")
                  ->orWhere('artist', 'like', "%{$search}%")
                  ->orWhere('category', 'like', "%{$search}%");
            });
        }

        $assets = $query->latest('id')->paginate($request->integer('per_page', 36));

        return MediaResource::collection($assets);
    }

    /**
     * Upload a new global studio design asset.
     */
    public function store(UploadMediaRequest $request): JsonResponse
    {
        if (!$request->user()->isAdmin()) {
            abort(403, 'Akses khusus administrator.');
        }

        $category = $request->input('category', 'ornament');
        $tags = $request->input('tags', []);
        $title = $request->input('title');
        $artist = $request->input('artist');

        if ($request->hasFile('files')) {
            $uploadedMedia = [];
            foreach ($request->file('files') as $file) {
                $media = $this->mediaService->upload(
                    file: $file,
                    user: $request->user(),
                    wedding: null,
                    category: $category,
                    tags: $tags,
                    isSystem: true,
                    title: $title,
                    artist: $artist
                );
                $uploadedMedia[] = $media;
            }

            return response()->json([
                'data' => MediaResource::collection(collect($uploadedMedia)),
                'meta' => [
                    'count' => count($uploadedMedia),
                    'timestamp' => now()->toIso8601String(),
                ],
            ], 201);
        }

        $media = $this->mediaService->upload(
            file: $request->file('file'),
            user: $request->user(),
            wedding: null,
            category: $category,
            tags: $tags,
            isSystem: true,
            title: $title,
            artist: $artist
        );

        return (new MediaResource($media))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Update metadata of a global studio design asset.
     */
    public function update(UpdateMediaMetadataRequest $request, Media $media): MediaResource
    {
        if (!$request->user()->isAdmin()) {
            abort(403, 'Akses khusus administrator.');
        }

        if (!$media->is_system) {
            abort(404, 'Asset global tidak ditemukan.');
        }

        $media->update($request->validated());

        return new MediaResource($media);
    }

    /**
     * Delete a global studio design asset.
     */
    public function destroy(Request $request, Media $media): JsonResponse
    {
        if (!$request->user()->isAdmin()) {
            abort(403, 'Akses khusus administrator.');
        }

        if (!$media->is_system) {
            abort(404, 'Asset global tidak ditemukan.');
        }

        $result = $this->mediaService->deleteMedia($media);

        return response()->json([
            'data' => $result,
            'meta' => [
                'timestamp' => now()->toIso8601String(),
            ],
        ]);
    }
}
