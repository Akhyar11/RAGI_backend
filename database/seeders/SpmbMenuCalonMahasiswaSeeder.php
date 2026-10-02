<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Menu;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Support\Facades\Schema;

class SpmbMenuCalonMahasiswaSeeder extends Seeder
{
    /**
     * Menu khusus Portal Calon Mahasiswa (modul SPMB) untuk role `calon_mhs`.
     * Idempoten (updateOrCreate) sehingga aman dijalankan berkali-kali.
     */
    public function run(): void
    {
        if (!Schema::hasTable('core_menus')) {
            $this->command->error('Tabel core_menus tidak ditemukan.');
            return;
        }

        $role = Role::where('slug', 'calon_mhs')->first();
        if (!$role) {
            $this->command->warn('Role calon_mhs tidak ditemukan. Jalankan RoleSeeder terlebih dahulu.');
            return;
        }

        $permission = Permission::where('slug', 'spmb.student.read')->first();
        $permissionId = $permission?->id;

        // 1. Section root Portal Calon Mahasiswa
        $root = Menu::updateOrCreate(
            [
                'parent_id' => null,
                'url' => '#portal_calon_mhs',
            ],
            [
                'name' => 'PORTAL CALON MAHASISWA',
                'icon' => 'FaUserGraduate',
                'module' => 'spmb',
                'permission_id' => $permissionId,
                'order_index' => 1,
                'is_active' => true,
            ]
        );

        // 2. Menu turunan portal calon mahasiswa
        $children = [
            ['name' => 'Dashboard Pendaftaran', 'url' => '/spmb/dashboard', 'icon' => 'FaChartPie', 'order_index' => 1],
            ['name' => 'Formulir Registrasi', 'url' => '/spmb/registrasi', 'icon' => 'FaPen', 'order_index' => 2],
            ['name' => 'Daftar Ulang', 'url' => '/spmb/daftar-ulang', 'icon' => 'FaClipboardCheck', 'order_index' => 3],
        ];

        $menuIds = [$root->id];

        foreach ($children as $child) {
            $menu = Menu::updateOrCreate(
                [
                    'parent_id' => $root->id,
                    'url' => $child['url'],
                ],
                [
                    'name' => $child['name'],
                    'icon' => $child['icon'],
                    'module' => 'spmb',
                    'permission_id' => $permissionId,
                    'order_index' => $child['order_index'],
                    'is_active' => true,
                ]
            );

            $menuIds[] = $menu->id;
        }

        // 3. Ikatkan menu ke role calon mahasiswa
        $role->menus()->syncWithoutDetaching($menuIds);

        $this->command->info('Seeder menu Portal Calon Mahasiswa (role calon_mhs) berhasil dijalankan.');
    }
}
