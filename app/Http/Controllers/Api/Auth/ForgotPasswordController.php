<?php

namespace App\Http\Controllers\Api\Auth;

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

class ForgotPasswordController extends Controller
{
    /**
     * Request a 6-digit OTP sent to the user's email for forgot password reset.
     */
    public function requestOtp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
        ], [
            'email.required' => 'Alamat email wajib diisi.',
            'email.email' => 'Format alamat email tidak valid.',
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (!$user) {
            return response()->json([
                'error' => [
                    'code' => 'USER_NOT_FOUND',
                    'message' => 'Akun dengan alamat email tersebut tidak ditemukan.',
                ],
            ], 404);
        }

        // 1. Cooldown rate-limiting: 60 seconds
        $latestOtp = PasswordResetOtp::where('user_id', $user->id)
            ->where('created_at', '>', now()->subSeconds(60))
            ->first();

        if ($latestOtp) {
            $secondsRemaining = max(1, 60 - now()->diffInSeconds($latestOtp->created_at));
            return response()->json([
                'error' => [
                    'code' => 'OTP_COOLDOWN',
                    'message' => "Mohon tunggu {$secondsRemaining} detik sebelum meminta kode OTP kembali.",
                    'secondsRemaining' => $secondsRemaining,
                ],
            ], 429);
        }

        // 2. Generate secure 6-digit numeric OTP
        $otp = str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT);

        // Invalidate old OTPs for this user
        PasswordResetOtp::where('user_id', $user->id)->delete();

        // 3. Store hashed OTP with 10-minute expiry
        PasswordResetOtp::create([
            'user_id' => $user->id,
            'email' => $user->email,
            'otp_hash' => Hash::make($otp),
            'expires_at' => now()->addMinutes(10),
            'attempts' => 0,
        ]);

        // 4. Send email safely via Resend SMTP
        try {
            Mail::to($user->email)->send(new PasswordOtpEmail($user, $otp, 10));
        } catch (\Throwable $e) {
            Log::error('Gagal mengirim email OTP lupa kata sandi: ' . $e->getMessage());
            return response()->json([
                'error' => [
                    'code' => 'EMAIL_SEND_FAILED',
                    'message' => 'Gagal mengirim email verifikasi OTP. Pastikan konfigurasi server email aktif.',
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
     * Reset user password using verified OTP code.
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email'],
            'otp' => ['required', 'string', 'size:6'],
            'password' => ['required', 'string', 'confirmed', Password::min(5)],
        ], [
            'email.required' => 'Alamat email wajib diisi.',
            'otp.required' => 'Kode OTP verifikasi wajib diisi.',
            'otp.size' => 'Kode OTP harus berupa 6 digit angka.',
            'password.required' => 'Kata sandi baru wajib diisi.',
            'password.min' => 'Kata sandi baru minimal 5 karakter.',
            'password.confirmed' => 'Konfirmasi kata sandi baru tidak cocok.',
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (!$user) {
            return response()->json([
                'error' => [
                    'code' => 'USER_NOT_FOUND',
                    'message' => 'Akun dengan alamat email tersebut tidak ditemukan.',
                ],
            ], 404);
        }

        // 1. Find latest OTP record
        $otpRecord = PasswordResetOtp::where('user_id', $user->id)->latest()->first();

        if (!$otpRecord || $otpRecord->expires_at->isPast()) {
            if ($otpRecord) {
                $otpRecord->delete();
            }
            return response()->json([
                'error' => [
                    'code' => 'OTP_EXPIRED',
                    'message' => 'Kode OTP telah kedaluwarsa atau tidak valid. Silakan minta kode OTP baru.',
                ],
            ], 422);
        }

        // 2. Brute-force protection: max 5 attempts
        if ($otpRecord->attempts >= 5) {
            $otpRecord->delete();
            return response()->json([
                'error' => [
                    'code' => 'MAX_ATTEMPTS_EXCEEDED',
                    'message' => 'Batas percobaan memasukkan OTP telah terlampaui. Silakan minta kode OTP baru.',
                ],
            ], 422);
        }

        // 3. Verify OTP hash
        if (!Hash::check($validated['otp'], $otpRecord->otp_hash)) {
            $otpRecord->increment('attempts');
            $remaining = 5 - $otpRecord->attempts;
            return response()->json([
                'error' => [
                    'code' => 'INVALID_OTP',
                    'message' => "Kode OTP yang Anda masukkan salah. Sisa kesempatan: {$remaining} kali.",
                ],
            ], 422);
        }

        // 4. Update user's password
        $user->password = Hash::make($validated['password']);
        $user->save();

        // 5. Delete used OTP
        $otpRecord->delete();

        return response()->json([
            'data' => [
                'message' => 'Kata sandi Anda berhasil diatur ulang. Silakan masuk menggunakan kata sandi baru.',
            ],
            'meta' => [
                'timestamp' => now()->toIso8601String(),
            ],
        ]);
    }
}
