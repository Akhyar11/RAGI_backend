<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\Menu;
use App\Models\Role;
use App\Models\Permission;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('core_menus')) {
            return;
        }

        // Baca definisi hierarki menu dari MenuSeeder secara aman tanpa truncate
        $seederPath = database_path('seeders/IAM/MenuSeeder.php');
        if (!file_exists($seederPath)) {
            return;
        }

        $code = file_get_contents($seederPath);
        preg_match('/\$menus\s*=\s*(\[.*?\]);\s*foreach/s', $code, $matches);

        if (!isset($matches[1])) {
            return;
        }

        eval('$menus = ' . $matches[1] . ';');

        foreach ($menus as $menuData) {
            $permissionId = null;
            if (isset($menuData['permission_slug'])) {
                $perm = Permission::where('slug', $menuData['permission_slug'])->first();
                $permissionId = $perm ? $perm->id : null;
            }

            $existing = Menu::where('module', $menuData['module'])
                ->where('url', $menuData['url'])
                ->first();

            if ($existing) {
                $existing->update([
                    'name' => $menuData['name'],
                    'icon' => $menuData['icon'] ?? $existing->icon,
                    'permission_id' => $permissionId,
                    'order_index' => $menuData['order_index'] ?? $existing->order_index,
                    'is_active' => true,
                ]);
                $parentId = $existing->id;
            } else {
                $newMenu = Menu::create([
                    'name' => $menuData['name'],
                    'url' => $menuData['url'],
                    'icon' => $menuData['icon'] ?? 'FaList',
                    'module' => $menuData['module'],
                    'permission_id' => $permissionId,
                    'order_index' => $menuData['order_index'] ?? 1,
                    'is_active' => true,
                ]);
                $parentId = $newMenu->id;
            }

            if (isset($menuData['children'])) {
                foreach ($menuData['children'] as $childData) {
                    $childPermId = null;
                    if (isset($childData['permission_slug'])) {
                        $cPerm = Permission::where('slug', $childData['permission_slug'])->first();
                        $childPermId = $cPerm ? $cPerm->id : null;
                    }

                    $existingChild = Menu::where('module', $childData['module'])
                        ->where('url', $childData['url'])
                        ->first();

                    if ($existingChild) {
                        $existingChild->update([
                            'parent_id' => $parentId,
                            'name' => $childData['name'],
                            'icon' => $childData['icon'] ?? $existingChild->icon,
                            'permission_id' => $childPermId,
                            'order_index' => $childData['order_index'] ?? $existingChild->order_index,
                            'is_active' => true,
                        ]);
                    } else {
                        Menu::create([
                            'parent_id' => $parentId,
                            'name' => $childData['name'],
                            'url' => $childData['url'],
                            'icon' => $childData['icon'] ?? 'FaList',
                            'module' => $childData['module'],
                            'permission_id' => $childPermId,
                            'order_index' => $childData['order_index'] ?? 1,
                            'is_active' => true,
                        ]);
                    }
                }
            }
        }

        // Sinkronisasi hak akses menu default per role (idempoten)
        if (Schema::hasTable('core_menu_role') && Schema::hasTable('core_roles')) {
            $allMenuIds = Menu::pluck('id')->toArray();
            $akunKeamananIds = Menu::where('url', '#akun_keamanan')
                ->orWhere('url', 'like', '/profile%')
                ->pluck('id')
                ->toArray();

            $roles = Role::all();
            foreach ($roles as $role) {
                if (in_array($role->slug, ['superadmin', 'admin'])) {
                    $role->menus()->syncWithoutDetaching($allMenuIds);
                } else {
                    $role->menus()->syncWithoutDetaching($akunKeamananIds);

                    $moduleSlug = str_replace('admin_', '', $role->slug);
                    $moduleSlug = str_replace('operator_', '', $moduleSlug);
                    if (in_array($role->slug, ['operator_sikeu', 'kabag_keuangan'])) {
                        $moduleSlug = 'sikeu';
                    }
                    if (in_array($role->slug, ['admin_lppm', 'reviewer_lppm', 'reviewer_sippm'])) {
                        $moduleSlug = 'sippm';
                    }

                    $roleMenuIds = Menu::whereIn('module', ['sso', $moduleSlug])
                        ->where(function ($q) {
                            $q->whereNull('permission_id')
                              ->orWhere('url', 'like', '/profile%')
                              ->orWhere('url', '#akun_keamanan')
                              ->orWhere('url', '/dashboard');
                        })
                        ->pluck('id')
                        ->toArray();

                    if (!empty($roleMenuIds)) {
                        $role->menus()->syncWithoutDetaching($roleMenuIds);
                    }

                    if (in_array($role->slug, ['operator_sikeu', 'kabag_keuangan'])) {
                        $sikeuMenuIds = Menu::where('module', 'sikeu')->pluck('id')->toArray();
                        $role->menus()->syncWithoutDetaching($sikeuMenuIds);
                    }

                    if (in_array($role->slug, ['admin_simpeg', 'operator_sdm'])) {
                        $simpegMenuIds = Menu::where('module', 'simpeg')->pluck('id')->toArray();
                        $role->menus()->syncWithoutDetaching($simpegMenuIds);
                    }

                    if (in_array($role->slug, ['admin_spmb', 'panitia_spmb'])) {
                        $spmbMenuIds = Menu::where('module', 'spmb')->pluck('id')->toArray();
                        $role->menus()->syncWithoutDetaching($spmbMenuIds);
                    }

                    if (in_array($role->slug, ['admin_sippm', 'admin_lppm'])) {
                        $sippmMenuIds = Menu::where('module', 'sippm')->pluck('id')->toArray();
                        $role->menus()->syncWithoutDetaching($sippmMenuIds);
                    }

                    if ($role->slug === 'dosen') {
                        $dosenSimpegMenuIds = Menu::where('module', 'simpeg')
                            ->whereIn('url', ['/simpeg', '/simpeg/presensi', '/simpeg/cuti', '/simpeg/payroll', '/simpeg/kompetensi', '/simpeg/surat-tugas', '/simpeg/izin-kerja', '/simpeg/sk-pegawai'])
                            ->pluck('id')
                            ->toArray();
                        $role->menus()->syncWithoutDetaching($dosenSimpegMenuIds);
                    }
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Non-destructive: tidak menghapus menu agar data operasional aman
    }
};
