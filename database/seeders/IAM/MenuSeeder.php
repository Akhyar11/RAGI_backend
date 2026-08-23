<?php

namespace Database\Seeders\IAM;

use Illuminate\Database\Seeder;
use App\Models\Menu;
use App\Models\Permission;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MenuSeeder extends Seeder
{
    public function run(): void
    {
        Schema::disableForeignKeyConstraints();
        Menu::truncate();
        Schema::enableForeignKeyConstraints();

        $menus = [
            // ── MODUL SSO (IAM) ───────────────────────────────────
            [
                'name' => 'Dashboard Utama',
                'url' => '/dashboard',
                'icon' => 'FaHome',
                'module' => 'sso',
                'order_index' => 1,
            ],
            [
                'name' => 'KONTROL AKSES SSO',
                'url' => '#iam_section',
                'icon' => 'FaShieldAlt',
                'module' => 'sso',
                'order_index' => 2,
                'children' => [
                    ['name' => 'Pengguna Portal', 'url' => '/admin/users', 'icon' => 'FaUsers', 'module' => 'sso', 'permission_slug' => 'iam.users.read', 'order_index' => 1],
                    ['name' => 'Master Role', 'url' => '/admin/roles', 'icon' => 'FaShieldAlt', 'module' => 'sso', 'permission_slug' => 'iam.roles.read', 'order_index' => 2],
                    ['name' => 'Hak Akses (Permissions)', 'url' => '/admin/permissions', 'icon' => 'FaList', 'module' => 'sso', 'permission_slug' => 'iam.permissions.read', 'order_index' => 3],
                    ['name' => 'Plotting User Role', 'url' => '/admin/user-roles', 'icon' => 'FaUsers', 'module' => 'sso', 'permission_slug' => 'iam.user_roles.manage', 'order_index' => 4],
                    ['name' => 'Plotting Role Permission', 'url' => '/admin/role-permissions', 'icon' => 'FaClipboardCheck', 'module' => 'sso', 'permission_slug' => 'iam.permissions.manage', 'order_index' => 5],
                    ['name' => 'Plotting Role Menu', 'url' => '/admin/role-menus', 'icon' => 'FaList', 'module' => 'sso', 'permission_slug' => 'iam.roles.update', 'order_index' => 6],
                    ['name' => 'Master Menu', 'url' => '/admin/menus', 'icon' => 'FaList', 'module' => 'sso', 'permission_slug' => 'iam.roles.update', 'order_index' => 7],
                    ['name' => 'Master Modul', 'url' => '/admin/modules', 'icon' => 'FaList', 'module' => 'sso', 'permission_slug' => 'iam.roles.update', 'order_index' => 8],
                ]
            ],
            [
                'name' => 'LOG & AUDIT',
                'url' => '#audit_section',
                'icon' => 'FaFileAlt',
                'module' => 'sso',
                'order_index' => 3,
                'children' => [
                    ['name' => 'Sesi Login Aktif', 'url' => '/admin/sessions', 'icon' => 'FaUsers', 'module' => 'sso', 'permission_slug' => 'iam.sessions.read', 'order_index' => 1],
                    ['name' => 'Audit Log Aktivitas', 'url' => '/admin/audit-logs', 'icon' => 'FaFileAlt', 'module' => 'sso', 'permission_slug' => 'iam.audit_logs.read', 'order_index' => 2],
                    ['name' => 'Pengaturan Sistem', 'url' => '/iam/settings', 'icon' => 'FaCogs', 'module' => 'sso', 'permission_slug' => 'iam.roles.update', 'order_index' => 3],
                ]
            ],
            [
                'name' => 'AKUN & KEAMANAN',
                'url' => '#akun_keamanan',
                'icon' => 'FaShieldAlt',
                'module' => 'sso',
                'order_index' => 999,
                'children' => [
                    ['name' => 'Profil Saya', 'url' => '/profile', 'icon' => 'FaUser', 'module' => 'sso', 'order_index' => 1],
                    ['name' => 'Sesi Perangkat', 'url' => '/profile/sessions', 'icon' => 'FaSmartphone', 'module' => 'sso', 'order_index' => 2],
                    ['name' => 'Autentikasi 2FA', 'url' => '/profile/mfa', 'icon' => 'FaShieldCheck', 'module' => 'sso', 'order_index' => 3],
                ]
            ],

            // ── MODUL SIAKAD (MENU DENGAN PEMBATASAN LEVEL ROLE & PERMISSION) ─────
            [
                'name' => 'Dashboard Akademik',
                'url' => '/siakad',
                'icon' => 'FaGraduationCap',
                'module' => 'siakad',
                'permission_slug' => 'siakad.dashboard.read',
                'order_index' => 1,
            ],
            [
                'name' => 'KRS Semester Aktif',
                'url' => '/siakad/krs',
                'icon' => 'FaClipboardCheck',
                'module' => 'siakad',
                'permission_slug' => 'siakad.krs.read',
                'order_index' => 2,
            ],
            [
                'name' => 'Jadwal Kuliah & RPS',
                'url' => '/siakad/perkuliahan/kelas',
                'icon' => 'FaCalendarCheck',
                'module' => 'siakad',
                'permission_slug' => 'siakad.kelas.read',
                'order_index' => 3,
            ],
            [
                'name' => 'KHS & Transkrip Nilai',
                'url' => '/siakad/nilai',
                'icon' => 'FaAward',
                'module' => 'siakad',
                'permission_slug' => 'siakad.nilai.read',
                'order_index' => 4,
            ],
            [
                'name' => 'MASTER AKADEMIK (BAAK)',
                'url' => '#master_siakad',
                'icon' => 'FaDatabase',
                'module' => 'siakad',
                'order_index' => 5,
                'children' => [
                    ['name' => 'Fakultas & Prodi', 'url' => '/siakad/master/fakultas', 'icon' => 'FaBuilding', 'module' => 'siakad', 'permission_slug' => 'siakad.master.manage', 'order_index' => 1],
                    ['name' => 'Kurikulum OBE', 'url' => '/siakad/master/kurikulum', 'icon' => 'FaBookOpen', 'module' => 'siakad', 'permission_slug' => 'siakad.master.manage', 'order_index' => 2],
                    ['name' => 'Mata Kuliah & Bobot', 'url' => '/siakad/master/matakuliah', 'icon' => 'FaList', 'module' => 'siakad', 'permission_slug' => 'siakad.matakuliah.manage', 'order_index' => 3],
                ]
            ],
            [
                'name' => 'CIVITAS AKADEMIKA (BAAK)',
                'url' => '#civitas_siakad',
                'icon' => 'FaUsers',
                'module' => 'siakad',
                'order_index' => 6,
                'children' => [
                    ['name' => 'Direktori Mahasiswa', 'url' => '/siakad/civitas/mahasiswa', 'icon' => 'FaUserGraduate', 'module' => 'siakad', 'permission_slug' => 'siakad.mahasiswa.read', 'order_index' => 1],
                    ['name' => 'Konversi Mahasiswa Transfer', 'url' => '/siakad/civitas/konversi', 'icon' => 'FaExchangeAlt', 'module' => 'siakad', 'permission_slug' => 'siakad.konversi.manage', 'order_index' => 2],
                    ['name' => 'Direktori Dosen Pengajar', 'url' => '/siakad/civitas/dosen', 'icon' => 'FaChalkboardTeacher', 'module' => 'siakad', 'permission_slug' => 'siakad.dosen.manage', 'order_index' => 3],
                ]
            ],
            [
                'name' => 'INTEGRASI DIKTI (BAAK)',
                'url' => '#feeder_siakad',
                'icon' => 'FaSyncAlt',
                'module' => 'siakad',
                'order_index' => 7,
                'children' => [
                    ['name' => 'Sinkronisasi Neo Feeder', 'url' => '/siakad/feeder-sync', 'icon' => 'FaCloudUploadAlt', 'module' => 'siakad', 'permission_slug' => 'siakad.feeder.manage', 'order_index' => 1],
                ]
            ],

            // Menu SPMB (Penerimaan Mahasiswa Baru)
            // A. Menu Portal Calon Mahasiswa
            [
                'name' => 'Dashboard Saya',
                'url' => '/spmb/dashboard',
                'icon' => 'FaChartPie',
                'module' => 'spmb',
                'permission_slug' => 'spmb.student.read',
                'order_index' => 1,
            ],
            [
                'name' => 'Formulir Pendaftaran',
                'url' => '/spmb/registrasi',
                'icon' => 'FaUserPlus',
                'module' => 'spmb',
                'permission_slug' => 'spmb.student.read',
                'order_index' => 2,
            ],

            // B. Menu Pengelola Admin SPMB
            [
                'name' => 'PENDAFTARAN & VERIFIKASI',
                'url' => '#layanan_spmb',
                'icon' => 'FaList',
                'module' => 'spmb',
                'permission_slug' => 'spmb.admin.manage',
                'order_index' => 3,
                'children' => [
                    ['name' => 'Data Pendaftar & Verifikasi', 'url' => '/spmb/pendaftaran', 'icon' => 'FaUsers', 'module' => 'spmb', 'permission_slug' => 'spmb.admin.manage', 'order_index' => 1],
                ]
            ],
            [
                'name' => 'SELEKSI ADMINISTRASI',
                'url' => '#seleksi_spmb',
                'icon' => 'FaCheckSquare',
                'module' => 'spmb',
                'permission_slug' => 'spmb.admin.manage',
                'order_index' => 4,
                'children' => [
                    ['name' => 'Hasil Seleksi Administrasi', 'url' => '/spmb/seleksi', 'icon' => 'FaTrophy', 'module' => 'spmb', 'permission_slug' => 'spmb.admin.manage', 'order_index' => 1],
                ]
            ],
            [
                'name' => 'MASTER DATA SPMB',
                'url' => '#master_spmb',
                'icon' => 'FaList',
                'module' => 'spmb',
                'permission_slug' => 'spmb.admin.manage',
                'order_index' => 5,
                'children' => [
                    ['name' => 'Jalur Masuk', 'url' => '/spmb/master/jalur', 'icon' => 'FaCogs', 'module' => 'spmb', 'permission_slug' => 'spmb.admin.manage', 'order_index' => 1],
                    ['name' => 'Gelombang Penerimaan', 'url' => '/spmb/master/gelombang', 'icon' => 'FaCalendar', 'module' => 'spmb', 'permission_slug' => 'spmb.admin.manage', 'order_index' => 2],
                    ['name' => 'Kuota Program Studi', 'url' => '/spmb/master/kuota', 'icon' => 'FaChartPie', 'module' => 'spmb', 'permission_slug' => 'spmb.admin.manage', 'order_index' => 3],
                    ['name' => 'Persyaratan Berkas', 'url' => '/spmb/master/berkas-requirement', 'icon' => 'FaFileAlt', 'module' => 'spmb', 'permission_slug' => 'spmb.admin.manage', 'order_index' => 4],
                ]
            ],

            // ── MODUL SINAPRA ─────────────────────────────────────
            [
                'name' => 'Gedung & Ruangan',
                'url' => '/sinapra/gedung-ruangan',
                'icon' => 'FaBuilding',
                'module' => 'sinapra',
                'permission_slug' => 'sinapra.ruangan.read',
                'order_index' => 1,
            ],
            [
                'name' => 'Inventaris Aset',
                'url' => '/sinapra/aset',
                'icon' => 'FaBoxes',
                'module' => 'sinapra',
                'permission_slug' => 'sinapra.aset.read',
                'order_index' => 2,
            ],
            [
                'name' => 'Peminjaman',
                'url' => '/sinapra/peminjaman',
                'icon' => 'FaCalendarCheck',
                'module' => 'sinapra',
                'permission_slug' => 'sinapra.dashboard.read',
                'order_index' => 3,
            ],
            [
                'name' => 'Maintenance',
                'url' => '/sinapra/maintenance',
                'icon' => 'FaWrench',
                'module' => 'sinapra',
                'permission_slug' => 'sinapra.dashboard.read',
                'order_index' => 4,
            ],
            [
                'name' => 'Pengadaan Barang',
                'url' => '/sinapra/pengadaan',
                'icon' => 'FaShoppingCart',
                'module' => 'sinapra',
                'permission_slug' => 'sinapra.dashboard.read',
                'order_index' => 5,
            ],
        ];

        foreach ($menus as $menuData) {
            $permissionId = null;
            if (isset($menuData['permission_slug'])) {
                $permission = Permission::where('slug', $menuData['permission_slug'])->first();
                $permissionId = $permission ? $permission->id : null;
            }

            $parent = Menu::create([
                'name' => $menuData['name'],
                'url' => $menuData['url'],
                'icon' => $menuData['icon'],
                'module' => $menuData['module'],
                'permission_id' => $permissionId,
                'order_index' => $menuData['order_index'],
                'is_active' => true,
            ]);

            if (isset($menuData['children'])) {
                foreach ($menuData['children'] as $childData) {
                    $childPermissionId = null;
                    if (isset($childData['permission_slug'])) {
                        $childPermission = Permission::where('slug', $childData['permission_slug'])->first();
                        $childPermissionId = $childPermission ? $childPermission->id : null;
                    }

                    Menu::create([
                        'parent_id' => $parent->id,
                        'name' => $childData['name'],
                        'url' => $childData['url'],
                        'icon' => $childData['icon'],
                        'module' => $childData['module'],
                        'permission_id' => $childPermissionId,
                        'order_index' => $childData['order_index'],
                        'is_active' => true,
                    ]);
                }
            }
        }

        // Attach default role_menus
        $allMenuIds = Menu::pluck('id')->toArray();
        $akunKeamananIds = Menu::where('url', '#akun_keamanan')
            ->orWhere('url', 'like', '/profile%')
            ->pluck('id')
            ->toArray();

        $roles = \App\Models\Role::all();
        foreach ($roles as $role) {
            if (in_array($role->slug, ['superadmin', 'admin'])) {
                $role->menus()->sync($allMenuIds);
            } else {
                // Ensure profile/security menus are attached for all roles
                $role->menus()->syncWithoutDetaching($akunKeamananIds);

                // Sync module specific menus
                $moduleSlug = str_replace('admin_', '', $role->slug);
                $moduleSlug = str_replace('operator_', '', $role->slug);
                $roleMenuIds = Menu::whereIn('module', ['sso', $moduleSlug])
                    ->where(function($q) {
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
            }
        }
    }
}
