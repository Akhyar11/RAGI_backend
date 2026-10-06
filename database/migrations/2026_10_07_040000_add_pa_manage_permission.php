<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\Permission;
use App\Models\Role;

/**
 * Permission plotting Dosen PA (menggantikan hardcode role di controller).
 *
 * Endpoint bulk-assign-pa / assign-pa-kelas / auto-distribute-pa memakai
 * Gate `siakad.pa.manage`. Superadmin/admin mendapatkannya otomatis via
 * PermissionSeeder (auto-all); kaprodi/wakil_prodi diberi di sini secara
 * idempoten (dosen & mahasiswa TIDAK diberi).
 */
return new class extends Migration
{
    private const SLUG = 'siakad.pa.manage';

    private const ROLE_SLUGS = [
        'admin',
        'kaprodi',
        'wakil_prodi',
    ];

    public function up(): void
    {
        $permission = Permission::updateOrCreate(
            ['slug' => self::SLUG],
            [
                'name' => 'Plotting Dosen PA',
                'module' => 'siakad',
                'action' => 'update',
                'description' => 'Menetapkan dan mendistribusikan Dosen Pembimbing Akademik (BAAK/Kaprodi)',
            ]
        );

        $roles = Role::whereIn('slug', self::ROLE_SLUGS)->get();

        foreach ($roles as $role) {
            $role->permissions()->syncWithoutDetaching([$permission->id]);
        }
    }

    public function down(): void
    {
        $permission = Permission::where('slug', self::SLUG)->first();

        if ($permission) {
            Role::whereIn('slug', self::ROLE_SLUGS)
                ->each(fn (Role $role) => $role->permissions()->detach([$permission->id]));
            $permission->delete();
        }
    }
};
