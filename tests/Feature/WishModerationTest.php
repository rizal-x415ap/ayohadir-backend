<?php

namespace Tests\Feature;

use App\Mail\RsvpNotificationEmail;
use App\Models\Guest;
use App\Models\Invitation;
use App\Models\Rsvp;
use App\Models\User;
use App\Models\Wedding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WishModerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_wishes_auto_approved_when_moderation_disabled(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        $wedding = Wedding::factory()->create([
            'user_id' => $user->id,
            'slug' => 'auto-approved-wedding',
            'status' => 'published',
            'wishes_enabled' => true,
            'wishes_moderation_enabled' => false,
            'rsvp_notification_enabled' => true,
        ]);

        $response = $this->postJson("/api/v1/public/invitations/{$wedding->slug}/wishes", [
            'name' => 'Budi Santoso',
            'message' => 'Selamat dan semoga berbahagia!',
        ]);

        $response->assertStatus(200);

        $rsvp = Rsvp::where('wedding_id', $wedding->id)->first();
        $this->assertNotNull($rsvp);
        $this->assertTrue($rsvp->is_approved);
        $this->assertNull($rsvp->approval_token);

        Mail::assertSent(RsvpNotificationEmail::class, function ($mail) {
            return $mail->needsApproval === false &&
                   $mail->approvalToken === null;
        });

        // Check public invitation query returns this wish
        $pubResponse = $this->getJson("/api/v1/public/invitations/{$wedding->slug}");
        $pubResponse->assertStatus(200);
        $this->assertCount(1, $pubResponse->json('data.wishes'));
    }

    public function test_wishes_require_approval_when_moderation_enabled(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        $wedding = Wedding::factory()->create([
            'user_id' => $user->id,
            'slug' => 'moderated-wedding',
            'status' => 'published',
            'wishes_enabled' => true,
            'wishes_moderation_enabled' => true,
            'rsvp_notification_enabled' => true,
        ]);

        $response = $this->postJson("/api/v1/public/invitations/{$wedding->slug}/wishes", [
            'name' => 'Rina Melati',
            'message' => 'Semoga langgeng selamanya!',
        ]);

        $response->assertStatus(200);

        $rsvp = Rsvp::where('wedding_id', $wedding->id)->first();
        $this->assertNotNull($rsvp);
        $this->assertFalse($rsvp->is_approved);
        $this->assertNotEmpty($rsvp->approval_token);

        Mail::assertSent(RsvpNotificationEmail::class, function ($mail) use ($rsvp) {
            return $mail->needsApproval === true &&
                   $mail->approvalToken === $rsvp->approval_token;
        });

        // Unapproved wish must NOT appear on public invitation
        $pubResponse = $this->getJson("/api/v1/public/invitations/{$wedding->slug}");
        $pubResponse->assertStatus(200);
        $this->assertCount(0, $pubResponse->json('data.wishes'));
    }

    public function test_public_one_click_approval_from_email_magic_link(): void
    {
        $user = User::factory()->create();
        $wedding = Wedding::factory()->create([
            'user_id' => $user->id,
            'slug' => 'magic-approve-wedding',
            'bride_name' => 'Siti',
            'groom_name' => 'Fajar',
            'status' => 'published',
            'wishes_enabled' => true,
            'wishes_moderation_enabled' => true,
        ]);

        $guest = Guest::create([
            'wedding_id' => $wedding->id,
            'name' => 'Tamu Istimewa',
            'max_attendees' => 1,
        ]);
        $invitation = Invitation::create([
            'wedding_id' => $wedding->id,
            'guest_id' => $guest->id,
            'token' => 'inv_test_token_123',
        ]);

        $token = 'magic_secure_token_1234567890abcdef';
        $rsvp = Rsvp::create([
            'wedding_id' => $wedding->id,
            'invitation_id' => $invitation->id,
            'guest_id' => $guest->id,
            'attending' => true,
            'attendee_count' => 1,
            'wishes' => 'Barakallah!',
            'is_approved' => false,
            'approval_token' => $token,
            'responded_at' => now(),
        ]);

        // 1. Approve via public magic link
        $response = $this->postJson('/api/v1/public/wishes/approve', [
            'token' => $token,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('already_approved', false)
            ->assertJsonPath('data.guestName', 'Tamu Istimewa');

        $rsvp->refresh();
        $this->assertTrue($rsvp->is_approved);

        // 2. Now wish must appear on public invitation
        $pubResponse = $this->getJson("/api/v1/public/invitations/{$wedding->slug}");
        $pubResponse->assertStatus(200);
        $this->assertCount(1, $pubResponse->json('data.wishes'));

        // 3. Second click is idempotent and succeeds safely
        $secondResponse = $this->postJson('/api/v1/public/wishes/approve', [
            'token' => $token,
        ]);
        $secondResponse->assertStatus(200)
            ->assertJsonPath('already_approved', true);
    }

    public function test_owner_can_toggle_approval_from_dashboard(): void
    {
        $user = User::factory()->create();
        $wedding = Wedding::factory()->create([
            'user_id' => $user->id,
            'wishes_moderation_enabled' => true,
        ]);

        $guest = Guest::create([
            'wedding_id' => $wedding->id,
            'name' => 'Tamu Dashboard',
            'max_attendees' => 1,
        ]);
        $invitation = Invitation::create([
            'wedding_id' => $wedding->id,
            'guest_id' => $guest->id,
            'token' => 'inv_test_token_456',
        ]);

        $rsvp = Rsvp::create([
            'wedding_id' => $wedding->id,
            'invitation_id' => $invitation->id,
            'guest_id' => $guest->id,
            'attending' => true,
            'attendee_count' => 1,
            'wishes' => 'Selamat!',
            'is_approved' => false,
            'responded_at' => now(),
        ]);

        Sanctum::actingAs($user);

        // Approve from dashboard
        $response = $this->postJson("/api/v1/weddings/{$wedding->id}/rsvps/{$rsvp->id}/toggle-approval", [
            'is_approved' => true,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.isApproved', true);

        $rsvp->refresh();
        $this->assertTrue($rsvp->is_approved);
    }
}
