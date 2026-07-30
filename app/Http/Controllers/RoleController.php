<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Http\Requests\StoreRoleRequest;
use App\Http\Requests\UpdateRoleRequest;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Role::class);

        $perPage = min(100, $request->integer('per_page', 15));
        $query = Role::query()->with('permissions');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $allowedSortColumns = ['created_at', 'updated_at', 'name'];
        $sortBy = in_array($request->sort_by, $allowedSortColumns) ? $request->sort_by : 'created_at';
        $sortOrder = $request->sort_order === 'asc' ? 'asc' : 'desc';
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
                'sort_order' => $sortOrder
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

        if ($role->slug === 'superadmin' || $role->slug === 'admin' || $role->users()->exists()) {
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
