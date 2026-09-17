<?php

namespace Database\Seeders\Simpeg;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Simpeg\JabatanFungsionalAkademik;
use App\Models\Simpeg\MasterBracketPph21;
use App\Models\Simpeg\MasterKomponenGaji;
use App\Models\Simpeg\MasterSkalaGajiPokok;
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
                'keterangan' => 'Gaji pokok bulanan pegawai dihitung dinamis dari matriks skala gaji & masa kerja',
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
                'keterangan' => 'Tunjangan kepangkatan dosen fungsional (Asisten Ahli, Lektor, Lektor Kepala, Guru Besar)',
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
                'kode' => 'POT_KETERLAMBATAN',
                'nama' => 'Potongan Denda Keterlambatan Hadir',
                'jenis' => 'potongan',
                'tipe_nilai' => 'rumus_kehadiran',
                'nilai_default' => 25000, // Tarif per kejadian terlambat (> 08:15)
                'is_taxable' => false,
                'is_active' => true,
                'urutan' => 9,
                'keterangan' => 'Potongan denda keterlambatan kehadiran per kejadian di atas batas jam toleransi SIMPEG',
            ],
            [
                'kode' => 'POT_ALPHA',
                'nama' => 'Potongan Ketidakhadiran (Alpha)',
                'jenis' => 'potongan',
                'tipe_nilai' => 'rumus_kehadiran',
                'nilai_default' => 100000, // Tarif per hari alpha
                'is_taxable' => false,
                'is_active' => true,
                'urutan' => 10,
                'keterangan' => 'Potongan ketidakhadiran kerja tanpa keterangan sah per hari',
            ],
            [
                'kode' => 'POT_BPJS_KES',
                'nama' => 'Potongan Iuran BPJS Kesehatan',
                'jenis' => 'potongan',
                'tipe_nilai' => 'tetap',
                'nilai_default' => 150000,
                'is_taxable' => false,
                'is_active' => true,
                'urutan' => 11,
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
                'urutan' => 12,
                'keterangan' => 'Potongan iuran jaminan hari tua & jaminan pensiun',
            ],
            [
                'kode' => 'POT_PPH21',
                'nama' => 'Potongan Pajak Penghasilan (PPh 21)',
                'jenis' => 'potongan',
                'tipe_nilai' => 'rumus_pph21',
                'nilai_default' => 0, // Dihitung dinamis oleh service berdasarkan bracket
                'is_taxable' => false,
                'is_active' => true,
                'urutan' => 13,
                'keterangan' => 'Potongan pajak penghasilan pasal 21 bulanan sesuai tarif efektif (TER) dari database',
            ],
            [
                'kode' => 'POT_KOPERASI',
                'nama' => 'Potongan Simpanan Koperasi / Sosial',
                'jenis' => 'potongan',
                'tipe_nilai' => 'tetap',
                'nilai_default' => 50000,
                'is_taxable' => false,
                'is_active' => true,
                'urutan' => 14,
                'keterangan' => 'Iuran sukarela koperasi karyawan dan dana sosial kampus',
            ],
        ];

        foreach ($komponens as $item) {
            MasterKomponenGaji::updateOrCreate(
                ['kode' => $item['kode']],
                $item
            );
        }

        // ── TUNJANGAN JABATAN FUNGSIONAL AKADEMIK (DOSEN) ──
        $jafungList = JabatanFungsionalAkademik::all();
        foreach ($jafungList as $jafung) {
            $nama = strtolower($jafung->nama);
            $nominal = 500000;
            if (str_contains($nama, 'guru besar') || str_contains($nama, 'profesor')) {
                $nominal = 2500000;
            } elseif (str_contains($nama, 'lektor kepala')) {
                $nominal = 1750000;
            } elseif (str_contains($nama, 'lektor')) {
                $nominal = 1250000;
            } elseif (str_contains($nama, 'asisten ahli')) {
                $nominal = 750000;
            }

            if ($jafung->tunjangan_nominal == 0) {
                $jafung->update(['tunjangan_nominal' => $nominal]);
            }
        }

        // ── MASTER SKALA GAJI POKOK (MASA KERJA) ──
        $skalaGajiList = [
            // Golongan asisten_ahli
            ['nama_skala' => 'Asisten Ahli (Masa Kerja 0-2 Thn)', 'golongan' => 'asisten_ahli', 'masa_kerja_min_tahun' => 0, 'masa_kerja_max_tahun' => 2, 'nominal_gaji' => 3500000, 'keterangan' => 'Dosen Asisten Ahli masa bakti awal'],
            ['nama_skala' => 'Asisten Ahli (Masa Kerja 3-5 Thn)', 'golongan' => 'asisten_ahli', 'masa_kerja_min_tahun' => 3, 'masa_kerja_max_tahun' => 5, 'nominal_gaji' => 4000000, 'keterangan' => 'Dosen Asisten Ahli masa bakti menengah'],
            ['nama_skala' => 'Asisten Ahli (Masa Kerja >5 Thn)', 'golongan' => 'asisten_ahli', 'masa_kerja_min_tahun' => 6, 'masa_kerja_max_tahun' => 99, 'nominal_gaji' => 4500000, 'keterangan' => 'Dosen Asisten Ahli senior'],

            // Golongan lektor
            ['nama_skala' => 'Lektor (Masa Kerja 0-3 Thn)', 'golongan' => 'lektor', 'masa_kerja_min_tahun' => 0, 'masa_kerja_max_tahun' => 3, 'nominal_gaji' => 4500000, 'keterangan' => 'Dosen Lektor muda'],
            ['nama_skala' => 'Lektor (Masa Kerja 4-7 Thn)', 'golongan' => 'lektor', 'masa_kerja_min_tahun' => 4, 'masa_kerja_max_tahun' => 7, 'nominal_gaji' => 5200000, 'keterangan' => 'Dosen Lektor madya'],
            ['nama_skala' => 'Lektor (Masa Kerja >7 Thn)', 'golongan' => 'lektor', 'masa_kerja_min_tahun' => 8, 'masa_kerja_max_tahun' => 99, 'nominal_gaji' => 6000000, 'keterangan' => 'Dosen Lektor senior'],

            // Golongan lektor_kepala
            ['nama_skala' => 'Lektor Kepala (Masa Kerja 0-5 Thn)', 'golongan' => 'lektor_kepala', 'masa_kerja_min_tahun' => 0, 'masa_kerja_max_tahun' => 5, 'nominal_gaji' => 6500000, 'keterangan' => 'Dosen Lektor Kepala madya'],
            ['nama_skala' => 'Lektor Kepala (Masa Kerja >5 Thn)', 'golongan' => 'lektor_kepala', 'masa_kerja_min_tahun' => 6, 'masa_kerja_max_tahun' => 99, 'nominal_gaji' => 7500000, 'keterangan' => 'Dosen Lektor Kepala senior'],

            // Golongan guru_besar
            ['nama_skala' => 'Guru Besar / Profesor Utama', 'golongan' => 'guru_besar', 'masa_kerja_min_tahun' => 0, 'masa_kerja_max_tahun' => 99, 'nominal_gaji' => 9500000, 'keterangan' => 'Guru Besar / Profesor Kampus'],

            // Skala Umum / Tendik (Non Golongan Fungsional)
            ['nama_skala' => 'Staf / Tendik (Masa Kerja 0-1 Thn)', 'golongan' => null, 'masa_kerja_min_tahun' => 0, 'masa_kerja_max_tahun' => 1, 'nominal_gaji' => 3200000, 'keterangan' => 'Tenaga Kependidikan baru'],
            ['nama_skala' => 'Staf / Tendik (Masa Kerja 2-4 Thn)', 'golongan' => null, 'masa_kerja_min_tahun' => 2, 'masa_kerja_max_tahun' => 4, 'nominal_gaji' => 3800000, 'keterangan' => 'Tenaga Kependidikan pratama'],
            ['nama_skala' => 'Staf / Tendik (Masa Kerja 5-10 Thn)', 'golongan' => null, 'masa_kerja_min_tahun' => 5, 'masa_kerja_max_tahun' => 10, 'nominal_gaji' => 4600000, 'keterangan' => 'Tenaga Kependidikan madya'],
            ['nama_skala' => 'Staf / Tendik (Masa Kerja >10 Thn)', 'golongan' => null, 'masa_kerja_min_tahun' => 11, 'masa_kerja_max_tahun' => 99, 'nominal_gaji' => 5500000, 'keterangan' => 'Tenaga Kependidikan utama'],
        ];

        foreach ($skalaGajiList as $skala) {
            MasterSkalaGajiPokok::updateOrCreate(
                [
                    'nama_skala' => $skala['nama_skala'],
                    'golongan' => $skala['golongan'],
                    'masa_kerja_min_tahun' => $skala['masa_kerja_min_tahun'],
                ],
                $skala
            );
        }

        // ── MASTER BRACKET PPH 21 (TER) ──
        $brackets = [
            ['kategori' => 'DEFAULT', 'penghasilan_bruto_min' => 0, 'penghasilan_bruto_max' => 5400000, 'tarif_persen' => 0.0000, 'keterangan' => 'Penghasilan di bawah PTKP (Bebas Pajak)'],
            ['kategori' => 'DEFAULT', 'penghasilan_bruto_min' => 5400001, 'penghasilan_bruto_max' => 7000000, 'tarif_persen' => 0.0050, 'keterangan' => 'Tarif Efektif Rata-rata 0.5%'],
            ['kategori' => 'DEFAULT', 'penghasilan_bruto_min' => 7000001, 'penghasilan_bruto_max' => 15000000, 'tarif_persen' => 0.0150, 'keterangan' => 'Tarif Efektif Rata-rata 1.5%'],
            ['kategori' => 'DEFAULT', 'penghasilan_bruto_min' => 15000001, 'penghasilan_bruto_max' => null, 'tarif_persen' => 0.0500, 'keterangan' => 'Tarif Efektif Rata-rata 5.0%'],
        ];

        foreach ($brackets as $b) {
            MasterBracketPph21::updateOrCreate(
                [
                    'kategori' => $b['kategori'],
                    'penghasilan_bruto_min' => $b['penghasilan_bruto_min'],
                ],
                $b
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
