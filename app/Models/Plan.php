<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'price',
        'quota_invitations',
        'duration_days',
        'description',
        'features',
        'badge',
        'is_active',
        'order',
    ];

    protected $casts = [
        'price' => 'integer',
        'quota_invitations' => 'integer',
        'duration_days' => 'integer',
        'features' => 'array',
        'is_active' => 'boolean',
        'order' => 'integer',
    ];

    public function subscriptions(): HasMany
    {
        return $this->hasMany(UserSubscription::class);
    }
}
