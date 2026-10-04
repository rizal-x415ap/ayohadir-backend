<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\GuestGroupResource;
use App\Http\Resources\GuestResource;
use App\Http\Resources\RsvpResource;
use App\Models\Guest;
use App\Models\GuestGroup;
use App\Models\Invitation;
use App\Models\Rsvp;
use App\Models\Wedding;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PublicGuestManagerController extends Controller
{
    /**
     * Resolve wedding by guest_manager_token or abort 404.
     */
    protected function resolveWedding(string $token): Wedding
    {
        return Wedding::where('guest_manager_token', $token)->firstOrFail();
    }

    /**
     * Get wedding summary & info for the public guest manager.
     */
    public function info(string $token): JsonResponse
    {
        $wedding = $this->resolveWedding($token);

        return response()->json([
            'data' => [
                'id' => $wedding->id,
                'slug' => $wedding->slug,
                'brideName' => $wedding->bride_name,
                'groomName' => $wedding->groom_name,
                'weddingDate' => $wedding->wedding_date?->format('Y-m-d'),
                'weddingTime' => $wedding->wedding_time,
                'venueName' => $wedding->venue_name,
                'venueAddress' => $wedding->venue_address,
                'status' => $wedding->status,
                'guestManagerToken' => $wedding->guest_manager_token,
                'guest_manager_token' => $wedding->guest_manager_token,
            ],
        ]);
    }

    /**
     * List guests with search and filtering.
     */
    public function getGuests(Request $request, string $token): AnonymousResourceCollection
    {
        $wedding = $this->resolveWedding($token);

        $query = $wedding->guests()
            ->with(['group', 'invitation.rsvp']);

        if ($request->filled('search')) {
            $search = $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->filled('filter.group_id')) {
            $query->where('guest_group_id', $request->input('filter.group_id'));
        }

        if ($request->filled('filter.rsvp_status')) {
            $status = $request->input('filter.rsvp_status');
            if ($status === 'confirmed') {
                $query->whereHas('invitation.rsvp', function ($q) {
                    $q->where('attending', true);
                });
            } elseif ($status === 'declined') {
                $query->whereHas('invitation.rsvp', function ($q) {
                    $q->where('attending', false);
                });
            } elseif ($status === 'pending') {
                $query->whereDoesntHave('invitation.rsvp');
            }
        }

        $perPage = $request->integer('per_page', 50);
        $guests = $query->orderBy('name')->paginate($perPage);

        return GuestResource::collection($guests);
    }

    /**
     * Store new guest.
     */
    public function storeGuest(Request $request, string $token): JsonResponse
    {
        $wedding = $this->resolveWedding($token);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'max_attendees' => ['nullable', 'integer', 'min:1', 'max:20'],
            'is_group' => ['nullable', 'boolean'],
            'guest_group_id' => [
                'nullable',
                Rule::exists('guest_groups', 'id')->where('wedding_id', $wedding->id),
            ],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        if (empty($data['max_attendees'])) {
            $data['max_attendees'] = 5;
        }

        $guest = $wedding->guests()->create($data);

        $guest->invitation()->create([
            'wedding_id' => $wedding->id,
            'token' => Invitation::generateUniqueToken(),
        ]);

        $guest->load(['group', 'invitation.rsvp']);

        return (new GuestResource($guest))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Update guest.
     */
    public function updateGuest(Request $request, string $token, Guest $guest): GuestResource
    {
        $wedding = $this->resolveWedding($token);

        if ($guest->wedding_id !== $wedding->id) {
            abort(404, 'Tamu tidak ditemukan.');
        }

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'max_attendees' => ['nullable', 'integer', 'min:1', 'max:20'],
            'is_group' => ['nullable', 'boolean'],
            'guest_group_id' => [
                'nullable',
                Rule::exists('guest_groups', 'id')->where('wedding_id', $wedding->id),
            ],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $guest->update($data);
        $guest->load(['group', 'invitation.rsvp']);

        return new GuestResource($guest);
    }

    /**
     * Delete guest.
     */
    public function destroyGuest(string $token, Guest $guest): JsonResponse
    {
        $wedding = $this->resolveWedding($token);

        if ($guest->wedding_id !== $wedding->id) {
            abort(404, 'Tamu tidak ditemukan.');
        }

        $guest->delete();

        return response()->json([
            'data' => [
                'deleted' => true,
                'message' => 'Data tamu berhasil dihapus.',
            ],
        ]);
    }

    /**
     * Batch store multiple guests.
     */
    public function batchStoreGuests(Request $request, string $token): JsonResponse
    {
        $wedding = $this->resolveWedding($token);

        $validated = $request->validate([
            'guests' => 'required|array|min:1|max:500',
            'guests.*.name' => 'required|string|max:100',
            'guests.*.phone' => 'nullable|string|max:50',
            'guests.*.email' => 'nullable|email|max:100',
            'guests.*.max_attendees' => 'nullable|integer|min:1|max:20',
            'guests.*.guest_group_id' => [
                'nullable',
                Rule::exists('guest_groups', 'id')->where('wedding_id', $wedding->id),
            ],
            'guests.*.notes' => 'nullable|string|max:500',
        ]);

        $createdGuests = DB::transaction(function () use ($wedding, $validated) {
            $list = [];
            foreach ($validated['guests'] as $item) {
                $guest = $wedding->guests()->create([
                    'name' => trim($item['name']),
                    'phone' => !empty($item['phone']) ? trim($item['phone']) : null,
                    'email' => !empty($item['email']) ? trim($item['email']) : null,
                    'max_attendees' => !empty($item['max_attendees']) ? (int) $item['max_attendees'] : 5,
                    'guest_group_id' => $item['guest_group_id'] ?? null,
                    'notes' => $item['notes'] ?? null,
                ]);

                $guest->invitation()->create([
                    'wedding_id' => $wedding->id,
                    'token' => Invitation::generateUniqueToken(),
                ]);

                $guest->load(['group', 'invitation']);
                $list[] = $guest;
            }
            return $list;
        });

        return response()->json([
            'message' => 'Berhasil menambahkan ' . count($createdGuests) . ' tamu undangan.',
            'count' => count($createdGuests),
            'data' => GuestResource::collection($createdGuests),
        ], 201);
    }

    /**
     * Mark WhatsApp sent for guest.
     */
    public function markWhatsAppSent(string $token, Guest $guest): JsonResponse
    {
        $wedding = $this->resolveWedding($token);

        if ($guest->wedding_id !== $wedding->id) {
            abort(404, 'Tamu tidak ditemukan.');
        }

        $invitation = $guest->invitation;
        if ($invitation) {
            $invitation->whatsapp_sent_at = now();
            $invitation->save();
        }

        return response()->json([
            'message' => 'Status pengiriman WhatsApp berhasil diperbarui.',
            'whatsapp_sent_at' => $invitation?->whatsapp_sent_at?->toIso8601String(),
        ]);
    }

    /**
     * List guest groups.
     */
    public function getGroups(string $token): AnonymousResourceCollection
    {
        $wedding = $this->resolveWedding($token);

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
    public function storeGroup(Request $request, string $token): JsonResponse
    {
        $wedding = $this->resolveWedding($token);

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('guest_groups', 'name')->where('wedding_id', $wedding->id),
            ],
        ]);

        $group = $wedding->guestGroups()->create($validated);

        return (new GuestGroupResource($group))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Update guest group.
     */
    public function updateGroup(Request $request, string $token, GuestGroup $group): GuestGroupResource
    {
        $wedding = $this->resolveWedding($token);

        if ($group->wedding_id !== $wedding->id) {
            abort(404, 'Grup tamu tidak ditemukan.');
        }

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('guest_groups', 'name')->where('wedding_id', $wedding->id)->ignore($group->id),
            ],
        ]);

        $group->update($validated);

        return new GuestGroupResource($group);
    }

    /**
     * Delete guest group.
     */
    public function destroyGroup(string $token, GuestGroup $group): JsonResponse
    {
        $wedding = $this->resolveWedding($token);

        if ($group->wedding_id !== $wedding->id) {
            abort(404, 'Grup tamu tidak ditemukan.');
        }

        $group->delete();

        return response()->json([
            'data' => [
                'deleted' => true,
                'message' => 'Grup tamu berhasil dihapus.',
            ],
        ]);
    }

    /**
     * List RSVPs and summary.
     */
    public function getRsvps(Request $request, string $token): JsonResponse
    {
        $wedding = $this->resolveWedding($token);

        $query = $wedding->rsvps()->with(['guest.group', 'invitation']);

        if ($request->has('filter.attending')) {
            $query->where('attending', filter_var($request->input('filter.attending'), FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->filled('search')) {
            $search = $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhereHas('guest', function ($g) use ($search) {
                        $g->where('name', 'like', "%{$search}%");
                    });
            });
        }

        $rsvps = $query->orderByDesc('responded_at')->paginate($request->integer('per_page', 20));

        $totalGuests = $wedding->guests()->count();
        $confirmedCount = $wedding->rsvps()->where('attending', true)->count();
        $declinedCount = $wedding->rsvps()->where('attending', false)->count();
        $pendingCount = max(0, $totalGuests - ($confirmedCount + $declinedCount));
        $totalAttendees = (int) $wedding->rsvps()->where('attending', true)->sum('attendee_count');

        return response()->json([
            'data' => RsvpResource::collection($rsvps),
            'meta' => [
                'currentPage' => $rsvps->currentPage(),
                'lastPage' => $rsvps->lastPage(),
                'perPage' => $rsvps->perPage(),
                'total' => $rsvps->total(),
            ],
            'summary' => [
                'totalGuests' => $totalGuests,
                'confirmed' => $confirmedCount,
                'declined' => $declinedCount,
                'pending' => $pendingCount,
                'totalAttendees' => $totalAttendees,
            ],
        ]);
    }

    /**
     * Delete an RSVP record.
     */
    public function destroyRsvp(string $token, Rsvp $rsvp): JsonResponse
    {
        $wedding = $this->resolveWedding($token);

        if ($rsvp->wedding_id !== $wedding->id) {
            abort(404, 'Data RSVP tidak ditemukan.');
        }

        $rsvp->delete();

        return response()->json([
            'data' => [
                'deleted' => true,
                'message' => 'Data RSVP berhasil dihapus.',
            ],
        ]);
    }

    /**
     * Delete or clear wish from an RSVP.
     */
    public function destroyWish(string $token, Rsvp $rsvp): JsonResponse
    {
        $wedding = $this->resolveWedding($token);

        if ($rsvp->wedding_id !== $wedding->id) {
            abort(404, 'Data ucapan tidak ditemukan.');
        }

        $rsvp->update([
            'wishes' => null,
            'is_approved' => false,
        ]);

        return response()->json([
            'data' => [
                'deleted' => true,
                'message' => 'Ucapan berhasil dihapus dari halaman publik.',
            ],
        ]);
    }

    /**
     * Toggle wish approval status.
     */
    public function toggleWishApproval(Request $request, string $token, Rsvp $rsvp): JsonResponse
    {
        $wedding = $this->resolveWedding($token);

        if ($rsvp->wedding_id !== $wedding->id) {
            abort(404, 'Data RSVP tidak ditemukan.');
        }

        $newStatus = $request->has('is_approved')
            ? $request->boolean('is_approved')
            : !$rsvp->is_approved;

        $rsvp->update([
            'is_approved' => $newStatus,
        ]);

        return response()->json([
            'success' => true,
            'message' => $newStatus
                ? 'Ucapan berhasil disetujui dan kini tampil di undangan.'
                : 'Ucapan berhasil disembunyikan dari halaman undangan.',
            'data' => new RsvpResource($rsvp->load('guest')),
        ]);
    }

    /**
     * Get customized WhatsApp message template.
     */
    public function getWhatsAppTemplate(string $token): JsonResponse
    {
        $wedding = $this->resolveWedding($token);

        $customContent = $wedding->custom_content ?? [];
        $template = $customContent['whatsapp_template'] ?? null;

        if (empty($template)) {
            $template = "Kepada Yth.\nBapak/Ibu/Saudara/i: *{nama_tamu}*\n\nTanpa mengurangi rasa hormat, perkenankan kami mengundang Bapak/Ibu/Saudara/i untuk menghadiri acara pernikahan kami:\n\n*{nama_pengantin}*\n\nBerikut tautan undangan digital Anda:\n{link_undangan}\n\nMerupakan suatu kehormatan dan kebahagiaan bagi kami apabila Bapak/Ibu/Saudara/i berkenan hadir dan memberikan doa restu.\n\nTerima kasih.";
        }

        return response()->json([
            'template' => $template,
        ]);
    }

    /**
     * Save customized WhatsApp message template.
     */
    public function saveWhatsAppTemplate(Request $request, string $token): JsonResponse
    {
        $wedding = $this->resolveWedding($token);

        $request->validate([
            'template' => 'required|string|max:2000',
        ]);

        $customContent = $wedding->custom_content ?? [];
        $customContent['whatsapp_template'] = $request->input('template');
        $wedding->custom_content = $customContent;
        $wedding->save();

        return response()->json([
            'message' => 'Template pesan WhatsApp berhasil disimpan.',
            'template' => $customContent['whatsapp_template'],
        ]);
    }
}
