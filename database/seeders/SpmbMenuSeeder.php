<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Menu;
use App\Models\Role;
use App\Models\Module;

class SpmbMenuSeeder extends Seeder
{
    public function run(): void
    {
        $modSpmb = 'spmb';

        Module::updateOrCreate(
            ['code' => $modSpmb],
            [
                'name' => 'SPMB (Penerimaan Mahasiswa)',
                'description' => 'Sistem Penerimaan Mahasiswa Baru Kampus',
                'is_active' => true
            ]
        );

        $spmbMenus = [
            [
                'name' => 'Dashboard SPMB',
                'url' => '/spmb',
                'icon' => 'FaChartPie',
                'module' => $modSpmb,
                'permission_slug' => 'spmb.dashboard.read',
                'order_index' => 1,
            ],
            [
                'name' => 'ADMISI & PENDAFTARAN',
                'url' => '#admisi_spmb',
                'icon' => 'FaUserCheck',
                'module' => $modSpmb,
                'permission_slug' => 'spmb.admin.manage',
                'order_index' => 2,
                'children' => [
                    ['name' => 'Data Calon Mahasiswa', 'url' => '/spmb/pendaftar', 'icon' => 'FaUsers', 'module' => $modSpmb, 'permission_slug' => 'spmb.admin.manage', 'order_index' => 1],
                    ['name' => 'Pendaftaran Mahasiswa Baru', 'url' => '/spmb/pendaftaran', 'icon' => 'FaUserPlus', 'module' => $modSpmb, 'permission_slug' => 'spmb.admin.manage', 'order_index' => 2],
                    ['name' => 'Verifikasi Daftar Ulang', 'url' => '/spmb/daftar-ulang', 'icon' => 'FaClipboardCheck', 'module' => $modSpmb, 'permission_slug' => 'spmb.admin.manage', 'order_index' => 3],
                    ['name' => 'Registrasi Online', 'url' => '/spmb/registrasi', 'icon' => 'FaPen', 'module' => $modSpmb, 'permission_slug' => 'spmb.admin.manage', 'order_index' => 4],
                ]
            ],
            [
                'name' => 'MASTER DATA SPMB',
                'url' => '#master_spmb',
                'icon' => 'FaDatabase',
                'module' => $modSpmb,
                'permission_slug' => 'spmb.admin.manage',
                'order_index' => 3,
                'children' => [
                    ['name' => 'Jalur Masuk', 'url' => '/spmb/master/jalur', 'icon' => 'FaCogs', 'module' => $modSpmb, 'permission_slug' => 'spmb.admin.manage', 'order_index' => 1],
                    ['name' => 'Tipe Jalur Masuk', 'url' => '/spmb/master/tipe-jalur', 'icon' => 'FaTags', 'module' => $modSpmb, 'permission_slug' => 'spmb.admin.manage', 'order_index' => 2],
                    ['name' => 'Gelombang Penerimaan', 'url' => '/spmb/master/gelombang', 'icon' => 'FaCalendar', 'module' => $modSpmb, 'permission_slug' => 'spmb.admin.manage', 'order_index' => 3],
                    ['name' => 'Kuota Program Studi', 'url' => '/spmb/master/kuota', 'icon' => 'FaChartPie', 'module' => $modSpmb, 'permission_slug' => 'spmb.admin.manage', 'order_index' => 4],
                    ['name' => 'Persyaratan Berkas', 'url' => '/spmb/master/berkas-requirement', 'icon' => 'FaFileAlt', 'module' => $modSpmb, 'permission_slug' => 'spmb.admin.manage', 'order_index' => 5],
                    ['name' => 'Tarif Masuk & UKT', 'url' => '/spmb/master/tarif-ukt', 'icon' => 'FaMoneyBillWave', 'module' => $modSpmb, 'permission_slug' => 'spmb.admin.manage', 'order_index' => 6],
                    ['name' => 'Master Data Referensi', 'url' => '/spmb/master/referensi', 'icon' => 'FaDatabase', 'module' => $modSpmb, 'permission_slug' => 'spmb.admin.manage', 'order_index' => 7],
                    ['name' => 'Master Tipe Referensi', 'url' => '/spmb/master/tipe-referensi', 'icon' => 'FaLayers', 'module' => $modSpmb, 'permission_slug' => 'spmb.admin.manage', 'order_index' => 8],
                ]
            ],
            [
                'name' => 'LAPORAN & STATISTIK',
                'url' => '#laporan_spmb',
                'icon' => 'FaChartBar',
                'module' => $modSpmb,
                'permission_slug' => 'spmb.laporan.read',
                'order_index' => 4,
                'children' => [
                    ['name' => 'Statistik Pendaftaran', 'url' => '/spmb/laporan/statistik', 'icon' => 'FaChartBar', 'module' => $modSpmb, 'permission_slug' => 'spmb.laporan.read', 'order_index' => 1],
                ]
            ],
        ];

        $allMenuIds = [];

        foreach ($spmbMenus as $menuData) {
            $parent = Menu::updateOrCreate(
                [
                    'url' => $menuData['url'],
                    'module' => $menuData['module'],
                ],
                [
                    'name' => $menuData['name'],
                    'icon' => $menuData['icon'],
                    'order_index' => $menuData['order_index'],
                    'is_active' => true,
                    'parent_id' => null,
                ]
            );

            $allMenuIds[] = $parent->id;

            if (isset($menuData['children'])) {
                foreach ($menuData['children'] as $childData) {
                    $child = Menu::updateOrCreate(
                        [
                            'url' => $childData['url'],
                            'module' => $childData['module'],
                        ],
                        [
                            'parent_id' => $parent->id,
                            'name' => $childData['name'],
                            'icon' => $childData['icon'],
                            'order_index' => $childData['order_index'],
                            'is_active' => true,
                        ]
                    );

                    $allMenuIds[] = $child->id;
                }
            }
        }

        $spmbAdminRole = Role::where('slug', 'admin-spmb')->first();
        if ($spmbAdminRole) {
            $spmbAdminRole->menus()->syncWithoutDetaching($allMenuIds);
        }

        $superAdminRole = Role::where('slug', 'super-admin')->first();
        if ($superAdminRole) {
            $superAdminRole->menus()->syncWithoutDetaching($allMenuIds);
        }

        // Mahasiswa mendapatkan akses pendaftaran mandiri
        $studentMenuIds = Menu::where('module', $modSpmb)
            ->whereIn('url', [
                '/spmb',
                '/spmb/registrasi',
                '/spmb/pendaftaran',
                '/spmb/daftar-ulang',
            ])
            ->pluck('id')
            ->toArray();

        $mhsRole = Role::where('slug', 'mahasiswa')->first();
        if ($mhsRole) {
            $mhsRole->menus()->syncWithoutDetaching($studentMenuIds);
        }
    }
}
