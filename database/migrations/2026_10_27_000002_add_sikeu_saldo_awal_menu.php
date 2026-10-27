<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Menu "Saldo Awal Akuntansi" untuk instalasi yang sudah berjalan.
 *
 * MenuSeeder melakukan truncate pada `core_menus`, sehingga menu yang hanya
 * didefinisikan di `menu_definitions.php` belum tentu ada di database sebelum
 * seeding dijalankan. Migrasi ini memastikan menu + pemetaan role tersedia
 * setelah `php artisan migrate` (idempoten, aman bila seeder sudah dijalankan).
 */
return new class extends Migration
{
    private const URL = '/sikeu/akuntansi/saldo-awal';
    private const PARENT_URL = '#akuntansi_sikeu';

    /**
     * Menu referensi yang memiliki cakupan role sama dengan saldo awal
     * (Jurnal Umum). Pemetaan role diturunkan dari relasi menu-role yang sudah
     * ada, bukan dari daftar slug role yang di-hardcode.
     */
    private const REFERENSI_URL = '/sikeu/akuntansi/jurnal';

    public function up(): void
    {
        if (! Schema::hasTable('core_menus') || ! Schema::hasTable('core_menu_role')) {
            return;
        }

        $parentId = DB::table('core_menus')->where('url', self::PARENT_URL)->value('id');
        $permissionId = Schema::hasTable('core_permissions')
            ? DB::table('core_permissions')->where('slug', 'sikeu.akuntansi.manage')->value('id')
            : null;

        $menuId = DB::table('core_menus')
            ->where('url', self::URL)
            ->where('module', 'sikeu')
            ->value('id');

        $data = [
            'parent_id' => $parentId,
            'name' => 'Saldo Awal Akuntansi',
            'icon' => 'FaBalanceScale',
            'module' => 'sikeu',
            'permission_id' => $permissionId,
            'order_index' => 4,
            'is_active' => true,
            'updated_at' => now(),
        ];

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

        $referensiMenuId = DB::table('core_menus')->where('url', self::REFERENSI_URL)->value('id');
        if (! $referensiMenuId) {
            // Instalasi baru: MenuSeeder akan memetakan role setelah migrasi.
            return;
        }

        $roleIds = DB::table('core_menu_role')
            ->where('menu_id', $referensiMenuId)
            ->pluck('role_id');

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
            ->where('module', 'sikeu')
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
