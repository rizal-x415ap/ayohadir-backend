<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreGuestGroupRequest;
use App\Http\Requests\UpdateGuestGroupRequest;
use App\Http\Resources\GuestGroupResource;
use App\Models\GuestGroup;
use App\Models\Wedding;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class GuestGroupController extends Controller
{
    use AuthorizesRequests;

    /**
     * List guest groups for a wedding.
     */
    public function index(Request $request, Wedding $wedding): AnonymousResourceCollection
    {
        $this->authorize('view', $wedding);

        $groups = $wedding->guestGroups()
            ->withCount('guests')
            ->orderBy('name')
            ->get();

        if ($groups->isEmpty()) {
            $defaultNames = ['Keluarga', 'Teman / Sahabat', 'Rekan Kerja', 'VIP'];
            foreach ($defaultNames as $name) {
                $wedding->guestGroups()->create(['name' => $name]);
            }
            $groups = $wedding->guestGroups()
                ->withCount('guests')
                ->orderBy('name')
                ->get();
        }

        return GuestGroupResource::collection($groups);
    }

    /**
     * Store new guest group.
     */
    public function store(StoreGuestGroupRequest $request, Wedding $wedding): JsonResponse
    {
        $this->authorize('update', $wedding);

        $group = $wedding->guestGroups()->create($request->validated());

        return (new GuestGroupResource($group))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Update guest group.
     */
    public function update(UpdateGuestGroupRequest $request, Wedding $wedding, GuestGroup $group): GuestGroupResource
    {
        $this->authorize('update', $wedding);

        if ($group->wedding_id !== $wedding->id) {
            abort(404, 'Grup tamu tidak ditemukan pada proyek ini.');
        }

        $group->update($request->validated());

        return new GuestGroupResource($group);
    }

    /**
     * Delete guest group.
     */
    public function destroy(Request $request, Wedding $wedding, GuestGroup $group): JsonResponse
    {
        $this->authorize('update', $wedding);

        if ($group->wedding_id !== $wedding->id) {
            abort(404, 'Grup tamu tidak ditemukan pada proyek ini.');
        }

        $group->delete();

        return response()->json([
            'data' => [
                'deleted' => true,
                'message' => 'Grup tamu berhasil dihapus.',
            ],
        ]);
    }
}
