<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Wedding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WeddingManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_access_weddings(): void
    {
        $response = $this->getJson('/api/v1/weddings');
        $response->assertStatus(401);
    }

    public function test_user_can_create_wedding(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/v1/weddings', [
            'bride_name' => 'Rina Putri',
            'groom_name' => 'Budi Santoso',
            'slug' => 'rina-dan-budi',
            'wedding_date' => now()->addMonths(2)->format('Y-m-d'),
            'venue_name' => 'Grand Ballroom Jakarta',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.brideName', 'Rina Putri')
            ->assertJsonPath('data.groomName', 'Budi Santoso')
            ->assertJsonPath('data.slug', 'rina-dan-budi')
            ->assertJsonPath('data.status', 'draft');

        $this->assertDatabaseHas('weddings', [
            'user_id' => $user->id,
            'slug' => 'rina-dan-budi',
            'bride_name' => 'Rina Putri',
        ]);
    }

    public function test_user_can_list_only_their_own_weddings(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $wedding1 = Wedding::factory()->create(['user_id' => $user1->id, 'slug' => 'wedding-one']);
        $wedding2 = Wedding::factory()->create(['user_id' => $user2->id, 'slug' => 'wedding-two']);

        $response = $this->actingAs($user1)->getJson('/api/v1/weddings');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'wedding-one');
    }

    public function test_user_can_view_their_own_wedding(): void
    {
        $user = User::factory()->create();
        $wedding = Wedding::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->getJson('/api/v1/weddings/' . $wedding->id);

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $wedding->id);
    }

    public function test_user_cannot_view_other_users_wedding_idor_prevention(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $wedding2 = Wedding::factory()->create(['user_id' => $user2->id]);

        $response = $this->actingAs($user1)->getJson('/api/v1/weddings/' . $wedding2->id);

        $response->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');
    }

    public function test_user_can_update_their_own_wedding(): void
    {
        $user = User::factory()->create();
        $wedding = Wedding::factory()->create([
            'user_id' => $user->id,
            'bride_name' => 'Old Name',
        ]);

        $response = $this->actingAs($user)->putJson('/api/v1/weddings/' . $wedding->id, [
            'bride_name' => 'Updated Bride Name',
            'venue_name' => 'New Venue',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.brideName', 'Updated Bride Name')
            ->assertJsonPath('data.venueName', 'New Venue');

        $this->assertDatabaseHas('weddings', [
            'id' => $wedding->id,
            'bride_name' => 'Updated Bride Name',
        ]);
    }

    public function test_user_cannot_update_other_users_wedding(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $wedding2 = Wedding::factory()->create(['user_id' => $user2->id]);

        $response = $this->actingAs($user1)->putJson('/api/v1/weddings/' . $wedding2->id, [
            'bride_name' => 'Malicious Update',
        ]);

        $response->assertStatus(403);
    }

    public function test_user_can_delete_their_own_wedding(): void
    {
        $user = User::factory()->create();
        $wedding = Wedding::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->deleteJson('/api/v1/weddings/' . $wedding->id);

        $response->assertStatus(200);

        $this->assertSoftDeleted('weddings', [
            'id' => $wedding->id,
        ]);
    }

    public function test_user_cannot_delete_other_users_wedding(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $wedding2 = Wedding::factory()->create(['user_id' => $user2->id]);

        $response = $this->actingAs($user1)->deleteJson('/api/v1/weddings/' . $wedding2->id);

        $response->assertStatus(403);
    }

    public function test_wedding_creation_validates_required_fields_and_slug_format(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/v1/weddings', [
            'bride_name' => '',
            'groom_name' => '',
            'slug' => 'Invalid Slug with Spaces!',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonStructure([
                'error' => [
                    'details' => [
                        'errors' => ['bride_name', 'groom_name', 'slug'],
                    ],
                ],
            ]);
    }

    public function test_admin_can_view_any_wedding(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create();
        $wedding = Wedding::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($admin)->getJson('/api/v1/weddings/' . $wedding->id);

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $wedding->id);
    }

    public function test_user_can_list_trashed_weddings_with_retention_metadata(): void
    {
        $user = User::factory()->create();
        $activeWedding = Wedding::factory()->create(['user_id' => $user->id, 'slug' => 'active-wedding']);
        $trashedWedding = Wedding::factory()->create([
            'user_id' => $user->id,
            'slug' => 'trashed-wedding',
            'deleted_at' => now()->subDays(2),
        ]);

        $response = $this->actingAs($user)->getJson('/api/v1/weddings/trash');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'trashed-wedding')
            ->assertJsonPath('data.0.canRestore', true);

        $this->assertNotNull($response->json('data.0.daysRemaining'));
    }

    public function test_user_can_restore_wedding_within_7_days(): void
    {
        $user = User::factory()->create();
        $wedding = Wedding::factory()->create([
            'user_id' => $user->id,
            'deleted_at' => now()->subDays(3),
        ]);

        $response = $this->actingAs($user)->postJson('/api/v1/weddings/' . $wedding->id . '/restore');

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $wedding->id);

        $this->assertDatabaseHas('weddings', [
            'id' => $wedding->id,
            'deleted_at' => null,
        ]);
    }

    public function test_user_cannot_restore_wedding_after_7_days(): void
    {
        $user = User::factory()->create();
        $wedding = Wedding::factory()->create([
            'user_id' => $user->id,
            'deleted_at' => now()->subDays(8), // Expired retention (> 7 days)
        ]);

        $response = $this->actingAs($user)->postJson('/api/v1/weddings/' . $wedding->id . '/restore');

        $response->assertStatus(422);

        $this->assertSoftDeleted('weddings', [
            'id' => $wedding->id,
        ]);
    }

    public function test_user_can_force_delete_wedding_and_wipe_storage_directory(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $wedding = Wedding::factory()->create([
            'user_id' => $user->id,
            'deleted_at' => now()->subDay(),
        ]);

        // Put test files in wedding media storage directory
        $filePath = "media/weddings/{$wedding->id}/image/sample.webp";
        Storage::disk('public')->put($filePath, 'sample image content');
        $this->assertTrue(Storage::disk('public')->exists($filePath));

        $response = $this->actingAs($user)->deleteJson('/api/v1/weddings/' . $wedding->id . '/force-delete');

        $response->assertStatus(200);

        // Assert database record is permanently wiped
        $this->assertDatabaseMissing('weddings', [
            'id' => $wedding->id,
        ]);

        // Assert physical storage directory is wiped
        $this->assertFalse(Storage::disk('public')->exists($filePath));
    }

    public function test_purge_command_permanently_deletes_expired_trashed_weddings_and_storage(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();

        // 1. Expired wedding (8 days ago)
        $expiredWedding = Wedding::factory()->create([
            'user_id' => $user->id,
            'deleted_at' => now()->subDays(8),
        ]);
        $expiredPath = "media/weddings/{$expiredWedding->id}/image/expired.webp";
        Storage::disk('public')->put($expiredPath, 'expired content');

        // 2. Recent trashed wedding (2 days ago, still within 7 days)
        $recentWedding = Wedding::factory()->create([
            'user_id' => $user->id,
            'deleted_at' => now()->subDays(2),
        ]);
        $recentPath = "media/weddings/{$recentWedding->id}/image/recent.webp";
        Storage::disk('public')->put($recentPath, 'recent content');

        // Run purge command
        $this->artisan('weddings:purge-trashed', ['--days' => 7])
            ->assertExitCode(0);

        // Expired wedding must be gone from DB and storage
        $this->assertDatabaseMissing('weddings', ['id' => $expiredWedding->id]);
        $this->assertFalse(Storage::disk('public')->exists($expiredPath));

        // Recent wedding must still exist in trash and storage
        $this->assertDatabaseHas('weddings', ['id' => $recentWedding->id]);
        $this->assertTrue(Storage::disk('public')->exists($recentPath));
    }
}
