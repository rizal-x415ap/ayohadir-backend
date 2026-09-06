<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Wedding;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Wedding>
 */
class WeddingFactory extends Factory
{
    protected $model = Wedding::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $bride = fake()->firstNameFemale();
        $groom = fake()->firstNameMale();
        $slug = Str::slug($bride . '-dan-' . $groom . '-' . fake()->unique()->numberBetween(100, 9999));

        return [
            'user_id' => User::factory(),
            'slug' => $slug,
            'bride_name' => $bride,
            'groom_name' => $groom,
            'bride_parents' => 'Bpk. ' . fake()->lastName() . ' & Ibu ' . fake()->lastName(),
            'groom_parents' => 'Bpk. ' . fake()->lastName() . ' & Ibu ' . fake()->lastName(),
            'wedding_date' => fake()->dateTimeBetween('+1 week', '+1 year')->format('Y-m-d'),
            'wedding_time' => '09:00:00',
            'venue_name' => 'Grand Ballroom Hotel ' . fake()->city(),
            'venue_address' => fake()->address(),
            'venue_map_url' => 'https://maps.google.com/?q=' . fake()->latitude() . ',' . fake()->longitude(),
            'rsvp_enabled' => true,
            'wishes_enabled' => true,
            'status' => 'draft',
        ];
    }
}
