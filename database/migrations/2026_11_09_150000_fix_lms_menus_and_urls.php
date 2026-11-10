<?php

use App\Models\Menu;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('core_permissions') && Schema::hasTable('core_roles')) {
            $permPresensiCreate = Permission::updateOrCreate(
                ['slug' => 'lms.presensi.create'],
                [
                    'name' => 'Scan QR / Input Presensi & Izin LMS',
                    'module' => 'lms',
                    'action' => 'create',
                    'description' => 'Mencatat kehadiran via scan QR/token 30 detik dan mengajukan permohonan izin/sakit perkuliahan',
                ]
            );
            $roleMhs = Role::where('slug', 'mahasiswa')->first();
            $roleMhs?->permissions()->syncWithoutDetaching([$permPresensiCreate->id]);
        }

        if (!Schema::hasTable('core_menus')) {
            return;
        }

        // 1. Pisahkan URL Dashboard LMS (/lms/dashboard) dari Kelas Perkuliahan (/lms)
        Menu::where('module', 'lms')
            ->whereNull('parent_id')
            ->where('name', 'Dashboard LMS')
            ->update(['url' => '/lms/dashboard']);

        // 2. Rapikan submenu di bawah grup PEMBELAJARAN & PERKULIAHAN (#pembelajaran_lms)
        $parentPembelajaran = Menu::where('module', 'lms')
            ->where('url', '#pembelajaran_lms')
            ->first();

        if ($parentPembelajaran) {
            $permKelasRead = Permission::where('slug', 'siakad.kelas.read')->value('id');

            Menu::updateOrCreate(
                ['module' => 'lms', 'url' => '/lms/pertemuan'],
                [
                    'name' => 'Pertemuan',
                    'icon' => 'FaCalendarAlt',
                    'parent_id' => $parentPembelajaran->id,
                    'permission_id' => $permKelasRead,
                    'order_index' => 2,
                    'is_active' => true,
                ]
            );

            Menu::where('module', 'lms')
                ->where('parent_id', $parentPembelajaran->id)
                ->where('url', '/lms')
                ->update(['order_index' => 1]);

            Menu::where('module', 'lms')
                ->where('parent_id', $parentPembelajaran->id)
                ->where('url', '/lms/forum')
                ->update(['order_index' => 3]);

            Menu::where('module', 'lms')
                ->where('parent_id', $parentPembelajaran->id)
                ->where('url', '/lms/notifikasi')
                ->update(['order_index' => 4]);
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('core_menus')) {
            return;
        }

        Menu::where('module', 'lms')
            ->whereNull('parent_id')
            ->where('name', 'Dashboard LMS')
            ->update(['url' => '/lms']);
    }
};
