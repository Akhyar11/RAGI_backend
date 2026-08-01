<?php

namespace Database\Seeders\Simpeg;

use App\Models\Simpeg\Jabatan;
use App\Models\Simpeg\JabatanFungsionalAkademik;
use App\Models\Simpeg\Pegawai;
use App\Models\Simpeg\RiwayatJabatan;
use App\Models\Simpeg\RiwayatPendidikanPegawai;
use App\Models\Simpeg\UnitKerja;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class PegawaiSeeder extends Seeder
{
    public function run(): void
    {
        $rektorat = UnitKerja::where('kode', 'REK')->first();
        $jabatanDosen = Jabatan::where('nama', 'Dosen Pengajar')->first();
        $jafungLektor = JabatanFungsionalAkademik::where('golongan', 'lektor')->first();
        $jafungKepala = JabatanFungsionalAkademik::where('golongan', 'lektor_kepala')->first();
        $jafungAsisten = JabatanFungsionalAkademik::where('golongan', 'asisten_ahli')->first();

        $dosenRole = Role::where('slug', 'dosen')->first();

        // 1. Admin & Dosen Wasis Utama
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

        // 2. Data 5 Dosen per Program Studi (6 Prodi = 30 Dosen)
        $prodiDosenMap = [
            'IF' => [
                ['nama' => 'Prof. Dr. Ir. H. Ahmad Dahlan, M.Kom', 'gender' => 'L', 'jenjang' => 's3', 'univ' => 'Institut Teknologi Bandung', 'prodi' => 'Teknik Informatika', 'bidang' => 'Deep Learning & Medical Imaging'],
                ['nama' => 'Dr. Siti Nurhaliza, S.T., M.T.', 'gender' => 'P', 'jenjang' => 's3', 'univ' => 'Universitas Gadjah Mada', 'prodi' => 'Teknik Elektro & Komputer', 'bidang' => 'IoT & Precision Agriculture'],
                ['nama' => 'Budi Santoso, M.Kom.', 'gender' => 'L', 'jenjang' => 's2', 'univ' => 'Universitas Indonesia', 'prodi' => 'Ilmu Komputer', 'bidang' => 'Kriptografi & Keamanan Jaringan'],
                ['nama' => 'Eka Putri, M.T.', 'gender' => 'P', 'jenjang' => 's2', 'univ' => 'Institut Teknologi Sepuluh Nopember', 'prodi' => 'Teknik Informatika', 'bidang' => 'Natural Language Processing'],
                ['nama' => 'Dr. Eng. Rahmat Hidayat, M.Sc.', 'gender' => 'L', 'jenjang' => 's3', 'univ' => 'Kyoto University', 'prodi' => 'Computer Science', 'bidang' => 'Embedded Systems & Robotics'],
            ],
            'SI' => [
                ['nama' => 'Dr. Rina Wijaya, S.Kom., M.T.', 'gender' => 'P', 'jenjang' => 's3', 'univ' => 'Universitas Indonesia', 'prodi' => 'Sistem Informasi', 'bidang' => 'Enterprise Architecture & Governance'],
                ['nama' => 'Hendra Setiawan, M.Kom.', 'gender' => 'L', 'jenjang' => 's2', 'univ' => 'Universitas Gadjah Mada', 'prodi' => 'Magister Teknologi Informasi', 'bidang' => 'Business Intelligence & Data Analytics'],
                ['nama' => 'Maya Kartika, S.T., M.IS.', 'gender' => 'P', 'jenjang' => 's2', 'univ' => 'University of Melbourne', 'prodi' => 'Information Systems', 'bidang' => 'UI/UX Design & Usability Testing'],
                ['nama' => 'Agus Kurniawan, M.T.', 'gender' => 'L', 'jenjang' => 's2', 'univ' => 'Institut Teknologi Bandung', 'prodi' => 'Sistem & Teknologi Informasi', 'bidang' => 'IT Project Management'],
                ['nama' => 'Dr. Diana Permata, M.Kom.', 'gender' => 'P', 'jenjang' => 's3', 'univ' => 'Universitas Padjadjaran', 'prodi' => 'Sistem Informasi Management', 'bidang' => 'E-Commerce & Supply Chain IT'],
            ],
            'DKV' => [
                ['nama' => 'Bambang Sudarsono, M.Sn.', 'gender' => 'L', 'jenjang' => 's2', 'univ' => 'Institut Teknologi Bandung', 'prodi' => 'Desain Komunikasi Visual', 'bidang' => 'Brand Identity & Visual Culture'],
                ['nama' => 'Nadia Utami, S.Ds., M.A.', 'gender' => 'P', 'jenjang' => 's2', 'univ' => 'Goldsmiths, University of London', 'prodi' => 'Design & Innovation', 'bidang' => 'Digital Illustration & Animation'],
                ['nama' => 'Faris Pratama, M.Ds.', 'gender' => 'L', 'jenjang' => 's2', 'univ' => 'Institut Seni Indonesia Yogyakarta', 'prodi' => 'Desain Media Digital', 'bidang' => 'Motion Graphic & Game Art'],
                ['nama' => 'Dr. Ratna Sari, M.Sn.', 'gender' => 'P', 'jenjang' => 's3', 'univ' => 'Institut Seni Indonesia Surakarta', 'prodi' => 'Seni & Desain Interaktif', 'bidang' => 'Semiotika Visual & Tipografi'],
                ['nama' => 'Doni Kusuma, S.Sn., M.Media.', 'gender' => 'L', 'jenjang' => 's2', 'univ' => 'RMIT University Australia', 'prodi' => 'Interactive Digital Media', 'bidang' => 'Virtual Reality & UI Motion'],
            ],
            'TE' => [
                ['nama' => 'Ir. Hendra Gunawan, M.T., Ph.D.', 'gender' => 'L', 'jenjang' => 's3', 'univ' => 'Tokyo Institute of Technology', 'prodi' => 'Electrical Engineering', 'bidang' => 'Renewable Energy & Microgrid'],
                ['nama' => 'Dr. Tri Wibowo, S.T., M.Eng.', 'gender' => 'L', 'jenjang' => 's3', 'univ' => 'Nanyang Technological University', 'prodi' => 'Power Systems', 'bidang' => 'Smart Grid & High Voltage'],
                ['nama' => 'Dewi Anggraini, M.T.', 'gender' => 'P', 'jenjang' => 's2', 'univ' => 'Institut Teknologi Sepuluh Nopember', 'prodi' => 'Teknik Elektro', 'bidang' => 'Signal Processing & Biomedical'],
                ['nama' => 'Lutfi Hakim, S.T., M.Sc.', 'gender' => 'L', 'jenjang' => 's2', 'univ' => 'TU Delft Netherlands', 'prodi' => 'Embedded Microcontrollers', 'bidang' => 'VLSI & Chip Design'],
                ['nama' => 'Siti Zulaikha, M.Eng.', 'gender' => 'P', 'jenjang' => 's2', 'univ' => 'Kyung Hee University South Korea', 'prodi' => 'Telecommunication Systems', 'bidang' => '5G/6G Wireless Networks'],
            ],
            'MI' => [
                ['nama' => 'Nurhasanah, S.Kom., M.Kom.', 'gender' => 'P', 'jenjang' => 's2', 'univ' => 'Universitas Diponegoro', 'prodi' => 'Manajemen Informatika', 'bidang' => 'Database Administration & Cloud SQL'],
                ['nama' => 'Rifan Syahputra, M.T.', 'gender' => 'L', 'jenjang' => 's2', 'univ' => 'Telkom University', 'prodi' => 'Informatika Vokasi', 'bidang' => 'Mobile App Development (Flutter/Android)'],
                ['nama' => 'Dr. Arif Budiman, M.Kom.', 'gender' => 'L', 'jenjang' => 's3', 'univ' => 'Universitas Indonesia', 'prodi' => 'Sistem Informasi Vokasi', 'bidang' => 'ERP & Accounting Information Systems'],
                ['nama' => 'Gita Savitri, S.ST., M.Tr.Kom.', 'gender' => 'P', 'jenjang' => 's2', 'univ' => 'Politeknik Negeri Elektronika Surabaya', 'prodi' => 'Teknologi Rekayasa Komputer', 'bidang' => 'Web Security & Microservices'],
                ['nama' => 'Haryo Damar, M.Kom.', 'gender' => 'L', 'jenjang' => 's2', 'univ' => 'Universitas Bina Nusantara', 'prodi' => 'Teknologi Informasi', 'bidang' => 'Cybersecurity Operations & Audit'],
            ],
            'D3SI' => [
                ['nama' => 'Fajar Nugraha, M.Kom.', 'gender' => 'L', 'jenjang' => 's2', 'univ' => 'Universitas Gadjah Mada', 'prodi' => 'Sistem Informasi D3/D4', 'bidang' => 'Fullstack Web Engineering'],
                ['nama' => 'Siska Amelia, S.Kom., M.T.', 'gender' => 'P', 'jenjang' => 's2', 'univ' => 'Institut Teknologi Bandung', 'prodi' => 'Informatika Terapan', 'bidang' => 'Software Testing & Quality Assurance'],
                ['nama' => 'Rian Hidayat, M.Kom.', 'gender' => 'L', 'jenjang' => 's2', 'univ' => 'Universitas Sebelas Maret', 'prodi' => 'Sistem Komputer Vokasi', 'bidang' => 'Network Administration & Linux Server'],
                ['nama' => 'Dr. Endang Lestari, M.T.', 'gender' => 'P', 'jenjang' => 's3', 'univ' => 'Universitas Brawijaya', 'prodi' => 'Teknologi Informasi', 'bidang' => 'Database & Data Warehouse'],
                ['nama' => 'Taufik Hidayatullah, M.Tr.T.', 'gender' => 'L', 'jenjang' => 's2', 'univ' => 'Politeknik Negeri Bandung', 'prodi' => 'Teknologi Rekayasa Informatika', 'bidang' => 'IoT Smart Building Solutions'],
            ]
        ];

        $counter = 100;
        foreach ($prodiDosenMap as $kodeProdi => $dosenArr) {
            $unitProdi = UnitKerja::where('kode', $kodeProdi)->first();
            if (!$unitProdi) continue;

            foreach ($dosenArr as $index => $data) {
                $counter++;
                $idxNum = $index + 1;
                $email = strtolower($kodeProdi) . '.dosen' . $idxNum . '@kampus.ac.id';
                $username = strtolower($kodeProdi) . '_dosen' . $idxNum;

                $user = User::updateOrCreate([
                    'email' => $email,
                ], [
                    'username' => $username,
                    'password' => Hash::make('password'),
                    'is_active' => true,
                    'is_verified' => true,
                ]);

                if ($dosenRole && !$user->roles()->where('role_id', $dosenRole->id)->exists()) {
                    $user->roles()->attach($dosenRole->id);
                }

                $nip = '19' . (80 + ($counter % 15)) . sprintf('%02d', ($counter % 12) + 1) . '1520101' . sprintf('%03d', $counter);
                $nik = '3271' . sprintf('%04d', $counter * 17) . '000' . $idxNum;

                $peg = Pegawai::updateOrCreate([
                    'user_id' => $user->id,
                ], [
                    'unit_kerja_id' => $unitProdi->id,
                    'nip' => $nip,
                    'nik' => $nik,
                    'nama_lengkap' => $data['nama'],
                    'tanggal_lahir' => '19' . (80 + ($counter % 15)) . '-05-15',
                    'tempat_lahir' => 'Bandung',
                    'jenis_kelamin' => $data['gender'],
                    'agama' => 'Islam',
                    'jenis_pegawai' => 'dosen',
                    'status_kepegawaian' => 'tetap_yayasan',
                    'tanggal_masuk' => '2018-09-01',
                    'status' => 'aktif',
                    'telepon' => '0812' . sprintf('%08d', $counter * 12345),
                    'alamat' => 'Jl. Akademik Kampus No. ' . $idxNum . ', Bandung',
                ]);

                $jafung = ($data['jenjang'] === 's3') ? $jafungKepala : ($idxNum % 2 === 0 ? $jafungLektor : $jafungAsisten);

                RiwayatJabatan::updateOrCreate([
                    'pegawai_id' => $peg->id,
                ], [
                    'jabatan_id' => $jabatanDosen?->id,
                    'jabatan_fungsional_id' => $jafung?->id,
                    'mulai_jabatan' => '2020-01-01',
                    'sk_nomor' => 'SK/SK-DOSEN/2020/' . sprintf('%03d', $counter),
                    'sk_tanggal' => '2020-01-01',
                    'is_active' => true,
                ]);

                RiwayatPendidikanPegawai::updateOrCreate([
                    'pegawai_id' => $peg->id,
                    'jenjang' => $data['jenjang'],
                ], [
                    'nama_institusi' => $data['univ'],
                    'program_studi' => $data['prodi'],
                    'bidang_ilmu' => $data['bidang'],
                    'tahun_masuk' => 2014,
                    'tahun_lulus' => ($data['jenjang'] === 's3') ? 2020 : 2017,
                    'nomor_ijazah' => 'IJZ-' . sprintf('%04d', $counter) . '-' . strtoupper($data['jenjang']),
                    'is_pendidikan_terakhir' => true,
                ]);
            }
        }
    }
}
