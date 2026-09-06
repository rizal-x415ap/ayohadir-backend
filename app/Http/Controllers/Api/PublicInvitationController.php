<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invitation;
use App\Models\PageView;
use App\Models\Wedding;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class PublicInvitationController extends Controller
{
    /**
     * Fetch public invitation data strictly from published snapshot, with optional guest personalization and privacy-friendly view tracking.
     */
    public function show(Request $request, string $slug): JsonResponse
    {
        $guestToken = $request->query('guest');
        $cacheKey = "public:wedding:{$slug}";

        // 1. Try to fetch published snapshot
        $snapshot = Cache::get($cacheKey);
        $wedding = null;

        if (!$snapshot || empty($snapshot['schema']) || empty($snapshot['schema']['sections'])) {
            $wedding = Wedding::where('slug', $slug)
                ->where('status', 'published')
                ->with(['design', 'template'])
                ->first();

            if (!$wedding) {
                return response()->json([
                    'error' => [
                        'code' => 'NOT_FOUND',
                        'message' => 'Undangan tidak ditemukan atau belum dipublikasikan.',
                    ],
                ], 404);
            }

            $schema = $wedding->design?->schema ?? $wedding->template?->schema;
            $rawSnapshot = $wedding->design?->published_schema ?? [];

            if (!empty($rawSnapshot['schema'])) {
                $snapshot = $rawSnapshot;
            } elseif (!empty($rawSnapshot['sections'])) {
                $snapshot = is_array($rawSnapshot) ? $rawSnapshot : [];
                $snapshot['schema'] = ['sections' => $rawSnapshot['sections']];
                $snapshot['design'] = ['schema' => $snapshot['schema']];
            } else {
                $snapshot = is_array($rawSnapshot) ? $rawSnapshot : [];
                $resolvedSchema = $schema ?? ['sections' => []];
                $snapshot['schema'] = $resolvedSchema;
                $snapshot['design'] = ['schema' => $resolvedSchema];
            }
                if (!isset($snapshot['wedding'])) {
                    $snapshot['wedding'] = [
                        'id' => $wedding->id,
                        'slug' => $wedding->slug,
                        'status' => 'published',
                        'brideName' => $wedding->bride_name,
                        'groomName' => $wedding->groom_name,
                        'weddingDate' => $wedding->wedding_date?->format('Y-m-d'),
                        'weddingTime' => $wedding->wedding_time,
                        'venueName' => $wedding->venue_name,
                        'venueAddress' => $wedding->venue_address,
                        'venueMapUrl' => $wedding->venue_map_url,
                        'customContent' => $wedding->custom_content ?? [],
                    ];
                }
                if ($wedding->design) {
                    $wedding->design->published_schema = $snapshot;
                    $wedding->design->save();
                }

            Cache::put($cacheKey, $snapshot, now()->addDays(7));
        }

        // Fetch wedding record if not already loaded (for ID and tracking)
        if (!$wedding) {
            $wedding = Wedding::where('slug', $slug)->first();
        }

        // Guest Personalization (Isolated & Non-leaking)
        $guestTo = $request->query('to');
        $guestData = null;
        $hasRsvp = false;

        if ($guestToken && $wedding) {
            $invitation = Invitation::where('token', $guestToken)
                ->where('wedding_id', $wedding->id)
                ->with(['guest', 'rsvp'])
                ->first();

            if ($invitation && $invitation->guest) {
                $guestData = [
                    'token' => $invitation->token,
                    'name' => $invitation->guest->name,
                    'maxAttendees' => $invitation->guest->max_attendees,
                    'isRegistered' => true,
                    'existingRsvp' => $invitation->rsvp ? [
                        'attending' => (bool) $invitation->rsvp->attending,
                        'attendeeCount' => $invitation->rsvp->attendee_count,
                        'wishes' => $invitation->rsvp->wishes,
                    ] : null,
                ];
                $hasRsvp = (bool) $invitation->rsvp;

                if (!$invitation->opened_at) {
                    $invitation->opened_at = now();
                    $invitation->open_count = 1;
                    $invitation->save();
                }
            }
        } elseif ($guestTo) {
            $guestData = [
                'token' => null,
                'name' => trim((string) $guestTo),
                'maxAttendees' => 5,
                'isRegistered' => false,
                'existingRsvp' => null,
            ];
        }

        // Fetch Approved / Real Wishes from RSVPs
        $wishes = [];
        if ($wedding) {
            $wishes = $wedding->rsvps()
                ->whereNotNull('wishes')
                ->where('wishes', '!=', '')
                ->with('guest')
                ->orderByDesc('responded_at')
                ->limit(50)
                ->get()
                ->map(function ($r) {
                    return [
                        'id' => "wish_{$r->id}",
                        'name' => $r->guest?->name ?? 'Tamu Undangan',
                        'message' => $r->wishes,
                        'timestamp' => $r->responded_at ? $r->responded_at->diffForHumans() : 'Baru saja',
                        'date' => $r->responded_at ? $r->responded_at->format('Y-m-d') : now()->format('Y-m-d'),
                        'attending' => (bool) $r->attending,
                    ];
                })
                ->values()
                ->toArray();
        }

        // Privacy-Preserving Page View Tracking (Zero Raw IP Stored)
        if ($wedding) {
            $ip = $request->ip() ?? '127.0.0.1';
            $ua = $request->userAgent() ?? '';
            $sessionHash = hash('sha256', "{$ip}-{$ua}-" . date('Y-m-d') . "-{$wedding->id}");
            $source = $guestToken ? 'guest_token' : ($guestTo ? 'direct_name' : ($request->headers->get('referer') ? 'shared' : 'direct'));

            try {
                PageView::create([
                    'wedding_id' => $wedding->id,
                    'session_hash' => $sessionHash,
                    'source' => $source,
                    'created_at' => now(),
                ]);
            } catch (\Throwable $e) {
                // Tracking failure should never crash the public view
            }
        }

        $hasSubmittedFromDevice = false;
        if ($wedding && !$guestToken) {
            $ip = $request->ip() ?? '127.0.0.1';
            $hasSubmittedFromDevice = Cache::has("public_rsvp:{$wedding->id}:" . md5($ip));
        }

        $responseData = is_array($snapshot) ? $snapshot : [];
        $responseData['guest'] = $guestData;
        $responseData['hasRsvp'] = $hasRsvp || $hasSubmittedFromDevice;
        $responseData['hasSubmittedFromDevice'] = $hasSubmittedFromDevice;
        $responseData['wishes'] = $wishes;

        return response()->json([
            'data' => $responseData,
            'meta' => [
                'timestamp' => now()->toIso8601String(),
                'source' => 'published_snapshot',
            ],
        ]);
    }
}
