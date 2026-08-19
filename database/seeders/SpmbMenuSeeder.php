<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Menu;
use App\Models\Role;

class SpmbMenuSeeder extends Seeder
{
    public function run(): void
    {
        $adminRole = Role::where('name', 'admin_spmb')->first();

        // 1. Kuota Prodi
        $masterGroup = Menu::where('name', 'MASTER DATA')->where('module', 'spmb')->first();
        if ($masterGroup) {
            $menu = Menu::firstOrCreate(
                ['url' => '/spmb/master/kuota'],
                ['name' => 'Kuota Prodi', 'parent_id' => $masterGroup->id, 'module' => 'spmb', 'icon' => 'FaUsers', 'order_index' => 3, 'is_active' => true]
            );
            if ($adminRole) $adminRole->menus()->syncWithoutDetaching([$menu->id]);
        }

        // 2. Ujian Masuk (CBT)
        $ujianGroup = Menu::firstOrCreate(
            ['url' => '#ujian_masuk'],
            ['name' => 'UJIAN MASUK', 'module' => 'spmb', 'icon' => 'FaClipboardCheck', 'order_index' => 3, 'is_active' => true]
        );
        if ($adminRole) $adminRole->menus()->syncWithoutDetaching([$ujianGroup->id]);

        $jadwalMenu = Menu::firstOrCreate(
            ['url' => '/spmb/ujian/jadwal'],
            ['name' => 'Jadwal Ujian', 'parent_id' => $ujianGroup->id, 'module' => 'spmb', 'icon' => 'FaCalendar', 'order_index' => 1, 'is_active' => true]
        );
        $pesertaMenu = Menu::firstOrCreate(
            ['url' => '/spmb/ujian/peserta'],
            ['name' => 'Plotting Peserta', 'parent_id' => $ujianGroup->id, 'module' => 'spmb', 'icon' => 'FaUsers', 'order_index' => 2, 'is_active' => true]
        );
        if ($adminRole) $adminRole->menus()->syncWithoutDetaching([$jadwalMenu->id, $pesertaMenu->id]);

        // 3. Seleksi (Daftar Ulang)
        $seleksiGroup = Menu::firstOrCreate(
            ['url' => '#seleksi_spmb'],
            ['name' => 'SELEKSI', 'module' => 'spmb', 'icon' => 'FaCheckSquare', 'order_index' => 4, 'is_active' => true]
        );
        if ($adminRole) $adminRole->menus()->syncWithoutDetaching([$seleksiGroup->id]);

        $daftarUlangMenu = Menu::firstOrCreate(
            ['url' => '/spmb/seleksi/daftar-ulang'],
            ['name' => 'Daftar Ulang', 'parent_id' => $seleksiGroup->id, 'module' => 'spmb', 'icon' => 'FaFileCheck', 'order_index' => 1, 'is_active' => true]
        );
        if ($adminRole) $adminRole->menus()->syncWithoutDetaching([$daftarUlangMenu->id]);
    }
}
