<?php

namespace Tests\Feature;

use App\Models\Guest;
use App\Models\GuestGroup;
use App\Models\User;
use App\Models\Wedding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicGuestManagerTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_access_wedding_info_with_valid_guest_manager_token(): void
    {
        $user = User::factory()->create();
        $wedding = Wedding::factory()->create([
            'user_id' => $user->id,
            'bride_name' => 'Sarah',
            'groom_name' => 'Reza',
        ]);

        $this->assertNotEmpty($wedding->guest_manager_token);

        $response = $this->getJson("/api/v1/public/buku-tamu/{$wedding->guest_manager_token}/info");
        $response->assertStatus(200)
            ->assertJsonPath('data.brideName', 'Sarah')
            ->assertJsonPath('data.groomName', 'Reza');
    }

    public function test_invalid_token_returns_404(): void
    {
        $response = $this->getJson('/api/v1/public/buku-tamu/invalid-token-12345/info');
        $response->assertStatus(404);
    }

    public function test_can_manage_guests_publicly_with_token(): void
    {
        $user = User::factory()->create();
        $wedding = Wedding::factory()->create(['user_id' => $user->id]);
        $token = $wedding->guest_manager_token;

        // 1. Create Guest
        $createRes = $this->postJson("/api/v1/public/buku-tamu/{$token}/guests", [
            'name' => 'Budi Pratama',
            'phone' => '08123456789',
            'email' => 'budi@example.com',
        ]);
        $createRes->assertStatus(201)
            ->assertJsonPath('data.name', 'Budi Pratama');

        $guestId = $createRes->json('data.id');

        // 2. List Guests
        $listRes = $this->getJson("/api/v1/public/buku-tamu/{$token}/guests");
        $listRes->assertStatus(200)
            ->assertJsonCount(1, 'data');

        // 3. Update Guest
        $updateRes = $this->putJson("/api/v1/public/buku-tamu/{$token}/guests/{$guestId}", [
            'name' => 'Budi Pratama S.T.',
            'phone' => '08123456789',
        ]);
        $updateRes->assertStatus(200)
            ->assertJsonPath('data.name', 'Budi Pratama S.T.');

        // 4. Batch Store Guests
        $batchRes = $this->postJson("/api/v1/public/buku-tamu/{$token}/guests/batch", [
            'guests' => [
                ['name' => 'Tamu Batch 1', 'phone' => '081111111'],
                ['name' => 'Tamu Batch 2', 'phone' => '082222222'],
            ],
        ]);
        $batchRes->assertStatus(201)
            ->assertJsonPath('count', 2);

        // 5. Delete Guest
        $deleteRes = $this->deleteJson("/api/v1/public/buku-tamu/{$token}/guests/{$guestId}");
        $deleteRes->assertStatus(200)
            ->assertJsonPath('data.deleted', true);
    }

    public function test_can_manage_groups_publicly_with_token(): void
    {
        $user = User::factory()->create();
        $wedding = Wedding::factory()->create(['user_id' => $user->id]);
        $token = $wedding->guest_manager_token;

        // 1. Get default groups (auto-created if empty)
        $groupsRes = $this->getJson("/api/v1/public/buku-tamu/{$token}/groups");
        $groupsRes->assertStatus(200)
            ->assertJsonCount(4, 'data');

        // 2. Create Group
        $createGroupRes = $this->postJson("/api/v1/public/buku-tamu/{$token}/groups", [
            'name' => 'Teman Kuliah ITB',
        ]);
        $createGroupRes->assertStatus(201)
            ->assertJsonPath('data.name', 'Teman Kuliah ITB');

        $groupId = $createGroupRes->json('data.id');

        // 3. Update Group
        $updateGroupRes = $this->putJson("/api/v1/public/buku-tamu/{$token}/groups/{$groupId}", [
            'name' => 'Alumni ITB 2018',
        ]);
        $updateGroupRes->assertStatus(200)
            ->assertJsonPath('data.name', 'Alumni ITB 2018');

        // 4. Delete Group
        $deleteGroupRes = $this->deleteJson("/api/v1/public/buku-tamu/{$token}/groups/{$groupId}");
        $deleteGroupRes->assertStatus(200)
            ->assertJsonPath('data.deleted', true);
    }

    public function test_owner_can_regenerate_token(): void
    {
        $user = User::factory()->create();
        $wedding = Wedding::factory()->create(['user_id' => $user->id]);
        $oldToken = $wedding->guest_manager_token;

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/weddings/{$wedding->id}/regenerate-guest-token");

        $response->assertStatus(200)
            ->assertJsonStructure(['guestManagerToken']);

        $newToken = $response->json('guestManagerToken');
        $this->assertNotEquals($oldToken, $newToken);

        // Old token should now return 404
        $this->getJson("/api/v1/public/buku-tamu/{$oldToken}/info")->assertStatus(404);

        // New token should work
        $this->getJson("/api/v1/public/buku-tamu/{$newToken}/info")->assertStatus(200);
    }

    public function test_can_manage_rsvps_and_wishes_publicly(): void
    {
        $user = User::factory()->create();
        $wedding = Wedding::factory()->create(['user_id' => $user->id]);
        $token = $wedding->guest_manager_token;

        $guest = $wedding->guests()->create(['name' => 'Keluarga Ahmad']);
        $invitation = $guest->invitation()->create([
            'wedding_id' => $wedding->id,
            'token' => \App\Models\Invitation::generateUniqueToken(),
        ]);

        $rsvp = \App\Models\Rsvp::create([
            'wedding_id' => $wedding->id,
            'guest_id' => $guest->id,
            'invitation_id' => $invitation->id,
            'name' => 'Ahmad',
            'attending' => true,
            'attendee_count' => 2,
            'wishes' => 'Selamat menempuh hidup baru!',
            'is_approved' => true,
            'responded_at' => now(),
        ]);

        // 1. List RSVPs
        $listRes = $this->getJson("/api/v1/public/buku-tamu/{$token}/rsvps");
        $listRes->assertStatus(200)
            ->assertJsonPath('summary.confirmed', 1)
            ->assertJsonPath('summary.totalAttendees', 2);

        // 2. Toggle wish approval
        $toggleRes = $this->postJson("/api/v1/public/buku-tamu/{$token}/rsvps/{$rsvp->id}/toggle-approval", [
            'is_approved' => false,
        ]);
        $toggleRes->assertStatus(200)
            ->assertJsonPath('data.isApproved', false);

        // 3. Delete wish
        $deleteWishRes = $this->deleteJson("/api/v1/public/buku-tamu/{$token}/rsvps/{$rsvp->id}/wishes");
        $deleteWishRes->assertStatus(200);
        $this->assertNull($rsvp->fresh()->wishes);

        // 4. Delete RSVP
        $deleteRsvpRes = $this->deleteJson("/api/v1/public/buku-tamu/{$token}/rsvps/{$rsvp->id}");
        $deleteRsvpRes->assertStatus(200);
        $this->assertDatabaseMissing('rsvps', ['id' => $rsvp->id]);
    }
}
