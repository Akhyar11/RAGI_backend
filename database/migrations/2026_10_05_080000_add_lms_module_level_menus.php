<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\Menu;
use App\Models\Permission;
use App\Models\Role;

/**
 * Tambahkan menu modul LMS pada level modul (module-level).
 *
 * Latar belakang: seluruh endpoint LMS lama bersifat per-kelas
 * (`/kelas/{kelasId}/tryout`, dll), sedangkan menu sidebar bersifat modul-level
 * (URL tanpa parameter). Karena itu modul ini menambah halaman agregat:
 *   - /lms/pertemuan  → daftar pertemuan dari seluruh kelas yang diakses
 *   - /lms/tryout     → daftar tryout dari seluruh kelas yang diakses
 *   - /lms/forum      → daftar topik forum dari seluruh kelas yang diakses
 *   - /lms/pengaturan → rekap pengaturan LMS per kelas
 *
 * Menu ini dibuat lewat migrasi (bukan MenuSeeder) karena MenuSeeder melakukan
 * `Menu::truncate()`, sehingga menu yang hanya dibuat di seeder akan hilang
 * setiap kali seeding dijalankan. Menu LMS yang pertama kali dibuat juga
 * memakai pola yang sama pada 2026_10_01_000001_create_lms_standalone_module.
 *
 * Pembedaan hak akses:
 *   - Empat halaman baca  → permission `siakad.kelas.read`
 *   - Pengaturan LMS      → permission `siakad.kelas.manage` (dosen/kaprodi/admin)
 *
 * Mahasiswa sengaja TIDAK diberi `siakad.kelas.manage` maupun menu Pengaturan.
 *
 * Kaprodi/Wakil Prodi belum memegang `siakad.kelas.manage` (di `PermissionSeeder`
 * permission ini hanya diberikan ke role Dosen), sehingga migrasi ini juga memberi
 * permission tersebut secara idempoten. Tanpa itu, menu Pengaturan LMS sudah
 * ter-mapping ke role tersebut tapi tetap tidak muncul, karena penyaring
 * `MenuService` mewajibkan role memegang permission menu.
 */
return new class extends Migration
{
    /**
     * Definisi menu module-level LMS.
     *
     * `permission_slug` menunjuk ke tabel master permission (bukan string
     * literal di query), dan `role_slugs` memakai slug role yang sudah ada di
     * `core_roles`. Keduanya dicek keberadaannya secara defensif agar migrasi
     * aman dijalankan pada database yang belum ter-seed penuh.
     *
     * @var array<int, array<string, mixed>>
     */
    private const MENUS = [
        [
            'url' => '/lms/pertemuan',
            'name' => 'Pertemuan',
            'icon' => 'FaCalendarCheck',
            'permission_slug' => 'siakad.kelas.read',
            'order_index' => 2,
            'role_slugs' => ['superadmin', 'admin', 'dosen', 'mahasiswa', 'kaprodi', 'wakil_prodi'],
        ],
        [
            'url' => '/lms/tryout',
            'name' => 'Tryout',
            'icon' => 'FaClipboardCheck',
            'permission_slug' => 'siakad.kelas.read',
            'order_index' => 3,
            'role_slugs' => ['superadmin', 'admin', 'dosen', 'mahasiswa', 'kaprodi', 'wakil_prodi'],
        ],
        [
            'url' => '/lms/forum',
            'name' => 'Forum Diskusi',
            'icon' => 'FaUsers',
            'permission_slug' => 'siakad.kelas.read',
            'order_index' => 4,
            'role_slugs' => ['superadmin', 'admin', 'dosen', 'mahasiswa', 'kaprodi', 'wakil_prodi'],
        ],
        [
            'url' => '/lms/pengaturan',
            'name' => 'Pengaturan LMS',
            'icon' => 'FaCogs',
            // Halaman pengaturan hanya bagi pengelola kelas.
            'permission_slug' => 'siakad.kelas.manage',
            'order_index' => 5,
            // Mahasiswa tidak punya siakad.kelas.manage, jadi sengaja tidak di sini.
            'role_slugs' => ['superadmin', 'admin', 'dosen', 'kaprodi', 'wakil_prodi'],
        ],
    ];

    /**
     * Permission yang perlu ditambahkan agar Kaprodi/Wakil Prodi bisa membuka
     * menu Pengaturan LMS. Mahasiswa tidak termasuk daftar ini.
     *
     * @var array<int, string>
     */
    private const MANAGE_PERMISSION_GRANTS = [
        'siakad.kelas.manage',
    ];

    /**
     * Role golong yang menerima MANAGE_PERMISSION_GRANTS.
     *
     * @var array<int, string>
     */
    private const MANAGE_PERMISSION_ROLES = [
        'kaprodi',
        'wakil_prodi',
    ];

    public function up(): void
    {
        foreach (self::MENUS as $definition) {
            $permission = Permission::where('slug', $definition['permission_slug'])->first();

            $menu = Menu::updateOrCreate(
                ['url' => $definition['url'], 'module' => 'lms'],
                [
                    'parent_id' => null,
                    'name' => $definition['name'],
                    'icon' => $definition['icon'],
                    'permission_id' => $permission?->id,
                    'order_index' => $definition['order_index'],
                    'is_active' => true,
                ]
            );

            $roleIds = Role::whereIn('slug', $definition['role_slugs'])->pluck('id')->all();

            if ($roleIds !== []) {
                $menu->roles()->sync($roleIds);
            }
        }

        $this->grantManagePermission();
    }

    /**
     * Beri permission kelola kelas kepada Kaprodi/Wakil Prodi.
     *
     * Tanpa ini, menu Pengaturan LMS sudah ter-mapping ke role tersebut
     * tetap tidak muncul karena penyaring MenuService mewajibkan permission.
     *
     * Dijalankan secara defensif (idempoten) agar aman bila migrasi dijalankan
     * pada database yang belum ter-seed penuh.
     */
    private function grantManagePermission(): void
    {
        $roles = Role::whereIn('slug', self::MANAGE_PERMISSION_ROLES)->get();

        if ($roles->isEmpty()) {
            return;
        }

        foreach (self::MANAGE_PERMISSION_GRANTS as $slug) {
            $permission = Permission::where('slug', $slug)->first();

            if (!$permission) {
                continue;
            }

            foreach ($roles as $role) {
                $role->permissions()->syncWithoutDetaching([$permission->id]);
            }
        }
    }

    public function down(): void
    {
        $menus = Menu::where('module', 'lms')
            ->whereIn('url', array_column(self::MENUS, 'url'))
            ->get();

        foreach ($menus as $menu) {
            $menu->roles()->detach();
            $menu->delete();
        }

        // Cabut kembali permission yang diberikan migrasi ini.
        $managePermission = Permission::where('slug', 'siakad.kelas.manage')->first();

        if ($managePermission) {
            Role::whereIn('slug', self::MANAGE_PERMISSION_ROLES)
                ->each(fn (Role $role) => $role->permissions()->detach([$managePermission->id]));
        }
    }
};