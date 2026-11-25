<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Menu PEMBELAJARAN LMS dipangkas: pertemuan sudah tersedia ketika user
     * membuka Kelas (`/lms`), sehingga menu agregat `/lms/pertemuan` yang
     * mewajibkan user mencari matakuliah tidak lagi ditampilkan.
     *
     * Menu dinonaktifkan (tidak dihapus) agar relasi role tetap ada dan
     * rollback aman, mengikuti pola migrasi `2026_10_06_090000_lms_alur_baru`.
     */
    public function up(): void
    {
        if (! Schema::hasTable('core_menus')) {
            return;
        }

        DB::table('core_menus')
            ->where('module', 'lms')
            ->where('url', '/lms/pertemuan')
            ->update(['is_active' => false]);

        $parentId = DB::table('core_menus')
            ->where('module', 'lms')
            ->where('url', '#pembelajaran_lms')
            ->value('id');

        if ($parentId) {
            DB::table('core_menus')
                ->where('module', 'lms')
                ->where('parent_id', $parentId)
                ->where('url', '/lms/forum')
                ->update(['order_index' => 2]);

            DB::table('core_menus')
                ->where('module', 'lms')
                ->where('parent_id', $parentId)
                ->where('url', '/lms/notifikasi')
                ->update(['order_index' => 3]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('core_menus')) {
            return;
        }

        DB::table('core_menus')
            ->where('module', 'lms')
            ->where('url', '/lms/pertemuan')
            ->update(['is_active' => true]);

        $parentId = DB::table('core_menus')
            ->where('module', 'lms')
            ->where('url', '#pembelajaran_lms')
            ->value('id');

        if ($parentId) {
            DB::table('core_menus')
                ->where('module', 'lms')
                ->where('parent_id', $parentId)
                ->where('url', '/lms/forum')
                ->update(['order_index' => 3]);

            DB::table('core_menus')
                ->where('module', 'lms')
                ->where('parent_id', $parentId)
                ->where('url', '/lms/notifikasi')
                ->update(['order_index' => 4]);
        }
    }
};
