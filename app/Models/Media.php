<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Media extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'media';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'wedding_id',
        'type',
        'title',
        'artist',
        'category',
        'tags',
        'is_system',
        'filename',
        'disk',
        'path',
        'mime_type',
        'size',
        'width',
        'height',
        'duration_seconds',
        'variants',
        'alt_text',
        'usage_count',
        'processing_status',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'variants' => 'array',
            'is_system' => 'boolean',
            'width' => 'integer',
            'height' => 'integer',
            'duration_seconds' => 'integer',
            'usage_count' => 'integer',
        ];
    }

    /**
     * Uploader / owner relationship.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Wedding project relationship (NULL for Admin Global Assets).
     */
    public function wedding(): BelongsTo
    {
        return $this->belongsTo(Wedding::class);
    }

    /**
     * Scope to filter user's wedding media.
     */
    public function scopeForWedding(Builder $query, int|string $weddingId): Builder
    {
        return $query->where('wedding_id', $weddingId)->where('is_system', false);
    }

    /**
     * Scope to filter admin global system assets.
     */
    public function scopeGlobalAssets(Builder $query): Builder
    {
        return $query->where('is_system', true);
    }

    /**
     * Get public access URL.
     */
    public function getUrlAttribute(): string
    {
        return Storage::disk($this->disk)->url($this->path);
    }

    /**
     * Get thumbnail URL.
     */
    public function getThumbnailUrlAttribute(): string
    {
        if (!empty($this->variants['thumb'])) {
            return Storage::disk($this->disk)->url($this->variants['thumb']);
        }
        return $this->url;
    }
}
