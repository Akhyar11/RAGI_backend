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

        // Cek apakah user adalah superadmin atau admin
        $superAdminRole = \App\Models\SystemSetting::where('key', 'superadmin_role')->value('value') ?? 'superadmin';
        $isSuperAdmin = $user->roles()->whereIn('slug', ['superadmin', 'admin', $superAdminRole])->exists();

        // 1. Dapatkan daftar id role yang dimiliki user
        $roleIds = $user->roles()->pluck('roles.id')->toArray();

        // 2. Ambil menu root yang aktif, sesuai modul, dan user memiliki hak akses melalui role_menus pivot (langsung atau via child)
        $menus = Menu::with(['children' => function($query) use ($roleIds, $isSuperAdmin) {
                $query->where('is_active', true);
                if (!$isSuperAdmin) {
                    $query->whereHas('roles', function($r) use ($roleIds) {
                        $r->whereIn('roles.id', $roleIds);
                    });
                }
                $query->orderBy('order_index');
            }])
            ->whereNull('parent_id')
            ->where('module', $module)
            ->where('is_active', true)
            ->when(!$isSuperAdmin, function ($query) use ($roleIds) {
                $query->where(function($q) use ($roleIds) {
                    $q->whereHas('roles', function($r) use ($roleIds) {
                        $r->whereIn('roles.id', $roleIds);
                    })
                    ->orWhereHas('children', function($cq) use ($roleIds) {
                        $cq->where('is_active', true)
                           ->whereHas('roles', function($r) use ($roleIds) {
                               $r->whereIn('roles.id', $roleIds);
                           });
                    });
                });
            })
            ->orderBy('order_index')
            ->get();

        return $menus;
    }

    /**
     * Check if a user has access to a specific menu URL based on their roles in DB menu_role pivot table.
     */
    public static function hasAccess($user, string $menuUrl): bool
    {
        if (!$user) {
            return false;
        }

        $superAdminRole = \App\Models\SystemSetting::where('key', 'superadmin_role')->value('value') ?? 'superadmin';
        if ($user->roles()->whereIn('slug', ['superadmin', 'admin', $superAdminRole])->exists()) {
            return true;
        }

        $menu = Menu::where('url', $menuUrl)->first();
        if (!$menu) {
            return true;
        }

        $roleIds = $user->roles()->pluck('roles.id')->toArray();
        return $menu->roles()->whereIn('roles.id', $roleIds)->exists();
    }
}
