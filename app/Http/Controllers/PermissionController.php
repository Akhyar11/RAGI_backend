<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use Illuminate\Http\Request;

class PermissionController extends Controller
{
    public function index(Request $request)
    {
        // Hanya bisa diakses jika user punya permission melihat roles atau user
        $this->authorize('viewAny', App\Models\Role::class);

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
}
