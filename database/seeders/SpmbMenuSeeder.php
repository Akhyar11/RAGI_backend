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
        Menu::where('module', $modSpmb)->delete();

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
                'name' => 'Dashboard Saya',
                'url' => '/spmb/dashboard',
                'icon' => 'FaChartPie',
                'module' => $modSpmb,
                'order_index' => 1,
            ],
            [
                'name' => 'Formulir Pendaftaran',
                'url' => '/spmb/registrasi',
                'icon' => 'FaUserPlus',
                'module' => $modSpmb,
                'order_index' => 2,
            ],
            [
                'name' => 'PENDAFTARAN & VERIFIKASI',
                'url' => '#pendaftaran_spmb',
                'icon' => 'FaList',
                'module' => $modSpmb,
                'order_index' => 3,
                'children' => [
                    ['name' => 'Data Pendaftar & Verifikasi', 'url' => '/spmb/pendaftaran', 'icon' => 'FaUsers', 'module' => $modSpmb, 'order_index' => 1],
                ]
            ],
            [
                'name' => 'SELEKSI ADMINISTRASI',
                'url' => '#seleksi_spmb',
                'icon' => 'FaCheckSquare',
                'module' => $modSpmb,
                'order_index' => 4,
                'children' => [
                    ['name' => 'Hasil Seleksi Administrasi', 'url' => '/spmb/seleksi', 'icon' => 'FaTrophy', 'module' => $modSpmb, 'order_index' => 1],
                ]
            ],
            [
                'name' => 'MASTER DATA SPMB',
                'url' => '#master_spmb',
                'icon' => 'FaList',
                'module' => $modSpmb,
                'order_index' => 5,
                'children' => [
                    ['name' => 'Jalur Masuk', 'url' => '/spmb/master/jalur', 'icon' => 'FaCogs', 'module' => $modSpmb, 'order_index' => 1],
                    ['name' => 'Jalur Masuk (Legacy)', 'url' => '/spmb/master/jalur-masuk', 'icon' => 'FaCogs', 'module' => $modSpmb, 'order_index' => 2],
                    ['name' => 'Gelombang Penerimaan', 'url' => '/spmb/master/gelombang', 'icon' => 'FaCalendar', 'module' => $modSpmb, 'order_index' => 3],
                    ['name' => 'Kuota Program Studi', 'url' => '/spmb/master/kuota', 'icon' => 'FaChartPie', 'module' => $modSpmb, 'order_index' => 4],
                    ['name' => 'Persyaratan Berkas', 'url' => '/spmb/master/berkas-requirement', 'icon' => 'FaFileAlt', 'module' => $modSpmb, 'order_index' => 5],
                ]
            ],
        ];

        $allMenuIds = [];

        foreach ($spmbMenus as $menuData) {
            $parent = Menu::create([
                'name' => $menuData['name'],
                'url' => $menuData['url'],
                'icon' => $menuData['icon'],
                'module' => $menuData['module'],
                'order_index' => $menuData['order_index'],
                'is_active' => true,
            ]);

            $allMenuIds[] = $parent->id;

            if (isset($menuData['children'])) {
                foreach ($menuData['children'] as $childData) {
                    $child = Menu::create([
                        'parent_id' => $parent->id,
                        'name' => $childData['name'],
                        'url' => $childData['url'],
                        'icon' => $childData['icon'],
                        'module' => $childData['module'],
                        'order_index' => $childData['order_index'],
                        'is_active' => true,
                    ]);

                    $allMenuIds[] = $child->id;
                }
            }
        }

        $roles = Role::all();
        foreach ($roles as $role) {
            if (method_exists($role, 'menus')) {
                $role->menus()->syncWithoutDetaching($allMenuIds);
            }
        }
    }
}
