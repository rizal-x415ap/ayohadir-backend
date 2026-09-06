<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Invitation extends Model
{
    use HasFactory;

    protected $fillable = [
        'wedding_id',
        'guest_id',
        'token',
        'opened_at',
        'open_count',
        'whatsapp_sent_at',
    ];

    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime',
            'whatsapp_sent_at' => 'datetime',
            'open_count' => 'integer',
        ];
    }

    /**
     * Generate cryptographically secure URL-safe short token (maksimal 4 karakter)
     */
    public static function generateUniqueToken(): string
    {
        // Karakter rapi & terbaca jelas tanpa karakter ambigu (0/O, 1/l/I)
        $characters = '23456789abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ';
        $max = strlen($characters) - 1;

        $attempts = 0;
        do {
            $token = '';
            for ($i = 0; $i < 4; $i++) {
                $token .= $characters[random_int(0, $max)];
            }
            $attempts++;
            if ($attempts > 100) {
                // Fallback jika ruang 4 karakter penuh
                for ($i = 0; $i < 5; $i++) {
                    $token .= $characters[random_int(0, $max)];
                }
            }
        } while (static::where('token', $token)->exists());

        return $token;
    }

    public function wedding(): BelongsTo
    {
        return $this->belongsTo(Wedding::class);
    }

    public function guest(): BelongsTo
    {
        return $this->belongsTo(Guest::class);
    }

    public function rsvp(): HasOne
    {
        return $this->hasOne(Rsvp::class);
    }
}
