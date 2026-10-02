<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Menu;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Support\Facades\Schema;

class SpmbMenuAdminSeeder extends Seeder
{
    /**
     * Menu administrasi SPMB untuk role admin SPMB (admin_spmb).
     * Menormalkan permission menu (spmb.dashboard.read / spmb.manage /
     * spmb.laporan.read) sekaligus memastikan seluruh menu SPMB admin
     * terpasang pada role. Idempoten (updateOrCreate).
     */
    public function run(): void
    {
        if (!Schema::hasTable('core_menus')) {
            $this->command->error('Tabel core_menus tidak ditemukan.');
            return;
        }

        $permissions = Permission::whereIn('slug', [
            'spmb.dashboard.read',
            'spmb.manage',
            'spmb.laporan.read',
        ])->get()->keyBy('slug');

        $permissionId = fn (string $slug) => $permissions->get($slug)?->id;

        // 1. Root tunggal: Dashboard SPMB
        $rootMenus = [
            [
                'name' => 'Dashboard SPMB',
                'url' => '/spmb',
                'icon' => 'FaChartPie',
                'permission' => 'spmb.dashboard.read',
                'order_index' => 1,
            ],
        ];

        // 2. Section + menu turunan
        $sections = [
            [
                'name' => 'ADMISI & PENDAFTARAN',
                'url' => '#admisi_spmb',
                'icon' => 'FaUserCheck',
                'permission' => 'spmb.manage',
                'order_index' => 2,
                'children' => [
                    ['name' => 'Pendaftaran Mahasiswa Baru', 'url' => '/spmb/pendaftaran', 'icon' => 'FaUserPlus', 'order_index' => 1],
                    ['name' => 'Verifikasi Daftar Ulang', 'url' => '/spmb/daftar-ulang', 'icon' => 'FaClipboardCheck', 'order_index' => 2],
                    ['name' => 'Registrasi Online', 'url' => '/spmb/registrasi', 'icon' => 'FaPen', 'order_index' => 3],
                ],
            ],
            [
                'name' => 'MASTER PENERIMAAN SPMB',
                'url' => '#master_spmb',
                'icon' => 'FaDatabase',
                'permission' => 'spmb.manage',
                'order_index' => 3,
                'children' => [
                    ['name' => 'Jalur Masuk', 'url' => '/spmb/master/jalur', 'icon' => 'FaCogs', 'order_index' => 1],
                    ['name' => 'Tipe Jalur Masuk', 'url' => '/spmb/master/tipe-jalur', 'icon' => 'FaTags', 'order_index' => 2],
                    ['name' => 'Gelombang Penerimaan', 'url' => '/spmb/master/gelombang', 'icon' => 'FaCalendar', 'order_index' => 3],
                    ['name' => 'Kuota Program Studi', 'url' => '/spmb/master/kuota', 'icon' => 'FaChartPie', 'order_index' => 4],
                ],
            ],
            [
                'name' => 'MASTER BIAYA & REFERENSI SPMB',
                'url' => '#master_biaya_referensi',
                'icon' => 'FaCoins',
                'permission' => 'spmb.manage',
                'order_index' => 4,
                'children' => [
                    ['name' => 'Persyaratan Berkas', 'url' => '/spmb/master/berkas-requirement', 'icon' => 'FaFileAlt', 'order_index' => 1],
                    ['name' => 'Master Biaya SPMB', 'url' => '/spmb/master/biaya', 'icon' => 'FaCoins', 'order_index' => 2],
                    ['name' => 'Komponen Biaya', 'url' => '/spmb/master/komponen-biaya', 'icon' => 'FaTag', 'order_index' => 3],
                    ['name' => 'Master Data Referensi', 'url' => '/spmb/master/referensi', 'icon' => 'FaDatabase', 'order_index' => 4],
                    ['name' => 'Master Tipe Referensi', 'url' => '/spmb/master/tipe-referensi', 'icon' => 'FaLayers', 'order_index' => 5],
                    ['name' => 'Template SK & Surat', 'url' => '/spmb/master/template-surat', 'icon' => 'FaFileSignature', 'order_index' => 6],
                ],
            ],
            [
                'name' => 'LAPORAN & STATISTIK',
                'url' => '#laporan_spmb',
                'icon' => 'FaChartBar',
                'permission' => 'spmb.laporan.read',
                'order_index' => 5,
                'children' => [
                    ['name' => 'Statistik Pendaftaran', 'url' => '/spmb/laporan/statistik', 'icon' => 'FaChartBar', 'order_index' => 1],
                    ['name' => 'Laporan Referral', 'url' => '/spmb/laporan/referral', 'icon' => 'FaUsers', 'order_index' => 2],
                ],
            ],
        ];

        $menuIds = [];

        foreach ($rootMenus as $root) {
            $menu = Menu::updateOrCreate(
                ['parent_id' => null, 'url' => $root['url']],
                [
                    'name' => $root['name'],
                    'icon' => $root['icon'],
                    'module' => 'spmb',
                    'permission_id' => $permissionId($root['permission']),
                    'order_index' => $root['order_index'],
                    'is_active' => true,
                ]
            );
            $menuIds[] = $menu->id;
        }

        foreach ($sections as $section) {
            $root = Menu::updateOrCreate(
                ['parent_id' => null, 'url' => $section['url']],
                [
                    'name' => $section['name'],
                    'icon' => $section['icon'],
                    'module' => 'spmb',
                    'permission_id' => $permissionId($section['permission']),
                    'order_index' => $section['order_index'],
                    'is_active' => true,
                ]
            );

            $menuIds[] = $root->id;

            foreach ($section['children'] as $child) {
                $menu = Menu::updateOrCreate(
                    ['parent_id' => $root->id, 'url' => $child['url']],
                    [
                        'name' => $child['name'],
                        'icon' => $child['icon'],
                        'module' => 'spmb',
                        'permission_id' => $permissionId($section['permission']),
                        'order_index' => $child['order_index'],
                        'is_active' => true,
                    ]
                );

                $menuIds[] = $menu->id;
            }
        }

        // 3. Pasang ke role admin SPMB
        $roles = Role::whereIn('slug', ['admin_spmb', 'admin-spmb'])->get();

        if ($roles->isEmpty()) {
            $this->command->warn('Role admin SPMB tidak ditemukan. Jalankan RoleSeeder terlebih dahulu.');
            return;
        }

        foreach ($roles as $role) {
            $role->menus()->syncWithoutDetaching($menuIds);
        }

        $this->command->info('Seeder menu Admin SPMB berhasil dijalankan.');
    }
}
