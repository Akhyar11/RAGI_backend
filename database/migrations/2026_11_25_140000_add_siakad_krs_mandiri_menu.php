<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Menu "KRS Mandiri" (/siakad/krs) untuk instalasi yang sudah berjalan.
 *
 * Halaman portal KRS mahasiswa sudah ada di frontend tetapi belum punya menu
 * sehingga tidak muncul di sidebar. Definisi yang sama ada di
 * `menu_definitions.php` (dipakai MenuSeeder untuk instalasi baru); migrasi ini
 * memastikan menu + pemetaan role tersedia tanpa menjalankan seeder yang
 * melakukan truncate. Idempoten.
 */
return new class extends Migration
{
    private const URL = '/siakad/krs';
    private const PARENT_URL = '#perkuliahan_siakad';
    private const PERMISSION_SLUG = 'siakad.krs.read';

    /** Role pengguna portal KRS; superadmin/admin mengikuti aturan MenuSeeder. */
    private const ROLE_SLUGS = ['superadmin', 'admin', 'mahasiswa', 'admin_siakad'];

    public function up(): void
    {
        if (! Schema::hasTable('core_menus') || ! Schema::hasTable('core_menu_role')) {
            return;
        }

        $parentId = DB::table('core_menus')
            ->where('url', self::PARENT_URL)
            ->where('module', 'siakad')
            ->value('id');

        if (! $parentId) {
            // Instalasi baru: MenuSeeder membuat grup beserta menu ini.
            return;
        }

        $permissionId = Schema::hasTable('core_permissions')
            ? DB::table('core_permissions')->where('slug', self::PERMISSION_SLUG)->value('id')
            : null;

        $data = [
            'parent_id' => $parentId,
            'name' => 'KRS Mandiri',
            'icon' => 'FaClipboardCheck',
            'module' => 'siakad',
            'permission_id' => $permissionId,
            'order_index' => 3,
            'is_active' => true,
            'updated_at' => now(),
        ];

        $menuId = DB::table('core_menus')
            ->where('url', self::URL)
            ->where('module', 'siakad')
            ->value('id');

        if ($menuId) {
            DB::table('core_menus')->where('id', $menuId)->update($data);
        } else {
            $menuId = DB::table('core_menus')->insertGetId($data + [
                'url' => self::URL,
                'created_at' => now(),
            ]);
        }

        if (! Schema::hasTable('core_roles')) {
            return;
        }

        $roleIds = DB::table('core_roles')->whereIn('slug', self::ROLE_SLUGS)->pluck('id');
        foreach ($roleIds as $roleId) {
            DB::table('core_menu_role')->updateOrInsert([
                'menu_id' => $menuId,
                'role_id' => $roleId,
            ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('core_menus')) {
            return;
        }

        $menuId = DB::table('core_menus')
            ->where('url', self::URL)
            ->where('module', 'siakad')
            ->value('id');

        if (! $menuId) {
            return;
        }

        if (Schema::hasTable('core_menu_role')) {
            DB::table('core_menu_role')->where('menu_id', $menuId)->delete();
        }

        DB::table('core_menus')->where('id', $menuId)->delete();
    }
};
