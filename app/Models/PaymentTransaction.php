<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'merchant_order_id',
        'duitku_reference',
        'user_id',
        'wedding_id',
        'template_id',
        'base_price',
        'global_discount_amount',
        'coupon_id',
        'coupon_code',
        'coupon_discount_amount',
        'amount',
        'payment_method',
        'payment_url',
        'status',
        'paid_at',
        'raw_callback',
    ];

    protected function casts(): array
    {
        return [
            'base_price' => 'integer',
            'global_discount_amount' => 'integer',
            'coupon_discount_amount' => 'integer',
            'amount' => 'integer',
            'paid_at' => 'datetime',
            'raw_callback' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function wedding(): BelongsTo
    {
        return $this->belongsTo(Wedding::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }
}
