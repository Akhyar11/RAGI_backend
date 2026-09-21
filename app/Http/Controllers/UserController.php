<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Http\Requests\IAM\StoreUserRequest;
use App\Http\Requests\IAM\UpdateUserRequest;
use App\Services\IAM\UserService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function __construct(protected UserService $userService) {}

    /**
     * Check if current user is admin
     */
    private function ensureAdmin()
    {
        if (!auth()->user()->isSuperAdmin()) {
            abort(403, 'Anda tidak memiliki akses superadmin.');
        }
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $this->ensureAdmin();
        
        $query = User::query();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('username', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->filled('is_verified')) {
            $query->where('is_verified', filter_var($request->is_verified, FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->filled('name')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->name}%")
                  ->orWhere('username', 'like', "%{$request->name}%");
            });
        }

        if ($request->filled('role_id')) {
            $query->whereHas('roles', function ($q) use ($request) {
                $q->where('core_roles.id', $request->role_id);
            });
        }

        if ($request->filled('created_at')) {
            $query->whereDate('created_at', $request->created_at);
        }

        $allowedSorts = ['id', 'name', 'username', 'email', 'created_at', 'is_active', 'is_verified'];
        $sortBy = in_array($request->sort_by, $allowedSorts) ? $request->sort_by : 'created_at';
        $sortOrder = $request->sort_order === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortBy, $sortOrder);

        $perPage = min(100, $request->integer('per_page', $request->integer('limit', 15)));

        $users = $query->with('roles')->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'message' => 'Data user berhasil dimuat.',
            'data' => $users->items(),
            'meta' => [
                'current_page' => $users->currentPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
                'last_page' => $users->lastPage(),
                'from' => $users->firstItem(),
                'to' => $users->lastItem(),
            ],
            'filters' => [
                'search' => $request->search,
                'name' => $request->name,
                'is_active' => $request->is_active,
                'is_verified' => $request->is_verified,
                'role_id' => $request->role_id,
                'created_at' => $request->created_at,
                'sort_by' => $sortBy,
                'sort_order' => $sortOrder,
            ],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreUserRequest $request)
    {
        $this->ensureAdmin();
        
        $user = $this->userService->create($request->validated());

        return response()->json([
            'status' => 'success',
            'message' => 'User berhasil dibuat.',
            'data' => $user,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(User $user)
    {
        $this->ensureAdmin();

        return response()->json([
            'status' => 'success',
            'message' => 'Data user berhasil dimuat.',
            'data' => $user->load('roles'),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateUserRequest $request, User $user)
    {
        $this->ensureAdmin();

        $updatedUser = $this->userService->update($user, $request->validated());

        return response()->json([
            'status' => 'success',
            'message' => 'User berhasil diperbarui.',
            'data' => $updatedUser,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $user)
    {
        $this->ensureAdmin();
        $user->delete();
        
        return response()->json([
            'status' => 'success',
            'message' => 'User berhasil dihapus.',
            'data' => [
                'id' => $user->id,
                'deleted_at' => $user->deleted_at ? $user->deleted_at->toISOString() : now()->toISOString(),
            ],
        ]);
    }

    /**
     * Toggle the active status of the user.
     */
    public function toggleStatus(Request $request, $id)
    {
        $this->ensureAdmin();
        
        $user = User::findOrFail($id);
        
        $request->validate([
            'is_active' => 'required|boolean'
        ]);

        $user->update(['is_active' => $request->is_active]);

        $statusStr = $request->is_active ? 'diaktifkan' : 'dinonaktifkan';

        return response()->json([
            'status' => 'success',
            'message' => "User berhasil {$statusStr}.",
            'data' => $user
        ]);
    }

    /**
     * Change user password by admin.
     */
    public function changePassword(Request $request, $id)
    {
        $this->ensureAdmin();

        $user = User::findOrFail($id);

        $request->validate([
            'password' => 'required|string|min:6|confirmed',
        ]);

        $user->update([
            'password' => Hash::make($request->password),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => "Password pengguna {$user->username} berhasil diperbarui.",
            'data' => $user,
        ]);
    }

    /**
     * Impersonate a user (Merasuki pengguna).
     */
    public function impersonate(Request $request, $id)
    {
        $this->ensureAdmin();

        $targetUser = User::with('roles')->findOrFail($id);
        $admin = $request->user();

        $result = $this->userService->impersonate($targetUser, $admin);

        return response()->json([
            'status' => 'success',
            'message' => "Berhasil merasuki pengguna {$targetUser->name}.",
            'data' => $result,
        ]);
    }

    /**
     * Leave impersonation mode (Keluar dari mode rasuki).
     */
    public function leaveImpersonate(Request $request)
    {
        $user = $request->user();
        if ($user && $user->currentAccessToken()) {
            try {
                \App\Services\AuditLogService::record(
                    module: 'IAM',
                    action: 'logout',
                    tableName: 'core_users',
                    recordId: $user->id,
                    oldValues: ['impersonation_ended' => true],
                    newValues: null,
                    request: $request
                );
            } catch (\Throwable $e) {
                report($e);
            }

            $user->currentAccessToken()->delete();
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Sesi impersonasi berhasil diakhiri.',
            'data' => null,
        ]);
    }
}

