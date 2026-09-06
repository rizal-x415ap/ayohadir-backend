<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\Coupon;
use App\Models\PaymentTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminFinanceController extends Controller
{
    /**
     * Get payment gateway and global discount settings.
     */
    public function getSettings(): JsonResponse
    {
        $duitkuMerchantCode = AppSetting::get('duitku_merchant_code', env('DUITKU_MERCHANT_CODE', ''));
        $duitkuApiKey = AppSetting::get('duitku_api_key', env('DUITKU_API_KEY', ''));
        $duitkuEnv = AppSetting::get('duitku_environment', env('DUITKU_ENV', 'sandbox'));

        $globalDiscountEnabled = filter_var(AppSetting::get('discount_global_enabled', false), FILTER_VALIDATE_BOOLEAN);
        $globalDiscountType = AppSetting::get('discount_global_type', 'percentage');
        $globalDiscountValue = (int) AppSetting::get('discount_global_value', 0);
        $globalDiscountTitle = AppSetting::get('discount_global_title', 'Promo Peluncuran');

        $whatsappNumber = AppSetting::get('admin_whatsapp_number', env('ADMIN_WHATSAPP_NUMBER', '081234567890'));
        $whatsappMessage = AppSetting::get('admin_whatsapp_message', "Halo Admin Ayo Hadir, saya tertarik dibuatkan undangan pernikahan dengan tema *{template_name}*. Mohon info langkah selanjutnya. Terima kasih!");

        return response()->json([
            'contact' => [
                'whatsapp_number' => $whatsappNumber,
                'whatsapp_message' => $whatsappMessage,
            ],
            'duitku' => [
                'merchant_code' => $duitkuMerchantCode,
                'api_key' => $duitkuApiKey,
                'environment' => $duitkuEnv,
                'callback_url' => route('api.payment.duitku.callback'),
                'return_url' => url('/app/dashboard'),
            ],
            'global_discount' => [
                'enabled' => $globalDiscountEnabled,
                'type' => $globalDiscountType,
                'value' => $globalDiscountValue,
                'title' => $globalDiscountTitle,
            ],
        ]);
    }

    /**
     * Update payment gateway and global discount settings.
     */
    public function updateSettings(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'duitku.merchant_code' => 'nullable|string|max:100',
            'duitku.api_key' => 'nullable|string|max:255',
            'duitku.environment' => 'nullable|in:sandbox,production',
            'global_discount.enabled' => 'nullable|boolean',
            'global_discount.type' => 'nullable|in:percentage,fixed',
            'global_discount.value' => 'nullable|integer|min:0',
            'global_discount.title' => 'nullable|string|max:150',
            'contact.whatsapp_number' => 'nullable|string|max:30',
            'contact.whatsapp_message' => 'nullable|string|max:1000',
        ]);

        if (isset($validated['duitku']['merchant_code'])) {
            AppSetting::set('duitku_merchant_code', trim($validated['duitku']['merchant_code'] ?? ''), 'payment');
        }
        if (isset($validated['duitku']['api_key'])) {
            AppSetting::set('duitku_api_key', trim($validated['duitku']['api_key'] ?? ''), 'payment');
        }
        AppSetting::set('duitku_environment', $validated['duitku']['environment'], 'payment');

        $discountValue = isset($validated['global_discount']['value']) ? (int) $validated['global_discount']['value'] : 0;
        $discountTitle = !empty($validated['global_discount']['title']) ? trim($validated['global_discount']['title']) : 'Promo Diskon';

        AppSetting::set('discount_global_enabled', $validated['global_discount']['enabled'] ? '1' : '0', 'discount');
        AppSetting::set('discount_global_type', $validated['global_discount']['type'], 'discount');
        AppSetting::set('discount_global_value', (string) $discountValue, 'discount');
        AppSetting::set('discount_global_title', $discountTitle, 'discount');

        return response()->json([
            'message' => 'Pengaturan pembayaran dan diskon berhasil disimpan.',
        ]);
    }

    /**
     * Get transaction history with pagination and search.
     */
    public function getTransactions(Request $request): JsonResponse
    {
        $query = PaymentTransaction::with(['user:id,name,email', 'wedding:id,slug,bride_name,groom_name', 'template:id,name,thumbnail'])
            ->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('merchant_order_id', 'like', "%{$search}%")
                    ->orWhere('duitku_reference', 'like', "%{$search}%")
                    ->orWhere('coupon_code', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($uq) use ($search) {
                        $uq->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        $transactions = $query->paginate($request->input('per_page', 15));

        return response()->json($transactions);
    }

    /**
     * Get financial statistics summary.
     */
    public function getSummary(): JsonResponse
    {
        $totalRevenue = (int) PaymentTransaction::where('status', 'paid')->sum('amount');
        $totalPaidTransactions = PaymentTransaction::where('status', 'paid')->count();
        $totalPendingTransactions = PaymentTransaction::where('status', 'pending')->count();
        $totalDiscountsGiven = (int) PaymentTransaction::where('status', 'paid')
            ->sum(\DB::raw('global_discount_amount + coupon_discount_amount'));
        $activeCouponsCount = Coupon::where('is_active', true)->count();

        return response()->json([
            'total_revenue' => $totalRevenue,
            'total_revenue_formatted' => 'Rp ' . number_format($totalRevenue, 0, ',', '.'),
            'total_paid_transactions' => $totalPaidTransactions,
            'total_pending_transactions' => $totalPendingTransactions,
            'total_discounts_given' => $totalDiscountsGiven,
            'total_discounts_formatted' => 'Rp ' . number_format($totalDiscountsGiven, 0, ',', '.'),
            'active_coupons_count' => $activeCouponsCount,
        ]);
    }
}
