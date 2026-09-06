<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreGuestRequest;
use App\Http\Requests\UpdateGuestRequest;
use App\Http\Resources\GuestResource;
use App\Models\Guest;
use App\Models\Invitation;
use App\Models\Wedding;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class GuestController extends Controller
{
    use AuthorizesRequests;

    /**
     * List guests with search, group filter, and RSVP status filter.
     */
    public function index(Request $request, Wedding $wedding): AnonymousResourceCollection
    {
        $this->authorize('view', $wedding);

        $query = $wedding->guests()
            ->with(['group', 'invitation.rsvp']);

        // Search by name, phone, email
        if ($request->filled('search')) {
            $search = $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Filter by guest_group_id
        if ($request->filled('filter.group_id')) {
            $query->where('guest_group_id', $request->input('filter.group_id'));
        }

        // Filter by RSVP status (confirmed | declined | pending)
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

        $guests = $query->orderBy('name')->paginate($request->integer('per_page', 20));

        return GuestResource::collection($guests);
    }

    /**
     * Store new guest and auto-generate invitation token.
     */
    public function store(StoreGuestRequest $request, Wedding $wedding): JsonResponse
    {
        $this->authorize('update', $wedding);

        $data = $request->validated();
        if (empty($data['max_attendees'])) {
            $data['max_attendees'] = 5;
        }

        $guest = $wedding->guests()->create($data);

        // Generate invitation token
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
     * Display a guest.
     */
    public function show(Request $request, Wedding $wedding, Guest $guest): GuestResource
    {
        $this->authorize('view', $wedding);

        if ($guest->wedding_id !== $wedding->id) {
            abort(404, 'Tamu tidak ditemukan pada proyek ini.');
        }

        $guest->load(['group', 'invitation.rsvp']);

        return new GuestResource($guest);
    }

    /**
     * Update guest.
     */
    public function update(UpdateGuestRequest $request, Wedding $wedding, Guest $guest): GuestResource
    {
        $this->authorize('update', $wedding);

        if ($guest->wedding_id !== $wedding->id) {
            abort(404, 'Tamu tidak ditemukan pada proyek ini.');
        }

        $guest->update($request->validated());
        $guest->load(['group', 'invitation.rsvp']);

        return new GuestResource($guest);
    }

    /**
     * Delete guest (Soft delete).
     */
    public function destroy(Request $request, Wedding $wedding, Guest $guest): JsonResponse
    {
        $this->authorize('update', $wedding);

        if ($guest->wedding_id !== $wedding->id) {
            abort(404, 'Tamu tidak ditemukan pada proyek ini.');
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
     * Generate invitation tokens for guests who don't have one yet.
     */
    public function generateTokens(Request $request, Wedding $wedding): JsonResponse
    {
        $this->authorize('update', $wedding);

        $guestsWithoutToken = $wedding->guests()
            ->doesntHave('invitation')
            ->get();

        $generatedCount = 0;
        foreach ($guestsWithoutToken as $g) {
            $g->invitation()->create([
                'wedding_id' => $wedding->id,
                'token' => Invitation::generateUniqueToken(),
            ]);
            $generatedCount++;
        }

        // Also refresh any existing invitations that have legacy long tokens (> 4 chars)
        $invitationsWithLongToken = Invitation::where('wedding_id', $wedding->id)
            ->whereRaw('LENGTH(token) > 4')
            ->get();
        foreach ($invitationsWithLongToken as $inv) {
            $inv->token = Invitation::generateUniqueToken();
            $inv->save();
            $generatedCount++;
        }

        return response()->json([
            'data' => [
                'generated' => $generatedCount,
                'message' => "Berhasil membuat/memperbarui {$generatedCount} tautan undangan.",
            ],
        ]);
    }

    /**
     * Store multiple guests at once (bulk import).
     */
    public function batchStore(Request $request, Wedding $wedding): JsonResponse
    {
        $this->authorize('update', $wedding);

        $validated = $request->validate([
            'guests' => 'required|array|min:1|max:500',
            'guests.*.name' => 'required|string|max:100',
            'guests.*.phone' => 'nullable|string|max:50',
            'guests.*.email' => 'nullable|email|max:100',
            'guests.*.max_attendees' => 'nullable|integer|min:1|max:20',
            'guests.*.guest_group_id' => 'nullable|exists:guest_groups,id',
            'guests.*.notes' => 'nullable|string|max:500',
        ]);

        $createdGuests = \Illuminate\Support\Facades\DB::transaction(function () use ($wedding, $validated) {
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

                // Create invitation token automatically
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
     * Mark guest invitation as sent via WhatsApp.
     */
    public function markWhatsAppSent(Request $request, Wedding $wedding, Guest $guest): JsonResponse
    {
        $this->authorize('update', $wedding);

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
     * Get customized WhatsApp message template for the wedding.
     */
    public function getWhatsAppTemplate(Wedding $wedding): JsonResponse
    {
        $this->authorize('view', $wedding);

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
    public function saveWhatsAppTemplate(Request $request, Wedding $wedding): JsonResponse
    {
        $this->authorize('update', $wedding);

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
