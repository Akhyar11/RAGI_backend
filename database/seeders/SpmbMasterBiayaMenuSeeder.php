<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Menu;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Support\Facades\DB;
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

        // 1. Cari parent section "MASTER DATA SPMB"
        $parent = Menu::where('module', 'spmb')
            ->where(function ($q) {
                $q->where('url', '#master_spmb')
                  ->orWhere('name', 'like', '%MASTER DATA%');
            })
            ->first();

        if (!$parent) {
            // Jika parent belum ada, buat parent section
            $perm = Permission::where('slug', 'spmb.manage')->first();
            $parent = Menu::create([
                'name' => 'MASTER DATA SPMB',
                'url' => '#master_spmb',
                'icon' => 'FaDatabase',
                'module' => 'spmb',
                'permission_id' => $perm ? $perm->id : null,
                'order_index' => 3,
                'is_active' => true,
            ]);
        }

        $permission = Permission::where('slug', 'spmb.manage')->first();
        $permissionId = $permission ? $permission->id : null;

        // 2. Geser urutan menu Referensi jika ada
        Menu::where('parent_id', $parent->id)
            ->where('url', '/spmb/master/referensi')
            ->update(['order_index' => 8]);

        Menu::where('parent_id', $parent->id)
            ->where('url', '/spmb/master/tipe-referensi')
            ->update(['order_index' => 9]);

        // 3. Upsert Menu Master Biaya SPMB
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
                'order_index' => 6,
                'is_active' => true,
            ]
        );

        // 4. Upsert Menu Komponen Biaya
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
                'order_index' => 7,
                'is_active' => true,
            ]
        );

        // 5. Hubungkan ke Role yang berhak (superadmin, admin, admin_spmb)
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
