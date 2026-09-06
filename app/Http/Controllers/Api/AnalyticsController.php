<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Wedding;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    use AuthorizesRequests;

    /**
     * Get aggregate analytics summary for a wedding.
     */
    public function show(Request $request, Wedding $wedding): JsonResponse
    {
        $this->authorize('view', $wedding);

        // 1. Page View Metrics
        $totalViews = $wedding->pageViews()->count();
        $uniqueVisitors = $wedding->pageViews()->distinct('session_hash')->count('session_hash');

        // 2. Invitation & Guest Metrics
        $totalGuests = $wedding->guests()->count();
        $openedInvitations = $wedding->invitations()->where('open_count', '>', 0)->count();
        $totalOpenEvents = (int) $wedding->invitations()->sum('open_count');

        // 3. RSVP Metrics
        $confirmedRsvps = $wedding->rsvps()->where('attending', true)->count();
        $declinedRsvps = $wedding->rsvps()->where('attending', false)->count();
        $totalResponses = $confirmedRsvps + $declinedRsvps;
        $pendingRsvps = max(0, $totalGuests - $totalResponses);
        $totalAttendees = (int) $wedding->rsvps()->where('attending', true)->sum('attendee_count');
        $attendanceRate = $totalResponses > 0 ? round(($confirmedRsvps / $totalResponses) * 100, 1) : 0;

        // 4. Last 7 Days Daily Views Trend
        $startDate = Carbon::today()->subDays(6);
        $dailyViews = $wedding->pageViews()
            ->where('created_at', '>=', $startDate)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as views, COUNT(DISTINCT session_hash) as unique_views')
            ->groupBy('date')
            ->pluck('views', 'date')
            ->toArray();

        $viewsTrend = [];
        for ($i = 6; $i >= 0; $i--) {
            $d = Carbon::today()->subDays($i)->format('Y-m-d');
            $viewsTrend[] = [
                'date' => $d,
                'views' => $dailyViews[$d] ?? 0,
            ];
        }

        // 5. Recent RSVP Activity
        $recentRsvps = $wedding->rsvps()
            ->with('guest')
            ->orderByDesc('responded_at')
            ->limit(10)
            ->get()
            ->map(function ($r) {
                return [
                    'id' => $r->id,
                    'guestName' => $r->guest?->name ?? 'Tamu',
                    'attending' => (bool) $r->attending,
                    'attendeeCount' => $r->attendee_count,
                    'wishes' => $r->wishes,
                    'respondedAt' => $r->responded_at->toIso8601String(),
                ];
            });

        return response()->json([
            'data' => [
                'overview' => [
                    'totalViews' => $totalViews,
                    'uniqueVisitors' => $uniqueVisitors,
                    'totalGuests' => $totalGuests,
                    'openedInvitations' => $openedInvitations,
                    'totalOpenEvents' => $totalOpenEvents,
                    'confirmed' => $confirmedRsvps,
                    'declined' => $declinedRsvps,
                    'pending' => $pendingRsvps,
                    'totalAttendees' => $totalAttendees,
                    'attendanceRate' => $attendanceRate,
                ],
                'viewsTrend' => $viewsTrend,
                'recentRsvps' => $recentRsvps,
                'publishing' => [
                    'status' => $wedding->status,
                    'publishedAt' => $wedding->published_at?->toIso8601String(),
                    'slug' => $wedding->slug,
                    'publicUrl' => url("/{$wedding->slug}"),
                ],
            ],
            'meta' => [
                'timestamp' => now()->toIso8601String(),
            ],
        ]);
    }
}
