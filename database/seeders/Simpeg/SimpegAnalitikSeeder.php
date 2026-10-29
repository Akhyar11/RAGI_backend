<?php

namespace Database\Seeders\Simpeg;

use App\Models\Menu;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class SimpegAnalitikSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Ambil permission resmi simpeg.pegawai.read
        $readPerm = Permission::where('slug', 'simpeg.pegawai.read')->first();

        // 2. Daftarkan Menu "Analitik Pegawai" di bawah MANAJEMEN KEPEGAWAIAN
        $parentMenu = Menu::where('module', 'simpeg')
            ->where('name', 'MANAJEMEN KEPEGAWAIAN')
            ->first();

        $parentId = $parentMenu ? $parentMenu->id : null;

        Menu::updateOrCreate(
            [
                'module' => 'simpeg',
                'url' => '/simpeg/analitik',
            ],
            [
                'parent_id' => $parentId,
                'name' => 'Analitik Pegawai',
                'icon' => 'FaChartPie',
                'permission_id' => $readPerm?->id,
                'order_index' => 3,
                'is_active' => true,
            ]
        );
    }
}
