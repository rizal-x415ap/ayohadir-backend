<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PaymentTransaction;
use App\Models\Template;
use App\Models\Wedding;
use App\Services\PricingService;
use App\Services\PublishingService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublishingController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected PublishingService $publishingService,
        protected PricingService $pricingService
    ) {}

    /**
     * Pre-publish validation check with payment requirement status and pricing breakdown.
     */
    public function validateWedding(Request $request, Wedding $wedding): JsonResponse
    {
        $this->authorize('update', $wedding);

        $errors = $this->publishingService->validateForPublishing($wedding);

        $templateId = $wedding->applied_template_id ?: $wedding->design?->template_id;
        $template = $templateId ? Template::find($templateId) : null;

        $user = $request->user();
        $isPaidTemplate = $template && (int) $template->price > 0;
        $isUnlocked = $wedding->isTemplateUnlocked($template, $user);
        $requiresPayment = $isPaidTemplate && !$isUnlocked;

        $couponCode = $request->query('coupon');
        $pricing = $this->pricingService->calculate($template ? (int) $template->price : 0, $couponCode);

        // Check for active pending transaction for this wedding
        $pendingPayment = PaymentTransaction::where('wedding_id', $wedding->id)
            ->where('user_id', $request->user()->id)
            ->where('status', 'pending')
            ->latest()
            ->first();

        if ($pendingPayment && $pendingPayment->created_at < now()->subHours(24)) {
            $pendingPayment->update(['status' => 'expired']);
            $pendingPayment = null;
        }

        return response()->json([
            'data' => [
                'canPublish' => empty($errors),
                'errors' => $errors,
                'paymentInfo' => [
                    'requiresPayment' => $requiresPayment,
                    'isUnlocked' => $isUnlocked,
                    'pendingPayment' => $pendingPayment ? [
                        'merchant_order_id' => $pendingPayment->merchant_order_id,
                        'amount' => $pendingPayment->amount,
                        'reference' => $pendingPayment->duitku_reference,
                        'payment_url' => $pendingPayment->payment_url,
                        'created_at' => $pendingPayment->created_at ? $pendingPayment->created_at->toIso8601String() : null,
                    ] : null,
                    'templateId' => $template?->id,
                    'templateName' => $template?->name ?? 'Template Pilihan',
                    'templatePrice' => $template ? (int) $template->price : 0,
                    'formattedPrice' => $template && (int) $template->price > 0 ? 'Rp ' . number_format($template->price, 0, ',', '.') : 'Gratis',
                    'pricing' => $pricing,
                ],
            ],
            'meta' => [
                'timestamp' => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * Publish current wedding draft to immutable published snapshot.
     */
    public function publish(Request $request, Wedding $wedding): JsonResponse
    {
        $this->authorize('update', $wedding);

        $user = $request->user();
        $templateId = $wedding->applied_template_id ?: $wedding->design?->template_id;
        $template = $templateId ? Template::find($templateId) : null;

        $isPaidTemplate = $template && (int) $template->price > 0;
        $isUnlocked = $wedding->isTemplateUnlocked($template, $user);

        if ($isPaidTemplate && !$isUnlocked) {
            return response()->json([
                'error' => [
                    'code' => 'PAYMENT_REQUIRED',
                    'message' => "Undangan ini menggunakan template berbayar ('{$template->name}'). Silakan lakukan pembayaran terlebih dahulu sebelum menerbitkan.",
                    'template' => [
                        'id' => $template->id,
                        'name' => $template->name,
                        'price' => $template->price,
                        'formattedPrice' => 'Rp ' . number_format($template->price, 0, ',', '.'),
                    ],
                ],
            ], 402);
        }

        $result = $this->publishingService->publish($wedding);

        return response()->json([
            'data' => $result,
            'meta' => [
                'timestamp' => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * Unpublish wedding project.
     */
    public function unpublish(Wedding $wedding): JsonResponse
    {
        $this->authorize('update', $wedding);

        $result = $this->publishingService->unpublish($wedding);

        return response()->json([
            'data' => $result,
            'meta' => [
                'timestamp' => now()->toIso8601String(),
            ],
        ]);
    }
}
