<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SubmitRsvpRequest;
use App\Http\Resources\RsvpResource;
use App\Mail\RsvpNotificationEmail;
use App\Models\Guest;
use App\Models\Invitation;
use App\Models\Rsvp;
use App\Models\Wedding;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class RsvpController extends Controller
{
    use AuthorizesRequests;

    /**
     * List RSVPs for a wedding (Couple/Owner dashboard).
     */
    public function index(Request $request, Wedding $wedding): JsonResponse
    {
        $this->authorize('view', $wedding);

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

        // Summary calculations
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
     * Submit RSVP (Public Guest Endpoint - Registered with token OR Public visitor without token).
     */
    public function submitPublic(SubmitRsvpRequest $request, string $slug): JsonResponse
    {
        $wedding = Wedding::where('slug', $slug)
            ->where('status', 'published')
            ->first();

        if (!$wedding) {
            abort(404, 'Undangan tidak ditemukan atau belum dipublikasikan.');
        }

        if (!$wedding->rsvp_enabled) {
            return response()->json([
                'error' => [
                    'code' => 'RSVP_DISABLED',
                    'message' => 'Konfirmasi kehadiran (RSVP) telah dinonaktifkan untuk undangan ini.',
                ],
            ], 422);
        }

        if ($wedding->rsvp_deadline && now()->toDateString() > $wedding->rsvp_deadline->toDateString()) {
            return response()->json([
                'error' => [
                    'code' => 'RSVP_DEADLINE_PASSED',
                    'message' => 'Batas waktu konfirmasi kehadiran telah berakhir.',
                ],
            ], 422);
        }

        $token = $request->input('token');
        $attending = (bool) $request->input('attending');
        $attendeeCount = $attending ? (int) $request->input('attendee_count', 1) : 0;
        $wishes = $request->input('wishes');

        // Skenario A: Tamu Terdaftar via Token
        if ($token) {
            $invitation = Invitation::where('token', $token)
                ->where('wedding_id', $wedding->id)
                ->with('guest')
                ->first();

            if (!$invitation || !$invitation->guest) {
                return response()->json([
                    'error' => [
                        'code' => 'INVALID_TOKEN',
                        'message' => 'Tautan undangan tidak valid untuk acara ini.',
                    ],
                ], 404);
            }

            $guest = $invitation->guest;
            $isGroup = (bool) $guest->is_group;

            if ($isGroup) {
                // Skenario A2: Tamu Rombongan / Grup (Multi-RSVP dibatasi per IP / Device)
                $ip = $request->ip() ?? '127.0.0.1';
                $groupCacheKey = "group_rsvp:{$wedding->id}:{$invitation->id}:" . md5($ip);

                if (Cache::has($groupCacheKey)) {
                    return response()->json([
                        'error' => [
                            'code' => 'ALREADY_SUBMITTED_FROM_DEVICE',
                            'message' => 'Konfirmasi kehadiran untuk rombongan ini telah dikirim dari perangkat/jaringan Anda.',
                        ],
                    ], 422);
                }

                $memberName = trim((string) $request->input('name'));
                if (empty($memberName)) {
                    return response()->json([
                        'error' => [
                            'code' => 'NAME_REQUIRED',
                            'message' => 'Mohon masukkan nama Anda sebagai anggota rombongan/keluarga.',
                        ],
                    ], 422);
                }

                // Cek apakah nama yang sama persis sudah pernah submit di rombongan ini
                $existingMember = Rsvp::where('wedding_id', $wedding->id)
                    ->where('invitation_id', $invitation->id)
                    ->whereRaw('LOWER(name) = ?', [mb_strtolower($memberName)])
                    ->first();

                if ($existingMember) {
                    return response()->json([
                        'error' => [
                            'code' => 'MEMBER_ALREADY_CONFIRMED',
                            'message' => "Nama \"{$memberName}\" sudah tercatat mengirimkan konfirmasi untuk rombongan ini.",
                        ],
                    ], 422);
                }

                if ($attending && $guest->max_attendees > 0 && $attendeeCount > $guest->max_attendees) {
                    return response()->json([
                        'error' => [
                            'code' => 'EXCEEDS_MAX_ATTENDEES',
                            'message' => "Jumlah tamu melebihi batas maksimal ({$guest->max_attendees} orang).",
                        ],
                    ], 422);
                }

                $hasWish = !empty(trim((string) $wishes));
                $needsApproval = $wedding->wishes_moderation_enabled && $hasWish;
                $isApproved = !$needsApproval;
                $approvalToken = $needsApproval ? Str::random(48) : null;

                // Create RSVP for group member
                $rsvp = Rsvp::create([
                    'wedding_id' => $wedding->id,
                    'invitation_id' => $invitation->id,
                    'guest_id' => $guest->id,
                    'name' => $memberName,
                    'attending' => $attending,
                    'attendee_count' => $attendeeCount,
                    'wishes' => $wishes,
                    'is_approved' => $isApproved,
                    'approval_token' => $approvalToken,
                    'responded_at' => now(),
                ]);

                // Kunci perangkat/IP ini agar tidak spam untuk rombongan ini
                Cache::put($groupCacheKey, true, now()->addDays(30));

                if (!$invitation->opened_at) {
                    $invitation->opened_at = now();
                }
                $invitation->open_count += 1;
                $invitation->save();
            } else {
                // Skenario A1: Tamu Personal Biasa (Aturan 1 Kali Konfirmasi Total)
                if ($attending && $attendeeCount > $invitation->guest->max_attendees) {
                    return response()->json([
                        'error' => [
                            'code' => 'EXCEEDS_MAX_ATTENDEES',
                            'message' => "Jumlah tamu melebihi batas maksimal ({$invitation->guest->max_attendees} orang).",
                        ],
                    ], 422);
                }

                $existingRsvp = Rsvp::where('wedding_id', $wedding->id)
                    ->where('invitation_id', $invitation->id)
                    ->first();

                if ($existingRsvp) {
                    return response()->json([
                        'error' => [
                            'code' => 'ALREADY_CONFIRMED',
                            'message' => 'Anda sudah mengirimkan konfirmasi kehadiran sebelumnya.',
                        ],
                    ], 422);
                }

                $hasWish = !empty(trim((string) $wishes));
                $needsApproval = $wedding->wishes_moderation_enabled && $hasWish;
                $isApproved = !$needsApproval;
                $approvalToken = $needsApproval ? Str::random(48) : null;

                // Create RSVP
                $rsvp = Rsvp::create([
                    'wedding_id' => $wedding->id,
                    'invitation_id' => $invitation->id,
                    'guest_id' => $guest->id,
                    'name' => $guest->name,
                    'attending' => $attending,
                    'attendee_count' => $attendeeCount,
                    'wishes' => $wishes,
                    'is_approved' => $isApproved,
                    'approval_token' => $approvalToken,
                    'responded_at' => now(),
                ]);

                if (!$invitation->opened_at) {
                    $invitation->opened_at = now();
                }
                $invitation->open_count += 1;
                $invitation->save();
            }
        } else {
            // Skenario B: Pengunjung Publik (Belum masuk daftar tamu)
            $ip = $request->ip() ?? '127.0.0.1';
            $cacheKey = "public_rsvp:{$wedding->id}:" . md5($ip);

            // Aturan 1 Kali Konfirmasi Publik: Dibatasi per perangkat/IP
            if (Cache::has($cacheKey)) {
                return response()->json([
                    'error' => [
                        'code' => 'ALREADY_SUBMITTED_FROM_DEVICE',
                        'message' => 'Konfirmasi kehadiran telah dikirim dari perangkat/jaringan ini.',
                    ],
                ], 422);
            }

            $name = trim((string) $request->input('name'));
            if (empty($name)) {
                return response()->json([
                    'error' => [
                        'code' => 'NAME_REQUIRED',
                        'message' => 'Mohon masukkan nama lengkap Anda.',
                    ],
                ], 422);
            }

            // Cek apakah tamu dengan nama ini sudah ada di daftar tamu pemilik undangan
            $matchedGuest = $wedding->guests()->where('name', $name)->first();
            if ($matchedGuest) {
                $guest = $matchedGuest;
                $invitation = $guest->invitation;
                if (!$invitation) {
                    $invitation = Invitation::create([
                        'wedding_id' => $wedding->id,
                        'guest_id' => $guest->id,
                        'token' => Invitation::generateUniqueToken(),
                        'opened_at' => now(),
                        'open_count' => 1,
                    ]);
                } else {
                    // Jika tamu sudah pernah mengirim RSVP, tolak agar tidak ganda
                    $existingRsvp = Rsvp::where('wedding_id', $wedding->id)
                        ->where('invitation_id', $invitation->id)
                        ->first();

                    if ($existingRsvp) {
                        return response()->json([
                            'error' => [
                                'code' => 'ALREADY_CONFIRMED',
                                'message' => 'Anda sudah mengirimkan konfirmasi kehadiran sebelumnya.',
                            ],
                        ], 422);
                    }
                }
            } else {
                // Buat data tamu baru di database
                $guest = Guest::create([
                    'wedding_id' => $wedding->id,
                    'name' => $name,
                    'phone' => $request->input('phone'),
                    'max_attendees' => max(1, $attendeeCount),
                    'notes' => 'RSVP Publik',
                ]);

                // Buat data undangan dengan token unik
                $invitation = Invitation::create([
                    'wedding_id' => $wedding->id,
                    'guest_id' => $guest->id,
                    'token' => Invitation::generateUniqueToken(),
                    'opened_at' => now(),
                    'open_count' => 1,
                ]);
            }

            $hasWish = !empty(trim((string) $wishes));
            $needsApproval = $wedding->wishes_moderation_enabled && $hasWish;
            $isApproved = !$needsApproval;
            $approvalToken = $needsApproval ? Str::random(48) : null;

            // Buat record RSVP
            $rsvp = Rsvp::create([
                'wedding_id' => $wedding->id,
                'invitation_id' => $invitation->id,
                'guest_id' => $guest->id,
                'name' => $name,
                'attending' => $attending,
                'attendee_count' => $attendeeCount,
                'wishes' => $wishes,
                'is_approved' => $isApproved,
                'approval_token' => $approvalToken,
                'responded_at' => now(),
            ]);

            // Tandai perangkat/IP ini telah mengirim RSVP untuk acara ini selama 30 hari
            Cache::put($cacheKey, true, now()->addDays(30));
        }

        $this->notifyRsvpEvent(
            wedding: $wedding,
            guestName: $rsvp->effective_name ?: $guest->name,
            attending: $rsvp->attending,
            attendeeCount: (int) $rsvp->attendee_count,
            wishes: $rsvp->wishes,
            type: 'rsvp',
            needsApproval: $needsApproval,
            approvalToken: $approvalToken
        );

        return response()->json([
            'success' => true,
            'data' => [
                'attending' => $rsvp->attending,
                'attendeeCount' => $rsvp->attendee_count,
                'guestName' => $guest->name,
                'token' => $invitation->token,
                'message' => 'Terima kasih atas konfirmasi kehadiran dan doa restunya.',
            ],
        ]);
    }

    /**
     * Submit Wish / Doa Restu directly from Greeting Widget.
     */
    public function submitWishPublic(Request $request, string $slug): JsonResponse
    {
        $wedding = Wedding::where('slug', $slug)
            ->where('status', 'published')
            ->first();

        if (!$wedding) {
            abort(404, 'Undangan tidak ditemukan atau belum dipublikasikan.');
        }

        $validated = $request->validate([
            'token' => ['nullable', 'string', 'exists:invitations,token'],
            'name' => ['required_without:token', 'nullable', 'string', 'min:2', 'max:150'],
            'message' => ['required', 'string', 'min:2', 'max:1000'],
        ], [
            'name.required_without' => 'Mohon masukkan nama Anda.',
            'message.required' => 'Mohon tuliskan ucapan atau doa restu.',
            'message.min' => 'Ucapan minimal terdiri dari 2 karakter.',
            'message.max' => 'Ucapan maksimal 1000 karakter.',
        ]);

        $token = $validated['token'] ?? null;
        $messageText = trim($validated['message']);

        if ($token) {
            $invitation = Invitation::where('token', $token)
                ->where('wedding_id', $wedding->id)
                ->with(['guest', 'rsvp'])
                ->first();

            if (!$invitation || !$invitation->guest) {
                return response()->json([
                    'error' => [
                        'code' => 'INVALID_TOKEN',
                        'message' => 'Tautan undangan tidak valid untuk acara ini.',
                    ],
                ], 404);
            }

            $guest = $invitation->guest;

            $needsApproval = (bool) $wedding->wishes_moderation_enabled;
            $isApproved = !$needsApproval;
            $approvalToken = $needsApproval ? Str::random(48) : null;

            // Update or create RSVP record with wishes
            $rsvp = Rsvp::updateOrCreate(
                [
                    'wedding_id' => $wedding->id,
                    'invitation_id' => $invitation->id,
                ],
                [
                    'guest_id' => $guest->id,
                    'attending' => $invitation->rsvp ? $invitation->rsvp->attending : true,
                    'attendee_count' => $invitation->rsvp ? $invitation->rsvp->attendee_count : 1,
                    'wishes' => $messageText,
                    'is_approved' => $isApproved,
                    'approval_token' => $approvalToken,
                    'responded_at' => now(),
                ]
            );
        } else {
            $name = trim((string) ($validated['name'] ?? 'Tamu Undangan'));

            $guest = Guest::create([
                'wedding_id' => $wedding->id,
                'name' => $name,
                'max_attendees' => 1,
                'notes' => 'Ucapan Publik',
            ]);

            $invitation = Invitation::create([
                'wedding_id' => $wedding->id,
                'guest_id' => $guest->id,
                'token' => Str::random(32),
                'opened_at' => now(),
                'open_count' => 1,
            ]);

            $needsApproval = (bool) $wedding->wishes_moderation_enabled;
            $isApproved = !$needsApproval;
            $approvalToken = $needsApproval ? Str::random(48) : null;

            $rsvp = Rsvp::create([
                'wedding_id' => $wedding->id,
                'invitation_id' => $invitation->id,
                'guest_id' => $guest->id,
                'attending' => true,
                'attendee_count' => 1,
                'wishes' => $messageText,
                'is_approved' => $isApproved,
                'approval_token' => $approvalToken,
                'responded_at' => now(),
            ]);
        }

        $this->notifyRsvpEvent(
            wedding: $wedding,
            guestName: $guest->name,
            attending: (bool) $rsvp->attending,
            attendeeCount: (int) $rsvp->attendee_count,
            wishes: $rsvp->wishes,
            type: 'wish',
            needsApproval: $needsApproval,
            approvalToken: $approvalToken
        );

        return response()->json([
            'success' => true,
            'data' => [
                'id' => "wish_{$rsvp->id}",
                'name' => $guest->name,
                'message' => $rsvp->wishes,
                'timestamp' => 'Baru saja',
                'date' => now()->format('Y-m-d'),
                'attending' => (bool) $rsvp->attending,
                'token' => $invitation->token,
            ],
            'message' => 'Terima kasih atas ucapan dan doa restunya.',
        ]);
    }

    /**
     * Delete/Clear an inappropriate wish message from an RSVP record (Owner moderation).
     */
    public function deleteWish(Request $request, Wedding $wedding, Rsvp $rsvp): JsonResponse
    {
        $this->authorize('update', $wedding);

        if ($rsvp->wedding_id !== $wedding->id) {
            return response()->json([
                'error' => [
                    'code' => 'NOT_FOUND',
                    'message' => 'Data RSVP tidak ditemukan pada acara ini.',
                ],
            ], 404);
        }

        $rsvp->wishes = null;
        $rsvp->save();

        return response()->json([
            'success' => true,
            'message' => 'Ucapan berhasil dihapus dari daftar.',
            'data' => new RsvpResource($rsvp->load('guest')),
        ]);
    }

    /**
     * Delete an entire RSVP record (Owner moderation).
     */
    public function destroy(Request $request, Wedding $wedding, Rsvp $rsvp): JsonResponse
    {
        $this->authorize('update', $wedding);

        if ($rsvp->wedding_id !== $wedding->id) {
            return response()->json([
                'error' => [
                    'code' => 'NOT_FOUND',
                    'message' => 'Data RSVP tidak ditemukan pada acara ini.',
                ],
            ], 404);
        }

        $rsvp->delete();

        return response()->json([
            'success' => true,
            'message' => 'Data RSVP berhasil dihapus.',
        ]);
    }

    /**
     * Toggle wish approval status from owner's guest manager dashboard.
     */
    public function toggleApproval(Request $request, Wedding $wedding, Rsvp $rsvp): JsonResponse
    {
        $this->authorize('update', $wedding);

        if ($rsvp->wedding_id !== $wedding->id) {
            return response()->json([
                'error' => [
                    'code' => 'NOT_FOUND',
                    'message' => 'Data RSVP tidak ditemukan pada acara ini.',
                ],
            ], 404);
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
     * Approve a wish publicly via 1-click magic link from email without requiring login.
     */
    public function approvePublicWish(Request $request): JsonResponse
    {
        $request->validate([
            'token' => ['required', 'string', 'max:100'],
        ], [
            'token.required' => 'Token persetujuan tidak ditemukan.',
        ]);

        $token = $request->input('token');

        $rsvp = Rsvp::where('approval_token', $token)
            ->with(['wedding', 'guest'])
            ->first();

        if (!$rsvp) {
            return response()->json([
                'error' => [
                    'code' => 'INVALID_TOKEN',
                    'message' => 'Tautan persetujuan ucapan tidak valid atau sudah kedaluwarsa.',
                ],
            ], 404);
        }

        $wedding = $rsvp->wedding;

        // If already approved, return idempotent success
        if ($rsvp->is_approved) {
            return response()->json([
                'success' => true,
                'already_approved' => true,
                'message' => 'Ucapan ini sudah disetujui sebelumnya dan sedang tampil di undangan.',
                'data' => [
                    'guestName' => $rsvp->guest?->name ?? 'Tamu Undangan',
                    'weddingTitle' => $wedding ? ($wedding->bride_name . ' & ' . $wedding->groom_name) : 'Undangan Pernikahan',
                    'weddingSlug' => $wedding?->slug,
                    'wish' => $rsvp->wishes,
                ],
            ]);
        }

        $rsvp->update([
            'is_approved' => true,
        ]);

        return response()->json([
            'success' => true,
            'already_approved' => false,
            'message' => 'Ucapan berhasil disetujui dan kini telah tampil di undangan!',
            'data' => [
                'guestName' => $rsvp->guest?->name ?? 'Tamu Undangan',
                'weddingTitle' => $wedding ? ($wedding->bride_name . ' & ' . $wedding->groom_name) : 'Undangan Pernikahan',
                'weddingSlug' => $wedding?->slug,
                'wish' => $rsvp->wishes,
            ],
        ]);
    }

    /**
     * Send email notification to wedding owner or custom recipient.
     */
    protected function notifyRsvpEvent(
        Wedding $wedding,
        string $guestName,
        ?bool $attending,
        int $attendeeCount,
        ?string $wishes,
        string $type = 'rsvp',
        bool $needsApproval = false,
        ?string $approvalToken = null
    ): void {
        try {
            if (!$wedding->rsvp_notification_enabled) {
                return;
            }

            $wedding->loadMissing('user');
            $recipient = $wedding->rsvp_notification_email ?: $wedding->user?->email;

            if (empty($recipient)) {
                return;
            }

            Mail::to($recipient)->send(
                new RsvpNotificationEmail(
                    wedding: $wedding,
                    guestName: $guestName,
                    attending: $attending,
                    attendeeCount: $attendeeCount,
                    wishes: $wishes,
                    type: $type,
                    needsApproval: $needsApproval,
                    approvalToken: $approvalToken
                )
            );
        } catch (\Throwable $e) {
            Log::warning("Failed to send RSVP email notification: " . $e->getMessage());
        }
    }
}
