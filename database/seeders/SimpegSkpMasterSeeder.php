<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Permission;
use App\Models\Role;

class SimpegSkpMasterSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Seed Master Kategori SKP
        $categories = [
            [
                'nama' => 'Pendidikan dan Pengajaran',
                'kode' => 'PENDIDIKAN',
                'deskripsi' => 'Pelaksanaan perkuliahan, bimbingan tugas akhir/skripsi, pengujian, dan pembinaan akademik.',
                'urutan' => 1,
                'is_active' => true,
            ],
            [
                'nama' => 'Penelitian dan Publikasi Ilmiah',
                'kode' => 'PENELITIAN',
                'deskripsi' => 'Penelitian mandiri/kelompok, publikasi jurnal terakreditasi/internasional, prosiding, buku ajar, dan HKI/paten.',
                'urutan' => 2,
                'is_active' => true,
            ],
            [
                'nama' => 'Pengabdian Kepada Masyarakat (PkM)',
                'kode' => 'PENGABDIAN',
                'deskripsi' => 'Pemberdayaan masyarakat, pelatihan UMKM, penerapan teknologi tepat guna, dan penyuluhan.',
                'urutan' => 3,
                'is_active' => true,
            ],
            [
                'nama' => 'Unsur Penunjang Tridharma',
                'kode' => 'PENUNJANG',
                'deskripsi' => 'Keanggotaan komite/senat, pembina ormawa/kegiatan kemahasiswaan, panitia ad-hoc kampus, dan asosiasi profesi.',
                'urutan' => 4,
                'is_active' => true,
            ],
            [
                'nama' => 'Tugas Tambahan & Manajerial',
                'kode' => 'TUGAS_TAMBAHAN',
                'deskripsi' => 'Penugasan jabatan struktural kampus (Dekan, Kaprodi, Kepala Lembaga, Koordinator Unit, dll.).',
                'urutan' => 5,
                'is_active' => true,
            ],
        ];

        foreach ($categories as $cat) {
            DB::table('simpeg_master_kategori_skp')->updateOrInsert(
                ['kode' => $cat['kode']],
                array_merge($cat, ['created_at' => now(), 'updated_at' => now()])
            );
        }

        // 2. Tambah Permission Evaluasi SKP & Manage
        $newPermissions = [
            [
                'name' => 'Evaluasi & Menilai SKP Kinerja',
                'slug' => 'simpeg.kinerja.evaluate',
                'module' => 'simpeg',
                'action' => 'approve',
                'description' => 'Menyetujui target sasaran kerja dan mengevaluasi capaian realisasi SKP',
            ],
            [
                'name' => 'Kelola Seluruh SKP Pegawai',
                'slug' => 'simpeg.kinerja.manage',
                'module' => 'simpeg',
                'action' => 'read',
                'description' => 'Merekap, memantau, dan mengelola seluruh siklus SKP pegawai',
            ],
        ];

        foreach ($newPermissions as $permData) {
            $perm = Permission::firstOrCreate(['slug' => $permData['slug']], $permData);

            // Assign to super-admin & admin roles
            $adminRoles = Role::whereIn('name', ['Super Admin', 'Admin', 'Admin SDM', 'SDM', 'Pimpinan', 'Kepala Biro'])->get();
            foreach ($adminRoles as $role) {
                if (!$role->permissions()->where('permissions.id', $perm->id)->exists()) {
                    $role->permissions()->attach($perm->id);
                }
            }
        }
    }
}
