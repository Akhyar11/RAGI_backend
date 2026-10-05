<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\Module;
use App\Models\Menu;
use App\Models\Permission;
use App\Models\Role;

/**
 * Pisah LMS jadi modul standalone agar tidak menumpuk di menu SIAKAD.
 *
 * - Daftarkan module `lms` (indigo #6366f1).
 * - Hapus menu lama `/siakad/lms` (module=siakad).
 * - Buat menu baru `/lms` (module=lms) untuk dosen/mahasiswa/admin.
 * - Tabel lms_* TETAP berelasi ke siakad_kelas/pertemuan/KRS/OBE.
 * - Otorisasi API tetap memakai Gate siakad.kelas.* agar role existing tidak putus.
 */
return new class extends Migration
{
    public function up(): void
    {
        Module::updateOrCreate(
            ['code' => 'lms'],
            [
                'name' => 'LMS (Learning Management)',
                'description' => 'Sistem Manajemen Pembelajaran — Materi, Tugas & Absensi Terintegrasi',
                'is_active' => true,
                'primary_color' => '#6366f1',
            ]
        );

        // Hapus menu lama yang menumpang di SIAKAD
        $oldMenus = Menu::where('url', '/siakad/lms')->where('module', 'siakad')->get();
        foreach ($oldMenus as $menu) {
            $menu->roles()->detach();
            $menu->delete();
        }

        $permission = Permission::where('slug', 'siakad.kelas.read')->first();

        $lmsMenu = Menu::updateOrCreate(
            ['url' => '/lms', 'module' => 'lms'],
            [
                'parent_id' => null,
                'name' => 'LMS & Kelas Saya',
                'icon' => 'BookOpen',
                'permission_id' => $permission?->id,
                'order_index' => 1,
                'is_active' => true,
            ]
        );

        $roles = Role::whereIn('slug', [
            'superadmin',
            'admin',
            'dosen',
            'mahasiswa',
            'admin_spmb',
        ])->orWhereIn('name', [
            'Super Administrator',
            'Administrator',
            'Dosen Pengajar',
            'Mahasiswa',
            'Ketua Program Studi',
            'Wakil Ketua Program Studi',
        ])->get();

        if ($roles->isNotEmpty()) {
            $lmsMenu->roles()->syncWithoutDetaching($roles->pluck('id'));
        }
    }

    public function down(): void
    {
        $menus = Menu::where('url', '/lms')->where('module', 'lms')->get();
        foreach ($menus as $menu) {
            $menu->roles()->detach();
            $menu->delete();
        }

        Module::where('code', 'lms')->delete();
    }
};
