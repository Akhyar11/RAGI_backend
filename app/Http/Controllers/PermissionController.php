<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use Illuminate\Http\Request;

class PermissionController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Permission::class);

        $perPage = min(100, $request->integer('per_page', 50));
        $query = Permission::query();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('module', 'like', "%{$search}%");
            });
        }

        if ($request->filled('module')) {
            $query->where('module', $request->module);
        }

        $allowedSortColumns = ['module', 'name'];
        $sortBy = in_array($request->sort_by, $allowedSortColumns) ? $request->sort_by : 'module';
        $sortOrder = $request->sort_order === 'desc' ? 'desc' : 'asc';
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
                'module' => $request->module,
                'sort_by' => $sortBy,
                'sort_order' => $sortOrder
            ]
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', Permission::class);

        $request->validate([
            'module' => 'required|string|max:100',
            'action' => 'required|string|max:50',
            'name' => 'required|string|max:150',
            'slug' => 'required|string|max:150|unique:permissions',
            'description' => 'nullable|string',
        ]);

        $permission = Permission::create($request->only(['module', 'action', 'name', 'slug', 'description']));

        return response()->json([
            'status' => 'success',
            'message' => 'Permission created successfully',
            'data' => $permission
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $permission = Permission::findOrFail($id);
        $this->authorize('update', $permission);

        $request->validate([
            'module' => 'required|string|max:100',
            'action' => 'required|string|max:50',
            'name' => 'required|string|max:150',
            'slug' => 'required|string|max:150|unique:permissions,slug,' . $id,
            'description' => 'nullable|string',
        ]);

        $permission->update($request->only(['module', 'action', 'name', 'slug', 'description']));

        return response()->json([
            'status' => 'success',
            'message' => 'Permission updated successfully',
            'data' => $permission
        ]);
    }

    public function destroy($id)
    {
        $permission = Permission::findOrFail($id);
        $this->authorize('delete', $permission);

        $permission->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Permission deleted successfully'
        ]);
    }
}
