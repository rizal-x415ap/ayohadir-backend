<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WeddingTemplatePurchase extends Model
{
    use HasFactory;

    protected $fillable = [
        'wedding_id',
        'template_id',
        'payment_transaction_id',
        'unlocked_at',
    ];

    protected $casts = [
        'unlocked_at' => 'datetime',
    ];

    public function wedding(): BelongsTo
    {
        return $this->belongsTo(Wedding::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }

    public function paymentTransaction(): BelongsTo
    {
        return $this->belongsTo(PaymentTransaction::class);
    }
}
