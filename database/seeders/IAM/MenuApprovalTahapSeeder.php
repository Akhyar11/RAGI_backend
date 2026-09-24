<?php

namespace Database\Seeders\IAM;

use App\Models\Menu;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Menu antrean approval pengajuan operasional per tahap:
 * Sarpras (pending_sarpras), Keuangan (pending_keuangan), Pimpinan (pending_direktur).
 *
 * Idempoten (updateOrCreate berdasarkan url) sehingga aman dijalankan berulang.
 * Ketiga menu memakai permission `sikeu.pengajuan.approve`; pemecahan akses
 * per tahap (sarpras/keuangan/direktur) dilakukan kemudian via Master Menu
 * & Role ↔ Akses Menu tanpa mengubah kode.
 */
class MenuApprovalTahapSeeder extends Seeder
{
    public function run(): void
    {
        $parent = Menu::where('url', '#pengeluaran_sikeu')->first();
        if (!$parent) {
            $this->command->warn('Parent menu #pengeluaran_sikeu tidak ditemukan, lewati MenuApprovalTahapSeeder.');
            return;
        }

        // Tahap sarpras/keuangan visibilitas via role-menu (permission null) agar
        // tidak bocor ke role yang hanya memegang permission read/approve umum.
        // Tahap direktur tetap memakai sikeu.pengajuan.approve (pimpinan).
        $permission = Permission::where('slug', 'sikeu.pengajuan.approve')->first();
        $permissionId = $permission?->id;

        $menus = [
            [
                'name' => 'Approval Sarpras',
                'url' => '/sikeu/approval/sarpras',
                'icon' => 'FaClipboardCheck',
                'module' => 'sikeu',
                'order_index' => 6,
                'permission_id' => null,
            ],
            [
                'name' => 'Approval Keuangan',
                'url' => '/sikeu/approval/keuangan',
                'icon' => 'FaMoneyBillWave',
                'module' => 'sikeu',
                'order_index' => 7,
                'permission_id' => null,
            ],
            [
                'name' => 'Approval Pimpinan',
                'url' => '/sikeu/approval/direktur',
                'icon' => 'FaShieldCheck',
                'module' => 'sikeu',
                'order_index' => 8,
                'permission_id' => null,
                'uses_approval_permission' => true,
            ],
        ];

        $ids = [];
        foreach ($menus as $item) {
            $menu = Menu::updateOrCreate(
                ['url' => $item['url']],
                [
                    'parent_id' => $parent->id,
                    'name' => $item['name'],
                    'icon' => $item['icon'],
                    'module' => $item['module'],
                    'permission_id' => ($item['uses_approval_permission'] ?? false) ? $permissionId : null,
                    'order_index' => $item['order_index'],
                    'is_active' => true,
                ]
            );
            $ids[] = $menu->id;
        }

        foreach (['superadmin', 'admin'] as $slug) {
            $role = Role::where('slug', $slug)->first();
            if ($role) {
                $role->menus()->syncWithoutDetaching($ids);
            }
        }
    }
}
