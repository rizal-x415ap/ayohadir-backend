<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Wedding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class PublishingTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_publish(): void
    {
        $wedding = Wedding::factory()->create();

        $response = $this->postJson('/api/v1/weddings/' . $wedding->id . '/publish');
        $response->assertStatus(401);
    }

    public function test_cannot_publish_wedding_with_missing_mandatory_fields(): void
    {
        $user = User::factory()->create();
        $wedding = Wedding::factory()->create([
            'user_id' => $user->id,
            'bride_name' => '',
            'groom_name' => '',
            'wedding_date' => null,
            'venue_name' => '',
        ]);

        $response = $this->actingAs($user)->postJson('/api/v1/weddings/' . $wedding->id . '/publish');

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    public function test_owner_can_validate_and_publish_complete_wedding(): void
    {
        $user = User::factory()->create();
        $wedding = Wedding::factory()->create([
            'user_id' => $user->id,
            'bride_name' => 'Rina Putri',
            'groom_name' => 'Budi Santoso',
            'wedding_date' => '2026-10-10',
            'venue_name' => 'Grand Ballroom',
            'slug' => 'rina-dan-budi-publish',
            'status' => 'draft',
        ]);

        // 1. Pre-publish validation check
        $valRes = $this->actingAs($user)->postJson('/api/v1/weddings/' . $wedding->id . '/validate');
        $valRes->assertStatus(200)
            ->assertJsonPath('data.canPublish', true);

        // 2. Publish request
        $pubRes = $this->actingAs($user)->postJson('/api/v1/weddings/' . $wedding->id . '/publish');
        $pubRes->assertStatus(200)
            ->assertJsonPath('data.status', 'published')
            ->assertJsonPath('data.snapshot.wedding.brideName', 'Rina Putri');

        $this->assertDatabaseHas('weddings', [
            'id' => $wedding->id,
            'status' => 'published',
        ]);

        $this->assertDatabaseHas('designs', [
            'wedding_id' => $wedding->id,
        ]);
    }

    public function test_user_cannot_publish_other_users_wedding_idor(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $wedding2 = Wedding::factory()->create(['user_id' => $user2->id]);

        $response = $this->actingAs($user1)->postJson('/api/v1/weddings/' . $wedding2->id . '/publish');

        $response->assertStatus(403);
    }

    public function test_editing_after_publish_automatically_syncs_to_public_invitation(): void
    {
        $user = User::factory()->create();
        $wedding = Wedding::factory()->create([
            'user_id' => $user->id,
            'bride_name' => 'Original Bride',
            'groom_name' => 'Original Groom',
            'wedding_date' => '2026-10-10',
            'venue_name' => 'Original Venue',
            'slug' => 'original-wedding',
        ]);

        // Publish snapshot
        $this->actingAs($user)->postJson('/api/v1/weddings/' . $wedding->id . '/publish');

        // Edit content in User Editor while published
        $this->actingAs($user)->putJson('/api/v1/weddings/' . $wedding->id . '/content', [
            'bride_name' => 'Mutated Draft Name',
        ]);

        // Verify content is updated in database
        $this->assertDatabaseHas('weddings', [
            'id' => $wedding->id,
            'bride_name' => 'Mutated Draft Name',
        ]);

        // Verify public endpoint IMMEDIATELY serves the updated name without unpublishing
        $publicRes = $this->getJson('/api/v1/public/invitations/original-wedding');
        $publicRes->assertStatus(200)
            ->assertJsonPath('data.wedding.brideName', 'Mutated Draft Name');
    }

    public function test_owner_can_unpublish_wedding(): void
    {
        $user = User::factory()->create();
        $wedding = Wedding::factory()->create([
            'user_id' => $user->id,
            'bride_name' => 'Rina',
            'groom_name' => 'Budi',
            'wedding_date' => '2026-10-10',
            'venue_name' => 'Venue',
            'slug' => 'unpublish-wedding',
            'status' => 'draft',
        ]);

        $this->actingAs($user)->postJson('/api/v1/weddings/' . $wedding->id . '/publish');

        // Unpublish
        $unpubRes = $this->actingAs($user)->postJson('/api/v1/weddings/' . $wedding->id . '/unpublish');
        $unpubRes->assertStatus(200)
            ->assertJsonPath('data.status', 'unpublished');

        // Public page now returns 404
        $publicRes = $this->getJson('/api/v1/public/invitations/unpublish-wedding');
        $publicRes->assertStatus(404);
    }

    public function test_public_invitation_endpoint_returns_404_for_draft_wedding(): void
    {
        Wedding::factory()->create([
            'slug' => 'draft-only-wedding',
            'status' => 'draft',
        ]);

        $response = $this->getJson('/api/v1/public/invitations/draft-only-wedding');
        $response->assertStatus(404);
    }
}
