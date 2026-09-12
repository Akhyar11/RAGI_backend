<?php

namespace Database\Seeders\Sikeu;

use Illuminate\Database\Seeder;
use App\Models\Sikeu\MasterBiaya;
use App\Models\Sikeu\TagihanMahasiswa;
use App\Models\Sikeu\DetailTagihan;
use App\Models\Sikeu\PotonganTagihan;
use App\Models\Sikeu\VirtualAccount;
use App\Models\Sikeu\MahasiswaTipeTagihan;
use Illuminate\Support\Facades\DB;

class MahasiswaBillingSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Ambil Master Biaya (SPP/Praktikum)
        $jenisBiayaUkt = MasterBiaya::where('kode', 'UKT_REG')->first() ?? MasterBiaya::first();
        $jenisBiayaPraktikum = MasterBiaya::firstOrCreate(
            ['kode' => 'PRAKTIKUM'],
            [
                'nama' => 'Biaya Laboratorium & Praktikum',
                'tipe' => 'praktikum',
                'deskripsi' => 'Biaya penggunaan laboratorium & modul praktikum',
                'is_recurring' => true,
                'is_active' => true,
            ]
        );

        // 2. Sample Penetapan Tipe Tagihan Mahasiswa (SPMB / SIAKAD / Admin Change)
        $samples = [
            [
                'mahasiswa_id' => 1,
                'nim' => '2301001001',
                'nama_mahasiswa' => 'Ahmad Fadillah',
                'tahun_angkatan' => 2023,
                'jalur_kelas' => 'Reguler',
                'kelompok_ukt' => 3,
                'status_pendaftaran' => 'SIAKAD_AKTIF',
                'catatan_perubahan' => 'Mahasiswa Aktif SIAKAD Prodi Teknik Informatika Angkatan 2023',
            ],
            [
                'mahasiswa_id' => 101,
                'nim' => '2024010042',
                'nama_mahasiswa' => 'Budi Santoso',
                'tahun_angkatan' => 2024,
                'jalur_kelas' => 'Reguler',
                'kelompok_ukt' => 3,
                'status_pendaftaran' => 'SIAKAD_AKTIF',
                'catatan_perubahan' => 'Penetapan awal dari SPMB (Penerima Subsidi 100%)',
            ],
            [
                'mahasiswa_id' => 102,
                'nim' => '2025010018',
                'nama_mahasiswa' => 'Siti Rahmawati',
                'tahun_angkatan' => 2025,
                'jalur_kelas' => 'Reguler',
                'kelompok_ukt' => 3,
                'status_pendaftaran' => 'SPMB_DITERIMA',
                'catatan_perubahan' => 'Pendaftaran Jalur Mandiri SPMB (Reguler)',
            ],
            [
                'mahasiswa_id' => 103,
                'nim' => '2023010088',
                'nama_mahasiswa' => 'Ahmad Fauzi',
                'tahun_angkatan' => 2023,
                'jalur_kelas' => 'Karyawan',
                'kelompok_ukt' => 4,
                'status_pendaftaran' => 'PENGATURAN_ADMIN',
                'catatan_perubahan' => 'Pindah jalur dari Reguler ke Kelas Karyawan pada Semester 3',
            ],
            [
                'mahasiswa_id' => 104,
                'nim' => '2025010099',
                'nama_mahasiswa' => 'Rian Hidayat',
                'tahun_angkatan' => 2025,
                'jalur_kelas' => 'Internasional',
                'kelompok_ukt' => 4,
                'status_pendaftaran' => 'SPMB_DITERIMA',
                'catatan_perubahan' => 'Pendaftaran Kelas Internasional (Diskon Mitra 50%)',
            ],
        ];

        foreach ($samples as $s) {
            MahasiswaTipeTagihan::updateOrCreate(
                ['mahasiswa_id' => $s['mahasiswa_id']],
                $s
            );
        }

        // 3. Seed Sample Tagihan Mahasiswa dengan Potongan Otomatis
        // Mahasiswa 101 (Budi Santoso - Subsidi 100%)
        $tagihanBudi = TagihanMahasiswa::updateOrCreate(
            ['nomor_tagihan' => 'INV-SIAKAD-2024-001'],
            [
                'mahasiswa_id' => 101,
                'tahun_akademik_id' => 1,
                'total_tagihan' => 3750000,
                'total_potongan' => 3750000,
                'total_denda' => 0,
                'total_bayar' => 0,
                'status' => 'lunas',
                'requires_approval' => false,
                'status_approval' => 'approved',
                'source_system' => 'SIAKAD',
                'jatuh_tempo' => now()->addDays(30)->toDateString(),
            ]
        );

        DetailTagihan::firstOrCreate(
            ['tagihan_id' => $tagihanBudi->id, 'master_biaya_id' => $jenisBiayaUkt->id],
            ['nominal' => 3750000, 'potongan' => 3750000, 'nominal_bersih' => 0, 'keterangan' => 'SPP Reguler Angkatan 2024']
        );

        PotonganTagihan::firstOrCreate(
            ['tagihan_id' => $tagihanBudi->id, 'tipe' => 'subsidi'],
            ['nominal_potongan' => 3750000, 'keterangan' => 'Subsidi Pemerintah 100%']
        );

        // Mahasiswa 102 (Siti Rahmawati - Reguler Mandiri Rp 4.000.000)
        $tagihanSiti = TagihanMahasiswa::updateOrCreate(
            ['nomor_tagihan' => 'INV-SIAKAD-2025-002'],
            [
                'mahasiswa_id' => 102,
                'tahun_akademik_id' => 1,
                'total_tagihan' => 4000000,
                'total_potongan' => 0,
                'total_denda' => 0,
                'total_bayar' => 0,
                'status' => 'belum_bayar',
                'requires_approval' => false,
                'status_approval' => 'approved',
                'source_system' => 'SIAKAD',
                'jatuh_tempo' => now()->addDays(20)->toDateString(),
            ]
        );

        DetailTagihan::firstOrCreate(
            ['tagihan_id' => $tagihanSiti->id, 'master_biaya_id' => $jenisBiayaUkt->id],
            ['nominal' => 4000000, 'potongan' => 0, 'nominal_bersih' => 4000000, 'keterangan' => 'SPP Reguler Angkatan 2025']
        );

        VirtualAccount::updateOrCreate(
            ['tagihan_id' => $tagihanSiti->id],
            [
                'va_number' => '88012025010018',
                'bank_kode' => 'BNI',
                'bank_nama' => 'Bank BNI (Virtual Account)',
                'nominal' => 4000000,
                'expired_at' => now()->addDays(20),
                'status' => 'aktif',
            ]
        );

        // Mahasiswa 104 (Rian Hidayat - Internasional Diskon Mitra 50%)
        $tagihanRian = TagihanMahasiswa::updateOrCreate(
            ['nomor_tagihan' => 'INV-SIAKAD-2025-004'],
            [
                'mahasiswa_id' => 104,
                'tahun_akademik_id' => 1,
                'total_tagihan' => 11000000,
                'total_potongan' => 5500000,
                'total_denda' => 0,
                'total_bayar' => 0,
                'status' => 'belum_bayar',
                'requires_approval' => false,
                'status_approval' => 'approved',
                'source_system' => 'SIAKAD',
                'jatuh_tempo' => now()->addDays(25)->toDateString(),
            ]
        );

        DetailTagihan::firstOrCreate(
            ['tagihan_id' => $tagihanRian->id, 'master_biaya_id' => $jenisBiayaUkt->id],
            ['nominal' => 11000000, 'potongan' => 5500000, 'nominal_bersih' => 5500000, 'keterangan' => 'SPP Kelas Internasional Angkatan 2025']
        );

        PotonganTagihan::firstOrCreate(
            ['tagihan_id' => $tagihanRian->id, 'tipe' => 'diskon'],
            ['nominal_potongan' => 5500000, 'keterangan' => 'Potongan Diskon Mitra 50%']
        );

        VirtualAccount::updateOrCreate(
            ['tagihan_id' => $tagihanRian->id],
            [
                'va_number' => '88012025010099',
                'bank_kode' => 'BNI',
                'bank_nama' => 'Bank BNI (Virtual Account)',
                'nominal' => 5500000,
                'expired_at' => now()->addDays(25),
                'status' => 'aktif',
            ]
        );

        // ── AHMAD FADILLAH (NIM: 2301001001) SAMPLE BILLS & PAYMENTS ──
        // Semester 1 (Gasal 2023/2024 - LUNAS)
        $tagihanAhmad1 = TagihanMahasiswa::updateOrCreate(
            ['nomor_tagihan' => 'INV-SIAKAD-2023-SMT1-00001'],
            [
                'mahasiswa_id' => 1,
                'tahun_akademik_id' => 1,
                'total_tagihan' => 3500000,
                'total_potongan' => 0,
                'total_denda' => 0,
                'total_bayar' => 3500000,
                'status' => 'lunas',
                'requires_approval' => false,
                'status_approval' => 'approved',
                'source_system' => 'SIAKAD',
                'jatuh_tempo' => '2023-09-30',
                'catatan_approval' => 'Tagihan masal UKT Semester 1 (Gasal 2023/2024)',
            ]
        );

        DetailTagihan::firstOrCreate(
            ['tagihan_id' => $tagihanAhmad1->id, 'master_biaya_id' => $jenisBiayaUkt->id],
            ['nominal' => 3500000, 'potongan' => 0, 'nominal_bersih' => 3500000, 'keterangan' => 'Uang Kuliah Tunggal (UKT) - Semester 1 (Gasal 2023/2024)']
        );

        \App\Models\Sikeu\Pembayaran::firstOrCreate(
            ['kode_transaksi' => 'TRX-LOKET-20230910-001'],
            [
                'tagihan_id' => $tagihanAhmad1->id,
                'jumlah_bayar' => 3500000,
                'waktu_bayar' => '2023-09-10 10:15:00',
                'channel_bayar' => 'BNI_VA',
                'status' => 'success',
                'catatan' => 'Pelunasan UKT Semester 1 (Gasal 2023/2024)',
            ]
        );

        // Semester 2 (Genap 2023/2024 - LUNAS)
        $tagihanAhmad2 = TagihanMahasiswa::updateOrCreate(
            ['nomor_tagihan' => 'INV-SIAKAD-2023-SMT2-00001'],
            [
                'mahasiswa_id' => 1,
                'tahun_akademik_id' => 2,
                'total_tagihan' => 3500000,
                'total_potongan' => 0,
                'total_denda' => 0,
                'total_bayar' => 3500000,
                'status' => 'lunas',
                'requires_approval' => false,
                'status_approval' => 'approved',
                'source_system' => 'SIAKAD',
                'jatuh_tempo' => '2024-02-28',
                'catatan_approval' => 'Tagihan masal UKT Semester 2 (Genap 2023/2024)',
            ]
        );

        DetailTagihan::firstOrCreate(
            ['tagihan_id' => $tagihanAhmad2->id, 'master_biaya_id' => $jenisBiayaUkt->id],
            ['nominal' => 3500000, 'potongan' => 0, 'nominal_bersih' => 3500000, 'keterangan' => 'Uang Kuliah Tunggal (UKT) - Semester 2 (Genap 2023/2024)']
        );

        \App\Models\Sikeu\Pembayaran::firstOrCreate(
            ['kode_transaksi' => 'TRX-LOKET-20240215-001'],
            [
                'tagihan_id' => $tagihanAhmad2->id,
                'jumlah_bayar' => 3500000,
                'waktu_bayar' => '2024-02-15 14:20:00',
                'channel_bayar' => 'LOKET_TUNAI',
                'status' => 'success',
                'catatan' => 'Pelunasan UKT Semester 2 (Genap 2023/2024)',
            ]
        );

        // Semester 3 (Gasal 2024/2025 - BELUM BAYAR / AKTIF)
        $tagihanAhmad3 = TagihanMahasiswa::updateOrCreate(
            ['nomor_tagihan' => 'INV-SIAKAD-2023-SMT3-00001'],
            [
                'mahasiswa_id' => 1,
                'tahun_akademik_id' => 3,
                'total_tagihan' => 3500000,
                'total_potongan' => 0,
                'total_denda' => 0,
                'total_bayar' => 0,
                'status' => 'belum_bayar',
                'requires_approval' => false,
                'status_approval' => 'approved',
                'source_system' => 'SIAKAD',
                'jatuh_tempo' => now()->addDays(30)->toDateString(),
                'catatan_approval' => 'Tagihan masal UKT Semester 3 (Gasal 2024/2025)',
            ]
        );

        DetailTagihan::firstOrCreate(
            ['tagihan_id' => $tagihanAhmad3->id, 'master_biaya_id' => $jenisBiayaUkt->id],
            ['nominal' => 3500000, 'potongan' => 0, 'nominal_bersih' => 3500000, 'keterangan' => 'Uang Kuliah Tunggal (UKT) - Semester 3 (Gasal 2024/2025)']
        );

        VirtualAccount::updateOrCreate(
            ['tagihan_id' => $tagihanAhmad3->id],
            [
                'va_number' => '880122301001001',
                'bank_kode' => 'BNI',
                'bank_nama' => 'Bank BNI (Virtual Account)',
                'nominal' => 3500000,
                'expired_at' => now()->addDays(30),
                'status' => 'aktif',
            ]
        );
    }
}

