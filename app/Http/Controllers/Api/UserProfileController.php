<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\PasswordOtpEmail;
use App\Models\PasswordResetOtp;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rules\Password;

class UserProfileController extends Controller
{
    /**
     * Get authenticated user profile details.
     */
    public function show(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'timezone' => $user->timezone ?? 'Asia/Jakarta',
                'locale' => $user->locale ?? 'id',
                'createdAt' => $user->created_at?->toIso8601String(),
                'weddingsCount' => $user->weddings()->count(),
            ],
            'meta' => [
                'timestamp' => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * Update user profile information.
     */
    public function update(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'timezone' => ['nullable', 'string', 'max:50'],
            'locale' => ['nullable', 'string', 'in:id,en'],
        ]);

        $user->update($validated);

        return response()->json([
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'timezone' => $user->timezone,
                'locale' => $user->locale,
                'message' => 'Profil berhasil diperbarui.',
            ],
            'meta' => [
                'timestamp' => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * Request a 6-digit OTP sent to the user's email for password change.
     */
    public function requestPasswordOtp(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        // 1. Cooldown rate-limiting: prevent spamming OTP requests (60 seconds cooldown)
        $latestOtp = PasswordResetOtp::where('user_id', $user->id)
            ->where('created_at', '>', now()->subSeconds(60))
            ->first();

        if ($latestOtp) {
            $secondsRemaining = 60 - now()->diffInSeconds($latestOtp->created_at);
            return response()->json([
                'error' => [
                    'code' => 'OTP_COOLDOWN',
                    'message' => "Mohon tunggu {$secondsRemaining} detik sebelum meminta kode OTP kembali.",
                    'secondsRemaining' => $secondsRemaining,
                ],
            ], 429);
        }

        // 2. Generate secure 6-digit OTP
        $otp = str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT);

        // Invalidate old unexpired OTPs
        PasswordResetOtp::where('user_id', $user->id)->delete();

        // 3. Store hashed OTP with 10-minute expiry
        PasswordResetOtp::create([
            'user_id' => $user->id,
            'email' => $user->email,
            'otp_hash' => Hash::make($otp),
            'expires_at' => now()->addMinutes(10),
            'attempts' => 0,
        ]);

        // 4. Send email safely
        try {
            Mail::to($user->email)->send(new PasswordOtpEmail($user, $otp, 10));
        } catch (\Throwable $e) {
            Log::error('Gagal mengirim email OTP: ' . $e->getMessage());
            return response()->json([
                'error' => [
                    'code' => 'EMAIL_SEND_FAILED',
                    'message' => 'Gagal mengirim email verifikasi OTP. Pastikan konfigurasi email server aktif.',
                ],
            ], 500);
        }

        return response()->json([
            'data' => [
                'message' => 'Kode OTP 6-digit telah dikirimkan ke email ' . $user->email . '.',
                'expiresInMinutes' => 10,
                'cooldownSeconds' => 60,
            ],
            'meta' => [
                'timestamp' => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * Confirm password change with current password, new password, and OTP code.
     */
    public function confirmPasswordChange(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'new_password' => ['required', 'string', 'confirmed', Password::min(8)],
            'otp' => ['required', 'string', 'size:6'],
        ], [
            'current_password.required' => 'Kata sandi saat ini wajib diisi.',
            'new_password.required' => 'Kata sandi baru wajib diisi.',
            'new_password.min' => 'Kata sandi baru minimal 8 karakter.',
            'new_password.confirmed' => 'Konfirmasi kata sandi baru tidak cocok.',
            'otp.required' => 'Kode OTP verifikasi wajib diisi.',
            'otp.size' => 'Kode OTP harus berupa 6 digit angka.',
        ]);

        // 1. Verify current password
        if (!Hash::check($validated['current_password'], $user->password)) {
            return response()->json([
                'error' => [
                    'code' => 'INVALID_CURRENT_PASSWORD',
                    'message' => 'Kata sandi saat ini yang Anda masukkan salah.',
                ],
            ], 422);
        }

        // 2. Find active OTP
        $otpRecord = PasswordResetOtp::where('user_id', $user->id)
            ->where('expires_at', '>', now())
            ->latest()
            ->first();

        if (!$otpRecord) {
            return response()->json([
                'error' => [
                    'code' => 'OTP_EXPIRED_OR_NOT_FOUND',
                    'message' => 'Kode OTP tidak ditemukan atau telah kedaluwarsa. Silakan minta kode OTP baru.',
                ],
            ], 422);
        }

        // 3. Brute-force protection: max 5 attempts
        if ($otpRecord->attempts >= 5) {
            $otpRecord->delete();
            return response()->json([
                'error' => [
                    'code' => 'OTP_ATTEMPTS_EXCEEDED',
                    'message' => 'Batas percobaan verifikasi telah terlampaui. Silakan minta kode OTP baru.',
                ],
            ], 422);
        }

        $otpRecord->increment('attempts');

        // 4. Verify OTP code
        if (!Hash::check($validated['otp'], $otpRecord->otp_hash)) {
            $remaining = 5 - $otpRecord->attempts;
            return response()->json([
                'error' => [
                    'code' => 'INVALID_OTP',
                    'message' => "Kode OTP yang Anda masukkan salah. Sisa percobaan: {$remaining} kali.",
                ],
            ], 422);
        }

        // 5. Update user password
        $user->password = Hash::make($validated['new_password']);
        $user->save();

        // 6. Delete all OTPs for user
        PasswordResetOtp::where('user_id', $user->id)->delete();

        Log::info("User ID {$user->id} successfully changed password via email OTP.");

        return response()->json([
            'data' => [
                'message' => 'Kata sandi berhasil diperbarui. Silakan gunakan kata sandi baru untuk login.',
            ],
            'meta' => [
                'timestamp' => now()->toIso8601String(),
            ],
        ]);
    }
}
