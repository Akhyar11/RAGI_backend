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
            'roles.*' => 'exists:roles,id'
        ]);

        $user->roles()->sync($request->roles);

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
            'permissions.*' => 'exists:permissions,id'
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

        $query = Role::with('permissions')->orderBy('name');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where('name', 'like', "%{$search}%")
                  ->orWhere('slug', 'like', "%{$search}%");
        }

        $roles = $query->paginate($request->integer('per_page', 15));

        return response()->json([
            'status' => 'success',
            'message' => 'Role permissions retrieved successfully',
            'data' => $roles->items(),
            'meta' => [
                'current_page' => $roles->currentPage(),
                'per_page' => $roles->perPage(),
                'total' => $roles->total(),
            ]
        ]);
    }
}
