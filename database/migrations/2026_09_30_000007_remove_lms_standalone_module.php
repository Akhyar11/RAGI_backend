<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\Module;
use App\Models\Menu;
use App\Models\Role;

/**
 * Keputusan arsitektur: LMS BUKAN subsistem/modul tersendiri.
 * LMS hidup sebagai menu di dalam Modul SIAKAD ("LMS & Konten Perkuliahan",
 * url /siakad/lms) karena tabel lms_* terikat erat ke siakad_kelas,
 * siakad_pertemuan, KRS, OBE, dan absensi.
 *
 * Migrasi ini menghapus sisa pendaftaran modul standalone 'lms' beserta
 * menu duplikatnya ("Kelas Saya") yang dibuat oleh migrasi 000003.
 * Menu SIAKAD (module=siakad) TIDAK tersentuh.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $lmsMenus = Menu::where('url', '/siakad/lms')
            ->where('module', 'lms')
            ->get();

        foreach ($lmsMenus as $menu) {
            $menu->roles()->detach();
            $menu->delete();
        }

        Module::where('code', 'lms')->delete();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $module = Module::updateOrCreate(
            ['code' => 'lms'],
            [
                'name' => 'LMS (Learning Management)',
                'description' => 'Sistem Manajemen Pembelajaran — Materi, Tugas & Absensi Terintegrasi',
                'is_active' => true,
                'primary_color' => '#6366f1', // Indigo
            ]
        );

        $roles = Role::whereIn('name', [
            'Super Administrator',
            'Administrator',
            'Dosen Pengajar',
            'Mahasiswa',
            'Ketua Program Studi',
            'Wakil Ketua Program Studi'
        ])->get();

        $lmsMenu = Menu::updateOrCreate(
            ['url' => '/siakad/lms', 'module' => 'lms'],
            [
                'parent_id' => null,
                'name' => 'Kelas Saya',
                'icon' => 'BookOpen',
                'order_index' => 1,
                'is_active' => true,
            ]
        );

        if ($roles->isNotEmpty()) {
            $lmsMenu->roles()->syncWithoutDetaching($roles->pluck('id'));
        }
    }
};
