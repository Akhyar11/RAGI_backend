<?php

namespace App\Services;

use App\Models\Menu;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class MenuService
{
    /**
     * Get menus for the currently authenticated user based on their active role.
     * 
     * @param string $module
     * @return Collection
     */
    public function getMyMenus(string $module = 'sso'): Collection
    {
        $user = Auth::user();
        
        if (!$user) {
            return collect();
        }

        // Cek apakah user adalah super-admin
        $isSuperAdmin = $user->roles()->where('slug', 'super-admin')->exists();

        // 1. Dapatkan daftar id permission yang dimiliki user dari relasi role_permissions (jika bukan super-admin)
        $permissionIds = [];
        if (!$isSuperAdmin) {
            $permissionIds = $user->roles()
                ->join('role_permissions', 'roles.id', '=', 'role_permissions.role_id')
                ->pluck('role_permissions.permission_id')
                ->unique()
                ->toArray();
        }

        // 2. Ambil menu root yang aktif, sesuai modul, dan user memiliki hak akses
        $menus = Menu::with(['children' => function($query) use ($permissionIds, $isSuperAdmin) {
                $query->where('is_active', true);
                if (!$isSuperAdmin) {
                    $query->where(function($q) use ($permissionIds) {
                        $q->whereNull('permission_id')
                          ->orWhereIn('permission_id', $permissionIds);
                    });
                }
                $query->orderBy('order_index');
            }])
            ->whereNull('parent_id')
            ->where('module', $module)
            ->where('is_active', true)
            ->when(!$isSuperAdmin, function ($query) use ($permissionIds) {
                $query->where(function($q) use ($permissionIds) {
                    $q->whereNull('permission_id')
                      ->orWhereIn('permission_id', $permissionIds);
                });
            })
            ->orderBy('order_index')
            ->get();

        return $menus;
    }
}
