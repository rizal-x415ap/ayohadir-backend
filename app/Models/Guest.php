<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Guest extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'wedding_id',
        'guest_group_id',
        'name',
        'phone',
        'email',
        'max_attendees',
        'is_group',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'max_attendees' => 'integer',
            'is_group' => 'boolean',
        ];
    }

    public function wedding(): BelongsTo
    {
        return $this->belongsTo(Wedding::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(GuestGroup::class, 'guest_group_id');
    }

    public function invitation(): HasOne
    {
        return $this->hasOne(Invitation::class);
    }

    public function rsvp(): HasOne
    {
        return $this->hasOne(Rsvp::class);
    }

    public function rsvps(): HasMany
    {
        return $this->hasMany(Rsvp::class);
    }
}
