<?php

namespace Database\Seeders\Sikeu;

use Illuminate\Database\Seeder;
use App\Models\Sikeu\JenisBiaya;
use App\Models\Sikeu\TarifUkt;
use App\Models\Sikeu\Beasiswa;
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
        // 1. Ensure Master Beasiswa exists
        $beasiswaKip = Beasiswa::firstOrCreate(
            ['kode' => 'KIP_KULIAH'],
            [
                'nama' => 'Beasiswa KIP Kuliah Pemerintah',
                'sumber' => 'pemerintah',
                'tipe_potongan' => 'persen',
                'nilai_potongan' => 100.00,
                'deskripsi' => 'Potongan 100% biaya UKT untuk mahasiswa penerima KIP-K',
                'is_active' => true,
            ]
        );

        $beasiswaPrestasi = Beasiswa::firstOrCreate(
            ['kode' => 'PRESTASI_AKADEMIK'],
            [
                'nama' => 'Beasiswa Prestasi Akademik Kampus',
                'sumber' => 'internal',
                'tipe_potongan' => 'nominal',
                'nilai_potongan' => 1500000.00,
                'deskripsi' => 'Potongan nominal UKT untuk mahasiswa berprestasi',
                'is_active' => true,
            ]
        );

        $beasiswaMitra = Beasiswa::firstOrCreate(
            ['kode' => 'BEASISWA_MITRA'],
            [
                'nama' => 'Beasiswa Kemitraan Eksternal (50%)',
                'sumber' => 'eksternal',
                'tipe_potongan' => 'persen',
                'nilai_potongan' => 50.00,
                'deskripsi' => 'Potongan 50% biaya pendidikan dari program kemitraan',
                'is_active' => true,
            ]
        );

        // 2. Seed Master Tarif UKT per Angkatan & Jalur Kelas
        $jenisBiayaUkt = JenisBiaya::where('kode', 'UKT_REG')->first() ?? JenisBiaya::first();
        $jenisBiayaPraktikum = JenisBiaya::firstOrCreate(
            ['kode' => 'PRAKTIKUM'],
            [
                'nama' => 'Biaya Laboratorium & Praktikum',
                'tipe' => 'praktikum',
                'deskripsi' => 'Biaya penggunaan laboratorium & modul praktikum',
                'is_recurring' => true,
                'is_active' => true,
            ]
        );

        $angkatanList = [2023, 2024, 2025, 2026, 2027];
        $jalurList = ['Reguler', 'Karyawan', 'Internasional'];

        foreach ($angkatanList as $year) {
            foreach ($jalurList as $jalur) {
                for ($kelompok = 1; $kelompok <= 4; $kelompok++) {
                    $multiplier = $jalur === 'Karyawan' ? 1.4 : ($jalur === 'Internasional' ? 2.0 : 1.0);
                    $baseNominal = match ($kelompok) {
                        1 => 500000,
                        2 => 1500000,
                        3 => 3500000 + (($year - 2023) * 250000),
                        4 => 5500000 + (($year - 2023) * 500000),
                    };

                    TarifUkt::firstOrCreate(
                        [
                            'tahun_angkatan' => $year,
                            'jalur_kelas' => $jalur,
                            'kelompok_ukt' => $kelompok,
                            'jenis_biaya_id' => $jenisBiayaUkt->id,
                        ],
                        [
                            'program_studi_id' => 1,
                            'tahun_akademik_id' => 1,
                            'nominal' => $baseNominal * $multiplier,
                            'is_active' => true,
                        ]
                    );
                }
            }
        }

        // 3. Sample Penetapan Tipe Tagihan Mahasiswa (SPMB / SIAKAD / Admin Change)
        $samples = [
            [
                'mahasiswa_id' => 101,
                'nim' => '2024010042',
                'nama_mahasiswa' => 'Budi Santoso',
                'tahun_angkatan' => 2024,
                'jalur_kelas' => 'Reguler',
                'kelompok_ukt' => 3,
                'beasiswa_id' => $beasiswaKip->id,
                'status_pendaftaran' => 'SIAKAD_AKTIF',
                'catatan_perubahan' => 'Penetapan awal dari SPMB (Penerima Beasiswa KIP-Kuliah 100%)',
            ],
            [
                'mahasiswa_id' => 102,
                'nim' => '2025010018',
                'nama_mahasiswa' => 'Siti Rahmawati',
                'tahun_angkatan' => 2025,
                'jalur_kelas' => 'Reguler',
                'kelompok_ukt' => 3,
                'beasiswa_id' => null,
                'status_pendaftaran' => 'SPMB_DITERIMA',
                'catatan_perubahan' => 'Pendaftaran Jalur Mandiri SPMB (Reguler Non-Beasiswa)',
            ],
            [
                'mahasiswa_id' => 103,
                'nim' => '2023010088',
                'nama_mahasiswa' => 'Ahmad Fauzi',
                'tahun_angkatan' => 2023,
                'jalur_kelas' => 'Karyawan',
                'kelompok_ukt' => 4,
                'beasiswa_id' => null,
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
                'beasiswa_id' => $beasiswaMitra->id,
                'status_pendaftaran' => 'SPMB_DITERIMA',
                'catatan_perubahan' => 'Pendaftaran Kelas Internasional (Diskon Beasiswa Mitra 50%)',
            ],
        ];

        foreach ($samples as $s) {
            MahasiswaTipeTagihan::updateOrCreate(
                ['mahasiswa_id' => $s['mahasiswa_id']],
                $s
            );

            // Seed Mahasiswa Beasiswa mapping if assigned
            if (!empty($s['beasiswa_id'])) {
                DB::table('mahasiswa_beasiswa')->updateOrInsert(
                    ['mahasiswa_id' => $s['mahasiswa_id'], 'beasiswa_id' => $s['beasiswa_id']],
                    [
                        'tahun_akademik_id' => 1,
                        'berlaku_mulai' => now()->startOfYear()->toDateString(),
                        'berlaku_sampai' => now()->addYear()->toDateString(),
                        'status' => 'aktif',
                        'ditetapkan_oleh' => 1,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }
        }

        // 4. Seed Sample Tagihan Mahasiswa dengan Potongan Beasiswa Otomatis
        // Mahasiswa 101 (Budi Santoso - KIP 100%)
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
            ['tagihan_id' => $tagihanBudi->id, 'jenis_biaya_id' => $jenisBiayaUkt->id],
            ['nominal' => 3750000, 'potongan' => 3750000, 'nominal_bersih' => 0, 'keterangan' => 'UKT Reguler Angkatan 2024']
        );

        PotonganTagihan::firstOrCreate(
            ['tagihan_id' => $tagihanBudi->id, 'beasiswa_id' => $beasiswaKip->id],
            ['tipe' => 'beasiswa', 'nominal_potongan' => 3750000, 'keterangan' => 'Beasiswa KIP-Kuliah 100%']
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
            ['tagihan_id' => $tagihanSiti->id, 'jenis_biaya_id' => $jenisBiayaUkt->id],
            ['nominal' => 4000000, 'potongan' => 0, 'nominal_bersih' => 4000000, 'keterangan' => 'UKT Reguler Angkatan 2025']
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

        // Mahasiswa 104 (Rian Hidayat - Internasional Beasiswa Mitra 50%)
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
            ['tagihan_id' => $tagihanRian->id, 'jenis_biaya_id' => $jenisBiayaUkt->id],
            ['nominal' => 11000000, 'potongan' => 5500000, 'nominal_bersih' => 5500000, 'keterangan' => 'UKT Kelas Internasional Angkatan 2025']
        );

        PotonganTagihan::firstOrCreate(
            ['tagihan_id' => $tagihanRian->id, 'beasiswa_id' => $beasiswaMitra->id],
            ['tipe' => 'beasiswa', 'nominal_potongan' => 5500000, 'keterangan' => 'Potongan Beasiswa Mitra 50%']
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
    }
}
