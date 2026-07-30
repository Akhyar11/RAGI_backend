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
        $adminUser = User::where('user_type', 'admin')->first() ?? User::where('email', 'admin@campus.ac.id')->first();
        $prodiIf = UnitKerja::where('kode', 'IF')->first();
        $jabatanDosen = Jabatan::where('nama', 'Dosen Pengajar')->first();
        $jafungLektor = JabatanFungsionalAkademik::where('golongan', 'lektor')->first();

        if ($adminUser && $prodiIf) {
            $pegawai = Pegawai::create([
                'user_id' => $adminUser->id,
                'unit_kerja_id' => $prodiIf->id,
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

            RiwayatJabatan::create([
                'pegawai_id' => $pegawai->id,
                'jabatan_id' => $jabatanDosen?->id,
                'jabatan_fungsional_id' => $jafungLektor?->id,
                'mulai_jabatan' => '2020-01-01',
                'sk_nomor' => 'SK/SK-PEG/2020/001',
                'sk_tanggal' => '2020-01-01',
                'is_active' => true,
            ]);

            RiwayatPendidikanPegawai::create([
                'pegawai_id' => $pegawai->id,
                'jenjang' => 's3',
                'nama_institusi' => 'Institut Teknologi Bandung',
                'program_studi' => 'Teknik Informatika',
                'bidang_ilmu' => 'Kecerdasan Buatan',
                'tahun_masuk' => 2015,
                'tahun_lulus' => 2019,
                'nomor_ijazah' => 'IJZ-ITB-2019-S3-098',
                'is_pendidikan_terakhir' => true,
            ]);
        }
    }
}
