<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\Menu;
use App\Models\Permission;
use App\Models\Role;

/**
 * Fase 5 LMS — ringkasan dashboard & pengaturan level modul.
 *
 * Menambahkan:
 *   - Permission `lms.pengaturan.read` & `lms.pengaturan.manage` (admin/superadmin).
 *   - Menu `/lms/dashboard` → ringkasan statistik split role.
 *   - Menu `/lms/pengaturan/modul` → form pengaturan global (`lms_*`).
 *
 * Dibuat lewat migrasi (bukan MenuSeeder) karena `MenuSeeder` melakukan
 * `Menu::truncate()`; menu yang hanya hidup di seeder akan hilang setiap
 * seeding. Pola ini sama dengan migrasi menu LMS module-level sebelumnya.
 */
return new class extends Migration
{
    private const PERMISSIONS = [
        [
            'slug' => 'lms.pengaturan.read',
            'name' => 'Lihat Pengaturan Modul LMS',
            'action' => 'read',
            'description' => 'Melihat konfigurasi global modul LMS (batas import, batas upload, feature flag)',
        ],
        [
            'slug' => 'lms.pengaturan.manage',
            'name' => 'Kelola Pengaturan Modul LMS',
            'action' => 'update',
            'description' => 'Mengubah konfigurasi global modul LMS',
        ],
    ];

    /**
     * @var array<int, array<string, mixed>>
     */
    private const MENUS = [
        [
            'url' => '/lms/dashboard',
            'name' => 'Ringkasan LMS',
            'icon' => 'FaChartPie',
            'permission_slug' => 'siakad.kelas.read',
            'order_index' => 0,
            'role_slugs' => ['superadmin', 'admin', 'dosen', 'mahasiswa', 'kaprodi', 'wakil_prodi'],
        ],
        [
            'url' => '/lms/pengaturan/modul',
            'name' => 'Pengaturan Modul LMS',
            'icon' => 'FaCogs',
            'permission_slug' => 'lms.pengaturan.read',
            'order_index' => 9,
            'role_slugs' => ['superadmin', 'admin'],
        ],
    ];

    private const ROLE_SLUGS = ['superadmin', 'admin'];

    public function up(): void
    {
        foreach (self::PERMISSIONS as $definition) {
            Permission::updateOrCreate(
                ['slug' => $definition['slug']],
                [
                    'name' => $definition['name'],
                    'module' => 'lms',
                    'action' => $definition['action'],
                    'description' => $definition['description'],
                ]
            );
        }

        $this->attachPermissions();

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
    }

    public function down(): void
    {
        Menu::whereIn('url', array_column(self::MENUS, 'url'))
            ->where('module', 'lms')
            ->delete();

        Permission::whereIn('slug', array_column(self::PERMISSIONS, 'slug'))->delete();
    }

    private function attachPermissions(): void
    {
        $roles = Role::whereIn('slug', self::ROLE_SLUGS)->get();

        if ($roles->isEmpty()) {
            return;
        }

        foreach (self::PERMISSIONS as $definition) {
            $permission = Permission::where('slug', $definition['slug'])->first();

            if (!$permission) {
                continue;
            }

            foreach ($roles as $role) {
                $role->permissions()->syncWithoutDetaching([$permission->id]);
            }
        }
    }
};
