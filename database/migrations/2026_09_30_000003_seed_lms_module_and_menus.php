<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use App\Models\Module;
use App\Models\Menu;
use App\Models\Role;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Daftarkan Modul LMS di core_modules
        $module = Module::updateOrCreate(
            ['code' => 'lms'],
            [
                'name' => 'LMS (Learning Management)',
                'description' => 'Sistem Manajemen Pembelajaran — Materi, Tugas & Absensi Terintegrasi',
                'is_active' => true,
                'primary_color' => '#6366f1', // Indigo
            ]
        );

        // Ambil role-role umum yang berhak mengakses LMS
        $roles = Role::whereIn('name', [
            'Super Administrator',
            'Administrator',
            'Dosen Pengajar',
            'Mahasiswa',
            'Ketua Program Studi',
            'Wakil Ketua Program Studi'
        ])->get();

        // 2. Menu di bawah Modul LMS (untuk AppLauncher langsung ke LMS)
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

        // 3. Menu di dalam Modul SIAKAD (di bawah PERKULIAHAN & OBE)
        $parentPerkuliahan = Menu::where('module', 'siakad')
            ->where('url', '#perkuliahan_siakad')
            ->first();

        $siakadLmsMenu = Menu::updateOrCreate(
            ['url' => '/siakad/lms', 'module' => 'siakad'],
            [
                'parent_id' => $parentPerkuliahan?->id,
                'name' => 'LMS & Konten Perkuliahan',
                'icon' => 'BookOpen',
                'order_index' => 67, // Tepat di area perkuliahan
                'is_active' => true,
            ]
        );

        if ($roles->isNotEmpty()) {
            $siakadLmsMenu->roles()->syncWithoutDetaching($roles->pluck('id'));
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Menu::where('url', '/siakad/lms')->delete();
        Module::where('code', 'lms')->delete();
    }
};
