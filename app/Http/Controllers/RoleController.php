<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Http\Requests\StoreRoleRequest;
use App\Http\Requests\UpdateRoleRequest;
use App\Services\IAM\RestrictedRoleService;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    public function __construct(private RestrictedRoleService $restrictedRoles) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', Role::class);

        $perPage = min(100, $request->integer('per_page', $request->integer('limit', 15)));
        $query = Role::query()->with('permissions');

        // Sembunyikan roles terestriksi dari non-pengelola IAM.
        $restrictedExcluded = $this->restrictedRoles->applyVisibilityScope($query, $request->user());

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('slug', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($request->filled('name')) {
            $query->where('name', 'like', "%{$request->name}%");
        }

        if ($request->filled('slug')) {
            $query->where('slug', 'like', "%{$request->slug}%");
        }

        if ($request->filled('description')) {
            $query->where('description', 'like', "%{$request->description}%");
        }

        if ($request->filled('created_at')) {
            $query->whereDate('created_at', $request->created_at);
        }

        $allowedSortColumns = ['id', 'name', 'slug', 'description', 'created_at', 'updated_at'];
        $sortByInput = $request->input('sort_by', $request->input('order_by', 'created_at'));
        $sortBy = in_array($sortByInput, $allowedSortColumns) ? $sortByInput : 'created_at';
        $sortOrderInput = $request->input('sort_order', $request->input('order_dir', 'desc'));
        $sortOrder = strtolower($sortOrderInput) === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortBy, $sortOrder);

        $data = $query->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'message' => 'Data retrieved successfully',
            'data' => $data->items(),
            'meta' => [
                'current_page' => $data->currentPage(),
                'per_page' => $data->perPage(),
                'total' => $data->total(),
                'last_page' => $data->lastPage(),
                'from' => $data->firstItem(),
                'to' => $data->lastItem(),
            ],
            'filters' => [
                'search' => $request->search,
                'sort_by' => $sortBy,
                'sort_order' => $sortOrder,
                'restricted_roles_excluded' => $restrictedExcluded
            ]
        ]);
    }

    public function store(StoreRoleRequest $request)
    {
        $this->authorize('create', Role::class);

        $role = Role::create($request->validated());

        if ($request->has('permissions')) {
            $role->permissions()->sync($request->permissions);
        }

        $role->load('permissions');

        return response()->json([
            'status' => 'success',
            'message' => 'Data created successfully',
            'data' => $role
        ], 201);
    }

    public function show(Role $role)
    {
        $this->authorize('view', clone $role);
        $role->load('permissions');

        return response()->json([
            'status' => 'success',
            'message' => 'Data retrieved successfully',
            'data' => $role
        ]);
    }

    public function update(UpdateRoleRequest $request, Role $role)
    {
        $this->authorize('update', clone $role);

        $role->update($request->validated());

        if ($request->has('permissions')) {
            $role->permissions()->sync($request->permissions);
        }

        $role->load('permissions');

        return response()->json([
            'status' => 'success',
            'message' => 'Data updated successfully',
            'data' => $role
        ]);
    }

    public function destroy(Role $role)
    {
        $this->authorize('delete', clone $role);

        // Protect root roles and roles with associated users
        if ($role->id === 1 || $role->id === 2 || $role->users()->exists()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Role tidak dapat dihapus karena sedang digunakan atau merupakan role bawaan sistem.'
            ], 400);
        }

        $role->permissions()->detach();
        $role->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Data deleted successfully'
        ]);
    }
}
