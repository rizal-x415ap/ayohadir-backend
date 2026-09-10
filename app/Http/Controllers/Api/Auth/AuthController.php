<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Mail\WelcomeEmail;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Register a new user account.
     */
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'confirmed', Password::min(5)],
        ], [
            'password.min' => 'Kata sandi minimal 5 karakter.',
            'password.confirmed' => 'Konfirmasi kata sandi tidak cocok.',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => 'user',
        ]);

        Auth::login($user);

        // Send Welcome Email (Non-blocking resilience)
        try {
            Mail::to($user->email)->send(new WelcomeEmail($user));
        } catch (\Throwable $e) {
            Log::warning('Failed to send welcome email upon registration: ' . $e->getMessage());
        }

        return response()->json([
            'data' => [
                'user' => $this->transformUser($user),
            ],
            'meta' => [
                'timestamp' => now()->toIso8601String(),
            ],
        ], 201);
    }

    /**
     * Authenticate and log in user.
     */
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (!Auth::attempt($credentials, $request->boolean('remember', true))) {
            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        /** @var User $user */
        $user = Auth::user();

        return response()->json([
            'data' => [
                'user' => $this->transformUser($user),
            ],
            'meta' => [
                'timestamp' => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * Return authenticated user profile.
     */
    public function me(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'error' => [
                    'code' => 'UNAUTHORIZED',
                    'message' => 'Unauthenticated session.',
                ],
            ], 401);
        }

        $transformed = $this->transformUser($user);
        $transformed['isImpersonating'] = $request->hasSession() && $request->session()->has('impersonator_id');

        return response()->json([
            'data' => [
                'user' => $transformed,
            ],
            'meta' => [
                'timestamp' => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * Stop impersonating and return to administrator account.
     */
    public function stopImpersonate(Request $request): JsonResponse
    {
        if (!$request->hasSession() || !$request->session()->has('impersonator_id')) {
            return response()->json([
                'error' => [
                    'code' => 'NOT_IMPERSONATING',
                    'message' => 'Anda tidak sedang berada dalam sesi impersonasi.',
                ],
            ], 400);
        }

        $adminId = $request->session()->pull('impersonator_id');
        $admin = User::find($adminId);

        if (!$admin || !$admin->isAdmin()) {
            return response()->json([
                'error' => [
                    'code' => 'INVALID_IMPERSONATOR',
                    'message' => 'Akun administrator asal tidak ditemukan.',
                ],
            ], 403);
        }

        Auth::guard('web')->login($admin);

        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        $transformed = $this->transformUser($admin);
        $transformed['isImpersonating'] = false;

        return response()->json([
            'message' => 'Kembali ke sesi administrator.',
            'data' => [
                'user' => $transformed,
            ],
            'meta' => [
                'timestamp' => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * Log out user and invalidate session.
     */
    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->json([
            'data' => [
                'message' => 'Successfully logged out.',
            ],
            'meta' => [
                'timestamp' => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * Transform user model for API response envelope.
     */
    protected function transformUser(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'timezone' => $user->timezone,
            'locale' => $user->locale,
            'emailVerifiedAt' => $user->email_verified_at?->toIso8601String(),
            'createdAt' => $user->created_at->toIso8601String(),
            'updatedAt' => $user->updated_at->toIso8601String(),
        ];
    }
}
