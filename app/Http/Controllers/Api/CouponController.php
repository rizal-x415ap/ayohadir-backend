<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CouponController extends Controller
{
    /**
     * Validate a coupon code and calculate discount for a given amount.
     */
    public function validateCoupon(Request $request): JsonResponse
    {
        $request->validate([
            'code' => 'required|string|max:50',
            'amount' => 'required|numeric|min:0',
        ]);

        $code = strtoupper(trim($request->input('code')));
        $amount = (int) $request->input('amount');

        $coupon = Coupon::where('code', $code)->first();

        if (!$coupon) {
            return response()->json([
                'valid' => false,
                'message' => 'Kode kupon promo tidak ditemukan.',
            ], 404);
        }

        $check = $coupon->validateForAmount($amount);

        if (!$check['valid']) {
            return response()->json([
                'valid' => false,
                'message' => $check['message'],
            ], 422);
        }

        $discountAmount = $coupon->calculateDiscount($amount);

        return response()->json([
            'valid' => true,
            'message' => 'Kupon berhasil diterapkan!',
            'coupon' => [
                'id' => $coupon->id,
                'code' => $coupon->code,
                'title' => $coupon->title,
                'type' => $coupon->type,
                'value' => $coupon->value,
                'discount_amount' => $discountAmount,
                'formatted_discount' => 'Rp ' . number_format($discountAmount, 0, ',', '.'),
            ],
        ]);
    }

    /**
     * Admin: List all coupons.
     */
    public function index(Request $request): JsonResponse
    {
        $coupons = Coupon::orderByDesc('created_at')->get();

        return response()->json([
            'data' => $coupons,
        ]);
    }

    /**
     * Admin: Create a new coupon.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:coupons,code',
            'title' => 'required|string|max:255',
            'type' => 'required|in:percentage,fixed',
            'value' => 'required|integer|min:1',
            'min_spend' => 'nullable|integer|min:0',
            'max_discount' => 'nullable|integer|min:0',
            'max_uses' => 'nullable|integer|min:1',
            'expires_at' => 'nullable|date',
            'is_active' => 'boolean',
        ]);

        $validated['code'] = strtoupper(trim($validated['code']));

        $coupon = Coupon::create($validated);

        return response()->json([
            'message' => 'Kupon berhasil dibuat.',
            'data' => $coupon,
        ], 201);
    }

    /**
     * Admin: Update coupon.
     */
    public function update(Request $request, Coupon $coupon): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:coupons,code,' . $coupon->id,
            'title' => 'required|string|max:255',
            'type' => 'required|in:percentage,fixed',
            'value' => 'required|integer|min:1',
            'min_spend' => 'nullable|integer|min:0',
            'max_discount' => 'nullable|integer|min:0',
            'max_uses' => 'nullable|integer|min:1',
            'expires_at' => 'nullable|date',
            'is_active' => 'boolean',
        ]);

        $validated['code'] = strtoupper(trim($validated['code']));

        $coupon->update($validated);

        return response()->json([
            'message' => 'Kupon berhasil diperbarui.',
            'data' => $coupon,
        ]);
    }

    /**
     * Admin: Delete coupon.
     */
    public function destroy(Coupon $coupon): JsonResponse
    {
        $coupon->delete();

        return response()->json([
            'message' => 'Kupon berhasil dihapus.',
        ]);
    }
}
