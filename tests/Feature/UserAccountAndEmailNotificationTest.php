<?php

namespace Tests\Feature;

use App\Mail\PasswordOtpEmail;
use App\Mail\RsvpNotificationEmail;
use App\Mail\TransactionSuccessEmail;
use App\Mail\WelcomeEmail;
use App\Models\PasswordResetOtp;
use App\Models\PaymentTransaction;
use App\Models\Template;
use App\Models\User;
use App\Models\Wedding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserAccountAndEmailNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_sends_welcome_email(): void
    {
        Mail::fake();

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Adit Pratama',
            'email' => 'adit@ayohadir.id',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertStatus(201);

        Mail::assertSent(WelcomeEmail::class, function ($mail) {
            return $mail->hasTo('adit@ayohadir.id') &&
                   $mail->user->name === 'Adit Pratama';
        });
    }

    public function test_user_can_request_password_otp_via_email(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'email' => 'user_otp@ayohadir.id',
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/user/password/request-otp');

        $response->assertStatus(200)
            ->assertJsonPath('data.expiresInMinutes', 10);

        Mail::assertSent(PasswordOtpEmail::class, function ($mail) use ($user) {
            return $mail->hasTo($user->email) &&
                   strlen($mail->otp) === 6;
        });

        $this->assertDatabaseHas('password_reset_otps', [
            'user_id' => $user->id,
            'email' => $user->email,
        ]);
    }

    public function test_user_can_confirm_password_change_with_valid_otp(): void
    {
        $user = User::factory()->create([
            'email' => 'change_pass@ayohadir.id',
            'password' => Hash::make('OldPassword123!'),
        ]);

        Sanctum::actingAs($user);

        $otpCode = '654321';
        PasswordResetOtp::create([
            'user_id' => $user->id,
            'email' => $user->email,
            'otp_hash' => Hash::make($otpCode),
            'expires_at' => now()->addMinutes(10),
            'attempts' => 0,
        ]);

        $response = $this->postJson('/api/v1/user/password/confirm', [
            'current_password' => 'OldPassword123!',
            'new_password' => 'NewPassword123!',
            'new_password_confirmation' => 'NewPassword123!',
            'otp' => $otpCode,
        ]);

        $response->assertStatus(200);

        // Verify password is changed
        $user->refresh();
        $this->assertTrue(Hash::check('NewPassword123!', $user->password));

        // Verify OTP is cleaned up
        $this->assertDatabaseMissing('password_reset_otps', [
            'user_id' => $user->id,
        ]);
    }

    public function test_password_change_fails_with_invalid_otp(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('OldPassword123!'),
        ]);

        Sanctum::actingAs($user);

        PasswordResetOtp::create([
            'user_id' => $user->id,
            'email' => $user->email,
            'otp_hash' => Hash::make('123456'),
            'expires_at' => now()->addMinutes(10),
            'attempts' => 0,
        ]);

        $response = $this->postJson('/api/v1/user/password/confirm', [
            'current_password' => 'OldPassword123!',
            'new_password' => 'NewPassword123!',
            'new_password_confirmation' => 'NewPassword123!',
            'otp' => '999999',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'INVALID_OTP');

        // Verify password was NOT changed
        $user->refresh();
        $this->assertTrue(Hash::check('OldPassword123!', $user->password));
    }

    public function test_rsvp_submission_triggers_email_notification(): void
    {
        Mail::fake();

        $user = User::factory()->create(['email' => 'wedding_owner@ayohadir.id']);
        $wedding = Wedding::factory()->create([
            'user_id' => $user->id,
            'slug' => 'dian-and-rama',
            'status' => 'published',
            'rsvp_enabled' => true,
            'rsvp_notification_enabled' => true,
            'rsvp_notification_email' => 'custom_notif@ayohadir.id',
        ]);

        $response = $this->postJson("/api/v1/public/invitations/{$wedding->slug}/rsvp", [
            'name' => 'Sahabat Dian',
            'attending' => true,
            'attendee_count' => 2,
            'wishes' => 'Selamat menempuh hidup baru!',
        ]);

        $response->assertStatus(200);

        Mail::assertSent(RsvpNotificationEmail::class, function ($mail) {
            return $mail->hasTo('custom_notif@ayohadir.id') &&
                   $mail->guestName === 'Sahabat Dian' &&
                   $mail->attending === true &&
                   $mail->attendeeCount === 2;
        });
    }

    public function test_wishes_submission_triggers_email_notification(): void
    {
        Mail::fake();

        $user = User::factory()->create(['email' => 'wedding_owner2@ayohadir.id']);
        $wedding = Wedding::factory()->create([
            'user_id' => $user->id,
            'slug' => 'siti-and-fajar',
            'status' => 'published',
            'wishes_enabled' => true,
            'rsvp_notification_enabled' => true,
            'rsvp_notification_email' => null, // defaults to user email
        ]);

        $response = $this->postJson("/api/v1/public/invitations/{$wedding->slug}/wishes", [
            'name' => 'Tamu Doa',
            'message' => 'Semoga bahagia dunia akhirat.',
        ]);

        $response->assertStatus(200);

        Mail::assertSent(RsvpNotificationEmail::class, function ($mail) use ($user) {
            return $mail->hasTo($user->email) &&
                   $mail->guestName === 'Tamu Doa' &&
                   $mail->type === 'wish';
        });
    }
}
