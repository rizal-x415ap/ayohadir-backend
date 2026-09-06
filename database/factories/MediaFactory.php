<?php

namespace Database\Factories;

use App\Models\Media;
use App\Models\User;
use App\Models\Wedding;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Media>
 */
class MediaFactory extends Factory
{
    protected $model = Media::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'wedding_id' => Wedding::factory(),
            'type' => 'image',
            'category' => 'general',
            'tags' => ['photo'],
            'is_system' => false,
            'filename' => fake()->word() . '.jpg',
            'disk' => 'public',
            'path' => 'media/' . fake()->uuid() . '.jpg',
            'mime_type' => 'image/jpeg',
            'size' => fake()->numberBetween(10000, 2000000),
            'width' => 1920,
            'height' => 1080,
            'usage_count' => 0,
            'processing_status' => 'done',
        ];
    }
}
