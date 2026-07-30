<?php

namespace Database\Seeders\IAM;

use Illuminate\Database\Seeder;
use App\Models\Menu;
use App\Models\Permission;

class MenuSeeder extends Seeder
{
    public function run(): void
    {
        \Illuminate\Support\Facades\Schema::disableForeignKeyConstraints();
        \Illuminate\Support\Facades\DB::table('menus')->truncate();
        \Illuminate\Support\Facades\Schema::enableForeignKeyConstraints();

        // Cari permission untuk dijadikan referensi
        $dashboardPermission = Permission::where('slug', 'dashboard.read')->first();
        $userPermission = Permission::where('slug', 'users.read')->first();
        $rolePermission = Permission::where('slug', 'roles.read')->first();

        $menus = [
            // Menu SSO (Core)
            [
                'name' => 'Dashboard Utama',
                'url' => '/dashboard',
                'icon' => 'FaChartPie',
                'module' => 'sso',
                'permission_slug' => 'dashboard.read',
                'order_index' => 1,
            ],
            [
                'name' => 'MASTER',
                'url' => '#master',
                'icon' => 'FaList',
                'module' => 'sso',
                'order_index' => 2,
                'children' => [
                    ['name' => 'User', 'url' => '/admin/users', 'icon' => 'FaUsers', 'module' => 'sso', 'permission_slug' => 'iam.users.read', 'order_index' => 1],
                    ['name' => 'Role', 'url' => '/admin/roles', 'icon' => 'FaShieldAlt', 'module' => 'sso', 'permission_slug' => 'iam.roles.read', 'order_index' => 2],
                    ['name' => 'Module (Sistem)', 'url' => '/admin/modules', 'icon' => 'FaList', 'module' => 'sso', 'permission_slug' => 'iam.permissions.manage', 'order_index' => 3],
                    ['name' => 'Menu', 'url' => '/admin/menus', 'icon' => 'FaList', 'module' => 'sso', 'permission_slug' => 'iam.permissions.manage', 'order_index' => 4],
                ]
            ],
            [
                'name' => 'MENU',
                'url' => '#menu',
                'icon' => 'FaList',
                'module' => 'sso',
                'order_index' => 3,
                'children' => [
                    ['name' => 'User Role', 'url' => '/admin/user-roles', 'icon' => 'FaUsers', 'module' => 'sso', 'permission_slug' => 'iam.user_roles.manage', 'order_index' => 1],
                    ['name' => 'Role Permission', 'url' => '/admin/role-permissions', 'icon' => 'FaShieldAlt', 'module' => 'sso', 'permission_slug' => 'iam.permissions.manage', 'order_index' => 2],
                    ['name' => 'Permission Akses', 'url' => '/admin/permissions', 'icon' => 'FaShieldAlt', 'module' => 'sso', 'permission_slug' => 'iam.permissions.read', 'order_index' => 3],
                    ['name' => 'Akses Menu', 'url' => '/admin/role-menus', 'icon' => 'FaList', 'module' => 'sso', 'permission_slug' => 'iam.permissions.manage', 'order_index' => 4],
                    ['name' => 'Monitor Aksi', 'url' => '/admin/sessions', 'icon' => 'FaUsers', 'module' => 'sso', 'permission_slug' => 'iam.sessions.read', 'order_index' => 5],
                    ['name' => 'Audit Logs', 'url' => '/admin/audit-logs', 'icon' => 'FaList', 'module' => 'sso', 'permission_slug' => 'iam.audit_logs.read', 'order_index' => 6],
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
