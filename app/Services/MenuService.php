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
        $isSuperAdmin = $user->roles()->where('slug', 'superadmin')->exists();

        // 1. Dapatkan daftar id role yang dimiliki user
        $roleIds = $user->roles()->pluck('roles.id')->toArray();

        // 2. Ambil menu root yang aktif, sesuai modul, dan user memiliki hak akses melalui role
        $menus = Menu::with(['children' => function($query) use ($roleIds, $isSuperAdmin) {
                $query->where('is_active', true);
                if (!$isSuperAdmin) {
                    $query->whereHas('roles', function($q) use ($roleIds) {
                        $q->whereIn('roles.id', $roleIds);
                    });
                }
                $query->orderBy('order_index');
            }])
            ->whereNull('parent_id')
            ->where('module', $module)
            ->where('is_active', true)
            ->when(!$isSuperAdmin, function ($query) use ($roleIds) {
                $query->whereHas('roles', function($q) use ($roleIds) {
                    $q->whereIn('roles.id', $roleIds);
                });
            })
            ->orderBy('order_index')
            ->get();

        return $menus;
    }
}
