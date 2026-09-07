<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TemplateResource;
use App\Models\Media;
use App\Models\PaymentTransaction;
use App\Models\Template;
use App\Models\User;
use App\Models\Wedding;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminOverviewController extends Controller
{
    /**
     * Get platform overview metrics for Admin Dashboard.
     */
    public function __invoke(Request $request): JsonResponse
    {
        if (!$request->user()?->isAdmin()) {
            abort(403, 'Akses khusus administrator.');
        }

        $totalTemplates = Template::count();
        $activeTemplates = Template::where('is_active', true)->count();
        $totalAssets = Media::where('is_system', true)->count();
        $totalWeddings = Wedding::count();
        $totalUsers = User::count();

        $totalRevenue = (int) PaymentTransaction::where('status', 'paid')->sum('amount');
        $paidTransactionsCount = PaymentTransaction::where('status', 'paid')->count();
        $pendingTransactionsCount = PaymentTransaction::where('status', 'pending')->count();
        $totalTransactionsCount = PaymentTransaction::count();

        $assetCategories = Media::where('is_system', true)
            ->selectRaw('category, count(*) as count')
            ->groupBy('category')
            ->pluck('count', 'category')
            ->toArray();

        $recentTemplates = Template::orderBy('order')
            ->orderByDesc('id')
            ->limit(6)
            ->get();

        return response()->json([
            'data' => [
                'overview' => [
                    'totalTemplates' => $totalTemplates,
                    'activeTemplates' => $activeTemplates,
                    'totalAssets' => $totalAssets,
                    'totalWeddings' => $totalWeddings,
                    'totalUsers' => $totalUsers,
                    'totalRevenue' => $totalRevenue,
                    'totalRevenueFormatted' => 'Rp ' . number_format($totalRevenue, 0, ',', '.'),
                    'paidTransactionsCount' => $paidTransactionsCount,
                    'pendingTransactionsCount' => $pendingTransactionsCount,
                    'totalTransactionsCount' => $totalTransactionsCount,
                ],
                'assetCategories' => $assetCategories,
                'recentTemplates' => TemplateResource::collection($recentTemplates),
            ],
            'meta' => [
                'timestamp' => now()->toIso8601String(),
            ],
        ]);
    }
}
