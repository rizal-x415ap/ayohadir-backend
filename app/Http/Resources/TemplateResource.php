<?php

namespace App\Http\Resources;

use App\Models\AppSetting;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Template
 */
class TemplateResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $rawPrice = (int) ($this->price ?? 0);
        $rawOriginalPrice = $this->original_price !== null ? (int) $this->original_price : null;

        // Check global discount setting
        $globalEnabled = filter_var(AppSetting::get('discount_global_enabled', false), FILTER_VALIDATE_BOOLEAN);
        $globalType = (string) AppSetting::get('discount_global_type', 'percentage');
        $globalValue = (int) AppSetting::get('discount_global_value', 0);
        $globalTitle = (string) AppSetting::get('discount_global_title', 'Promo Diskon');

        $finalPrice = $rawPrice;
        $originalPrice = $rawPrice;
        $hasDiscount = false;
        $discountPercentage = 0;
        $discountAmount = 0;
        $discountLabel = null;

        if ($rawPrice > 0) {
            if ($globalEnabled && $globalValue > 0) {
                // Global discount applies on base price
                if ($globalType === 'percentage') {
                    $globalCut = (int) round(($rawPrice * $globalValue) / 100);
                } else {
                    $globalCut = min($globalValue, $rawPrice);
                }
                $finalPrice = max(0, $rawPrice - $globalCut);

                // Baseline original price is manual original_price (if set higher than rawPrice) or rawPrice
                if ($rawOriginalPrice !== null && $rawOriginalPrice > $rawPrice) {
                    $originalPrice = $rawOriginalPrice;
                } else {
                    $originalPrice = $rawPrice;
                }

                $discountAmount = max(0, $originalPrice - $finalPrice);
                $hasDiscount = $discountAmount > 0;
                $discountPercentage = $originalPrice > 0 ? (int) round(($discountAmount / $originalPrice) * 100) : 0;
                $discountLabel = $globalTitle ?: ($discountPercentage > 0 ? "Diskon {$discountPercentage}%" : null);
            } elseif ($rawOriginalPrice !== null && $rawOriginalPrice > $rawPrice) {
                // Manual strikethrough price when global discount is not active
                $originalPrice = $rawOriginalPrice;
                $finalPrice = $rawPrice;
                $discountAmount = $originalPrice - $finalPrice;
                $hasDiscount = true;
                $discountPercentage = (int) round(($discountAmount / $originalPrice) * 100);
                $discountLabel = "Diskon {$discountPercentage}%";
            }
        }

        $formattedPrice = $finalPrice === 0 ? 'Gratis' : 'Rp ' . number_format($finalPrice, 0, ',', '.');
        $formattedOriginalPrice = $hasDiscount ? 'Rp ' . number_format($originalPrice, 0, ',', '.') : null;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'thumbnail' => $this->thumbnail,
            'thumbnailRatio' => $this->schema['metadata']['thumbnailRatio'] ?? '4/3',
            'thumbnailFit' => $this->schema['metadata']['thumbnailFit'] ?? 'cover',
            'schemaVersion' => $this->schema_version,
            'category' => $this->category,
            'price' => $finalPrice,
            'formattedPrice' => $formattedPrice,
            'originalPrice' => $hasDiscount ? $originalPrice : null,
            'formattedOriginalPrice' => $formattedOriginalPrice,
            'hasDiscount' => $hasDiscount,
            'discountPercentage' => $discountPercentage,
            'discountAmount' => $discountAmount,
            'discountLabel' => $discountLabel,
            'rawPrice' => $rawPrice,
            'rawOriginalPrice' => $rawOriginalPrice,
            'tier' => $this->tier ?? 'regular',
            'isIncludedInPlan' => $finalPrice === 0 || ($request->user() && $request->user()->getAvailableQuota() > 0),
            'isActive' => (bool) $this->is_active,
            'order' => $this->order,
            'schema' => $this->when($request->routeIs('*.show') || $request->user()?->isAdmin(), $this->schema),
            'contract' => $this->when($request->routeIs('*.show') || $request->user()?->isAdmin(), $this->contract),
            'createdAt' => $this->created_at->toIso8601String(),
            'updatedAt' => $this->updated_at->toIso8601String(),
        ];
    }
}
