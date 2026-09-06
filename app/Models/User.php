<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'timezone',
        'locale',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Weddings owned by the user.
     */
    public function weddings(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Wedding::class);
    }

    /**
     * Subscriptions & packages purchased by user.
     */
    public function subscriptions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(UserSubscription::class);
    }

    /**
     * Get primary active subscription with remaining quota.
     */
    public function activeSubscription(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(UserSubscription::class)
            ->where('status', 'active')
            ->where('remaining_quota', '>', 0)
            ->latest('id');
    }

    /**
     * Total available invitation creation quota.
     */
    public function getAvailableQuota(): int
    {
        return (int) $this->subscriptions()
            ->where('status', 'active')
            ->where('remaining_quota', '>', 0)
            ->sum('remaining_quota');
    }

    /**
     * Check if user has administrator privileges.
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }
}
