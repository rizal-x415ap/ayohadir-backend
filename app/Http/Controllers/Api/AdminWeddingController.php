<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Wedding;
use App\Services\PublishingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminWeddingController extends Controller
{
    public function __construct(
        protected PublishingService $publishingService
    ) {}

    /**
     * Display a listing of all weddings across all users.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Wedding::with(['user:id,name,email', 'template:id,name,thumbnail,price'])
            ->withCount(['guests', 'rsvps', 'pageViews'])
            ->latest('id');

        // Search query (bride, groom, slug, user name, user email)
        if ($request->filled('search')) {
            $search = trim($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('bride_name', 'like', "%{$search}%")
                    ->orWhere('groom_name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($uq) use ($search) {
                        $uq->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        // Status filter (draft, published, etc.)
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        // Premium license filter (1 = unlocked, 0 = locked)
        if ($request->has('is_premium_unlocked') && $request->input('is_premium_unlocked') !== '') {
            $isUnlocked = filter_var($request->input('is_premium_unlocked'), FILTER_VALIDATE_BOOLEAN);
            $query->where('is_premium_unlocked', $isUnlocked);
        }

        // Template filter
        if ($request->filled('template_id')) {
            $query->where('applied_template_id', $request->input('template_id'));
        }

        $weddings = $query->paginate($request->input('per_page', 15));

        return response()->json($weddings);
    }

    /**
     * Get platform wedding statistics.
     */
    public function statistics(): JsonResponse
    {
        $totalWeddings = Wedding::count();
        $publishedWeddings = Wedding::where('status', 'published')->count();
        $draftWeddings = Wedding::where('status', 'draft')->count();
        $unlockedWeddings = Wedding::where('is_premium_unlocked', true)->count();
        $totalGuests = \App\Models\Guest::count();
        $totalRsvps = \App\Models\Rsvp::count();
        $totalViews = \App\Models\PageView::count();

        return response()->json([
            'total_weddings' => $totalWeddings,
            'published_weddings' => $publishedWeddings,
            'draft_weddings' => $draftWeddings,
            'unlocked_weddings' => $unlockedWeddings,
            'total_guests' => $totalGuests,
            'total_rsvps' => $totalRsvps,
            'total_views' => $totalViews,
        ]);
    }

    /**
     * Display full details of the specified wedding.
     */
    public function show(Wedding $wedding): JsonResponse
    {
        $wedding->load([
            'user:id,name,email',
            'template:id,name,thumbnail,price',
            'design',
        ]);
        $wedding->loadCount(['guests', 'rsvps', 'pageViews']);

        return response()->json([
            'data' => $wedding,
        ]);
    }

    /**
     * Update the specified wedding as Administrator.
     */
    public function update(Request $request, Wedding $wedding): JsonResponse
    {
        $validated = $request->validate([
            'bride_name' => 'required|string|max:100',
            'groom_name' => 'required|string|max:100',
            'bride_parents' => 'nullable|string|max:255',
            'groom_parents' => 'nullable|string|max:255',
            'wedding_date' => 'required|date',
            'wedding_time' => 'nullable|string|max:50',
            'venue_name' => 'nullable|string|max:255',
            'venue_address' => 'nullable|string|max:500',
            'venue_map_url' => 'nullable|url|max:1000',
            'slug' => ['required', 'string', 'max:120', 'alpha_dash', Rule::unique('weddings')->ignore($wedding->id)],
            'status' => 'required|in:draft,published,archived',
            'is_premium_unlocked' => 'boolean',
            'applied_template_id' => 'nullable|exists:templates,id',
            'rsvp_enabled' => 'boolean',
            'rsvp_deadline' => 'nullable|date',
            'wishes_enabled' => 'boolean',
        ]);

        $wedding->update($validated);

        return response()->json([
            'message' => 'Data undangan berhasil diperbarui oleh Administrator.',
            'data' => $wedding->fresh(['user:id,name,email', 'template:id,name,thumbnail,price']),
        ]);
    }

    /**
     * Toggle publish status between draft and published.
     */
    public function togglePublish(Wedding $wedding): JsonResponse
    {
        if ($wedding->status === 'published') {
            $this->publishingService->unpublish($wedding);
            $newStatus = 'draft';
        } else {
            if (empty(trim($wedding->venue_name ?? ''))) {
                $wedding->venue_name = 'Tempat Acara';
                $wedding->save();
            }
            $this->publishingService->publish($wedding);
            $newStatus = 'published';
        }

        return response()->json([
            'message' => $newStatus === 'published' ? 'Undangan berhasil diterbitkan.' : 'Undangan dialihkan menjadi draft.',
            'status' => $newStatus,
            'is_published' => $newStatus === 'published',
        ]);
    }

    /**
     * Toggle premium unlock status.
     */
    public function togglePremium(Wedding $wedding): JsonResponse
    {
        $wedding->is_premium_unlocked = !$wedding->is_premium_unlocked;
        $wedding->save();

        return response()->json([
            'message' => $wedding->is_premium_unlocked ? 'Lisensi template dibuka (Unlocked).' : 'Lisensi template dikunci (Locked).',
            'is_premium_unlocked' => $wedding->is_premium_unlocked,
        ]);
    }

    /**
     * Delete the specified wedding project.
     */
    public function destroy(Wedding $wedding): JsonResponse
    {
        $wedding->delete();

        return response()->json([
            'message' => 'Proyek undangan berhasil dihapus oleh Administrator.',
        ]);
    }
}
