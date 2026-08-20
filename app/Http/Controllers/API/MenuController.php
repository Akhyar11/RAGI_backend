<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\MenuService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Menu;
use Illuminate\Support\Facades\Validator;

class MenuController extends Controller
{
    protected MenuService $menuService;

    public function __construct(MenuService $menuService)
    {
        $this->menuService = $menuService;
    }

    /**
     * Get dynamic menus for the logged-in user based on module.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getMyMenus(Request $request): JsonResponse
    {
        $module = $request->query('module', 'sso');
        
        $menus = $this->menuService->getMyMenus($module);

        return response()->json([
            'status' => 'success',
            'message' => 'Menus retrieved successfully',
            'data' => $menus
        ]);
    }

    /**
     * Get all menus for admin management (CRUD).
     */
    public function index(Request $request): JsonResponse
    {
        // Pengecekan authorization bisa dilakukan di middleware atau form request
        $module = $request->query('module', 'sso');
        
        $menus = Menu::with('permission', 'children')
            ->whereNull('parent_id')
            ->where('module', $module)
            ->orderBy('order_index')
            ->get();

        return response()->json([
            'status' => 'success',
            'message' => 'All menus retrieved successfully',
            'data' => $menus
        ]);
    }

    /**
     * Store a newly created menu.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'url' => 'required|string|max:255',
            'icon' => 'nullable|string|max:255',
            'module' => 'required|string|in:sso,simpeg,sippm,sikeu,spmb,SPMB,sinapra,siakad',
            'parent_id' => 'nullable|exists:menus,id',
            'permission_id' => 'nullable|exists:permissions,id',
            'order_index' => 'integer',
            'is_active' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation Error',
                'errors' => $validator->errors()
            ], 422);
        }

        $menu = Menu::create($validator->validated());

        return response()->json([
            'status' => 'success',
            'message' => 'Menu created successfully',
            'data' => $menu
        ], 201);
    }

    /**
     * Update the specified menu.
     */
    public function update(Request $request, Menu $menu): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'url' => 'required|string|max:255',
            'icon' => 'nullable|string|max:255',
            'module' => 'required|string|in:sso,simpeg,sippm,sikeu,spmb,SPMB,sinapra,siakad',
            'parent_id' => 'nullable|exists:menus,id',
            'permission_id' => 'nullable|exists:permissions,id',
            'order_index' => 'integer',
            'is_active' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation Error',
                'errors' => $validator->errors()
            ], 422);
        }

        $menu->update($validator->validated());

        return response()->json([
            'status' => 'success',
            'message' => 'Menu updated successfully',
            'data' => $menu
        ]);
    }

    /**
     * Remove the specified menu.
     */
    public function destroy(Menu $menu): JsonResponse
    {
        $menu->delete(); // Hard delete based on schema

        return response()->json([
            'status' => 'success',
            'message' => 'Menu deleted successfully'
        ]);
    }

    /**
     * Toggle the active status of a menu.
     */
    public function toggleActive(Menu $menu): JsonResponse
    {
        $menu->is_active = !$menu->is_active;
        $menu->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Menu status updated successfully',
            'data' => $menu
        ]);
    }
}
