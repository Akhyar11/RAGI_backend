<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\Permission;
use App\Models\Role;

return new class extends Migration
{
    private const SIMPEG_PERMISSIONS = [
        [
            'slug' => 'simpeg.nomor_surat.read',
            'name' => 'Lihat Penomoran Surat Pegawai',
            'module' => 'simpeg',
            'action' => 'read',
            'description' => 'Melihat daftar nomor surat resmi atas nama pegawai di portal mandiri SIMPEG',
        ],
        [
            'slug' => 'simpeg.nomor_surat.create',
            'name' => 'Ajukan Permohonan Nomor Surat Pegawai',
            'module' => 'simpeg',
            'action' => 'create',
            'description' => 'Mengajukan permohonan penerbitan nomor surat resmi kampus dari portal SIMPEG',
        ],
        [
            'slug' => 'simpeg.nomor_surat.upload',
            'name' => 'Unggah Berkas Scan Surat Pegawai',
            'module' => 'simpeg',
            'action' => 'update',
            'description' => 'Mengunggah berkas scan fisik surat resmi bertandatangan dari portal SIMPEG',
        ],
    ];

    private const SIMPEG_ROLES_TO_ASSIGN = [
        'superadmin',
        'admin',
        'admin_simpeg',
        'operator_sdm',
        'dosen',
        'tendik',
    ];

    public function up(): void
    {
        $createdPermissionIds = [];

        foreach (self::SIMPEG_PERMISSIONS as $permData) {
            $perm = Permission::updateOrCreate(
                ['slug' => $permData['slug']],
                $permData
            );
            $createdPermissionIds[] = $perm->id;
        }

        // 1. Berikan permission SIMPEG baru ke role-role terkait
        $roles = Role::whereIn('slug', self::SIMPEG_ROLES_TO_ASSIGN)->get();
        foreach ($roles as $role) {
            $role->permissions()->syncWithoutDetaching($createdPermissionIds);
        }

        // 2. Cabut permission modul ARSIP dari role Dosen dan Tendik agar modul ARSIP tidak muncul di dashboard mereka
        $dosenOrTendik = Role::whereIn('slug', ['dosen', 'tendik'])->get();
        $arsipPermIds = Permission::where('module', 'arsip')->pluck('id')->toArray();

        foreach ($dosenOrTendik as $role) {
            $role->permissions()->detach($arsipPermIds);
        }
    }

    public function down(): void
    {
        $slugs = array_column(self::SIMPEG_PERMISSIONS, 'slug');
        $perms = Permission::whereIn('slug', $slugs)->get();

        foreach ($perms as $p) {
            Role::whereIn('slug', self::SIMPEG_ROLES_TO_ASSIGN)
                ->each(fn (Role $r) => $r->permissions()->detach([$p->id]));
            $p->delete();
        }
    }
};
