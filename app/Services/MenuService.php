<?php

namespace App\Services;

use App\Models\Menu;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class MenuService
{
    /**
     * Get menus for the currently authenticated user based on their active role and permissions.
     * 
     * @param string $module
     * @return Collection
     */
    public function getMyMenus(string $module): Collection
    {
        $user = Auth::user();
        
        if (!$user) {
            return collect();
        }

        $isSuperAdmin = $user->isSuperAdmin();

        // 1. Dapatkan daftar id role dan slug permissions yang dimiliki user
        $roleIds = $user->roles()->pluck('core_roles.id')->toArray();
        $permissionSlugs = $user->roles()
            ->with('permissions')
            ->get()
            ->pluck('permissions')
            ->flatten()
            ->pluck('slug')
            ->unique()
            ->toArray();

        // 2. Query builder
        $query = Menu::with(['permission', 'children' => function($cq) use ($roleIds, $permissionSlugs, $isSuperAdmin) {
            $cq->with('permission')->where('is_active', true);
            if (!$isSuperAdmin) {
                $cq->where(function($q) use ($roleIds, $permissionSlugs) {
                    $q->where(function($permQ) use ($permissionSlugs) {
                        $permQ->whereNotNull('permission_id')
                              ->whereHas('permission', function($pq) use ($permissionSlugs) {
                                  $pq->whereIn('slug', $permissionSlugs);
                              });
                    })
                    ->orWhere(function($roleQ) use ($roleIds) {
                        $roleQ->whereNull('permission_id')
                              ->whereHas('roles', function($rq) use ($roleIds) {
                                  $rq->whereIn('core_roles.id', $roleIds);
                              });
                    })
                    ->orWhere(function($pub) {
                        $pub->whereNull('permission_id')
                            ->whereDoesntHave('roles');
                    });
                });
            }
            $cq->orderBy('order_index');
        }])
        ->whereNull('parent_id')
        ->when($module !== 'all', function ($q) use ($module) {
            $q->where('module', $module);
        })
        ->where('is_active', true);

        if (!$isSuperAdmin) {
            $query->where(function($q) use ($roleIds, $permissionSlugs) {
                // 1. Root menu memiliki permission yang dimiliki user
                $q->where(function($permQ) use ($permissionSlugs) {
                    $permQ->whereNotNull('permission_id')
                          ->whereHas('permission', function($pq) use ($permissionSlugs) {
                              $pq->whereIn('slug', $permissionSlugs);
                          });
                })
                // 2. ATAU root menu tanpa permission_id tetapi memiliki role yang cocok
                ->orWhere(function($roleQ) use ($roleIds) {
                    $roleQ->whereNull('permission_id')
                          ->whereHas('roles', function($rq) use ($roleIds) {
                              $rq->whereIn('core_roles.id', $roleIds);
                          });
                })
                // 3. ATAU root menu adalah grup hierarki yang memiliki child yang berizin
                ->orWhere(function($grp) use ($roleIds, $permissionSlugs) {
                    $grp->whereNull('permission_id')
                        ->whereHas('children', function($cq) use ($roleIds, $permissionSlugs) {
                            $cq->where('is_active', true)
                               ->where(function($subQ) use ($roleIds, $permissionSlugs) {
                                   $subQ->where(function($sp) use ($permissionSlugs) {
                                       $sp->whereNotNull('permission_id')
                                          ->whereHas('permission', function($pq) use ($permissionSlugs) {
                                              $pq->whereIn('slug', $permissionSlugs);
                                          });
                                   })
                                   ->orWhere(function($sr) use ($roleIds) {
                                       $sr->whereNull('permission_id')
                                          ->whereHas('roles', function($rq) use ($roleIds) {
                                              $rq->whereIn('core_roles.id', $roleIds);
                                          });
                                   });
                               });
                        });
                });
            });
        }

        $menus = $query->orderBy('order_index')->get();

        // 3. Filter akhir untuk parent grup yang tidak punya anak aktif
        if (!$isSuperAdmin) {
            $menus = $menus->filter(function ($menu) {
                if (empty($menu->url) || str_starts_with($menu->url, '#')) {
                    return $menu->children && $menu->children->count() > 0;
                }
                return true;
            })->values();
        }

        return $menus;
    }
}
