<?php

namespace Database\Seeders\IAM;

use Illuminate\Database\Seeder;
use App\Models\Menu;
use App\Models\Permission;

class MenuSeeder extends Seeder
{
    public function run(): void
    {
        \Illuminate\Support\Facades\Schema::disableForeignKeyConstraints();
        \Illuminate\Support\Facades\DB::table('menus')->truncate();
        \Illuminate\Support\Facades\Schema::enableForeignKeyConstraints();

        // Cari permission untuk dijadikan referensi
        $dashboardPermission = Permission::where('slug', 'dashboard.read')->first();
        $userPermission = Permission::where('slug', 'users.read')->first();
        $rolePermission = Permission::where('slug', 'roles.read')->first();

        $menus = [
            // Menu SSO (Core)
            [
                'name' => 'Dashboard Utama',
                'url' => '/dashboard',
                'icon' => 'FaChartPie',
                'module' => 'sso',
                'permission_slug' => 'dashboard.read',
                'order_index' => 1,
            ],
            [
                'name' => 'MASTER',
                'url' => '#master',
                'icon' => 'FaList',
                'module' => 'sso',
                'order_index' => 2,
                'children' => [
                    ['name' => 'User', 'url' => '/admin/users', 'icon' => 'FaUsers', 'module' => 'sso', 'permission_slug' => 'iam.users.read', 'order_index' => 1],
                    ['name' => 'Role', 'url' => '/admin/roles', 'icon' => 'FaShieldAlt', 'module' => 'sso', 'permission_slug' => 'iam.roles.read', 'order_index' => 2],
                    ['name' => 'Module (Sistem)', 'url' => '/admin/modules', 'icon' => 'FaList', 'module' => 'sso', 'permission_slug' => 'iam.permissions.manage', 'order_index' => 3],
                    ['name' => 'Menu', 'url' => '/admin/menus', 'icon' => 'FaList', 'module' => 'sso', 'permission_slug' => 'iam.permissions.manage', 'order_index' => 4],
                ]
            ],
            [
                'name' => 'MENU',
                'url' => '#menu',
                'icon' => 'FaList',
                'module' => 'sso',
                'order_index' => 3,
                'children' => [
                    ['name' => 'User Role', 'url' => '/admin/user-roles', 'icon' => 'FaUsers', 'module' => 'sso', 'permission_slug' => 'iam.user_roles.manage', 'order_index' => 1],
                    ['name' => 'Role Permission', 'url' => '/admin/role-permissions', 'icon' => 'FaShieldAlt', 'module' => 'sso', 'permission_slug' => 'iam.permissions.manage', 'order_index' => 2],
                    ['name' => 'Permission Akses', 'url' => '/admin/permissions', 'icon' => 'FaShieldAlt', 'module' => 'sso', 'permission_slug' => 'iam.permissions.read', 'order_index' => 3],
                    ['name' => 'Akses Menu', 'url' => '/admin/role-menus', 'icon' => 'FaList', 'module' => 'sso', 'permission_slug' => 'iam.permissions.manage', 'order_index' => 4],
                    ['name' => 'Monitor Aksi', 'url' => '/admin/sessions', 'icon' => 'FaUsers', 'module' => 'sso', 'permission_slug' => 'iam.sessions.read', 'order_index' => 5],
                    ['name' => 'Audit Logs', 'url' => '/admin/audit-logs', 'icon' => 'FaList', 'module' => 'sso', 'permission_slug' => 'iam.audit_logs.read', 'order_index' => 6],
                ]
            ],

            // Menu SIMPEG
            [
                'name' => 'Dashboard SIMPEG',
                'url' => '/simpeg',
                'icon' => 'FaChartPie',
                'module' => 'simpeg',
                'permission_slug' => 'simpeg.pegawai.read',
                'order_index' => 1,
            ],
            [
                'name' => 'LAYANAN PEGAWAI',
                'url' => '#layanan_pegawai',
                'icon' => 'FaList',
                'module' => 'simpeg',
                'order_index' => 2,
                'children' => [
                    ['name' => 'Data Pegawai', 'url' => '/simpeg/pegawai', 'icon' => 'FaUsers', 'module' => 'simpeg', 'permission_slug' => 'simpeg.pegawai.read', 'order_index' => 1],
                    ['name' => 'Dokumen E-File Digital', 'url' => '/simpeg/dokumen', 'icon' => 'FaFileAlt', 'module' => 'simpeg', 'permission_slug' => 'simpeg.dokumen.read', 'order_index' => 2],
                    ['name' => 'Layanan & Cuti Pegawai', 'url' => '/simpeg/cuti', 'icon' => 'FaCalendarCheck', 'module' => 'simpeg', 'permission_slug' => 'simpeg.cuti.read', 'order_index' => 3],
                    ['name' => 'Monitoring Presensi', 'url' => '/simpeg/presensi', 'icon' => 'FaClock', 'module' => 'simpeg', 'permission_slug' => 'simpeg.presensi.read', 'order_index' => 4],
                    ['name' => 'Payroll & Slip Gaji', 'url' => '/simpeg/payroll', 'icon' => 'FaMoneyBillWave', 'module' => 'simpeg', 'permission_slug' => 'simpeg.payroll.read', 'order_index' => 5],
                    ['name' => 'Usulan Jafung (KUM)', 'url' => '/simpeg/usulan-jafung', 'icon' => 'FaAward', 'module' => 'simpeg', 'permission_slug' => 'simpeg.usulan_jafung.read', 'order_index' => 6],
                    ['name' => 'Penilaian Kinerja SKP', 'url' => '/simpeg/kinerja', 'icon' => 'FaTrophy', 'module' => 'simpeg', 'permission_slug' => 'simpeg.kinerja.read', 'order_index' => 7],
                ]
            ],
            [
                'name' => 'MASTER DATA',
                'url' => '#master_simpeg',
                'icon' => 'FaList',
                'module' => 'simpeg',
                'order_index' => 3,
                'children' => [
                    ['name' => 'Unit Kerja', 'url' => '/simpeg/unit-kerja', 'icon' => 'FaSitemap', 'module' => 'simpeg', 'permission_slug' => 'simpeg.unit_kerja.read', 'order_index' => 1],
                    ['name' => 'Master Jabatan & Jafung', 'url' => '/simpeg/jabatan', 'icon' => 'FaBriefcase', 'module' => 'simpeg', 'permission_slug' => 'simpeg.jabatan.read', 'order_index' => 2],
                ]
            ],

            // Menu SIPPM (Penelitian & PkM)
            [
                'name' => 'Dashboard SIPPM',
                'url' => '/sippm',
                'icon' => 'FaChartPie',
                'module' => 'sippm',
                'permission_slug' => 'sippm.dashboard.read',
                'order_index' => 1,
            ],
            [
                'name' => 'PENELITIAN & PKM',
                'url' => '#layanan_sippm',
                'icon' => 'FaList',
                'module' => 'sippm',
                'order_index' => 2,
                'children' => [
                    ['name' => 'Proposal Usulan', 'url' => '/sippm/proposal', 'icon' => 'FaFileAlt', 'module' => 'sippm', 'permission_slug' => 'sippm.proposal.read', 'order_index' => 1],
                    ['name' => 'Portal Reviewer', 'url' => '/sippm/reviewer', 'icon' => 'FaClipboardCheck', 'module' => 'sippm', 'permission_slug' => 'sippm.reviewer.read', 'order_index' => 2],
                    ['name' => 'Kontrak Hibah SPK', 'url' => '/sippm/kontrak', 'icon' => 'FaFileCheck', 'module' => 'sippm', 'permission_slug' => 'sippm.kontrak.read', 'order_index' => 3],
                    ['name' => 'Pencairan Dana & LPJ', 'url' => '/sippm/pencairan', 'icon' => 'FaCreditCard', 'module' => 'sippm', 'permission_slug' => 'sippm.pencairan.read', 'order_index' => 4],
                ]
            ],
            [
                'name' => 'PORTOFOLIO LUARAN',
                'url' => '#luaran_sippm',
                'icon' => 'FaList',
                'module' => 'sippm',
                'order_index' => 3,
                'children' => [
                    ['name' => 'Publikasi Ilmiah', 'url' => '/sippm/luaran/publikasi', 'icon' => 'FaBookOpen', 'module' => 'sippm', 'permission_slug' => 'sippm.luaran.read', 'order_index' => 1],
                    ['name' => 'HKI & Paten Kampus', 'url' => '/sippm/luaran/hki', 'icon' => 'FaAward', 'module' => 'sippm', 'permission_slug' => 'sippm.luaran.read', 'order_index' => 2],
                ]
            ],
            [
                'name' => 'MASTER DATA',
                'url' => '#master_sippm',
                'icon' => 'FaList',
                'module' => 'sippm',
                'order_index' => 4,
                'children' => [
                    ['name' => 'Master Skema Kegiatan', 'url' => '/sippm/skema', 'icon' => 'FaLayers', 'module' => 'sippm', 'permission_slug' => 'sippm.skema.read', 'order_index' => 1],
                    ['name' => 'Master Periode Hibah', 'url' => '/sippm/periode', 'icon' => 'FaCalendar', 'module' => 'sippm', 'permission_slug' => 'sippm.periode.read', 'order_index' => 2],
                    ['name' => 'Standar IKU 5 Prodi', 'url' => '/sippm/iku5-standards', 'icon' => 'FaChartPie', 'module' => 'sippm', 'permission_slug' => 'sippm.iku5.manage', 'order_index' => 3],
                ]
            ],
        ];

        foreach ($menus as $menuData) {
            $permissionId = null;
            if (isset($menuData['permission_slug'])) {
                $permission = Permission::where('slug', $menuData['permission_slug'])->first();
                $permissionId = $permission ? $permission->id : null;
            }

            $parent = Menu::updateOrCreate(
                ['url' => $menuData['url'], 'module' => $menuData['module']],
                [
                    'name' => $menuData['name'],
                    'icon' => $menuData['icon'],
                    'permission_id' => $permissionId,
                    'order_index' => $menuData['order_index'],
                    'is_active' => true,
                ]
            );

            if (isset($menuData['children'])) {
                foreach ($menuData['children'] as $childData) {
                    $childPermissionId = null;
                    if (isset($childData['permission_slug'])) {
                        $childPermission = Permission::where('slug', $childData['permission_slug'])->first();
                        $childPermissionId = $childPermission ? $childPermission->id : null;
                    }

                    Menu::updateOrCreate(
                        ['url' => $childData['url'], 'module' => $childData['module']],
                        [
                            'parent_id' => $parent->id,
                            'name' => $childData['name'],
                            'icon' => $childData['icon'],
                            'permission_id' => $childPermissionId,
                            'order_index' => $childData['order_index'],
                            'is_active' => true,
                        ]
                    );
                }
            }
        }
    }
}
