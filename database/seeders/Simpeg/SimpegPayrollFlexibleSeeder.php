<?php

namespace Database\Seeders\Simpeg;

use App\Models\Simpeg\MasterKomponenGaji;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class SimpegPayrollFlexibleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $komponens = [
            // ── PENDAPATAN ──
            [
                'kode' => 'GAJI_POKOK',
                'nama' => 'Gaji Pokok',
                'jenis' => 'pendapatan',
                'tipe_nilai' => 'tetap',
                'nilai_default' => 4500000,
                'is_taxable' => true,
                'is_active' => true,
                'urutan' => 1,
                'keterangan' => 'Gaji pokok bulanan pegawai sesuai jenjang & masa kerja',
            ],
            [
                'kode' => 'TUNJ_FUNGSIONAL',
                'nama' => 'Tunjangan Jabatan Fungsional Akademik',
                'jenis' => 'pendapatan',
                'tipe_nilai' => 'tetap',
                'nilai_default' => 1000000,
                'is_taxable' => true,
                'is_active' => true,
                'urutan' => 2,
                'keterangan' => 'Tunjangan kepangkatan dosen (Asisten Ahli, Lektor, Lektor Kepala, Guru Besar)',
            ],
            [
                'kode' => 'TUNJ_STRUKTURAL',
                'nama' => 'Tunjangan Tugas Tambahan / Struktural',
                'jenis' => 'pendapatan',
                'tipe_nilai' => 'tetap',
                'nilai_default' => 750000,
                'is_taxable' => true,
                'is_active' => true,
                'urutan' => 3,
                'keterangan' => 'Tunjangan jabatan struktural (Kaprodi, Dekan, Kabag, dsb.)',
            ],
            [
                'kode' => 'HONOR_SKS',
                'nama' => 'Honor SKS Mengajar Perkuliahan',
                'jenis' => 'pendapatan',
                'tipe_nilai' => 'rumus_sks',
                'nilai_default' => 50000, // Tarif per SKS per bulan
                'is_taxable' => true,
                'is_active' => true,
                'urutan' => 4,
                'keterangan' => 'Honor mengajar perkuliahan dihitung dari total SKS kelas yang diampu pada semester aktif SIAKAD',
            ],
            [
                'kode' => 'INSENTIF_TRANSPORT',
                'nama' => 'Insentif Transport & Kehadiran',
                'jenis' => 'pendapatan',
                'tipe_nilai' => 'rumus_kehadiran',
                'nilai_default' => 50000, // Tarif per hari hadir tepat waktu
                'is_taxable' => false,
                'is_active' => true,
                'urutan' => 5,
                'keterangan' => 'Insentif kehadiran dihitung dari jumlah hari hadir tepat waktu pada modul presensi SIMPEG',
            ],
            [
                'kode' => 'TUNJ_KELUARGA',
                'nama' => 'Tunjangan Keluarga & Beras',
                'jenis' => 'pendapatan',
                'tipe_nilai' => 'tetap',
                'nilai_default' => 300000,
                'is_taxable' => true,
                'is_active' => true,
                'urutan' => 6,
                'keterangan' => 'Tunjangan kesejahteraan keluarga dan subsidi pangan',
            ],

            // ── POTONGAN ──
            [
                'kode' => 'POT_BPJS_KES',
                'nama' => 'Potongan Iuran BPJS Kesehatan',
                'jenis' => 'potongan',
                'tipe_nilai' => 'tetap',
                'nilai_default' => 150000,
                'is_taxable' => false,
                'is_active' => true,
                'urutan' => 10,
                'keterangan' => 'Potongan iuran jaminan kesehatan pekerja',
            ],
            [
                'kode' => 'POT_BPJS_TK',
                'nama' => 'Potongan BPJS Ketenagakerjaan (JHT & JP)',
                'jenis' => 'potongan',
                'tipe_nilai' => 'tetap',
                'nilai_default' => 100000,
                'is_taxable' => false,
                'is_active' => true,
                'urutan' => 11,
                'keterangan' => 'Potongan iuran jaminan hari tua & jaminan pensiun',
            ],
            [
                'kode' => 'POT_PPH21',
                'nama' => 'Potongan Pajak Penghasilan (PPh 21)',
                'jenis' => 'potongan',
                'tipe_nilai' => 'rumus_pph21',
                'nilai_default' => 0, // Dihitung dinamis oleh service
                'is_taxable' => false,
                'is_active' => true,
                'urutan' => 12,
                'keterangan' => 'Potongan pajak penghasilan pasal 21 bulanan sesuai ketentuan tarif efektif',
            ],
            [
                'kode' => 'POT_KOPERASI',
                'nama' => 'Potongan Simpanan Koperasi / Sosial',
                'jenis' => 'potongan',
                'tipe_nilai' => 'tetap',
                'nilai_default' => 50000,
                'is_taxable' => false,
                'is_active' => true,
                'urutan' => 13,
                'keterangan' => 'Iuran sukarela koperasi karyawan dan dana sosial kampus',
            ],
        ];

        foreach ($komponens as $item) {
            MasterKomponenGaji::updateOrCreate(
                ['kode' => $item['kode']],
                $item
            );
        }

        // Tambahkan permission payroll jika belum terdaftar
        $permissions = [
            'simpeg.payroll.view' => ['name' => 'Melihat slip gaji & rekapan payroll', 'action' => 'read'],
            'simpeg.payroll.read' => ['name' => 'Melihat daftar payroll', 'action' => 'read'],
            'simpeg.payroll.create' => ['name' => 'Menghitung kalkulasi payroll dan pengajuan SIKEU', 'action' => 'create'],
            'simpeg.payroll.manage' => ['name' => 'Mengelola penuh payroll, master komponen, dan eksekusi pembayaran SIKEU', 'action' => 'update'],
        ];

        foreach ($permissions as $slug => $meta) {
            Permission::firstOrCreate(
                ['slug' => $slug],
                [
                    'name' => $meta['name'],
                    'module' => 'simpeg',
                    'action' => $meta['action'],
                    'description' => $meta['name'],
                ]
            );
        }

        // Pasangkan ke role super-admin, admin-iam, dan admin-sikeu
        $roles = Role::whereIn('slug', ['super-admin', 'admin-iam', 'admin-sikeu'])->get();
        $permIds = Permission::whereIn('slug', array_keys($permissions))->pluck('id');

        foreach ($roles as $role) {
            $role->permissions()->syncWithoutDetaching($permIds);
        }
    }
}
