<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Models\PaymentTransaction;
use App\Models\Template;
use App\Models\Wedding;
use App\Services\DuitkuService;
use App\Services\PricingService;
use App\Services\PublishingService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PaymentController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected DuitkuService $duitkuService,
        protected PricingService $pricingService,
        protected PublishingService $publishingService
    ) {}

    /**
     * Initiate checkout for a wedding project's template.
     */
    public function checkout(Request $request, Wedding $wedding): JsonResponse
    {
        $this->authorize('update', $wedding);

        $request->validate([
            'coupon_code' => 'nullable|string|max:50',
            'phone_number' => 'nullable|string|max:20',
        ]);

        $user = $request->user();
        $templateId = $wedding->applied_template_id ?: $wedding->design?->template_id;
        $template = $templateId ? Template::find($templateId) : null;

        if (!$template || (int) $template->price <= 0) {
            // Free template, automatically unlock
            $wedding->is_premium_unlocked = true;
            $wedding->save();

            return response()->json([
                'success' => true,
                'is_free' => true,
                'message' => 'Template ini gratis, tidak memerlukan pembayaran.',
            ]);
        }

        if ($wedding->is_premium_unlocked) {
            return response()->json([
                'success' => true,
                'is_free' => false,
                'already_unlocked' => true,
                'message' => 'Template sudah terbuka untuk undangan ini.',
            ]);
        }

        $couponCode = $request->input('coupon_code');
        $pricing = $this->pricingService->calculate((int) $template->price, $couponCode);

        // If after stackable discounts the price is Rp 0
        if ($pricing['final_amount'] <= 0) {
            $wedding->is_premium_unlocked = true;
            $wedding->save();

            // Record transaction for 0 amount if coupon was used
            if (!empty($pricing['coupon']['id'])) {
                $coupon = Coupon::find($pricing['coupon']['id']);
                if ($coupon) {
                    $coupon->increment('used_count');
                }
            }

            PaymentTransaction::create([
                'merchant_order_id' => 'FREE-' . strtoupper(Str::random(10)),
                'duitku_reference' => null,
                'user_id' => $user->id,
                'wedding_id' => $wedding->id,
                'template_id' => $template->id,
                'base_price' => $pricing['base_price'],
                'global_discount_amount' => $pricing['global_discount']['amount'],
                'coupon_id' => $pricing['coupon']['id'] ?? null,
                'coupon_code' => $pricing['coupon']['code'] ?? null,
                'coupon_discount_amount' => $pricing['coupon']['amount'] ?? 0,
                'amount' => 0,
                'payment_method' => 'DISCOUNT_100',
                'status' => 'paid',
                'paid_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'is_free' => true,
                'message' => 'Diskon 100%! Template berhasil dibuka tanpa biaya.',
                'pricing' => $pricing,
            ]);
        }

        // Generate unique merchant order ID
        $merchantOrderId = 'AYO-' . date('YmdHis') . '-' . strtoupper(Str::random(5));

        // Create initial pending transaction
        $transaction = PaymentTransaction::create([
            'merchant_order_id' => $merchantOrderId,
            'user_id' => $user->id,
            'wedding_id' => $wedding->id,
            'template_id' => $template->id,
            'base_price' => $pricing['base_price'],
            'global_discount_amount' => $pricing['global_discount']['amount'],
            'coupon_id' => $pricing['coupon']['id'] ?? null,
            'coupon_code' => $pricing['coupon']['code'] ?? null,
            'coupon_discount_amount' => $pricing['coupon']['amount'] ?? 0,
            'amount' => $pricing['final_amount'],
            'status' => 'pending',
        ]);

        try {
            $duitkuResponse = $this->duitkuService->createInvoice([
                'amount' => $pricing['final_amount'],
                'merchantOrderId' => $merchantOrderId,
                'productDetails' => 'Undangan: ' . $wedding->bride_name . ' & ' . $wedding->groom_name . ' (' . $template->name . ')',
                'email' => $user->email,
                'customerVaName' => $user->name,
                'phoneNumber' => $request->input('phone_number', ''),
                'returnUrl' => url('/app/studio/' . $wedding->id . '?paid=1&order_id=' . $merchantOrderId),
                'callbackUrl' => route('api.payment.duitku.callback'),
            ]);

            $transaction->update([
                'duitku_reference' => $duitkuResponse['reference'] ?? null,
                'payment_url' => $duitkuResponse['paymentUrl'] ?? null,
            ]);

            return response()->json([
                'success' => true,
                'reference' => $duitkuResponse['reference'] ?? null,
                'payment_url' => $duitkuResponse['paymentUrl'] ?? null,
                'merchant_order_id' => $merchantOrderId,
                'duitku_js_url' => $this->duitkuService->getJsUrl(),
                'pricing' => $pricing,
            ]);
        } catch (\Exception $e) {
            $transaction->update(['status' => 'failed']);
            Log::error('Duitku checkout error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Gagal menghubungkan ke gateway pembayaran: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Check transaction status for a wedding.
     */
    public function checkStatus(Request $request, Wedding $wedding, string $merchantOrderId): JsonResponse
    {
        $this->authorize('update', $wedding);

        $transaction = PaymentTransaction::where('merchant_order_id', $merchantOrderId)
            ->where('wedding_id', $wedding->id)
            ->firstOrFail();

        return response()->json([
            'status' => $transaction->status,
            'is_paid' => $transaction->status === 'paid',
            'is_premium_unlocked' => (bool) $wedding->fresh()->is_premium_unlocked,
            'transaction' => $transaction,
        ]);
    }

    /**
     * Duitku callback / webhook receiver.
     */
    public function callback(Request $request): Response
    {
        Log::info('Duitku callback received', $request->all());

        $merchantOrderId = $request->input('merchantOrderId');
        $amount = $request->input('amount');
        $signature = $request->input('signature');
        $resultCode = $request->input('resultCode'); // '00' = Success
        $reference = $request->input('reference');
        $paymentCode = $request->input('paymentCode');

        if (empty($merchantOrderId) || empty($signature)) {
            Log::warning('Duitku callback missing parameters');
            return response('Bad Request: Missing parameters', 400);
        }

        if (!$this->duitkuService->verifyCallbackSignature($merchantOrderId, $amount, $signature)) {
            Log::error('Duitku callback signature mismatch', [
                'merchantOrderId' => $merchantOrderId,
                'amount' => $amount,
                'received_signature' => $signature,
            ]);
            return response('Bad Signature', 400);
        }

        $transaction = PaymentTransaction::where('merchant_order_id', $merchantOrderId)->first();

        if (!$transaction) {
            Log::error('Duitku callback transaction not found: ' . $merchantOrderId);
            return response('Transaction not found', 404);
        }

        // Idempotency: if already paid, return 200 OK directly
        if ($transaction->status === 'paid') {
            return response('OK', 200);
        }

        if ($resultCode === '00') {
            DB::transaction(function () use ($transaction, $reference, $paymentCode, $request) {
                $transaction->update([
                    'status' => 'paid',
                    'duitku_reference' => $reference ?: $transaction->duitku_reference,
                    'payment_method' => $paymentCode,
                    'paid_at' => now(),
                    'raw_callback' => $request->all(),
                ]);

                // Unlock wedding template
                $wedding = $transaction->wedding;
                if ($wedding) {
                    $wedding->is_premium_unlocked = true;
                    $wedding->save();

                    // Auto-publish if draft was waiting
                    try {
                        $this->publishingService->publish($wedding);
                    } catch (\Exception $pubEx) {
                        Log::warning('Auto-publish after payment warning: ' . $pubEx->getMessage());
                    }
                }

                // Increment coupon used count if coupon was used
                if ($transaction->coupon_id) {
                    Coupon::where('id', $transaction->coupon_id)->increment('used_count');
                }
            });

            Log::info("Duitku payment SUCCESS for order {$merchantOrderId}");
        } else {
            $transaction->update([
                'status' => 'failed',
                'raw_callback' => $request->all(),
            ]);
            Log::info("Duitku payment FAILED for order {$merchantOrderId}, code: {$resultCode}");
        }

        return response('OK', 200);
    }

    /**
     * Get paginated transaction history for the authenticated user.
     */
    public function userTransactions(Request $request): JsonResponse
    {
        $transactions = PaymentTransaction::with([
            'wedding:id,slug,bride_name,groom_name',
            'template:id,name,thumbnail,category,tier'
        ])
            ->where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 15));

        return response()->json($transactions);
    }
}
