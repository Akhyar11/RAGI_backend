<?php

namespace Database\Seeders\Simpeg;

use App\Models\Simpeg\Jabatan;
use App\Models\Simpeg\JabatanFungsionalAkademik;
use App\Models\Simpeg\Pegawai;
use App\Models\Simpeg\RiwayatJabatan;
use App\Models\Simpeg\RiwayatPendidikanPegawai;
use App\Models\Simpeg\UnitKerja;
use App\Models\User;
use Illuminate\Database\Seeder;

class PegawaiSeeder extends Seeder
{
    public function run(): void
    {

        $prodiIf = UnitKerja::where('kode', 'IF')->first();
        $prodiSi = UnitKerja::where('kode', 'SI')->first() ?? $prodiIf;
        $rektorat = UnitKerja::where('kode', 'REK')->first() ?? $prodiIf;
        $jabatanDosen = Jabatan::where('nama', 'Dosen Pengajar')->first();
        $jafungLektor = JabatanFungsionalAkademik::where('golongan', 'lektor')->first();
        $jafungKepala = JabatanFungsionalAkademik::where('golongan', 'lektor_kepala')->first();

        // 1. Pegawai: Dr. Wasis Utama, M.T. (Admin & Dosen)
        $adminUser = User::where('email', 'admin@kampus.ac.id')->first() ?? User::where('email', 'admin@campus.ac.id')->first();
        if ($adminUser) {
            $pegAdmin = Pegawai::updateOrCreate([
                'user_id' => $adminUser->id,
            ], [
                'unit_kerja_id' => $rektorat?->id,
                'nip' => '198501152010121001',
                'nik' => '3271011501850002',
                'nama_lengkap' => 'Dr. Wasis Utama, M.T.',
                'tanggal_lahir' => '1985-01-15',
                'tempat_lahir' => 'Bandung',
                'jenis_kelamin' => 'L',
                'agama' => 'Islam',
                'jenis_pegawai' => 'dosen',
                'status_kepegawaian' => 'tetap_yayasan',
                'tanggal_masuk' => '2010-12-01',
                'status' => 'aktif',
                'telepon' => '081234567890',
                'alamat' => 'Jl. Kampus Utama No. 12, Bandung',
            ]);

            RiwayatJabatan::updateOrCreate([
                'pegawai_id' => $pegAdmin->id,
            ], [
                'jabatan_id' => $jabatanDosen?->id,
                'jabatan_fungsional_id' => $jafungKepala?->id,
                'mulai_jabatan' => '2020-01-01',
                'sk_nomor' => 'SK/SK-PEG/2020/001',
                'sk_tanggal' => '2020-01-01',
                'is_active' => true,
            ]);

            RiwayatPendidikanPegawai::updateOrCreate([
                'pegawai_id' => $pegAdmin->id,
                'jenjang' => 's3',
            ], [
                'nama_institusi' => 'Institut Teknologi Bandung',
                'program_studi' => 'Teknik Informatika',
                'bidang_ilmu' => 'Kecerdasan Buatan',
                'tahun_masuk' => 2015,
                'tahun_lulus' => 2019,
                'nomor_ijazah' => 'IJZ-ITB-2019-S3-098',
                'is_pendidikan_terakhir' => true,
            ]);
        }

        // 2. Pegawai: Anisa Rahmawati, M.Kom. (Dosen)
        $dosenUser = User::where('email', 'dosen@kampus.ac.id')->first();
        if ($dosenUser) {
            $pegDosen = Pegawai::updateOrCreate([
                'user_id' => $dosenUser->id,
            ], [
                'unit_kerja_id' => $prodiIf?->id,
                'nip' => '199208252022012004',
                'nik' => '3271012508920005',
                'nama_lengkap' => 'Anisa Rahmawati, M.Kom.',
                'tanggal_lahir' => '1992-08-25',
                'tempat_lahir' => 'Jakarta',
                'jenis_kelamin' => 'P',
                'agama' => 'Islam',
                'jenis_pegawai' => 'dosen',
                'status_kepegawaian' => 'tetap_yayasan',
                'tanggal_masuk' => '2022-01-15',
                'status' => 'aktif',
                'telepon' => '081234567891',
                'alamat' => 'Jl. Pendidikan No. 45, Bandung',
            ]);

            RiwayatJabatan::updateOrCreate([
                'pegawai_id' => $pegDosen->id,
            ], [
                'jabatan_id' => $jabatanDosen?->id,
                'jabatan_fungsional_id' => $jafungLektor?->id,
                'mulai_jabatan' => '2022-01-15',
                'sk_nomor' => 'SK/SK-PEG/2022/088',
                'sk_tanggal' => '2022-01-15',
                'is_active' => true,
            ]);

            RiwayatPendidikanPegawai::updateOrCreate([
                'pegawai_id' => $pegDosen->id,
                'jenjang' => 's2',
            ], [
                'nama_institusi' => 'Universitas Indonesia',
                'program_studi' => 'Ilmu Komputer',
                'bidang_ilmu' => 'Rekayasa Perangkat Lunak',
                'tahun_masuk' => 2018,
                'tahun_lulus' => 2020,
                'nomor_ijazah' => 'IJZ-UI-2020-S2-451',
                'is_pendidikan_terakhir' => true,
            ]);
        }
    }
}
