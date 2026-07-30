<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Role;
use App\Models\Menu;

class RoleMenuController extends Controller
{
    /**
     * Get menus assigned to a specific role.
     */
    public function getRoleMenus($roleId): JsonResponse
    {
        $role = Role::with('menus')->findOrFail($roleId);
        
        return response()->json([
            'status' => 'success',
            'data' => $role->menus
        ]);
    }

    /**
     * Assign menus to a specific role.
     */
    public function assignMenusToRole(Request $request, $roleId): JsonResponse
    {
        $request->validate([
            'menu_ids' => 'present|array',
            'menu_ids.*' => 'exists:menus,id'
        ]);

        $role = Role::findOrFail($roleId);
        $role->menus()->sync($request->menu_ids);

        return response()->json([
            'status' => 'success',
            'message' => 'Menus assigned successfully to role'
        ]);
    }
}
