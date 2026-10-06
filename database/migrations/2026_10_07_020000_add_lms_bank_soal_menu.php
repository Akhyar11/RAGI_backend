<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\Menu;
use App\Models\Permission;
use App\Models\Role;

/**
 * Tambahkan menu "Bank Soal" modul LMS pada level modul (module-level).
 *
 * Menu: /lms/bank-soal → agregat bank soal dari seluruh kelas yang diakses.
 *
 * Hak akses: permission `siakad.nilai.manage` (dosen/kaprodi/admin).
 * Mahasiswa sengaja TIDAK diberi permission ini maupun menu Bank Soal.
 *
 * Kaprodi/Wakil Prodi belum tentu memegang `siakad.nilai.manage` (di
 * `PermissionSeeder` permission ini hanya diberikan ke role Dosen, selebihnya
 * Superadmin/Admin yang memegang semua permission), sehingga migrasi ini juga
 * memberi permission tersebut secara idempoten. Tanpa itu, menu Bank Soal sudah
 * ter-mapping ke role tersebut tapi tetap tidak muncul, karena penyaring
 * `MenuService` mewajibkan role memegang permission menu.
 */
return new class extends Migration
{
    /**
     * Definisi menu Bank Soal LMS.
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
            'url' => '/lms/bank-soal',
            'name' => 'Bank Soal',
            'icon' => 'FaDatabase',
            'permission_slug' => 'siakad.nilai.manage',
            'order_index' => 6,
            // Mahasiswa tidak punya siakad.nilai.manage, jadi sengaja tidak di sini.
            'role_slugs' => ['superadmin', 'admin', 'dosen', 'kaprodi', 'wakil_prodi'],
        ],
    ];

    /**
     * Permission yang perlu ditambahkan agar Kaprodi/Wakil Prodi bisa membuka
     * menu Bank Soal. Mahasiswa tidak termasuk daftar ini.
     *
     * @var array<int, string>
     */
    private const MANAGE_PERMISSION_GRANTS = [
        'siakad.nilai.manage',
    ];

    /**
     * Role yang menerima MANAGE_PERMISSION_GRANTS.
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
     * Beri permission nilai-manage kepada Kaprodi/Wakil Prodi.
     *
     * Tanpa ini, menu Bank Soal sudah ter-mapping ke role tersebut
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
        $managePermission = Permission::where('slug', 'siakad.nilai.manage')->first();

        if ($managePermission) {
            Role::whereIn('slug', self::MANAGE_PERMISSION_ROLES)
                ->each(fn (Role $role) => $role->permissions()->detach([$managePermission->id]));
        }
    }
};
