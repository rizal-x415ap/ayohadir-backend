<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Wedding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WeddingContentTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_access_wedding_content(): void
    {
        $wedding = Wedding::factory()->create();

        $response = $this->getJson('/api/v1/weddings/' . $wedding->id . '/content');
        $response->assertStatus(401);
    }

    public function test_owner_can_fetch_structured_wedding_content(): void
    {
        $user = User::factory()->create();
        $wedding = Wedding::factory()->create([
            'user_id' => $user->id,
            'bride_name' => 'Rina Putri',
            'groom_name' => 'Budi Santoso',
            'slug' => 'rina-budi-content',
        ]);

        $response = $this->actingAs($user)->getJson('/api/v1/weddings/' . $wedding->id . '/content');

        $response->assertStatus(200)
            ->assertJsonPath('data.weddingId', $wedding->id)
            ->assertJsonPath('data.slug', 'rina-budi-content')
            ->assertJsonPath('data.couple.bride.fullName', 'Rina Putri')
            ->assertJsonPath('data.couple.groom.fullName', 'Budi Santoso')
            ->assertJsonStructure([
                'data' => [
                    'schemaVersion',
                    'weddingId',
                    'slug',
                    'status',
                    'couple' => [
                        'bride' => ['fullName', 'shortName', 'parents'],
                        'groom' => ['fullName', 'shortName', 'parents'],
                    ],
                    'schedule' => ['date', 'time', 'venueName', 'venueAddress', 'venueMapUrl'],
                    'rsvp' => ['enabled', 'deadline', 'wishesEnabled'],
                    'customContent',
                    'sectionsConfig',
                ],
            ]);
    }

    public function test_user_cannot_fetch_other_users_wedding_content_idor(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $wedding2 = Wedding::factory()->create(['user_id' => $user2->id]);

        $response = $this->actingAs($user1)->getJson('/api/v1/weddings/' . $wedding2->id . '/content');

        $response->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');
    }

    public function test_owner_can_update_wedding_content(): void
    {
        $user = User::factory()->create();
        $wedding = Wedding::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->putJson('/api/v1/weddings/' . $wedding->id . '/content', [
            'bride_name' => 'Dr. Rina Putri',
            'groom_name' => 'Ir. Budi Santoso',
            'custom_content' => [
                'coverGreeting' => 'The Royal Wedding of',
                'storyTitle' => 'Awal Cerita Kami',
            ],
            'rsvp_enabled' => true,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.saved', true);

        $this->assertDatabaseHas('weddings', [
            'id' => $wedding->id,
            'bride_name' => 'Dr. Rina Putri',
            'groom_name' => 'Ir. Budi Santoso',
        ]);
    }

    public function test_user_cannot_update_other_users_wedding_content(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $wedding2 = Wedding::factory()->create(['user_id' => $user2->id]);

        $response = $this->actingAs($user1)->putJson('/api/v1/weddings/' . $wedding2->id . '/content', [
            'bride_name' => 'Hacked Name',
        ]);

        $response->assertStatus(403);
    }

    public function test_invalid_content_payload_rejected_by_validation(): void
    {
        $user = User::factory()->create();
        $wedding = Wedding::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->putJson('/api/v1/weddings/' . $wedding->id . '/content', [
            'wedding_date' => 'not-a-valid-date',
            'venue_map_url' => 'not-a-url',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonStructure([
                'error' => [
                    'details' => [
                        'errors' => ['wedding_date', 'venue_map_url'],
                    ],
                ],
            ]);
    }
}
