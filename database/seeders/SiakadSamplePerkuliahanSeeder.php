<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Siakad\TahunAkademik;
use App\Models\Siakad\Kurikulum;
use App\Models\Siakad\MataKuliah;
use App\Models\Siakad\Kelas;
use App\Models\Siakad\Dosen;
use App\Models\Siakad\DosenPengampu;
use App\Models\Siakad\Mahasiswa;
use App\Models\Siakad\TugasAkhir;
use App\Models\Siakad\Pkl;
use App\Models\Spmb\MasterProgramStudi;

class SiakadSamplePerkuliahanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Tahun Akademik
        $taGanjil = TahunAkademik::updateOrCreate(
            ['kode' => '20251'],
            [
                'nama' => '2025/2026 Ganjil',
                'tahun_mulai' => 2025,
                'tahun_selesai' => 2026,
                'is_active' => false,
                'mode_penilaian' => 'persentase',
            ]
        );

        $taGenap = TahunAkademik::updateOrCreate(
            ['kode' => '20252'],
            [
                'nama' => '2025/2026 Genap',
                'tahun_mulai' => 2025,
                'tahun_selesai' => 2026,
                'is_active' => true,
                'mode_penilaian' => 'persentase',
            ]
        );

        // 2. Program Studi
        $prodi = MasterProgramStudi::where('nama', 'like', '%Perangkat Lunak%')->first()
            ?? MasterProgramStudi::first()
            ?? MasterProgramStudi::firstOrCreate(
                ['kode_prodi' => 'TRPL'],
                [
                    'nama' => 'D4 Teknologi Rekayasa Perangkat Lunak',
                    'jenjang' => 'D4',
                ]
            );

        // 3. Pastikan Dosen Terhubung ke Homebase Prodi
        $dosenDwi = Dosen::where('pegawai_id', 2)->first() ?? Dosen::find(2);
        if ($dosenDwi && empty($dosenDwi->program_studi_id)) {
            $dosenDwi->update(['program_studi_id' => $prodi->id]);
        }

        $dosenFauzi = Dosen::where('pegawai_id', 5)->first() ?? Dosen::find(3);
        if ($dosenFauzi && empty($dosenFauzi->program_studi_id)) {
            $dosenFauzi->update(['program_studi_id' => $prodi->id]);
        }

        if (!$dosenDwi) {
            return;
        }

        // 4. Kurikulum
        $kurikulum = Kurikulum::updateOrCreate(
            ['kode' => 'KUR-TRPL-2024'],
            [
                'program_studi_id' => $prodi->id,
                'nama' => 'Kurikulum OBE TRPL 2024-2028',
                'tahun_berlaku' => 2024,
                'total_sks_lulus' => 144,
                'is_active' => true,
            ]
        );

        // 5. Mata Kuliah
        $mkPbo = MataKuliah::updateOrCreate(
            ['kurikulum_id' => $kurikulum->id, 'kode_mk' => 'TRPL201'],
            [
                'nama' => 'Pemrograman Berorientasi Objek',
                'sks_teori' => 2,
                'sks_praktik' => 1,
                'total_sks' => 3,
                'semester_anjuran' => 3,
                'is_active' => true,
            ]
        );

        $mkRpl = MataKuliah::updateOrCreate(
            ['kurikulum_id' => $kurikulum->id, 'kode_mk' => 'TRPL301'],
            [
                'nama' => 'Rekayasa Perangkat Lunak Terapan',
                'sks_teori' => 2,
                'sks_praktik' => 1,
                'total_sks' => 3,
                'semester_anjuran' => 5,
                'is_active' => true,
            ]
        );

        $mkCloud = MataKuliah::updateOrCreate(
            ['kurikulum_id' => $kurikulum->id, 'kode_mk' => 'TRPL305'],
            [
                'nama' => 'Arsitektur Komputasi Awan (Cloud Architecture)',
                'sks_teori' => 2,
                'sks_praktik' => 1,
                'total_sks' => 3,
                'semester_anjuran' => 5,
                'is_active' => true,
            ]
        );

        $mkProyek = MataKuliah::updateOrCreate(
            ['kurikulum_id' => $kurikulum->id, 'kode_mk' => 'TRPL401'],
            [
                'nama' => 'Manajemen Proyek TI & Kualitas',
                'sks_teori' => 2,
                'sks_praktik' => 0,
                'total_sks' => 2,
                'semester_anjuran' => 7,
                'is_active' => true,
            ]
        );

        // 6. Kelas Perkuliahan
        $kelasRpl = Kelas::updateOrCreate(
            ['kode_kelas' => 'TRPL301-A', 'tahun_akademik_id' => $taGanjil->id],
            [
                'mata_kuliah_id' => $mkRpl->id,
                'program_studi_id' => $prodi->id,
                'nama_kelas' => 'Kelas A Reguler RPL',
                'kapasitas' => 35,
                'kuota_krs' => 35,
                'hari' => 'Senin',
                'jam_mulai' => '08:00',
                'jam_selesai' => '10:30',
                'status' => 'aktif',
            ]
        );

        $kelasCloud = Kelas::updateOrCreate(
            ['kode_kelas' => 'TRPL305-A', 'tahun_akademik_id' => $taGenap->id],
            [
                'mata_kuliah_id' => $mkCloud->id,
                'program_studi_id' => $prodi->id,
                'nama_kelas' => 'Kelas A Cloud Computing',
                'kapasitas' => 30,
                'kuota_krs' => 30,
                'hari' => 'Rabu',
                'jam_mulai' => '13:00',
                'jam_selesai' => '15:30',
                'status' => 'aktif',
            ]
        );

        $kelasPbo = Kelas::updateOrCreate(
            ['kode_kelas' => 'TRPL201-A', 'tahun_akademik_id' => $taGanjil->id],
            [
                'mata_kuliah_id' => $mkPbo->id,
                'program_studi_id' => $prodi->id,
                'nama_kelas' => 'Kelas A Pemrograman Java/OOP',
                'kapasitas' => 35,
                'kuota_krs' => 35,
                'hari' => 'Kamis',
                'jam_mulai' => '10:00',
                'jam_selesai' => '12:30',
                'status' => 'aktif',
            ]
        );

        // 7. Dosen Pengampu Kelas
        DosenPengampu::updateOrCreate(
            ['kelas_id' => $kelasRpl->id, 'dosen_id' => $dosenDwi->id],
            [
                'peran' => 'pengampu_utama',
                'sks_substansi_total' => 3.00,
                'rencana_minggu_pertemuan' => 16,
                'realisasi_minggu_pertemuan' => 16,
            ]
        );

        DosenPengampu::updateOrCreate(
            ['kelas_id' => $kelasCloud->id, 'dosen_id' => $dosenDwi->id],
            [
                'peran' => 'pengampu_utama',
                'sks_substansi_total' => 3.00,
                'rencana_minggu_pertemuan' => 16,
                'realisasi_minggu_pertemuan' => 14,
            ]
        );

        if ($dosenFauzi) {
            DosenPengampu::updateOrCreate(
                ['kelas_id' => $kelasPbo->id, 'dosen_id' => $dosenFauzi->id],
                [
                    'peran' => 'pengampu_utama',
                    'sks_substansi_total' => 3.00,
                    'rencana_minggu_pertemuan' => 16,
                    'realisasi_minggu_pertemuan' => 16,
                ]
            );
        }

        // 8. Mahasiswa Perwalian & Bimbingan
        $mhs1 = Mahasiswa::updateOrCreate(
            ['nim' => '220101001'],
            [
                'nama_lengkap' => 'Bagas Pratama',
                'program_studi_id' => $prodi->id,
                'dosen_wali_id' => $dosenDwi->id,
                'angkatan' => 2022,
                'status' => 'aktif',
                'jenis_kelamin' => 'L',
                'email' => 'bagas.pratama@mhs.campus.ac.id',
            ]
        );

        $mhs2 = Mahasiswa::updateOrCreate(
            ['nim' => '220101002'],
            [
                'nama_lengkap' => 'Anisa Rahmawati',
                'program_studi_id' => $prodi->id,
                'dosen_wali_id' => $dosenDwi->id,
                'angkatan' => 2022,
                'status' => 'aktif',
                'jenis_kelamin' => 'P',
                'email' => 'anisa.rahma@mhs.campus.ac.id',
            ]
        );

        $mhs3 = Mahasiswa::updateOrCreate(
            ['nim' => '230101015'],
            [
                'nama_lengkap' => 'Dimas Aditya Saputra',
                'program_studi_id' => $prodi->id,
                'dosen_wali_id' => $dosenDwi->id,
                'angkatan' => 2023,
                'status' => 'aktif',
                'jenis_kelamin' => 'L',
                'email' => 'dimas.aditya@mhs.campus.ac.id',
            ]
        );

        $mhs4 = Mahasiswa::updateOrCreate(
            ['nim' => '230101018'],
            [
                'nama_lengkap' => 'Nabila Putri Larasati',
                'program_studi_id' => $prodi->id,
                'dosen_wali_id' => $dosenDwi->id,
                'angkatan' => 2023,
                'status' => 'aktif',
                'jenis_kelamin' => 'P',
                'email' => 'nabila.putri@mhs.campus.ac.id',
            ]
        );

        // 9. Bimbingan Tugas Akhir (SIAKAD)
        TugasAkhir::updateOrCreate(
            ['mahasiswa_id' => $mhs1->id],
            [
                'tahun_akademik_id' => $taGenap->id,
                'program_studi_id' => $prodi->id,
                'judul_utama' => 'Rancang Bangun Sistem Pemantauan IoT Presensi Cerdas Terdistribusi Berbasis Microservices',
                'judul_inggris' => 'Design and Implementation of Distributed IoT Smart Attendance System Based on Microservices',
                'bidang_kajian' => 'Internet of Things & Software Engineering',
                'dosen_pembimbing_1_id' => $dosenDwi->id,
                'dosen_pembimbing_2_id' => $dosenFauzi?->id ?? $dosenDwi->id,
                'tahap' => 'sidang_skripsi',
                'status' => 'siap_sidang',
                'nilai_huruf_akhir' => 'A',
                'nilai_angka_akhir' => 88.50,
                'bobot_mutu_akhir' => 4.00,
                'total_sesi_bimbingan' => 12,
            ]
        );

        TugasAkhir::updateOrCreate(
            ['mahasiswa_id' => $mhs2->id],
            [
                'tahun_akademik_id' => $taGenap->id,
                'program_studi_id' => $prodi->id,
                'judul_utama' => 'Penerapan Algoritma Random Forest dan XGBoost untuk Klasifikasi Kinerja Akademik Mahasiswa',
                'judul_inggris' => 'Application of Random Forest and XGBoost Algorithms for Academic Performance Classification',
                'bidang_kajian' => 'Machine Learning & Data Mining',
                'dosen_pembimbing_1_id' => $dosenDwi->id,
                'tahap' => 'penelitian',
                'status' => 'sedang_bimbingan',
                'total_sesi_bimbingan' => 6,
            ]
        );

        // 10. Bimbingan PKL / Magang (SIAKAD)
        Pkl::updateOrCreate(
            ['mahasiswa_id' => $mhs3->id],
            [
                'tahun_akademik_id' => $taGanjil->id,
                'program_studi_id' => $prodi->id,
                'dosen_pembimbing_id' => $dosenDwi->id,
                'jenis' => 'magang_industri',
                'instansi_nama' => 'PT Telekomunikasi Selular (Telkomsel)',
                'instansi_alamat' => 'Jl. Jenderal Sudirman Kav. 52-53, Jakarta Selatan',
                'instansi_kota' => 'Jakarta Selatan',
                'judul_laporan' => 'Pengembangan Modul Reporting Dashboard Menggunakan React dan Laravel Framework',
                'status' => 'sedang_berjalan',
                'tanggal_mulai' => '2025-09-01',
                'tanggal_selesai' => '2025-12-31',
            ]
        );

        Pkl::updateOrCreate(
            ['mahasiswa_id' => $mhs4->id],
            [
                'tahun_akademik_id' => $taGanjil->id,
                'program_studi_id' => $prodi->id,
                'dosen_pembimbing_id' => $dosenDwi->id,
                'jenis' => 'pkl_reguler',
                'instansi_nama' => 'Dinas Komunikasi dan Informatika Provinsi Jawa Tengah',
                'instansi_alamat' => 'Jl. Menteri Supeno No. 2, Semarang',
                'instansi_kota' => 'Semarang',
                'judul_laporan' => 'Analisis Keamanan Jaringan dan Manajemen Server Sistem Pemerintahan Berbasis Elektronik',
                'status' => 'laporan_selesai',
                'tanggal_mulai' => '2025-07-01',
                'tanggal_selesai' => '2025-08-31',
            ]
        );
    }
}
