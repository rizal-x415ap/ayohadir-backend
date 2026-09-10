<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AdminUserController extends Controller
{
    /**
     * Display a listing of users with search and pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $query = User::withCount('weddings')
            ->latest('id');

        if ($request->filled('search')) {
            $search = trim($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->filled('role')) {
            $query->where('role', $request->input('role'));
        }

        $users = $query->paginate($request->input('per_page', 15));

        return response()->json($users);
    }

    /**
     * Store a newly created user as Administrator.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email',
            'password' => 'required|string|min:8',
            'role' => 'required|in:admin,user',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
        ]);

        return response()->json([
            'message' => 'Pengguna baru berhasil ditambahkan.',
            'data' => $user,
        ], 201);
    }

    /**
     * Display the specified user profile.
     */
    public function show(User $user): JsonResponse
    {
        $user->load(['weddings' => function ($q) {
            $q->latest('id')->with('template:id,name');
        }]);
        $user->loadCount('weddings');

        return response()->json([
            'data' => $user,
        ]);
    }

    /**
     * Update the specified user data as Administrator.
     */
    public function update(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'role' => 'required|in:admin,user',
            'password' => 'nullable|string|min:8',
        ]);

        $user->name = $validated['name'];
        $user->email = $validated['email'];
        $user->role = $validated['role'];

        if (!empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }

        $user->save();

        return response()->json([
            'message' => 'Data pengguna berhasil diperbarui.',
            'data' => $user,
        ]);
    }

    /**
     * Remove the specified user.
     */
    public function destroy(Request $request, User $user): JsonResponse
    {
        if ($request->user()->id === $user->id) {
            return response()->json([
                'message' => 'Anda tidak dapat menghapus akun Anda sendiri.',
            ], 422);
        }

        $user->delete();

        return response()->json([
            'message' => 'Pengguna berhasil dihapus.',
        ]);
    }

    /**
     * Impersonate a user as administrator.
     */
    public function impersonate(Request $request, User $user): JsonResponse
    {
        $admin = $request->user();

        if ($admin->id === $user->id) {
            return response()->json([
                'message' => 'Anda tidak dapat mengimpersonasi akun Anda sendiri.',
            ], 422);
        }

        // Store original admin ID in session
        if ($request->hasSession()) {
            $request->session()->regenerate();
            $request->session()->put('impersonator_id', $admin->id);
        }

        // Login as the target user
        Auth::guard('web')->login($user);

        return response()->json([
            'message' => "Berhasil masuk sebagai pengguna {$user->name}.",
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'timezone' => $user->timezone,
                    'locale' => $user->locale,
                    'emailVerifiedAt' => $user->email_verified_at?->toIso8601String(),
                    'createdAt' => $user->created_at->toIso8601String(),
                    'updatedAt' => $user->updated_at->toIso8601String(),
                    'isImpersonating' => true,
                ],
            ],
        ]);
    }
}
