<?php

namespace Tests\Feature;

use App\Mail\PasswordOtpEmail;
use App\Models\PasswordResetOtp;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ForgotPasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_registration_accepts_five_character_password(): void
    {
        Mail::fake();

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Budi 5 Karakter',
            'email' => 'budi5@example.com',
            'password' => '12345',
            'password_confirmation' => '12345',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.user.email', 'budi5@example.com');

        $this->assertDatabaseHas('users', [
            'email' => 'budi5@example.com',
        ]);
    }

    public function test_user_registration_rejects_less_than_five_character_password(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Budi 4 Karakter',
            'email' => 'budi4@example.com',
            'password' => '1234',
            'password_confirmation' => '1234',
        ]);

        $response->assertStatus(422);
    }

    public function test_request_otp_sends_email_and_stores_record(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'email' => 'testuser@example.com',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->postJson('/api/v1/auth/forgot-password/request-otp', [
            'email' => 'testuser@example.com',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.cooldownSeconds', 60);

        Mail::assertSent(PasswordOtpEmail::class, function ($mail) use ($user) {
            return $mail->hasTo($user->email) && strlen($mail->otp) === 6;
        });

        $this->assertDatabaseHas('password_reset_otps', [
            'user_id' => $user->id,
            'email' => 'testuser@example.com',
        ]);
    }

    public function test_request_otp_fails_if_email_not_found(): void
    {
        $response = $this->postJson('/api/v1/auth/forgot-password/request-otp', [
            'email' => 'nonexistent@example.com',
        ]);

        $response->assertStatus(404)
            ->assertJsonPath('error.code', 'USER_NOT_FOUND');
    }

    public function test_request_otp_cooldown_rate_limiting(): void
    {
        Mail::fake();

        $user = User::factory()->create(['email' => 'cooldown@example.com']);

        // First request succeeds
        $this->postJson('/api/v1/auth/forgot-password/request-otp', [
            'email' => 'cooldown@example.com',
        ])->assertStatus(200);

        // Immediate second request triggers 429
        $second = $this->postJson('/api/v1/auth/forgot-password/request-otp', [
            'email' => 'cooldown@example.com',
        ]);

        $second->assertStatus(429)
            ->assertJsonPath('error.code', 'OTP_COOLDOWN');
    }

    public function test_reset_password_with_valid_otp_updates_password_and_allows_login(): void
    {
        $user = User::factory()->create([
            'email' => 'resetme@example.com',
            'password' => Hash::make('oldpass'),
        ]);

        PasswordResetOtp::create([
            'user_id' => $user->id,
            'email' => $user->email,
            'otp_hash' => Hash::make('654321'),
            'expires_at' => now()->addMinutes(10),
            'attempts' => 0,
        ]);

        $response = $this->postJson('/api/v1/auth/forgot-password/reset', [
            'email' => 'resetme@example.com',
            'otp' => '654321',
            'password' => '54321',
            'password_confirmation' => '54321',
        ]);

        $response->assertStatus(200);

        // OTP record should be deleted
        $this->assertDatabaseMissing('password_reset_otps', [
            'user_id' => $user->id,
        ]);

        // Verify user can now log in with the new 5-character password
        $loginRes = $this->postJson('/api/v1/auth/login', [
            'email' => 'resetme@example.com',
            'password' => '54321',
        ]);

        $loginRes->assertStatus(200)
            ->assertJsonPath('data.user.email', 'resetme@example.com');
    }

    public function test_reset_password_with_invalid_otp_increments_attempts(): void
    {
        $user = User::factory()->create(['email' => 'wrongotp@example.com']);

        $otp = PasswordResetOtp::create([
            'user_id' => $user->id,
            'email' => $user->email,
            'otp_hash' => Hash::make('112233'),
            'expires_at' => now()->addMinutes(10),
            'attempts' => 0,
        ]);

        $response = $this->postJson('/api/v1/auth/forgot-password/reset', [
            'email' => 'wrongotp@example.com',
            'otp' => '999999',
            'password' => 'newpass5',
            'password_confirmation' => 'newpass5',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'INVALID_OTP');

        $this->assertEquals(1, $otp->fresh()->attempts);
    }

    public function test_reset_password_rejects_password_shorter_than_five_chars(): void
    {
        $user = User::factory()->create(['email' => 'shortpass@example.com']);

        PasswordResetOtp::create([
            'user_id' => $user->id,
            'email' => $user->email,
            'otp_hash' => Hash::make('123123'),
            'expires_at' => now()->addMinutes(10),
            'attempts' => 0,
        ]);

        $response = $this->postJson('/api/v1/auth/forgot-password/reset', [
            'email' => 'shortpass@example.com',
            'otp' => '123123',
            'password' => '1234',
            'password_confirmation' => '1234',
        ]);

        $response->assertStatus(422);
    }
}
