<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UploadMediaRequest;
use App\Http\Resources\MediaResource;
use App\Models\Media;
use App\Models\Wedding;
use App\Services\MediaService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UserMediaController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected MediaService $mediaService
    ) {}

    /**
     * List user's media library for a specific wedding project.
     */
    public function index(Request $request, Wedding $wedding): AnonymousResourceCollection
    {
        $this->authorize('view', $wedding);

        $query = Media::forWedding($wedding->id);

        if ($request->filled('type')) {
            $query->where('type', $request->query('type'));
        }

        if ($request->filled('category')) {
            $query->where('category', $request->query('category'));
        }

        $media = $query->latest('id')->paginate($request->integer('per_page', 24));

        return MediaResource::collection($media)->additional([
            'meta' => [
                'storage' => $this->mediaService->getWeddingStorageUsage($wedding->id),
            ],
        ]);
    }

    /**
     * Upload a new media item to the user's wedding library.
     */
    public function store(UploadMediaRequest $request, Wedding $wedding): JsonResponse
    {
        $this->authorize('update', $wedding);

        $media = $this->mediaService->upload(
            file: $request->file('file'),
            user: $request->user(),
            wedding: $wedding,
            category: $request->input('category', 'general'),
            tags: $request->input('tags', []),
            isSystem: false,
            title: $request->input('title'),
            artist: $request->input('artist')
        );

        return (new MediaResource($media))
            ->additional([
                'meta' => [
                    'storage' => $this->mediaService->getWeddingStorageUsage($wedding->id),
                ],
            ])
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Delete a media item from the user's wedding library.
     */
    public function destroy(Wedding $wedding, Media $media): JsonResponse
    {
        if ($media->wedding_id !== $wedding->id) {
            abort(404, 'Media tidak ditemukan pada proyek undangan ini.');
        }

        $this->authorize('delete', $media);

        $result = $this->mediaService->deleteMedia($media);

        return response()->json([
            'data' => $result,
            'meta' => [
                'storage' => $this->mediaService->getWeddingStorageUsage($wedding->id),
                'timestamp' => now()->toIso8601String(),
            ],
        ]);
    }
}
