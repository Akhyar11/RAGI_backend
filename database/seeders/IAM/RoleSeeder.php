<?php

namespace Database\Seeders\IAM;

use Illuminate\Database\Seeder;
use App\Models\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        Schema::disableForeignKeyConstraints();
        DB::table('core_role_permissions')->truncate();
        DB::table('core_roles')->truncate();
        Schema::enableForeignKeyConstraints();

        $roles = [
            [
                'name' => 'Super Administrator',
                'slug' => 'superadmin',
                'description' => 'Akses penuh tanpa batas ke seluruh ekosistem SSO dan semua modul aplikasi universitas',
            ],
            [
                'name' => 'Administrator',
                'slug' => 'admin',
                'description' => 'Administrator tingkat tinggi dengan akses ke manajemen pengguna dan sistem',
            ],
            [
                'name' => 'Admin SIMPEG',
                'slug' => 'admin_simpeg',
                'description' => 'Administrator penuh Sistem Informasi Manajemen Kepegawaian (SIMPEG)',
            ],
            [
                'name' => 'Operator SDM',
                'slug' => 'operator_sdm',
                'description' => 'Staf operasional kepegawaian, pengelola dokumen e-file, presensi, & cuti',
            ],
            [
                'name' => 'Dosen Pengajar',
                'slug' => 'dosen',
                'description' => 'Tenaga pengajar dengan akses Portal Mandiri Dosen, BKD, & Usulan Jafung',
            ],
            [
                'name' => 'Tenaga Kependidikan',
                'slug' => 'tendik',
                'description' => 'Staf pendukung administrasi dengan akses Portal Mandiri Tendik, Presensi, & Cuti',
            ],
            [
                'name' => 'Admin LPPM (SIPPM)',
                'slug' => 'admin_lppm',
                'description' => 'Administrator Lembaga Penelitian & Pengabdian Masyarakat (Pengelola Master, Kontrak, & Keuangan SIPPM)',
            ],
            [
                'name' => 'Reviewer SIPPM',
                'slug' => 'reviewer_sippm',
                'description' => 'Tim penilai desk evaluation & verifikator kelayakan proposal riset serta luaran',
            ],
            [
                'name' => 'Mahasiswa Reguler',
                'slug' => 'mahasiswa',
                'description' => 'Pengguna SSO Portal Mahasiswa (Tidak memiliki akses ke sistem SIMPEG)',
            ],
            [
                'name' => 'Calon Mahasiswa',
                'slug' => 'calon_mhs',
                'description' => 'Pendaftar Sistem Penerimaan Mahasiswa Baru (SPMB)',
            ],
            [
                'name' => 'Pimpinan Kampus / WR II / Direktur Keuangan',
                'slug' => 'pimpinan',
                'description' => 'Pimpinan eksekutif universitas dengan wewenang approval tagihan, pencairan dana, & dispensasi keuangan',
            ],
            [
                'name' => 'Operator SIKEU',
                'slug' => 'operator_sikeu',
                'description' => 'Staf keuangan operasional SIKEU, pengelola tagihan, jurnal akuntansi, & pelaporan pajak',
            ],
            [
                'name' => 'Kabag Keuangan',
                'slug' => 'kabag_keuangan',
                'description' => 'Kepala Bagian Keuangan, penanggung jawab kas utama, verifikator pengeluaran & dispensasi',
            ],
            [
                'name' => 'Admin SINAPRA',
                'slug' => 'admin_sarpras',
                'description' => 'Administrator Sarana & Prasarana, pengelola aset, ruangan, maintenance, & pengadaan barang',
            ],
            [
                'name' => 'Admin SPMB',
                'slug' => 'admin_spmb',
                'description' => 'Administrator Penerimaan Mahasiswa Baru (SPMB)',
            ],
        ];

        foreach ($roles as $role) {
            Role::create([
                'name' => $role['name'],
                'slug' => $role['slug'],
                'description' => $role['description'],
                'is_active' => true,
            ]);
        }
    }
}
