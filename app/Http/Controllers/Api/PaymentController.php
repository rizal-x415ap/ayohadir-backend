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
            // Free template, no payment required
            return response()->json([
                'success' => true,
                'is_free' => true,
                'message' => 'Template ini gratis, tidak memerlukan pembayaran.',
            ]);
        }

        if ($wedding->isTemplateUnlocked($template, $user)) {
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
            // Record transaction for 0 amount if coupon was used
            if (!empty($pricing['coupon']['id'])) {
                $coupon = Coupon::find($pricing['coupon']['id']);
                if ($coupon) {
                    $coupon->increment('used_count');
                }
            }

            $tx = PaymentTransaction::create([
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

            $wedding->unlockTemplate($template, $tx->id);

            return response()->json([
                'success' => true,
                'is_free' => true,
                'message' => 'Diskon 100%! Template berhasil dibuka tanpa biaya.',
                'pricing' => $pricing,
            ]);
        }

        // Check if user already has an active pending transaction for this wedding
        $existingPending = PaymentTransaction::where('wedding_id', $wedding->id)
            ->where('user_id', $user->id)
            ->where('status', 'pending')
            ->latest()
            ->first();

        if ($existingPending) {
            $isOverdue = $existingPending->created_at < now()->subHours(24);
            $forceNew = $request->boolean('force_new');

            // Quick live check with Duitku status API if configured
            $duitkuStatus = $this->duitkuService->checkTransactionStatus($existingPending->merchant_order_id);
            $statusCode = $duitkuStatus['statusCode'] ?? null;

            if ($statusCode === '00') {
                // Payment was already made!
                $existingPending->update([
                    'status' => 'paid',
                    'paid_at' => now(),
                    'raw_callback' => $duitkuStatus,
                ]);
                $wedding->unlockTemplate($existingPending->template_id ?: $template, $existingPending->id);

                try {
                    $this->publishingService->publish($wedding);
                } catch (\Exception $pubEx) {
                    Log::warning('Auto-publish after check warning: ' . $pubEx->getMessage());
                }

                return response()->json([
                    'success' => true,
                    'is_free' => false,
                    'already_unlocked' => true,
                    'message' => 'Tagihan sebelumnya telah lunas terbayar.',
                ]);
            } elseif ($statusCode === '02' || $isOverdue) {
                // Expired / Canceled in Duitku
                $existingPending->update(['status' => 'expired', 'raw_callback' => $duitkuStatus]);
            } elseif (!$forceNew && $existingPending->amount === $pricing['final_amount'] && !empty($existingPending->duitku_reference)) {
                // REUSE EXISTING PENDING INVOICE without creating duplicate
                return response()->json([
                    'success' => true,
                    'reused' => true,
                    'reference' => $existingPending->duitku_reference,
                    'payment_url' => $existingPending->payment_url,
                    'merchant_order_id' => $existingPending->merchant_order_id,
                    'duitku_js_url' => $this->duitkuService->getJsUrl(),
                    'pricing' => $pricing,
                    'message' => 'Melanjutkan tagihan pembayaran yang masih aktif.',
                ]);
            } else {
                // User explicitly requested a new invoice or coupon changed
                $existingPending->update(['status' => 'cancelled']);
            }
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
                    if ($transaction->template_id) {
                        $wedding->unlockTemplate($transaction->template_id, $transaction->id);
                    } else {
                        $wedding->is_premium_unlocked = true;
                        $wedding->save();
                    }

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
        // Auto-expire pending transactions older than 24 hours
        PaymentTransaction::where('user_id', $request->user()->id)
            ->where('status', 'pending')
            ->where('created_at', '<', now()->subHours(24))
            ->update(['status' => 'expired']);

        $transactions = PaymentTransaction::with([
            'wedding:id,slug,bride_name,groom_name',
            'template:id,name,thumbnail,category,tier'
        ])
            ->where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 15));

        return response()->json($transactions);
    }

    /**
     * Get active pending payment for a wedding.
     */
    public function pendingForWedding(Request $request, Wedding $wedding): JsonResponse
    {
        $this->authorize('update', $wedding);

        $pending = PaymentTransaction::where('wedding_id', $wedding->id)
            ->where('user_id', $request->user()->id)
            ->where('status', 'pending')
            ->latest()
            ->first();

        if ($pending && $pending->created_at < now()->subHours(24)) {
            $pending->update(['status' => 'expired']);
            $pending = null;
        }

        return response()->json([
            'has_pending' => !empty($pending),
            'transaction' => $pending,
            'duitku_js_url' => $this->duitkuService->getJsUrl(),
        ]);
    }

    /**
     * Resume pending payment transaction (retrieve reference and payment URL).
     */
    public function resume(Request $request, string $merchantOrderId): JsonResponse
    {
        $transaction = PaymentTransaction::where('merchant_order_id', $merchantOrderId)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        if ($transaction->status !== 'pending') {
            return response()->json([
                'success' => false,
                'status' => $transaction->status,
                'message' => 'Transaksi ini sudah ' . ($transaction->status === 'paid' ? 'dibayar' : 'kedaluwarsa/dibatalkan') . '.',
            ], 400);
        }

        if ($transaction->created_at < now()->subHours(24)) {
            $transaction->update(['status' => 'expired']);
            return response()->json([
                'success' => false,
                'status' => 'expired',
                'message' => 'Tagihan ini telah kedaluwarsa (melebihi 24 jam). Silakan buat transaksi baru.',
            ], 400);
        }

        return response()->json([
            'success' => true,
            'reference' => $transaction->duitku_reference,
            'payment_url' => $transaction->payment_url,
            'merchant_order_id' => $transaction->merchant_order_id,
            'duitku_js_url' => $this->duitkuService->getJsUrl(),
            'amount' => $transaction->amount,
        ]);
    }

    /**
     * Sync transaction status on-demand directly with Duitku Inquiry API.
     */
    public function syncStatus(Request $request, string $merchantOrderId): JsonResponse
    {
        $transaction = PaymentTransaction::where('merchant_order_id', $merchantOrderId)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        if ($transaction->status === 'paid') {
            return response()->json([
                'status' => 'paid',
                'is_paid' => true,
                'message' => 'Transaksi sudah lunas.',
                'transaction' => $transaction,
            ]);
        }

        $duitkuStatus = $this->duitkuService->checkTransactionStatus($merchantOrderId);

        if ($duitkuStatus) {
            $statusCode = $duitkuStatus['statusCode'] ?? null;
            if ($statusCode === '00') {
                DB::transaction(function () use ($transaction, $duitkuStatus) {
                    $transaction->update([
                        'status' => 'paid',
                        'paid_at' => now(),
                        'raw_callback' => $duitkuStatus,
                    ]);

                    $wedding = $transaction->wedding;
                    if ($wedding) {
                        if ($transaction->template_id) {
                            $wedding->unlockTemplate($transaction->template_id, $transaction->id);
                        } else {
                            $wedding->is_premium_unlocked = true;
                            $wedding->save();
                        }

                        try {
                            $this->publishingService->publish($wedding);
                        } catch (\Exception $e) {
                            Log::warning('Auto-publish sync error: ' . $e->getMessage());
                        }
                    }

                    if ($transaction->coupon_id) {
                        Coupon::where('id', $transaction->coupon_id)->increment('used_count');
                    }
                });
            } elseif ($statusCode === '02') {
                $transaction->update([
                    'status' => 'expired',
                    'raw_callback' => $duitkuStatus,
                ]);
            }
        } else {
            if ($transaction->created_at < now()->subHours(24)) {
                $transaction->update(['status' => 'expired']);
            }
        }

        $transaction->refresh();

        return response()->json([
            'status' => $transaction->status,
            'is_paid' => $transaction->status === 'paid',
            'is_premium_unlocked' => (bool) $transaction->wedding?->is_premium_unlocked,
            'transaction' => $transaction,
            'message' => match ($transaction->status) {
                'paid' => 'Pembayaran berhasil dikonfirmasi! Undangan Anda telah dibuka.',
                'expired' => 'Tagihan telah kedaluwarsa.',
                'cancelled' => 'Tagihan telah dibatalkan.',
                default => 'Status pembayaran masih menunggu (pending). Silakan selesaikan pembayaran.',
            },
        ]);
    }

    /**
     * User can manually cancel a pending transaction.
     */
    public function cancelTransaction(Request $request, string $merchantOrderId): JsonResponse
    {
        $transaction = PaymentTransaction::where('merchant_order_id', $merchantOrderId)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        if ($transaction->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Hanya transaksi berstatus pending yang dapat dibatalkan.',
            ], 400);
        }

        $transaction->update([
            'status' => 'cancelled',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Tagihan pembayaran berhasil dibatalkan.',
            'transaction' => $transaction,
        ]);
    }
}
