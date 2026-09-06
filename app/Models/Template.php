<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Template extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'slug',
        'description',
        'thumbnail',
        'schema_version',
        'schema',
        'contract',
        'category',
        'price',
        'original_price',
        'tier',
        'is_active',
        'order',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'schema' => 'array',
            'contract' => 'array',
            'schema_version' => 'integer',
            'price' => 'integer',
            'original_price' => 'integer',
            'is_active' => 'boolean',
            'order' => 'integer',
        ];
    }

    /**
     * Associated designs derived from this template.
     */
    public function designs(): HasMany
    {
        return $this->hasMany(Design::class);
    }
}
