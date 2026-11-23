<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Menu;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Support\Facades\Schema;

class SpmbMasterBiayaMenuSeeder extends Seeder
{
    /**
     * Run the database seeds for SPMB Master Biaya menus only.
     */
    public function run(): void
    {
        if (!Schema::hasTable('core_menus')) {
            $this->command->error('Table core_menus tidak ditemukan.');
            return;
        }

        $legacyMenus = Menu::query()
            ->where('module', 'spmb')
            ->where('url', '/spmb/master/tipe-jalur')
            ->get();
        foreach ($legacyMenus as $legacyMenu) {
            $legacyMenu->roles()->detach();
            $legacyMenu->delete();
        }

        // 1. Cari parent section biaya yang sudah didefinisikan di katalog menu.
        $parent = Menu::where('module', 'spmb')
            ->where('url', '#master_biaya_referensi')
            ->first();

        if (!$parent) {
            // Jika parent belum ada, buat parent section
            $perm = Permission::where('slug', 'spmb.manage')->first();
            $parent = Menu::create([
                'name' => 'MASTER BIAYA & REFERENSI SPMB',
                'url' => '#master_biaya_referensi',
                'icon' => 'FaDatabase',
                'module' => 'spmb',
                'permission_id' => $perm ? $perm->id : null,
                'order_index' => 3,
                'is_active' => true,
            ]);
        }

        $permission = Permission::where('slug', 'spmb.manage')->first();
        $permissionId = $permission ? $permission->id : null;

        // 2. Upsert Menu Master Biaya SPMB sesuai urutan katalog utama.
        $menuBiaya = Menu::updateOrCreate(
            [
                'parent_id' => $parent->id,
                'url' => '/spmb/master/biaya',
            ],
            [
                'name' => 'Master Biaya SPMB',
                'icon' => 'FaCoins',
                'module' => 'spmb',
                'permission_id' => $permissionId,
                'order_index' => 2,
                'is_active' => true,
            ]
        );

        // 3. Upsert Menu Komponen Biaya sesuai urutan katalog utama.
        $menuKomponen = Menu::updateOrCreate(
            [
                'parent_id' => $parent->id,
                'url' => '/spmb/master/komponen-biaya',
            ],
            [
                'name' => 'Komponen Biaya',
                'icon' => 'FaTag',
                'module' => 'spmb',
                'permission_id' => $permissionId,
                'order_index' => 3,
                'is_active' => true,
            ]
        );

        // 4. Hubungkan ke Role yang berhak (superadmin, admin, admin_spmb)
        $newMenuIds = [$menuBiaya->id, $menuKomponen->id];

        $targetRoles = Role::whereIn('slug', ['superadmin', 'admin', 'admin_spmb'])->get();
        foreach ($targetRoles as $role) {
            $role->menus()->syncWithoutDetaching($newMenuIds);
        }

        // Pastikan juga parent menu terhubung ke role
        foreach ($targetRoles as $role) {
            $role->menus()->syncWithoutDetaching([$parent->id]);
        }

        $this->command->info('Seeder Menu Master Biaya SPMB & Komponen Biaya berhasil dijalankan.');
    }
}
