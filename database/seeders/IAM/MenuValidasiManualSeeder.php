<?php

namespace Database\Seeders\IAM;

use App\Models\Menu;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Menu validasi bukti transfer manual mahasiswa (keuangan).
 * Idempoten via updateOrCreate berdasarkan url.
 */
class MenuValidasiManualSeeder extends Seeder
{
    public function run(): void
    {
        $parent = Menu::where('url', '#pembayaran_mhs_sikeu')->first();
        if (!$parent) {
            $this->command->warn('Parent menu #pembayaran_mhs_sikeu tidak ditemukan, lewati MenuValidasiManualSeeder.');
            return;
        }

        // Visibilitas via role-menu (tanpa permission) agar tidak bocor
        // ke role yang hanya memegang permission read umum.
        $menu = Menu::updateOrCreate(
            ['url' => '/sikeu/pembayaran-mahasiswa/validasi-manual'],
            [
                'parent_id' => $parent->id,
                'name' => 'Validasi Transfer Manual',
                'icon' => 'FaClipboardCheck',
                'module' => 'sikeu',
                'permission_id' => null,
                'order_index' => 7,
                'is_active' => true,
            ]
        );

        foreach (['superadmin', 'admin', 'operator_sikeu', 'kabag_keuangan', 'admin_keuangan_pembayaran'] as $slug) {
            $role = Role::where('slug', $slug)->first();
            if ($role) {
                $role->menus()->syncWithoutDetaching([$menu->id]);
            }
        }
    }
}
