<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\UserSubscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SubscriptionController extends Controller
{
    /**
     * Get available public subscription & quota plans.
     */
    public function plans(): JsonResponse
    {
        $plans = Plan::where('is_active', true)
            ->orderBy('order')
            ->get()
            ->map(function (Plan $plan) {
                return [
                    'id' => $plan->id,
                    'name' => $plan->name,
                    'slug' => $plan->slug,
                    'price' => $plan->price,
                    'formattedPrice' => $plan->price === 0 ? 'Bayar per Tema' : 'Rp ' . number_format($plan->price, 0, ',', '.'),
                    'quotaInvitations' => $plan->quota_invitations,
                    'durationDays' => $plan->duration_days,
                    'description' => $plan->description,
                    'features' => $plan->features ?? [],
                    'badge' => $plan->badge,
                ];
            });

        return response()->json([
            'data' => $plans,
        ]);
    }

    /**
     * Get authenticated user's subscription and invitation quota status.
     */
    public function mySubscription(Request $request): JsonResponse
    {
        $user = $request->user();
        $activeSub = $user->activeSubscription()->with('plan')->first();
        $totalAvailableQuota = $user->getAvailableQuota();

        $history = $user->subscriptions()
            ->orderByDesc('id')
            ->take(10)
            ->get()
            ->map(function (UserSubscription $sub) {
                return [
                    'id' => $sub->id,
                    'orderNumber' => $sub->order_number,
                    'packageName' => $sub->package_name,
                    'amountPaid' => $sub->amount_paid,
                    'formattedAmount' => 'Rp ' . number_format($sub->amount_paid, 0, ',', '.'),
                    'totalQuota' => $sub->total_quota,
                    'usedQuota' => $sub->used_quota,
                    'remainingQuota' => $sub->remaining_quota,
                    'status' => $sub->status,
                    'activatedAt' => $sub->activated_at?->toIso8601String(),
                    'createdAt' => $sub->created_at->toIso8601String(),
                ];
            });

        return response()->json([
            'data' => [
                'hasActivePlan' => (bool) $activeSub,
                'currentPlanName' => $activeSub ? $activeSub->package_name : 'Akun Standar',
                'remainingQuota' => $totalAvailableQuota,
                'activeSubscription' => $activeSub ? [
                    'id' => $activeSub->id,
                    'packageName' => $activeSub->package_name,
                    'remainingQuota' => $activeSub->remaining_quota,
                    'totalQuota' => $activeSub->total_quota,
                    'usedQuota' => $activeSub->used_quota,
                    'activatedAt' => $activeSub->activated_at?->toIso8601String(),
                ] : null,
                'history' => $history,
            ],
        ]);
    }

    /**
     * Subscribe / purchase a package (instant activation for user flow).
     */
    public function subscribe(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'plan_id' => 'required|exists:plans,id',
            'payment_method' => 'nullable|string|max:50',
        ]);

        $plan = Plan::findOrFail($validated['plan_id']);
        $user = $request->user();

        $orderNumber = 'ORD-' . date('Ymd') . '-' . strtoupper(Str::random(6));

        $subscription = UserSubscription::create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'order_number' => $orderNumber,
            'package_name' => $plan->name,
            'amount_paid' => $plan->price,
            'total_quota' => $plan->quota_invitations,
            'used_quota' => 0,
            'remaining_quota' => $plan->quota_invitations,
            'status' => 'active',
            'payment_method' => $validated['payment_method'] ?? 'instant_qris',
            'activated_at' => now(),
            'expires_at' => $plan->duration_days ? now()->addDays($plan->duration_days) : null,
        ]);

        return response()->json([
            'data' => [
                'success' => true,
                'message' => "Paket '{$plan->name}' berhasil diaktifkan! Anda mendapatkan {$plan->quota_invitations} kuota undangan.",
                'subscription' => [
                    'id' => $subscription->id,
                    'orderNumber' => $subscription->order_number,
                    'packageName' => $subscription->package_name,
                    'remainingQuota' => $subscription->remaining_quota,
                    'totalQuota' => $subscription->total_quota,
                ],
                'newAvailableQuota' => $user->getAvailableQuota(),
            ],
        ], 201);
    }

    /**
     * Pay single template fee directly to unlock a specific wedding project.
     */
    public function unlockSingleWedding(Request $request, \App\Models\Wedding $wedding): JsonResponse
    {
        $this->authorize('update', $wedding);

        $templateId = $wedding->applied_template_id ?: $wedding->design?->template_id;
        $template = $templateId ? \App\Models\Template::find($templateId) : null;
        $user = $request->user();

        $price = $template ? (int) $template->price : 0;
        $orderNumber = 'ORD-TPL-' . date('Ymd') . '-' . strtoupper(Str::random(6));

        UserSubscription::create([
            'user_id' => $user->id,
            'plan_id' => null,
            'order_number' => $orderNumber,
            'package_name' => 'Tema: ' . ($template?->name ?? 'Single Theme'),
            'amount_paid' => $price,
            'total_quota' => 1,
            'used_quota' => 1,
            'remaining_quota' => 0,
            'status' => 'depleted',
            'payment_method' => $request->input('payment_method', 'instant_qris'),
            'activated_at' => now(),
        ]);

        $wedding->is_premium_unlocked = true;
        $wedding->save();

        return response()->json([
            'data' => [
                'success' => true,
                'message' => "Tema '{$template?->name}' berhasil dibayar dan undangan siap diterbitkan!",
                'isUnlocked' => true,
            ],
        ]);
    }
}
