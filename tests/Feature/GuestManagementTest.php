<?php

namespace Tests\Feature;

use App\Models\Guest;
use App\Models\GuestGroup;
use App\Models\User;
use App\Models\Wedding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuestManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_create_guest_group(): void
    {
        $user = User::factory()->create();
        $wedding = Wedding::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->postJson("/api/v1/weddings/{$wedding->id}/guest-groups", [
            'name' => 'Keluarga Besar',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Keluarga Besar');

        $this->assertDatabaseHas('guest_groups', [
            'wedding_id' => $wedding->id,
            'name' => 'Keluarga Besar',
        ]);
    }

    public function test_user_cannot_create_guest_group_for_other_users_wedding(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $wedding = Wedding::factory()->create(['user_id' => $owner->id]);

        $response = $this->actingAs($otherUser)->postJson("/api/v1/weddings/{$wedding->id}/guest-groups", [
            'name' => 'Intruder Group',
        ]);

        $response->assertStatus(403);
    }

    public function test_owner_can_create_guest_and_generates_token(): void
    {
        $user = User::factory()->create();
        $wedding = Wedding::factory()->create(['user_id' => $user->id]);
        $group = GuestGroup::create(['wedding_id' => $wedding->id, 'name' => 'Sahabat']);

        $response = $this->actingAs($user)->postJson("/api/v1/weddings/{$wedding->id}/guests", [
            'name' => 'Rahmat Hidayat',
            'phone' => '081234567890',
            'max_attendees' => 2,
            'guest_group_id' => $group->id,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Rahmat Hidayat')
            ->assertJsonPath('data.maxAttendees', 2);

        $guest = Guest::where('name', 'Rahmat Hidayat')->first();
        $this->assertNotNull($guest);
        $this->assertNotNull($guest->invitation);
        $this->assertNotEmpty($guest->invitation->token);
    }

    public function test_owner_can_list_and_search_guests(): void
    {
        $user = User::factory()->create();
        $wedding = Wedding::factory()->create(['user_id' => $user->id]);

        $g1 = Guest::create(['wedding_id' => $wedding->id, 'name' => 'Ahmad Dahlan', 'max_attendees' => 1]);
        $g1->invitation()->create(['wedding_id' => $wedding->id, 'token' => 'tok1']);

        $g2 = Guest::create(['wedding_id' => $wedding->id, 'name' => 'Budi Utomo', 'max_attendees' => 2]);
        $g2->invitation()->create(['wedding_id' => $wedding->id, 'token' => 'tok2']);

        $response = $this->actingAs($user)->getJson("/api/v1/weddings/{$wedding->id}/guests?search=Dahlan");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Ahmad Dahlan');
    }

    public function test_user_cannot_access_other_users_guests_idor(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $wedding = Wedding::factory()->create(['user_id' => $owner->id]);

        $response = $this->actingAs($stranger)->getJson("/api/v1/weddings/{$wedding->id}/guests");

        $response->assertStatus(403);
    }

    public function test_owner_can_soft_delete_guest(): void
    {
        $user = User::factory()->create();
        $wedding = Wedding::factory()->create(['user_id' => $user->id]);
        $guest = Guest::create(['wedding_id' => $wedding->id, 'name' => 'Hapus Saya', 'max_attendees' => 1]);

        $response = $this->actingAs($user)->deleteJson("/api/v1/weddings/{$wedding->id}/guests/{$guest->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('guests', ['id' => $guest->id]);
    }
}
