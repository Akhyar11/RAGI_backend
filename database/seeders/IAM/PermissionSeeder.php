<?php

namespace Database\Seeders\IAM;

use Illuminate\Database\Seeder;
use App\Models\Permission;
use App\Models\Role;
use App\Models\RolePermission;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            // Contoh permission granular
            ['name' => 'View Users', 'slug' => 'users.read', 'module' => 'IAM', 'action' => 'read', 'description' => 'Melihat daftar pengguna'],
            ['name' => 'Create Users', 'slug' => 'users.create', 'module' => 'IAM', 'action' => 'create', 'description' => 'Membuat pengguna baru'],
            ['name' => 'Update Users', 'slug' => 'users.update', 'module' => 'IAM', 'action' => 'update', 'description' => 'Mengubah pengguna'],
            ['name' => 'Delete Users', 'slug' => 'users.delete', 'module' => 'IAM', 'action' => 'delete', 'description' => 'Menghapus pengguna'],
            
            ['name' => 'View Roles', 'slug' => 'roles.read', 'module' => 'IAM', 'action' => 'read', 'description' => 'Melihat daftar role'],
            ['name' => 'Create Roles', 'slug' => 'roles.create', 'module' => 'IAM', 'action' => 'create', 'description' => 'Membuat role baru'],
            ['name' => 'Update Roles', 'slug' => 'roles.update', 'module' => 'IAM', 'action' => 'update', 'description' => 'Mengubah role'],
            ['name' => 'Delete Roles', 'slug' => 'roles.delete', 'module' => 'IAM', 'action' => 'delete', 'description' => 'Menghapus role'],
        ];

        foreach ($permissions as $perm) {
            Permission::updateOrCreate(
                ['slug' => $perm['slug']],
                [
                    'name' => $perm['name'],
                    'module' => $perm['module'],
                    'action' => $perm['action'],
                    'description' => $perm['description']
                ]
            );
        }

        // Auto assign IAM permissions to Admin IAM role
        $adminIam = Role::where('slug', 'admin-iam')->first();
        if ($adminIam) {
            $iamPermissions = Permission::where('module', 'IAM')->get();
            foreach ($iamPermissions as $p) {
                RolePermission::updateOrCreate([
                    'role_id' => $adminIam->id,
                    'permission_id' => $p->id
                ]);
            }
        }
    }
}
