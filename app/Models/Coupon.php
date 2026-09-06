<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'title',
        'type',
        'value',
        'min_spend',
        'max_discount',
        'max_uses',
        'used_count',
        'expires_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'integer',
            'min_spend' => 'integer',
            'max_discount' => 'integer',
            'max_uses' => 'integer',
            'used_count' => 'integer',
            'expires_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Validate coupon eligibility for a given amount.
     */
    public function validateForAmount(int $amount): array
    {
        if (!$this->is_active) {
            return ['valid' => false, 'message' => 'Kode kupon sudah tidak aktif.'];
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return ['valid' => false, 'message' => 'Masa berlaku kupon telah berakhir.'];
        }

        if ($this->max_uses !== null && $this->used_count >= $this->max_uses) {
            return ['valid' => false, 'message' => 'Kupon telah mencapai batas penggunaan maksimum.'];
        }

        if ($this->min_spend !== null && $amount < $this->min_spend) {
            return [
                'valid' => false,
                'message' => 'Minimum pembelian untuk menggunakan kupon ini adalah Rp ' . number_format($this->min_spend, 0, ',', '.'),
            ];
        }

        return ['valid' => true, 'message' => 'Kupon berhasil diterapkan.'];
    }

    /**
     * Calculate discount amount given a price.
     */
    public function calculateDiscount(int $amount): int
    {
        if ($this->type === 'percentage') {
            $discount = (int) round(($amount * $this->value) / 100);
            if ($this->max_discount !== null && $discount > $this->max_discount) {
                $discount = $this->max_discount;
            }
            return min($discount, $amount);
        }

        return min($this->value, $amount);
    }
}
