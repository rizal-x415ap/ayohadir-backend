<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWeddingRequest;
use App\Http\Requests\UpdateWeddingRequest;
use App\Http\Resources\WeddingResource;
use App\Models\Wedding;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;

class WeddingController extends Controller
{
    use AuthorizesRequests;

    /**
     * Display a listing of the authenticated user's weddings.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Wedding::class);

        $weddings = $request->user()->weddings()
            ->latest('id')
            ->get();

        return WeddingResource::collection($weddings);
    }

    /**
     * Store a newly created wedding in storage.
     */
    public function store(StoreWeddingRequest $request): JsonResponse
    {
        $this->authorize('create', Wedding::class);

        $data = $request->validated();
        $wedding = $request->user()->weddings()->create($data);

        if (!empty($wedding->applied_template_id)) {
            $template = \App\Models\Template::find($wedding->applied_template_id);
            if ($template && $template->schema) {
                $design = new \App\Models\Design(['wedding_id' => $wedding->id]);
                $design->template_id = $template->id;
                $design->schema_version = $template->schema_version;
                $design->schema = $template->schema;
                $design->version = 1;
                $design->save();
            }
        }

        // Seed default guest groups
        foreach (['Keluarga', 'Teman / Sahabat', 'Rekan Kerja', 'VIP'] as $name) {
            $wedding->guestGroups()->create(['name' => $name]);
        }

        return (new WeddingResource($wedding))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display the specified wedding.
     */
    public function show(Wedding $wedding): WeddingResource
    {
        $this->authorize('view', $wedding);

        return new WeddingResource($wedding);
    }

    /**
     * Update the specified wedding in storage.
     */
    public function update(UpdateWeddingRequest $request, Wedding $wedding): WeddingResource
    {
        $this->authorize('update', $wedding);

        $wedding->update($request->validated());

        return new WeddingResource($wedding);
    }

    /**
     * Remove (soft delete) the specified wedding from storage.
     */
    public function destroy(Wedding $wedding): JsonResponse
    {
        $this->authorize('delete', $wedding);

        $wedding->delete();

        return response()->json([
            'data' => [
                'message' => 'Proyek undangan telah dipindahkan ke kotak sampah.',
            ],
            'meta' => [
                'timestamp' => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * List user's trashed (soft-deleted) wedding projects.
     */
    public function trash(Request $request): AnonymousResourceCollection
    {
        $trashed = Wedding::onlyTrashed()
            ->where('user_id', $request->user()->id)
            ->latest('deleted_at')
            ->get();

        return WeddingResource::collection($trashed);
    }

    /**
     * Restore a soft-deleted wedding project within the 7-day retention window.
     */
    public function restore(Request $request, int|string $id): JsonResponse
    {
        $wedding = Wedding::onlyTrashed()
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);

        // Check if within 7-day retention window
        if ($wedding->deleted_at->copy()->addDays(7)->isPast()) {
            abort(422, 'Masa pemulihan undangan ini telah berakhir (melebihi 7 hari sejak dihapus).');
        }

        $wedding->restore();

        return (new WeddingResource($wedding))
            ->response()
            ->setStatusCode(200);
    }

    /**
     * Permanently remove a wedding project from database and wipe all its media files from storage.
     */
    public function forceDelete(Request $request, int|string $id): JsonResponse
    {
        $wedding = Wedding::onlyTrashed()
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);

        // 1. Wipe all physical media files in storage disk
        Storage::disk('public')->deleteDirectory("media/weddings/{$wedding->id}");

        // 2. Permanently delete wedding (cascades to all relational data)
        $wedding->forceDelete();

        return response()->json([
            'data' => [
                'message' => 'Proyek undangan dan seluruh file medianya telah dihapus permanen dari server.',
            ],
            'meta' => [
                'timestamp' => now()->toIso8601String(),
            ],
        ]);
    }
}
