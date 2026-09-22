<?php

namespace App\Services;

use App\Models\Menu;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class MenuService
{
    /**
     * Menu dasar modul IAM/SSO yang WAJIB tampil untuk semua role,
     * terlepas dari hasil plotting role-menu.
     */
    private const BASELINE_SSO_ROOT_URLS = [
        '/dashboard',
    ];

    /**
     * Anak menu dasar yang berada di dalam grup AKUN & KEAMANAN.
     */
    private const BASELINE_SSO_CHILD_URLS = [
        '/profile',
        '/profile/sessions',
        '/profile/mfa',
    ];

    /**
     * Grup menu yang menaungi menu dasar tersebut.
     */
    private const BASELINE_SSO_GROUP_URLS = [
        '#akun_keamanan',
    ];

    /**
     * Penyaring akhir pivot role-menu.
     *
     * Menu yang di-mapping ke role tertentu hanya terlihat oleh role yang
     * ter-mapping — walaupun pemanggil memegang permission menu tersebut.
     * Menu tanpa mapping apa pun lolos (otoritas penuh di permission).
     * Wajib dipanggil di dalam cabang permission agar pencopotan mapping
     * benar-benar menyembunyikan menu.
     */
    private function onlyMappedRoles($query, array $roleIds): void
    {
        $query->where(function ($q) use ($roleIds) {
            $q->whereDoesntHave('roles')
                ->orWhereHas('roles', function ($rq) use ($roleIds) {
                    $rq->whereIn('core_roles.id', $roleIds);
                });
        });
    }

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
                    $q->where(function($permQ) use ($roleIds, $permissionSlugs) {
                        $permQ->whereNotNull('permission_id')
                              ->whereHas('permission', function($pq) use ($permissionSlugs) {
                                  $pq->whereIn('slug', $permissionSlugs);
                              });
                        $this->onlyMappedRoles($permQ, $roleIds);
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
                $q->where(function($permQ) use ($roleIds, $permissionSlugs) {
                    $permQ->whereNotNull('permission_id')
                          ->whereHas('permission', function($pq) use ($permissionSlugs) {
                              $pq->whereIn('slug', $permissionSlugs);
                          });
                    $this->onlyMappedRoles($permQ, $roleIds);
                })
                // 2. ATAU root menu memiliki role yang cocok di tabel pivot menu_roles
                ->orWhere(function($roleQ) use ($roleIds) {
                    $roleQ->whereHas('roles', function($rq) use ($roleIds) {
                        $rq->whereIn('core_roles.id', $roleIds);
                    });
                })
                // 3. ATAU root menu adalah grup hierarki yang memiliki child aktif yang berizin bagi user
                ->orWhere(function($grp) use ($roleIds, $permissionSlugs) {
                    $grp->whereHas('children', function($cq) use ($roleIds, $permissionSlugs) {
                        $cq->where('is_active', true)
                           ->where(function($subQ) use ($roleIds, $permissionSlugs) {
                               $subQ->where(function($sp) use ($roleIds, $permissionSlugs) {
                                   $sp->whereNotNull('permission_id')
                                      ->whereHas('permission', function($pq) use ($permissionSlugs) {
                                          $pq->whereIn('slug', $permissionSlugs);
                                      });
                                   $this->onlyMappedRoles($sp, $roleIds);
                               })
                               ->orWhere(function($sr) use ($roleIds) {
                                   $sr->whereHas('roles', function($rq) use ($roleIds) {
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

        // 4. Menu dasar (Dashboard, Profil, Sesi Perangkat, 2FA) selalu tampil
        //    untuk role apa pun pada modul yang menaungi menu tersebut, tanpa
        //    perlu plotting role-menu. Modul ditentukan dari atribut menu di DB.
        $baselineMenus = $this->baselineSsoMenus();
        if ($baselineMenus->contains(fn ($baselineMenu) => $baselineMenu->module === $module)) {
            $menus = $this->mergeBaselineMenus($menus, $baselineMenus);
        }

        return $menus;
    }

    /**
     * Ambil menu dasar IAM/SSO langsung dari master menu.
     */
    private function baselineSsoMenus(): Collection
    {
        return Menu::with(['permission', 'children' => function ($cq) {
                $cq->with('permission')
                    ->where('is_active', true)
                    ->whereIn('url', self::BASELINE_SSO_CHILD_URLS)
                    ->orderBy('order_index');
            }])
            ->where('is_active', true)
            ->whereNull('parent_id')
            ->where(function ($q) {
                $q->whereIn('url', self::BASELINE_SSO_ROOT_URLS)
                    ->orWhereIn('url', self::BASELINE_SSO_GROUP_URLS);
            })
            ->orderBy('order_index')
            ->get();
    }

    /**
     * Gabungkan menu dasar ke daftar menu hasil filter role.
     */
    private function mergeBaselineMenus(Collection $menus, Collection $baselineMenus): Collection
    {
        $byId = $menus->keyBy('id');

        foreach ($baselineMenus as $baseline) {
            if (!$byId->has($baseline->id)) {
                $byId->put($baseline->id, $baseline);
                continue;
            }

            /** @var Menu $existing */
            $existing = $byId->get($baseline->id);
            $existingChildren = collect($existing->children ?? []);
            $existingChildIds = $existingChildren->pluck('id')->all();

            foreach ($baseline->children as $child) {
                if (!in_array($child->id, $existingChildIds, true)) {
                    $existingChildren->push($child);
                }
            }

            $existing->setRelation(
                'children',
                $existingChildren->sortBy('order_index')->values()
            );
        }

        return $byId->sortBy('order_index')->values();
    }
}
