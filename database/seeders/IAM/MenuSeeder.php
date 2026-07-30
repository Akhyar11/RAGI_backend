<?php

namespace Database\Seeders\IAM;

use Illuminate\Database\Seeder;
use App\Models\Menu;
use App\Models\Permission;

class MenuSeeder extends Seeder
{
    public function run(): void
    {
        // Cari permission untuk dijadikan referensi
        $dashboardPermission = Permission::where('slug', 'dashboard.read')->first();
        $userPermission = Permission::where('slug', 'users.read')->first();
        $rolePermission = Permission::where('slug', 'roles.read')->first();

        $menus = [
            // Menu SPMB
            [
                'name' => 'Dashboard SPMB',
                'url' => '/spmb/dashboard',
                'icon' => 'FaHome',
                'module' => 'SPMB',
                'permission_slug' => null, // Bebas jika tidak ada permission khusus
                'order_index' => 1,
            ],
            [
                'name' => 'Pendaftaran',
                'url' => '/spmb/pendaftaran',
                'icon' => 'FaUserPlus',
                'module' => 'SPMB',
                'permission_slug' => null,
                'order_index' => 2,
            ],
            // Menu SIAKAD (Core)
            [
                'name' => 'Dashboard Utama',
                'url' => '/dashboard',
                'icon' => 'FaChartPie',
                'module' => 'sso',
                'permission_slug' => 'dashboard.read',
                'order_index' => 1,
            ],
            [
                'name' => 'Manajemen Pengguna',
                'url' => '#admin-users',
                'icon' => 'FaUsers',
                'module' => 'sso',
                'permission_slug' => 'users.read',
                'order_index' => 2,
                'children' => [
                    [
                        'name' => 'Daftar Pengguna',
                        'url' => '/admin/users',
                        'icon' => 'FaList',
                        'module' => 'sso',
                        'permission_slug' => 'users.read',
                        'order_index' => 1,
                    ],
                    [
                        'name' => 'Hak Akses & Role',
                        'url' => '/admin/roles',
                        'icon' => 'FaShieldAlt',
                        'module' => 'sso',
                        'permission_slug' => 'roles.read',
                        'order_index' => 2,
                    ],
                    [
                        'name' => 'Manajemen Menu',
                        'url' => '/admin/menus',
                        'icon' => 'FaList',
                        'module' => 'sso',
                        'permission_slug' => 'roles.read', // Sementara disamakan dengan roles.read karena belum ada menus.read
                        'order_index' => 3,
                    ]
                ]
            ],
        ];

        foreach ($menus as $menuData) {
            $permissionId = null;
            if (isset($menuData['permission_slug'])) {
                $permission = Permission::where('slug', $menuData['permission_slug'])->first();
                $permissionId = $permission ? $permission->id : null;
            }

            $parent = Menu::updateOrCreate(
                ['url' => $menuData['url'], 'module' => $menuData['module']],
                [
                    'name' => $menuData['name'],
                    'icon' => $menuData['icon'],
                    'permission_id' => $permissionId,
                    'order_index' => $menuData['order_index'],
                    'is_active' => true,
                ]
            );

            if (isset($menuData['children'])) {
                foreach ($menuData['children'] as $childData) {
                    $childPermissionId = null;
                    if (isset($childData['permission_slug'])) {
                        $childPermission = Permission::where('slug', $childData['permission_slug'])->first();
                        $childPermissionId = $childPermission ? $childPermission->id : null;
                    }

                    Menu::updateOrCreate(
                        ['url' => $childData['url'], 'module' => $childData['module']],
                        [
                            'parent_id' => $parent->id,
                            'name' => $childData['name'],
                            'icon' => $childData['icon'],
                            'permission_id' => $childPermissionId,
                            'order_index' => $childData['order_index'],
                            'is_active' => true,
                        ]
                    );
                }
            }
        }
    }
}
