<?php

namespace Database\Seeders\IAM;

use App\Models\Menu;
use App\Models\Permission;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\Siakad\ProgramStudi;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class LaboranProdiRoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $rolesData = [
            [
                'name' => 'Laboran D3 Farmasi',
                'slug' => 'laboran_d3_farmasi',
                'description' => 'Laboran pengelola operasional laboratorium & sarana prasarana D3 Farmasi',
                'user_username' => 'laboran_farmasi',
                'prodi_search' => ['D3 Farmasi', 'Farmasi'],
            ],
            [
                'name' => 'Laboran D3 Perhotelan',
                'slug' => 'laboran_d3_perhotelan',
                'description' => 'Laboran pengelola operasional laboratorium & sarana prasarana D3 Perhotelan',
                'user_username' => 'laboran_htl',
                'prodi_search' => ['D3 Perhotelan', 'Perhotelan', 'HTL'],
            ],
            [
                'name' => 'Laboran D3 Sistem Informasi',
                'slug' => 'laboran_d3_sistem_informasi',
                'description' => 'Laboran pengelola operasional laboratorium & sarana prasarana D3 Sistem Informasi',
                'user_username' => 'laboran_si',
                'prodi_search' => ['D3 Sistem Informasi', 'Sistem Informasi'],
            ],
            [
                'name' => 'Laboran D4 Akuntansi Perpajakan',
                'slug' => 'laboran_d4_akuntansi_perpajakan',
                'description' => 'Laboran pengelola operasional laboratorium & sarana prasarana D4 Akuntansi Perpajakan',
                'user_username' => 'laboran_akuntansi',
                'prodi_search' => ['D4 Akuntansi Perpajakan', 'Akuntansi Perpajakan'],
            ],
            [
                'name' => 'Laboran D4 Bisnis Dan Manajemen Ritel',
                'slug' => 'laboran_d4_bisnis_manajemen_ritel',
                'description' => 'Laboran pengelola operasional laboratorium & sarana prasarana D4 Bisnis Dan Manajemen Ritel',
                'user_username' => 'laboran_ritel',
                'prodi_search' => ['D4 Bisnis Dan Manajemen Ritel', 'Bisnis Dan Manajemen Ritel'],
            ],
            [
                'name' => 'Laboran D4 Manajemen Informasi Kesehatan',
                'slug' => 'laboran_d4_manajemen_informasi_kesehatan',
                'description' => 'Laboran pengelola operasional laboratorium & sarana prasarana D4 Manajemen Informasi Kesehatan',
                'user_username' => 'laboran_mik',
                'prodi_search' => ['D4 Manajemen Informasi Kesehatan', 'Manajemen Informasi Kesehatan', 'MIK'],
            ],
            [
                'name' => 'Laboran D4 Produksi Media',
                'slug' => 'laboran_d4_produksi_media',
                'description' => 'Laboran pengelola operasional laboratorium & sarana prasarana D4 Produksi Media',
                'user_username' => 'laboran_pm',
                'prodi_search' => ['D4 Produksi Media', 'Produksi Media', 'PM'],
            ],
            [
                'name' => 'Laboran D4 Teknologi Laboratorium Medis',
                'slug' => 'laboran_d4_teknologi_laboratorium_medis',
                'description' => 'Laboran pengelola operasional laboratorium & sarana prasarana D4 Teknologi Laboratorium Medis',
                'user_username' => 'laboran_tlm',
                'prodi_search' => ['D4 Teknologi Laboratorium Medis', 'Teknologi Laboratorium Medis', 'TLM'],
            ],
            [
                'name' => 'Laboran D4 Teknologi Rekayasa Otomotif',
                'slug' => 'laboran_d4_teknologi_rekayasa_otomotif',
                'description' => 'Laboran pengelola operasional laboratorium & sarana prasarana D4 Teknologi Rekayasa Otomotif',
                'user_username' => 'laboran_otomotif',
                'prodi_search' => ['D4 Teknologi Rekayasa Otomotif', 'Teknologi Rekayasa Otomotif', 'TRO'],
            ],
            [
                'name' => 'Laboran D4 Teknologi Rekayasa Perangkat Lunak',
                'slug' => 'laboran_d4_trpl',
                'description' => 'Laboran pengelola operasional laboratorium & sarana prasarana D4 Teknologi Rekayasa Perangkat Lunak',
                'user_username' => 'laboran_trpl',
                'prodi_search' => ['D4 Teknologi Rekayasa Perangkat Lunak', 'Teknologi Rekayasa Perangkat Lunak', 'TRPL'],
            ],
        ];

        // Daftar slug permission standar operasional Laboran SINAPRA
        $laboranPermissionSlugs = [
            'sinapra.dashboard.read',
            'sinapra.ruangan.read',
            'sinapra.aset.read',
            'sinapra.aset.create',
            'sinapra.aset.update',
            'sinapra.aset.delete',
            'sinapra.peminjaman_ruangan.read',
            'sinapra.peminjaman_ruangan.approve_laboran',
            'sinapra.peminjaman_aset.read',
            'sinapra.peminjaman_aset.approve_laboran',
            'sinapra.maintenance.read',
            'sinapra.maintenance.create',
            'sinapra.maintenance.update',
            'sinapra.bhp.read',
            'sinapra.bhp.manage',
            'sinapra.bebas_tanggungan.read',
            'sinapra.bebas_tanggungan.approve',
            'sinapra.kalibrasi.read',
            'sinapra.kalibrasi.manage',
            'sinapra.opname.read',
            'sinapra.opname.manage',
            'sinapra.mutasi.read',
            'sinapra.mutasi.manage',
            'sinapra.disposal.read',
        ];

        $laboranPermissions = Permission::whereIn('slug', $laboranPermissionSlugs)->get();

        // Menu-menu SINAPRA untuk role laboran
        $sinapraMenuIds = [];
        if (Schema::hasTable('core_menus')) {
            $sinapraMenuIds = Menu::where('module', 'sinapra')->pluck('id')->toArray();
        }

        foreach ($rolesData as $data) {
            // 1. Buat atau perbarui Role secara idempoten
            $role = Role::updateOrCreate(
                ['slug' => $data['slug']],
                [
                    'name' => $data['name'],
                    'description' => $data['description'],
                    'is_active' => true,
                ]
            );

            // 2. Hubungkan Permissions SINAPRA
            foreach ($laboranPermissions as $permission) {
                RolePermission::firstOrCreate([
                    'role_id' => $role->id,
                    'permission_id' => $permission->id,
                ]);
            }

            // 3. Hubungkan Menu SINAPRA
            if (!empty($sinapraMenuIds)) {
                $role->menus()->syncWithoutDetaching($sinapraMenuIds);
            }

            // 4. Hubungkan role ke user contoh jika user ada di database
            if (!empty($data['user_username'])) {
                $user = User::where('username', $data['user_username'])->first();
                if ($user) {
                    $user->roles()->syncWithoutDetaching([$role->id]);
                }
            }

            // 5. Otomatis petakan role ke Program Studi di SINAPRA jika prodi sudah ada di SIAKAD
            if (Schema::hasTable('siakad_program_studi') && Schema::hasTable('sinapra_prodi_roles')) {
                foreach ($data['prodi_search'] as $searchTerm) {
                    $prodi = ProgramStudi::where('nama', 'like', "%{$searchTerm}%")
                        ->orWhere('kode_prodi', $searchTerm)
                        ->first();

                    if ($prodi) {
                        $prodi->sinapraRoles()->syncWithoutDetaching([$role->id]);
                        break;
                    }
                }
            }
        }
    }
}
