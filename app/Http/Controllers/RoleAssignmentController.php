<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Role;
use Illuminate\Support\Facades\Gate;

class RoleAssignmentController extends Controller
{
    public function assignRoles(Request $request, $id)
    {
        Gate::authorize('create', Role::class); // using role creation as authorization proxy for admin

        $user = User::findOrFail($id);

        $request->validate([
            'roles' => 'required|array',
            'roles.*' => 'exists:core_roles,id'
        ]);

        $user->roles()->sync($request->roles);

        try {
            (new \App\Services\IAM\UserService())->syncPegawaiForUser($user);
        } catch (\Throwable $e) {
            report($e);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Roles assigned successfully',
            'data' => $user->load('roles')
        ]);
    }

    public function assignPermissions(Request $request, $id)
    {
        Gate::authorize('update', Role::findOrFail($id));

        $role = Role::findOrFail($id);

        $request->validate([
            'permissions' => 'required|array',
            'permissions.*' => 'exists:core_permissions,id'
        ]);

        $role->permissions()->sync($request->permissions);

        return response()->json([
            'status' => 'success',
            'message' => 'Permissions assigned successfully',
            'data' => $role->load('permissions')
        ]);
    }

    public function getUserRoles(Request $request)
    {
        Gate::authorize('viewAny', Role::class);

        $query = User::with('roles')->orderBy('username');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where('username', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
        }

        $users = $query->paginate($request->integer('per_page', 15));

        return response()->json([
            'status' => 'success',
            'message' => 'User roles retrieved successfully',
            'data' => $users->items(),
            'meta' => [
                'current_page' => $users->currentPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
            ]
        ]);
    }

    public function getRolePermissions(Request $request)
    {
        Gate::authorize('viewAny', Role::class);

        $query = Role::with(['permissions' => function ($q) {
            $q->orderBy('module')->orderBy('name');
        }]);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        if ($request->filled('role_id')) {
            $query->where('id', $request->integer('role_id'));
        }

        $allowedSorts = ['created_at', 'updated_at', 'name'];
        $sortBy = in_array($request->query('sort_by'), $allowedSorts, true)
            ? $request->query('sort_by')
            : 'created_at';
        $sortOrder = $request->query('sort_order') === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortBy, $sortOrder);

        $perPage = min(100, $request->integer('per_page', 15));
        $roles = $query->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'message' => 'Role permissions retrieved successfully',
            'data' => $roles->items(),
            'meta' => [
                'current_page' => $roles->currentPage(),
                'per_page' => $roles->perPage(),
                'total' => $roles->total(),
                'last_page' => $roles->lastPage(),
                'from' => $roles->firstItem(),
                'to' => $roles->lastItem(),
            ],
            'filters' => [
                'search' => $request->query('search'),
                'role_id' => $request->query('role_id'),
                'sort_by' => $sortBy,
                'sort_order' => $sortOrder,
            ],
        ]);
    }
}
