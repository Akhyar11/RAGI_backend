<?php

use App\Models\Menu;
use App\Models\Permission;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Menu Diskusi LMS sebelumnya memakai `siakad.kelas.read`, padahal seluruh
 * endpoint forum dilindungi `lms.forum.read`. Akibatnya visibility menu tidak
 * mencerminkan otorisasi API. Perbaikan ini hanya memetakan ulang menu; tidak
 * menambah permission atau role baru.
 */
return new class extends Migration
{
    private const MENU_URL = '/lms/forum';
    private const PERMISSION_SEBELUMNYA = 'siakad.kelas.read';
    private const PERMISSION_BARU = 'lms.forum.read';

    public function up(): void
    {
        if (!Schema::hasTable('core_menus') || !Schema::hasTable('core_permissions')) {
            return;
        }

        $permissionId = Permission::where('slug', self::PERMISSION_BARU)->value('id');
        if (!$permissionId) {
            return;
        }

        // Beberapa database sudah punya menu forum dengan `permission_id` NULL
        // karena slug lama belum tersedia saat menu dibuat — therefore update
        // tanpa syarat slug lama agar data tersebut ikut diperbaiki.
        Menu::where('module', 'lms')
            ->where('url', self::MENU_URL)
            ->update(['permission_id' => $permissionId]);
    }

    public function down(): void
    {
        if (!Schema::hasTable('core_menus') || !Schema::hasTable('core_permissions')) {
            return;
        }

        $permissionId = Permission::where('slug', self::PERMISSION_SEBELUMNYA)->value('id');
        if (!$permissionId) {
            return;
        }

        Menu::where('module', 'lms')
            ->where('url', self::MENU_URL)
            ->whereHas('permission', fn ($q) => $q->where('slug', self::PERMISSION_BARU))
            ->update(['permission_id' => $permissionId]);
    }
};
