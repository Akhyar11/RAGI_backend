<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Spmb\MasterProgramStudi;
use App\Models\Siakad\MataKuliah;
use App\Models\Siakad\Kelas;
use App\Models\Siakad\Cpl;
use App\Models\Siakad\Cpmk;
use App\Models\Siakad\SubCpmk;
use App\Models\Siakad\KomponenPenilaian;
use App\Models\Siakad\KrsDetail;
use App\Models\Siakad\NilaiKomponenMahasiswa;
use App\Models\Siakad\KetercapaianCpmkMahasiswa;
use App\Models\Siakad\NilaiMahasiswa;

class SiakadObeSeeder extends Seeder
{
    public function run(): void
    {
        $prodis = MasterProgramStudi::all();

        // 1. Seed CPL untuk setiap Program Studi
        foreach ($prodis as $prodi) {
            $cplData = [
                [
                    'kode_cpl' => 'CPL-01',
                    'kategori' => 'sikap',
                    'deskripsi' => 'Mampu menunjukkan sikap bertakwa kepada Tuhan Yang Maha Esa, menjunjung tinggi nilai kemanusiaan, dan beretika profesi akademik.',
                ],
                [
                    'kode_cpl' => 'CPL-02',
                    'kategori' => 'pengetahuan',
                    'deskripsi' => 'Menguasai konsep teoretis sains, logika komputasi, algoritma, dan rekayasa arsitektur sistem informasi.',
                ],
                [
                    'kode_cpl' => 'CPL-03',
                    'kategori' => 'keterampilan_umum',
                    'deskripsi' => 'Mampu menerapkan pemikiran logis, kritis, sistematis, dan inovatif dalam konteks pengembangan iptek serta bekerja sama dalam tim.',
                ],
                [
                    'kode_cpl' => 'CPL-04',
                    'kategori' => 'keterampilan_khusus',
                    'deskripsi' => 'Mampu merancang, mengimplementasikan, dan mengevaluasi solusi perangkat lunak skala enterprise berbasis data cerdas.',
                ],
            ];

            foreach ($cplData as $cd) {
                Cpl::updateOrCreate(
                    ['program_studi_id' => $prodi->id, 'kode_cpl' => $cd['kode_cpl']],
                    ['kategori' => $cd['kategori'], 'deskripsi' => $cd['deskripsi'], 'is_active' => true]
                );
            }
        }

        // 2. Seed CPMK untuk setiap Mata Kuliah
        $matakuliahs = MataKuliah::all();
        foreach ($matakuliahs as $mk) {
            $prodiId = $mk->kurikulum?->program_studi_id ?? 1;
            $cpls = Cpl::where('program_studi_id', $prodiId)->get();

            $cpmk1 = Cpmk::updateOrCreate(
                ['mata_kuliah_id' => $mk->id, 'kode_cpmk' => 'CPMK-1'],
                [
                    'cpl_id' => $cpls->where('kode_cpl', 'CPL-02')->first()?->id ?? $cpls->first()?->id,
                    'deskripsi' => "Mampu memahami dan menjelaskan konsep fundamental serta metodologi pada {$mk->nama}.",
                    'bobot_persentase' => 30.00,
                ]
            );

            $cpmk2 = Cpmk::updateOrCreate(
                ['mata_kuliah_id' => $mk->id, 'kode_cpmk' => 'CPMK-2'],
                [
                    'cpl_id' => $cpls->where('kode_cpl', 'CPL-03')->first()?->id ?? $cpls->first()?->id,
                    'deskripsi' => "Mampu menganalisis kasus, merancang skema kerja, dan memecahkan persoalan praktis {$mk->nama}.",
                    'bobot_persentase' => 35.00,
                ]
            );

            $cpmk3 = Cpmk::updateOrCreate(
                ['mata_kuliah_id' => $mk->id, 'kode_cpmk' => 'CPMK-3'],
                [
                    'cpl_id' => $cpls->where('kode_cpl', 'CPL-04')->first()?->id ?? $cpls->first()?->id,
                    'deskripsi' => "Mampu mengembangkan proyek solusi terpadu berbasis luaran (PBL) pada domain {$mk->nama}.",
                    'bobot_persentase' => 35.00,
                ]
            );
        }

        // 3. Seed Komponen Penilaian Dinamis OBE untuk Kelas & Hitung Nilai Peserta
        $kelases = Kelas::with(['mataKuliah.cpmks'])->get();
        foreach ($kelases as $kelas) {
            $cpmks = $kelas->mataKuliah?->cpmks;
            if (!$cpmks || $cpmks->isEmpty()) continue;

            $c1 = $cpmks->where('kode_cpmk', 'CPMK-1')->first() ?? $cpmks->first();
            $c2 = $cpmks->where('kode_cpmk', 'CPMK-2')->first() ?? $cpmks->first();
            $c3 = $cpmks->where('kode_cpmk', 'CPMK-3')->first() ?? $cpmks->last();

            $komp1 = KomponenPenilaian::updateOrCreate(
                ['kelas_id' => $kelas->id, 'nama_komponen' => 'Tugas Mandiri & Studi Kasus'],
                ['cpmk_id' => $c1->id, 'teknik_penilaian' => 'tugas', 'bobot' => 20.00, 'urutan' => 1, 'is_aktif' => true]
            );

            $komp2 = KomponenPenilaian::updateOrCreate(
                ['kelas_id' => $kelas->id, 'nama_komponen' => 'Kuis Formatif & Review Teori'],
                ['cpmk_id' => $c1->id, 'teknik_penilaian' => 'kuis', 'bobot' => 15.00, 'urutan' => 2, 'is_aktif' => true]
            );

            $komp3 = KomponenPenilaian::updateOrCreate(
                ['kelas_id' => $kelas->id, 'nama_komponen' => 'Ujian Tengah Semester (UTS) - Problem Solving'],
                ['cpmk_id' => $c2->id, 'teknik_penilaian' => 'tes_tulis', 'bobot' => 30.00, 'urutan' => 3, 'is_aktif' => true]
            );

            $komp4 = KomponenPenilaian::updateOrCreate(
                ['kelas_id' => $kelas->id, 'nama_komponen' => 'Proyek PBL / Ujian Praktik Akhir (UAS)'],
                ['cpmk_id' => $c3->id, 'teknik_penilaian' => 'proyek', 'bobot' => 35.00, 'urutan' => 4, 'is_aktif' => true]
            );

            // Isi nilai peserta kelas
            $krsDetails = KrsDetail::where('kelas_id', $kelas->id)->get();
            foreach ($krsDetails as $kd) {
                // Mock skor sampel yang realistis
                $score1 = 85.0; // Tugas
                $score2 = 80.0; // Kuis
                $score3 = 88.0; // UTS
                $score4 = 92.0; // UAS PBL

                NilaiKomponenMahasiswa::updateOrCreate(
                    ['krs_detail_id' => $kd->id, 'komponen_penilaian_id' => $komp1->id],
                    ['nilai_angka' => $score1]
                );
                NilaiKomponenMahasiswa::updateOrCreate(
                    ['krs_detail_id' => $kd->id, 'komponen_penilaian_id' => $komp2->id],
                    ['nilai_angka' => $score2]
                );
                NilaiKomponenMahasiswa::updateOrCreate(
                    ['krs_detail_id' => $kd->id, 'komponen_penilaian_id' => $komp3->id],
                    ['nilai_angka' => $score3]
                );
                NilaiKomponenMahasiswa::updateOrCreate(
                    ['krs_detail_id' => $kd->id, 'komponen_penilaian_id' => $komp4->id],
                    ['nilai_angka' => $score4]
                );

                // Hitung Ketercapaian CPMK
                $cpmk1Score = round(($score1 * 20 + $score2 * 15) / 35, 2);
                $cpmk2Score = $score3;
                $cpmk3Score = $score4;

                KetercapaianCpmkMahasiswa::updateOrCreate(
                    ['krs_detail_id' => $kd->id, 'cpmk_id' => $c1->id],
                    ['skor_ketercapaian' => $cpmk1Score, 'status_ketercapaian' => 'tercapai']
                );
                KetercapaianCpmkMahasiswa::updateOrCreate(
                    ['krs_detail_id' => $kd->id, 'cpmk_id' => $c2->id],
                    ['skor_ketercapaian' => $cpmk2Score, 'status_ketercapaian' => 'tercapai']
                );
                KetercapaianCpmkMahasiswa::updateOrCreate(
                    ['krs_detail_id' => $kd->id, 'cpmk_id' => $c3->id],
                    ['skor_ketercapaian' => $cpmk3Score, 'status_ketercapaian' => 'tercapai']
                );

                $akhir = ($score1 * 0.20) + ($score2 * 0.15) + ($score3 * 0.30) + ($score4 * 0.35);

                NilaiMahasiswa::updateOrCreate(
                    ['krs_detail_id' => $kd->id],
                    [
                        'nilai_harian' => $score1,
                        'nilai_uts' => $score3,
                        'nilai_uas' => $score4,
                        'nilai_praktik' => $score2,
                        'nilai_akhir' => round($akhir, 2),
                        'nilai_huruf' => 'A',
                        'bobot_mutu' => 4.00,
                        'is_final' => true,
                    ]
                );
            }
        }
    }
}
