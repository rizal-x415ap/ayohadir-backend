<?php

namespace Tests\Feature;

use App\Models\Guest;
use App\Models\Invitation;
use App\Models\Rsvp;
use App\Models\User;
use App\Models\Wedding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RsvpTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_submit_public_rsvp_with_valid_token(): void
    {
        $user = User::factory()->create();
        $wedding = Wedding::factory()->create([
            'user_id' => $user->id,
            'slug' => 'budi-rina',
            'status' => 'published',
            'rsvp_enabled' => true,
        ]);
        $guest = Guest::create([
            'wedding_id' => $wedding->id,
            'name' => 'Pak Joko',
            'max_attendees' => 2,
        ]);
        $invitation = Invitation::create([
            'wedding_id' => $wedding->id,
            'guest_id' => $guest->id,
            'token' => 'valid-token-123',
        ]);

        $response = $this->postJson('/api/v1/public/invitations/budi-rina/rsvp', [
            'token' => 'valid-token-123',
            'attending' => true,
            'attendee_count' => 2,
            'wishes' => 'Selamat menempuh hidup baru!',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.attending', true)
            ->assertJsonPath('data.guestName', 'Pak Joko');

        $this->assertDatabaseHas('rsvps', [
            'wedding_id' => $wedding->id,
            'guest_id' => $guest->id,
            'attending' => true,
            'attendee_count' => 2,
        ]);
    }

    public function test_guest_cannot_exceed_max_attendees(): void
    {
        $user = User::factory()->create();
        $wedding = Wedding::factory()->create([
            'user_id' => $user->id,
            'slug' => 'budi-rina-2',
            'status' => 'published',
            'rsvp_enabled' => true,
        ]);
        $guest = Guest::create([
            'wedding_id' => $wedding->id,
            'name' => 'Single Guest',
            'max_attendees' => 1,
        ]);
        $invitation = Invitation::create([
            'wedding_id' => $wedding->id,
            'guest_id' => $guest->id,
            'token' => 'single-token',
        ]);

        $response = $this->postJson('/api/v1/public/invitations/budi-rina-2/rsvp', [
            'token' => 'single-token',
            'attending' => true,
            'attendee_count' => 3, // Exceeds max 1
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'EXCEEDS_MAX_ATTENDEES');
    }

    public function test_guest_cannot_submit_rsvp_after_deadline(): void
    {
        $user = User::factory()->create();
        $wedding = Wedding::factory()->create([
            'user_id' => $user->id,
            'slug' => 'budi-rina-expired',
            'status' => 'published',
            'rsvp_enabled' => true,
            'rsvp_deadline' => now()->subDay()->toDateString(),
        ]);
        $guest = Guest::create([
            'wedding_id' => $wedding->id,
            'name' => 'Late Guest',
            'max_attendees' => 2,
        ]);
        $invitation = Invitation::create([
            'wedding_id' => $wedding->id,
            'guest_id' => $guest->id,
            'token' => 'late-token',
        ]);

        $response = $this->postJson('/api/v1/public/invitations/budi-rina-expired/rsvp', [
            'token' => 'late-token',
            'attending' => true,
            'attendee_count' => 1,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'RSVP_DEADLINE_PASSED');
    }

    public function test_owner_can_view_rsvp_list_and_summary_metrics(): void
    {
        $user = User::factory()->create();
        $wedding = Wedding::factory()->create(['user_id' => $user->id]);

        $g1 = Guest::create(['wedding_id' => $wedding->id, 'name' => 'Guest 1', 'max_attendees' => 2]);
        $inv1 = Invitation::create(['wedding_id' => $wedding->id, 'guest_id' => $g1->id, 'token' => 'tok-1']);
        Rsvp::create([
            'wedding_id' => $wedding->id,
            'invitation_id' => $inv1->id,
            'guest_id' => $g1->id,
            'attending' => true,
            'attendee_count' => 2,
            'responded_at' => now(),
        ]);

        $response = $this->actingAs($user)->getJson("/api/v1/weddings/{$wedding->id}/rsvps");

        $response->assertStatus(200)
            ->assertJsonPath('summary.totalGuests', 1)
            ->assertJsonPath('summary.confirmed', 1)
            ->assertJsonPath('summary.totalAttendees', 2);
    }

    public function test_public_invitation_personalizes_guest_name_from_token(): void
    {
        $user = User::factory()->create();
        $wedding = Wedding::factory()->create([
            'user_id' => $user->id,
            'slug' => 'budi-rina-live',
            'status' => 'published',
        ]);
        $wedding->design()->create([
            'schema_version' => 1,
            'schema' => ['sections' => []],
            'published_schema' => ['sections' => []],
            'version' => 1,
        ]);

        $guest = Guest::create([
            'wedding_id' => $wedding->id,
            'name' => 'Bapak Subagio & Keluarga',
            'max_attendees' => 4,
        ]);
        $invitation = Invitation::create([
            'wedding_id' => $wedding->id,
            'guest_id' => $guest->id,
            'token' => 'subagio-vip',
        ]);

        $response = $this->getJson('/api/v1/public/invitations/budi-rina-live?guest=subagio-vip');

        $response->assertStatus(200)
            ->assertJsonPath('data.guest.name', 'Bapak Subagio & Keluarga')
            ->assertJsonPath('data.guest.maxAttendees', 4);
    }

    public function test_public_guest_can_submit_rsvp_without_token(): void
    {
        $user = User::factory()->create();
        $wedding = Wedding::factory()->create([
            'user_id' => $user->id,
            'slug' => 'adam-hawa',
            'status' => 'published',
            'rsvp_enabled' => true,
        ]);

        $response = $this->postJson('/api/v1/public/invitations/adam-hawa/rsvp', [
            'name' => 'Ahmad Dahlan',
            'attending' => true,
            'attendee_count' => 2,
            'wishes' => 'Barakallah lakuma wa baraka alaikuma!',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.guestName', 'Ahmad Dahlan')
            ->assertJsonPath('data.attending', true)
            ->assertJsonPath('data.attendeeCount', 2);

        $this->assertDatabaseHas('guests', [
            'wedding_id' => $wedding->id,
            'name' => 'Ahmad Dahlan',
            'notes' => 'RSVP Publik',
        ]);

        $this->assertDatabaseHas('rsvps', [
            'wedding_id' => $wedding->id,
            'attending' => true,
            'attendee_count' => 2,
            'wishes' => 'Barakallah lakuma wa baraka alaikuma!',
        ]);
    }

    public function test_public_guest_can_submit_wishes_directly(): void
    {
        $user = User::factory()->create();
        $wedding = Wedding::factory()->create([
            'user_id' => $user->id,
            'slug' => 'romeo-juliet',
            'status' => 'published',
            'rsvp_enabled' => true,
        ]);

        $response = $this->postJson('/api/v1/public/invitations/romeo-juliet/wishes', [
            'name' => 'Siti Khadijah',
            'message' => 'Semoga sakinah mawaddah warahmah selamanya.',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Siti Khadijah')
            ->assertJsonPath('data.message', 'Semoga sakinah mawaddah warahmah selamanya.');

        $this->assertDatabaseHas('guests', [
            'wedding_id' => $wedding->id,
            'name' => 'Siti Khadijah',
        ]);

        $this->assertDatabaseHas('rsvps', [
            'wedding_id' => $wedding->id,
            'wishes' => 'Semoga sakinah mawaddah warahmah selamanya.',
        ]);
    }

    public function test_public_invitation_personalizes_guest_name_from_to_param(): void
    {
        $user = User::factory()->create();
        $wedding = Wedding::factory()->create([
            'user_id' => $user->id,
            'slug' => 'to-param-wedding',
            'status' => 'published',
        ]);
        $wedding->design()->create([
            'schema_version' => 1,
            'schema' => ['sections' => []],
            'published_schema' => ['sections' => []],
            'version' => 1,
        ]);

        $response = $this->getJson('/api/v1/public/invitations/to-param-wedding?to=' . urlencode('Bapak Hendro & Partner'));

        $response->assertStatus(200)
            ->assertJsonPath('data.guest.name', 'Bapak Hendro & Partner')
            ->assertJsonPath('data.guest.isRegistered', false);
    }

    public function test_public_invitation_without_guest_has_null_guest_data(): void
    {
        $user = User::factory()->create();
        $wedding = Wedding::factory()->create([
            'user_id' => $user->id,
            'slug' => 'undangan-bebas',
            'status' => 'published',
        ]);
        $wedding->design()->create([
            'schema_version' => 1,
            'schema' => ['sections' => []],
            'published_schema' => ['sections' => []],
            'version' => 1,
        ]);

        $response = $this->getJson('/api/v1/public/invitations/undangan-bebas');

        $response->assertStatus(200)
            ->assertJsonPath('data.guest', null);
    }

    public function test_guest_can_submit_declined_attendance_rsvp(): void
    {
        $user = User::factory()->create();
        $wedding = Wedding::factory()->create([
            'user_id' => $user->id,
            'slug' => 'budi-ani',
            'status' => 'published',
            'rsvp_enabled' => true,
        ]);

        $response = $this->postJson('/api/v1/public/invitations/budi-ani/rsvp', [
            'name' => 'Siti Khadijah',
            'attending' => false,
            'attendee_count' => 0,
            'wishes' => 'Mohon maaf belum bisa hadir, selamat menempuh hidup baru!',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.attending', false)
            ->assertJsonPath('data.attendeeCount', 0);

        $this->assertDatabaseHas('rsvps', [
            'wedding_id' => $wedding->id,
            'attending' => false,
            'attendee_count' => 0,
            'wishes' => 'Mohon maaf belum bisa hadir, selamat menempuh hidup baru!',
        ]);
    }

    public function test_registered_guest_cannot_submit_rsvp_twice(): void
    {
        $user = User::factory()->create();
        $wedding = Wedding::factory()->create([
            'user_id' => $user->id,
            'slug' => 'sekali-saja',
            'status' => 'published',
            'rsvp_enabled' => true,
        ]);
        $guest = Guest::create(['wedding_id' => $wedding->id, 'name' => 'Budi Santoso', 'max_attendees' => 2]);
        $invitation = Invitation::create(['wedding_id' => $wedding->id, 'guest_id' => $guest->id, 'token' => 'sekali-token-123']);

        // Submit 1st time: Success
        $res1 = $this->postJson('/api/v1/public/invitations/sekali-saja/rsvp', [
            'token' => $invitation->token,
            'attending' => true,
            'attendee_count' => 1,
            'wishes' => 'Selamat ya!',
        ]);
        $res1->assertStatus(200);

        // Submit 2nd time: Blocked with 422 ALREADY_CONFIRMED
        $res2 = $this->postJson('/api/v1/public/invitations/sekali-saja/rsvp', [
            'token' => $invitation->token,
            'attending' => true,
            'attendee_count' => 1,
            'wishes' => 'Selamat ya lagi!',
        ]);
        $res2->assertStatus(422)
            ->assertJsonPath('error.code', 'ALREADY_CONFIRMED');
    }

    public function test_public_guest_cannot_submit_twice_from_same_ip(): void
    {
        $user = User::factory()->create();
        $wedding = Wedding::factory()->create([
            'user_id' => $user->id,
            'slug' => 'ip-test',
            'status' => 'published',
            'rsvp_enabled' => true,
        ]);

        $serverVars = ['REMOTE_ADDR' => '203.0.113.195'];

        // Submit 1st time from IP: Success
        $res1 = $this->withServerVariables($serverVars)
            ->postJson('/api/v1/public/invitations/ip-test/rsvp', [
                'name' => 'Pengunjung A',
                'attending' => true,
                'attendee_count' => 1,
                'wishes' => 'Selamat menempuh hidup baru!',
            ]);
        $res1->assertStatus(200);

        // Submit 2nd time from same IP: Blocked with 422 ALREADY_SUBMITTED_FROM_DEVICE
        $res2 = $this->withServerVariables($serverVars)
            ->postJson('/api/v1/public/invitations/ip-test/rsvp', [
                'name' => 'Pengunjung B',
                'attending' => true,
                'attendee_count' => 1,
                'wishes' => 'Mencoba kirim lagi',
            ]);
        $res2->assertStatus(422)
            ->assertJsonPath('error.code', 'ALREADY_SUBMITTED_FROM_DEVICE');
    }

    public function test_public_guest_can_submit_from_different_ip(): void
    {
        $user = User::factory()->create();
        $wedding = Wedding::factory()->create([
            'user_id' => $user->id,
            'slug' => 'diff-ip-test',
            'status' => 'published',
            'rsvp_enabled' => true,
        ]);

        // Submit from IP 1: Success
        $res1 = $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
            ->postJson('/api/v1/public/invitations/diff-ip-test/rsvp', [
                'name' => 'Teman Kampus',
                'attending' => true,
                'attendee_count' => 1,
                'wishes' => 'Selamat!',
            ]);
        $res1->assertStatus(200);

        // Submit from different IP 2: Also Success
        $res2 = $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.20'])
            ->postJson('/api/v1/public/invitations/diff-ip-test/rsvp', [
                'name' => 'Teman Kantor',
                'attending' => false,
                'attendee_count' => 0,
                'wishes' => 'Mohon maaf belum bisa hadir!',
            ]);
        $res2->assertStatus(200);
    }

    public function test_owner_can_delete_inappropriate_wish(): void
    {
        $owner = User::factory()->create();
        $wedding = Wedding::factory()->create(['user_id' => $owner->id]);
        $guest = Guest::create(['wedding_id' => $wedding->id, 'name' => 'Tamu Nakal', 'max_attendees' => 1]);
        $invitation = Invitation::create(['wedding_id' => $wedding->id, 'guest_id' => $guest->id, 'token' => 'nakal-123']);
        $rsvp = Rsvp::create([
            'wedding_id' => $wedding->id,
            'invitation_id' => $invitation->id,
            'guest_id' => $guest->id,
            'attending' => true,
            'attendee_count' => 1,
            'wishes' => 'Kata-kata kasar atau tidak sopan',
            'responded_at' => now(),
        ]);

        $response = $this->actingAs($owner, 'sanctum')
            ->deleteJson("/api/v1/weddings/{$wedding->id}/rsvps/{$rsvp->id}/wishes");

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('rsvps', [
            'id' => $rsvp->id,
            'wishes' => null,
        ]);
    }

    public function test_owner_can_delete_rsvp_record(): void
    {
        $owner = User::factory()->create();
        $wedding = Wedding::factory()->create(['user_id' => $owner->id]);
        $guest = Guest::create(['wedding_id' => $wedding->id, 'name' => 'Tamu Spam', 'max_attendees' => 1]);
        $invitation = Invitation::create(['wedding_id' => $wedding->id, 'guest_id' => $guest->id, 'token' => 'spam-123']);
        $rsvp = Rsvp::create([
            'wedding_id' => $wedding->id,
            'invitation_id' => $invitation->id,
            'guest_id' => $guest->id,
            'attending' => true,
            'attendee_count' => 1,
            'responded_at' => now(),
        ]);

        $response = $this->actingAs($owner, 'sanctum')
            ->deleteJson("/api/v1/weddings/{$wedding->id}/rsvps/{$rsvp->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('rsvps', [
            'id' => $rsvp->id,
        ]);
    }

    public function test_non_owner_cannot_delete_wish_or_rsvp(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $wedding = Wedding::factory()->create(['user_id' => $owner->id]);
        $guest = Guest::create(['wedding_id' => $wedding->id, 'name' => 'Tamu Asli', 'max_attendees' => 1]);
        $invitation = Invitation::create(['wedding_id' => $wedding->id, 'guest_id' => $guest->id, 'token' => 'asli-123']);
        $rsvp = Rsvp::create([
            'wedding_id' => $wedding->id,
            'invitation_id' => $invitation->id,
            'guest_id' => $guest->id,
            'attending' => true,
            'attendee_count' => 1,
            'wishes' => 'Pesan asli',
            'responded_at' => now(),
        ]);

        // Stranger attempts to delete wish
        $res1 = $this->actingAs($stranger, 'sanctum')
            ->deleteJson("/api/v1/weddings/{$wedding->id}/rsvps/{$rsvp->id}/wishes");
        $res1->assertStatus(403);

        // Stranger attempts to delete RSVP
        $res2 = $this->actingAs($stranger, 'sanctum')
            ->deleteJson("/api/v1/weddings/{$wedding->id}/rsvps/{$rsvp->id}");
        $res2->assertStatus(403);
    }
}
