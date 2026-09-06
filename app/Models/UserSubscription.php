<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSubscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'plan_id',
        'order_number',
        'package_name',
        'amount_paid',
        'total_quota',
        'used_quota',
        'remaining_quota',
        'status',
        'payment_method',
        'activated_at',
        'expires_at',
    ];

    protected $casts = [
        'amount_paid' => 'integer',
        'total_quota' => 'integer',
        'used_quota' => 'integer',
        'remaining_quota' => 'integer',
        'activated_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * Consume 1 quota for a wedding project
     */
    public function consumeQuota(int $amount = 1): bool
    {
        if ($this->remaining_quota < $amount) {
            return false;
        }

        $this->remaining_quota -= $amount;
        $this->used_quota += $amount;

        if ($this->remaining_quota === 0) {
            $this->status = 'depleted';
        }

        return $this->save();
    }
}
