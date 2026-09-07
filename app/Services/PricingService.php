<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\Coupon;
use App\Models\Template;
use App\Models\Wedding;

class PricingService
{
    /**
     * Calculate comprehensive price breakdown including stackable global & coupon discounts.
     */
    public function calculate(int $basePrice, ?string $couponCode = null): array
    {
        $globalEnabled = filter_var(AppSetting::get('discount_global_enabled', false), FILTER_VALIDATE_BOOLEAN);
        $globalType = (string) AppSetting::get('discount_global_type', 'percentage');
        $globalValue = (int) AppSetting::get('discount_global_value', 0);
        $globalTitle = (string) AppSetting::get('discount_global_title', 'Promo Diskon');

        $globalDiscountAmount = 0;
        if ($globalEnabled && $globalValue > 0) {
            if ($globalType === 'percentage') {
                $globalDiscountAmount = (int) round(($basePrice * $globalValue) / 100);
            } else {
                $globalDiscountAmount = min($globalValue, $basePrice);
            }
        }

        $remainingPrice = max($basePrice - $globalDiscountAmount, 0);

        $couponData = null;
        $couponDiscountAmount = 0;

        if (!empty($couponCode)) {
            $coupon = Coupon::where('code', strtoupper(trim($couponCode)))->first();
            if ($coupon) {
                $validation = $coupon->validateForAmount($remainingPrice);
                if ($validation['valid']) {
                    $couponDiscountAmount = $coupon->calculateDiscount($remainingPrice);
                    $couponData = [
                        'id' => $coupon->id,
                        'code' => $coupon->code,
                        'title' => $coupon->title,
                        'type' => $coupon->type,
                        'value' => $coupon->value,
                        'amount' => $couponDiscountAmount,
                    ];
                }
            }
        }

        $finalAmount = max($basePrice - $globalDiscountAmount - $couponDiscountAmount, 0);
        $totalDiscount = $globalDiscountAmount + $couponDiscountAmount;

        return [
            'base_price' => $basePrice,
            'formatted_base_price' => 'Rp ' . number_format($basePrice, 0, ',', '.'),
            'global_discount' => [
                'enabled' => $globalEnabled,
                'title' => $globalTitle,
                'type' => $globalType,
                'value' => $globalValue,
                'amount' => $globalDiscountAmount,
                'formatted_amount' => 'Rp ' . number_format($globalDiscountAmount, 0, ',', '.'),
            ],
            'global_discount_amount' => $globalDiscountAmount,
            'formatted_global_discount_amount' => 'Rp ' . number_format($globalDiscountAmount, 0, ',', '.'),
            'global_discount_title' => $globalTitle,
            'global_discount_percentage' => $globalType === 'percentage' ? $globalValue : 0,
            'coupon' => $couponData,
            'coupon_code' => $couponData['code'] ?? null,
            'coupon_discount_amount' => $couponDiscountAmount,
            'formatted_coupon_discount_amount' => 'Rp ' . number_format($couponDiscountAmount, 0, ',', '.'),
            'coupon_discount_percentage' => ($couponData && $couponData['type'] === 'percentage') ? $couponData['value'] : 0,
            'total_discount' => $totalDiscount,
            'formatted_total_discount' => 'Rp ' . number_format($totalDiscount, 0, ',', '.'),
            'final_amount' => $finalAmount,
            'final_price' => $finalAmount,
            'formatted_final_price' => 'Rp ' . number_format($finalAmount, 0, ',', '.'),
            'is_free' => $finalAmount === 0,
        ];
    }

    /**
     * Calculate price for a specific wedding based on its applied template.
     */
    public function calculateForWedding(Wedding $wedding, ?string $couponCode = null): array
    {
        $basePrice = 0;
        if ($wedding->applied_template_id) {
            $template = Template::find($wedding->applied_template_id);
            if ($template) {
                $basePrice = (int) ($template->price ?? 0);
            }
        }

        return $this->calculate($basePrice, $couponCode);
    }
}
