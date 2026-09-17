<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\Menu;
use App\Models\Role;
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

        $now = now();
        $modulesConfig = [
            [
                'module' => 'spmb',
                'parent_url' => '#master_spmb',
                'url' => '/spmb/master/tipe-referensi',
                'name' => 'Master Tipe Referensi',
                'icon' => 'FaTags',
                'role_slugs' => ['superadmin', 'admin', 'admin_spmb'],
            ],
            [
                'module' => 'siakad',
                'parent_url' => '#master_siakad',
                'url' => '/siakad/master/tipe-referensi',
                'name' => 'Master Tipe Referensi',
                'icon' => 'FaTags',
                'role_slugs' => ['superadmin', 'admin'],
            ],
            [
                'module' => 'simpeg',
                'parent_url' => '#master_simpeg',
                'url' => '/simpeg/master/tipe-referensi',
                'name' => 'Master Tipe Referensi',
                'icon' => 'FaTags',
                'role_slugs' => ['superadmin', 'admin', 'admin_simpeg', 'operator_sdm'],
            ],
            [
                'module' => 'sikeu',
                'parent_url' => '#master_sikeu',
                'url' => '/sikeu/master/tipe-referensi',
                'name' => 'Master Tipe Referensi',
                'icon' => 'FaTags',
                'role_slugs' => ['superadmin', 'admin', 'operator_sikeu', 'kabag_keuangan'],
            ],
        ];

        foreach ($modulesConfig as $cfg) {
            $parent = Menu::where('module', $cfg['module'])->where('url', $cfg['parent_url'])->first();
            $parentId = $parent ? $parent->id : null;

            $maxOrder = Menu::where('module', $cfg['module'])
                ->where('parent_id', $parentId)
                ->max('order_index') ?? 5;

            $menu = Menu::where('module', $cfg['module'])
                ->where('url', $cfg['url'])
                ->first();

            if ($menu) {
                $menu->update([
                    'parent_id' => $parentId,
                    'name' => $cfg['name'],
                    'icon' => $cfg['icon'],
                    'order_index' => $maxOrder + 1,
                    'is_active' => true,
                ]);
            } else {
                $menu = Menu::create([
                    'parent_id' => $parentId,
                    'name' => $cfg['name'],
                    'url' => $cfg['url'],
                    'icon' => $cfg['icon'],
                    'module' => $cfg['module'],
                    'order_index' => $maxOrder + 1,
                    'is_active' => true,
                ]);
            }

            // Hubungkan ke roles
            if (Schema::hasTable('core_menu_role') && Schema::hasTable('core_roles')) {
                $roles = Role::whereIn('slug', $cfg['role_slugs'])->get();
                foreach ($roles as $role) {
                    $role->menus()->syncWithoutDetaching([$menu->id]);
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('core_menus')) {
            $urls = [
                '/spmb/master/tipe-referensi',
                '/siakad/master/tipe-referensi',
                '/simpeg/master/tipe-referensi',
                '/sikeu/master/tipe-referensi',
            ];
            $menuIds = Menu::whereIn('url', $urls)->pluck('id')->toArray();
            if (!empty($menuIds) && Schema::hasTable('core_menu_role')) {
                DB::table('core_menu_role')->whereIn('menu_id', $menuIds)->delete();
            }
            Menu::whereIn('id', $menuIds)->delete();
        }
    }
};
