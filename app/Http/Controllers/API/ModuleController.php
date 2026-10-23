<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Module;
use App\Services\MenuService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class ModuleController extends Controller
{
    protected MenuService $menuService;

    public function __construct(MenuService $menuService)
    {
        $this->menuService = $menuService;
    }

    /**
     * Get accessible active modules for the currently authenticated user.
     */
    public function myModules(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthenticated'
            ], 401);
        }

        // Jika superadmin, seluruh modul aktif dapat diakses
        if ($user->isSuperAdmin()) {
            $modules = Module::where('is_active', true)
                ->orderBy('name')
                ->get();

            return response()->json([
                'status' => 'success',
                'message' => 'Berhasil mengambil daftar modul pengguna',
                'data' => $modules,
            ]);
        }

        // Ambil daftar modul yang memiliki setidaknya satu menu berizin untuk user dari MenuService
        $myMenus = $this->menuService->getMyMenus('all');
        $allowedModuleCodes = $myMenus->pluck('module')->filter()->unique()->values()->toArray();

        $modules = Module::where('is_active', true)
            ->whereIn('code', $allowedModuleCodes)
            ->orderBy('name')
            ->get();

        return response()->json([
            'status' => 'success',
            'message' => 'Berhasil mengambil daftar modul pengguna',
            'data' => $modules,
        ]);
    }

    /**
     * Get all modules.
     */
    public function index(Request $request): JsonResponse
    {
        $modules = Module::orderBy('name')->get();

        return response()->json([
            'status' => 'success',
            'data' => $modules
        ]);
    }

    /**
     * Store a newly created module.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:core_modules,code',
            'description' => 'nullable|string',
            'primary_color' => 'nullable|string|max:20',
            'is_active' => 'boolean'
        ]);

        $module = Module::create([
            'name' => $request->name,
            'code' => Str::slug($request->code),
            'description' => $request->description,
            'primary_color' => $request->primary_color ?? '#3b82f6',
            'is_active' => $request->is_active ?? true,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Module created successfully',
            'data' => $module
        ], 201);
    }

    /**
     * Update the specified module.
     */
    public function update(Request $request, Module $module): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:core_modules,code,' . $module->id,
            'description' => 'nullable|string',
            'primary_color' => 'nullable|string|max:20',
            'is_active' => 'boolean'
        ]);

        $module->update([
            'name' => $request->name,
            'code' => Str::slug($request->code),
            'description' => $request->description,
            'primary_color' => $request->primary_color ?? $module->primary_color ?? '#3b82f6',
            'is_active' => $request->is_active ?? $module->is_active,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Module updated successfully',
            'data' => $module
        ]);
    }

    /**
     * Remove the specified module.
     */
    public function destroy(Module $module): JsonResponse
    {
        if ($module->code === 'sso') {
            return response()->json([
                'status' => 'error',
                'message' => 'Cannot delete the core SSO module.'
            ], 403);
        }

        $module->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Module deleted successfully'
        ]);
    }

    /**
     * Toggle active status.
     */
    public function toggleActive(Module $module): JsonResponse
    {
        if ($module->code === 'sso' && $module->is_active) {
            return response()->json([
                'status' => 'error',
                'message' => 'Cannot deactivate the core SSO module.'
            ], 403);
        }

        $module->update([
            'is_active' => !$module->is_active
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Module status updated',
            'data' => $module
        ]);
    }
}
