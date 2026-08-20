<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Menu;
use App\Models\Role;
use App\Models\Module;

class SpmbMenuSeeder extends Seeder
{
    /**
     * Run the SPMB Module Menu Seeder.
     */
    public function run(): void
    {
        // 1. Ensure SPMB module exists in `modules` table
        Module::updateOrCreate(
            ['code' => 'spmb'],
            [
                'name' => 'SPMB (Penerimaan Mahasiswa)',
                'description' => 'Sistem Penerimaan Mahasiswa Baru Kampus',
                'is_active' => true
            ]
        );

        // 2. Comprehensive SPMB Menu Structure
        $spmbMenus = [
            [
                'name' => 'Dashboard SPMB',
                'url' => '/spmb/dashboard',
                'icon' => 'FaChartPie',
                'module' => 'spmb',
                'order_index' => 1,
            ],
            [
                'name' => 'PENDAFTARAN',
                'url' => '#pendaftaran_spmb',
                'icon' => 'FaUsers',
                'module' => 'spmb',
                'order_index' => 2,
                'children' => [
                    ['name' => 'Formulir Registrasi', 'url' => '/spmb/registrasi', 'icon' => 'FaEdit', 'module' => 'spmb', 'order_index' => 1],
                    ['name' => 'Data Pendaftar SPMB', 'url' => '/spmb/pendaftaran', 'icon' => 'FaUsers', 'module' => 'spmb', 'order_index' => 2],
                    ['name' => 'Verifikasi Pembayaran', 'url' => '/spmb/pembayaran', 'icon' => 'FaCreditCard', 'module' => 'spmb', 'order_index' => 3],
                ]
            ],
            [
                'name' => 'UJIAN MASUK',
                'url' => '#ujian_spmb',
                'icon' => 'FaClipboardCheck',
                'module' => 'spmb',
                'order_index' => 3,
                'children' => [
                    ['name' => 'Jadwal Ujian CAT', 'url' => '/spmb/ujian/jadwal', 'icon' => 'FaCalendar', 'module' => 'spmb', 'order_index' => 1],
                    ['name' => 'Plotting Peserta Ujian', 'url' => '/spmb/ujian/peserta', 'icon' => 'FaUserCheck', 'module' => 'spmb', 'order_index' => 2],
                ]
            ],
            [
                'name' => 'SELEKSI & HASIL',
                'url' => '#seleksi_spmb',
                'icon' => 'FaCheckSquare',
                'module' => 'spmb',
                'order_index' => 4,
                'children' => [
                    ['name' => 'Hasil & Nilai Seleksi', 'url' => '/spmb/seleksi', 'icon' => 'FaTrophy', 'module' => 'spmb', 'order_index' => 1],
                    ['name' => 'Registrasi / Daftar Ulang', 'url' => '/spmb/seleksi/daftar-ulang', 'icon' => 'FaFileCheck', 'module' => 'spmb', 'order_index' => 2],
                ]
            ],
            [
                'name' => 'MASTER DATA SPMB',
                'url' => '#master_spmb',
                'icon' => 'FaList',
                'module' => 'spmb',
                'order_index' => 5,
                'children' => [
                    ['name' => 'Jalur Masuk', 'url' => '/spmb/master/jalur', 'icon' => 'FaCogs', 'module' => 'spmb', 'order_index' => 1],
                    ['name' => 'Gelombang Penerimaan', 'url' => '/spmb/master/gelombang', 'icon' => 'FaCalendar', 'module' => 'spmb', 'order_index' => 2],
                    ['name' => 'Kuota Prodi SPMB', 'url' => '/spmb/master/kuota', 'icon' => 'FaUsers', 'module' => 'spmb', 'order_index' => 3],
                ]
            ],
        ];

        $allMenuIds = [];

        foreach ($spmbMenus as $menuData) {
            $parent = Menu::updateOrCreate(
                ['url' => $menuData['url'], 'module' => 'spmb'],
                [
                    'name' => $menuData['name'],
                    'icon' => $menuData['icon'],
                    'order_index' => $menuData['order_index'],
                    'is_active' => true,
                ]
            );

            $allMenuIds[] = $parent->id;

            if (isset($menuData['children'])) {
                foreach ($menuData['children'] as $childData) {
                    $child = Menu::updateOrCreate(
                        ['url' => $childData['url'], 'module' => 'spmb'],
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

        // Attach SPMB menus to Admin and Superadmin roles
        $rolesToAttach = Role::whereIn('slug', ['superadmin', 'admin', 'admin_spmb', 'super-admin'])->get();
        foreach ($rolesToAttach as $role) {
            if (method_exists($role, 'menus')) {
                $role->menus()->syncWithoutDetaching($allMenuIds);
            }
        }

        // Attach Student SPMB menus to Calon Mahasiswa & Mahasiswa roles
        $studentMenuUrls = ['/spmb/dashboard', '#pendaftaran_spmb', '/spmb/registrasi', '#ujian_spmb', '/spmb/ujian/jadwal', '#seleksi_spmb', '/spmb/seleksi'];
        $studentMenuIds = Menu::where('module', 'spmb')->whereIn('url', $studentMenuUrls)->pluck('id')->toArray();

        $studentRoles = Role::whereIn('slug', ['calon_mhs', 'calon-mahasiswa', 'mahasiswa'])->get();
        foreach ($studentRoles as $role) {
            if (method_exists($role, 'menus')) {
                $role->menus()->syncWithoutDetaching($studentMenuIds);
            }
        }
    }
}
