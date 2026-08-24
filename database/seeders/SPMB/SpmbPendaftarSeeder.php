<?php

namespace Database\Seeders\SPMB;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Role;
use App\Models\Spmb\JalurMasuk;
use App\Models\Spmb\GelombangPenerimaan;
use App\Models\Spmb\MasterProgramStudi;
use App\Models\Spmb\PendaftaranCalonMhs;
use App\Models\Spmb\DokumenPendaftaran;
use App\Models\Spmb\PembayaranSpmb;
use App\Models\Spmb\HasilSeleksi;
use App\Models\Spmb\SpmbStatusHistory;

class SpmbPendaftarSeeder extends Seeder
{
    public function run(): void
    {
        $tipeReguler = DB::table('core_master_tipe_jalur')->where('kode', 'reguler')->first();
        $tipeBeasiswa = DB::table('core_master_tipe_jalur')->where('kode', 'beasiswa')->first();

        // 1. Data Master Jalur Masuk
        $jalurReguler = JalurMasuk::updateOrCreate(
            ['kode' => 'JALUR-REGULER'],
            [
                'nama' => 'Jalur Reguler Rapor',
                'deskripsi' => 'Penerimaan berdasarkan nilai rata-rata rapor SMA/SMK/MA semester 1-5',
                'master_tipe_jalur_id' => $tipeReguler ? $tipeReguler->id : 1,
                'ada_ujian_tulis' => false,
                'ada_ujian_praktik' => false,
                'ada_wawancara' => false,
                'is_active' => true,
            ]
        );

        $jalurPrestasi = JalurMasuk::updateOrCreate(
            ['kode' => 'JALUR-PRESTASI'],
            [
                'nama' => 'Jalur Prestasi Akademik & Non-Akademik',
                'deskripsi' => 'Penerimaan berdasarkan sertifikat kejuaraan dan capaian akademik',
                'master_tipe_jalur_id' => $tipeBeasiswa ? $tipeBeasiswa->id : 3,
                'ada_ujian_tulis' => false,
                'ada_ujian_praktik' => false,
                'ada_wawancara' => true,
                'is_active' => true,
            ]
        );

        $jalurCbt = JalurMasuk::updateOrCreate(
            ['kode' => 'JALUR-CBT'],
            [
                'nama' => 'Jalur Ujian Masuk (CBT)',
                'deskripsi' => 'Penerimaan melalui ujian Computer Based Test (CBT) secara online/offline',
                'master_tipe_jalur_id' => $tipeReguler ? $tipeReguler->id : 1,
                'ada_ujian_tulis' => true,
                'ada_ujian_praktik' => false,
                'ada_wawancara' => false,
                'is_active' => true,
            ]
        );

        // 2. Data Master Program Studi
        $prodiTI = MasterProgramStudi::updateOrCreate(
            ['kode_prodi' => '55201'],
            ['nama' => 'S1 Teknik Informatika', 'jenjang' => 'S1', 'is_active' => true]
        );

        $prodiSI = MasterProgramStudi::updateOrCreate(
            ['kode_prodi' => '57201'],
            ['nama' => 'S1 Sistem Informasi', 'jenjang' => 'S1', 'is_active' => true]
        );

        $prodiManajemen = MasterProgramStudi::updateOrCreate(
            ['kode_prodi' => '61201'],
            ['nama' => 'S1 Manajemen', 'jenjang' => 'S1', 'is_active' => true]
        );

        $prodiAkuntansi = MasterProgramStudi::updateOrCreate(
            ['kode_prodi' => '62201'],
            ['nama' => 'S1 Akuntansi', 'jenjang' => 'S1', 'is_active' => true]
        );

        $prodiHukum = MasterProgramStudi::updateOrCreate(
            ['kode_prodi' => '74201'],
            ['nama' => 'S1 Ilmu Hukum', 'jenjang' => 'S1', 'is_active' => true]
        );

        // 2b. Data Master Tahun Akademik
        $ta2026 = DB::table('spmb_master_tahun_akademik')->where('kode', '20261')->first();
        if (!$ta2026) {
            $taId = DB::table('spmb_master_tahun_akademik')->insertGetId([
                'kode' => '20261',
                'nama' => 'Tahun Akademik 2026/2027 Ganjil',
                'tahun_mulai' => 2026,
                'tahun_selesai' => 2027,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $taId = $ta2026->id;
        }

        // 3. Data Gelombang Penerimaan
        $gelombang1 = GelombangPenerimaan::updateOrCreate(
            ['nama' => 'Gelombang 1 (TA 2026/2027)'],
            [
                'jalur_masuk_id' => $jalurReguler->id,
                'tahun_akademik_id' => $taId,
                'tanggal_buka' => '2026-01-02',
                'tanggal_tutup' => '2026-05-31',
                'tanggal_ujian' => '2026-06-05',
                'tanggal_pengumuman' => '2026-06-12',
                'kuota_total' => 300,
                'kuota_terisi' => 5,
                'biaya_pendaftaran' => 350000.00,
                'status' => 'buka',
            ]
        );

        // 4. Peran Calon Mahasiswa & Verifikator
        $roleCalonMhs = Role::where('slug', 'calon_mhs')->first();
        $adminUser = User::where('username', 'adminspmb')->orWhere('username', 'superadmin')->first();

        // 5. Array Data 5 Pendaftar Lengkap
        $pendaftarList = [
            [
                'email' => 'ahmad.fadhil@gmail.com',
                'username' => 'ahmad.fadhil',
                'no_pendaftaran' => 'SPMB20260001',
                'nim' => '2026101001',
                'nama_lengkap' => 'Ahmad Fadhil Prasetya',
                'nik' => '3174011205040001',
                'tanggal_lahir' => '2004-05-12',
                'tempat_lahir' => 'Jakarta',
                'jenis_kelamin' => 'L',
                'no_hp' => '081234567801',
                'alamat' => 'Jl. Sudirman No. 45, Kebayoran Baru, Jakarta Selatan (Kode Pos: 12190)',
                'asal_sekolah' => 'SMAN 1 Jakarta',
                'jurusan_sekolah' => 'MIPA',
                'nilai_rata_rapor' => 88.50,
                'tahun_lulus' => '2024',
                'nama_wali' => 'Bambang Prasetya (PNS)',
                'telepon_wali' => '081234567801',
                'gelombang_id' => $gelombang1->id,
                'program_studi_id' => $prodiTI->id,
                'program_studi_pilihan2_id' => $prodiSI->id,
                'status' => PendaftaranCalonMhs::STATUS_MAHASISWA_BARU,
                'status_pembayaran' => PendaftaranCalonMhs::STATUS_PEMBAYARAN_LUNAS,
                'catatan_verifikasi' => 'Seluruh berkas pendaftaran dan persyaratan lulus administrasi serta telah melakukan pendaftaran ulang.',
                'hasil_seleksi' => [
                    'diterima_prodi_id' => $prodiTI->id,
                    'nilai_total' => 88.50,
                    'peringkat' => 1,
                    'status' => 'lulus',
                    'catatan' => 'Selamat, Anda dinyatakan LULUS di pilihan 1 (S1 Teknik Informatika).',
                ],
                'dokumen' => [
                    ['jenis' => 'ktp', 'file' => 'spmb/dokumen/3174011205040001_ktp.pdf', 'verified' => true, 'catatan' => 'Valid'],
                    ['jenis' => 'ijazah', 'file' => 'spmb/dokumen/3174011205040001_ijazah.pdf', 'verified' => true, 'catatan' => 'Valid'],
                    ['jenis' => 'rapor', 'file' => 'spmb/dokumen/3174011205040001_rapor.pdf', 'verified' => true, 'catatan' => 'Sesuai transkrip sekolah'],
                    ['jenis' => 'kk', 'file' => 'spmb/dokumen/3174011205040001_kk.pdf', 'verified' => true, 'catatan' => 'Valid'],
                    ['jenis' => 'foto', 'file' => 'spmb/dokumen/3174011205040001_pasfoto.jpg', 'verified' => true, 'catatan' => 'Latar merah, pasfoto resmi'],
                ]
            ],
            [
                'email' => 'siti.nurhaliza@gmail.com',
                'username' => 'siti.nurhaliza',
                'no_pendaftaran' => 'SPMB20260002',
                'nim' => null,
                'nama_lengkap' => 'Siti Nurhaliza Putri',
                'nik' => '3273022508040002',
                'tanggal_lahir' => '2004-08-25',
                'tempat_lahir' => 'Bandung',
                'jenis_kelamin' => 'P',
                'no_hp' => '081234567802',
                'alamat' => 'Jl. Merdeka No. 12, Bandung Wetan, Kota Bandung (Kode Pos: 40115)',
                'asal_sekolah' => 'SMAN 3 Bandung',
                'jurusan_sekolah' => 'IPS',
                'nilai_rata_rapor' => 89.20,
                'tahun_lulus' => '2024',
                'nama_wali' => 'Heri Putra (Wiraswasta)',
                'telepon_wali' => '081234567802',
                'gelombang_id' => $gelombang1->id,
                'program_studi_id' => $prodiManajemen->id,
                'program_studi_pilihan2_id' => $prodiAkuntansi->id,
                'status' => PendaftaranCalonMhs::STATUS_LULUS_ADMINISTRASI,
                'status_pembayaran' => PendaftaranCalonMhs::STATUS_PEMBAYARAN_LUNAS,
                'catatan_verifikasi' => 'Berkas pendaftaran dinyatakan lengkap dan valid. Menunggu proses seleksi kelulusan akhir.',
                'hasil_seleksi' => [
                    'diterima_prodi_id' => $prodiManajemen->id,
                    'nilai_total' => 89.20,
                    'peringkat' => 2,
                    'status' => 'lulus',
                    'catatan' => 'Dinyatakan LULUS di pilihan 1 (S1 Manajemen). Silakan melanjutkan pendaftaran ulang.',
                ],
                'dokumen' => [
                    ['jenis' => 'ktp', 'file' => 'spmb/dokumen/3273022508040002_ktp.pdf', 'verified' => true, 'catatan' => 'Valid'],
                    ['jenis' => 'ijazah', 'file' => 'spmb/dokumen/3273022508040002_ijazah.pdf', 'verified' => true, 'catatan' => 'Valid'],
                    ['jenis' => 'rapor', 'file' => 'spmb/dokumen/3273022508040002_rapor.pdf', 'verified' => true, 'catatan' => 'Valid'],
                    ['jenis' => 'sertifikat', 'file' => 'spmb/dokumen/3273022508040002_sertifikat.pdf', 'verified' => true, 'catatan' => 'Juara 1 Olimpiade Ekonomi Provinsi'],
                    ['jenis' => 'foto', 'file' => 'spmb/dokumen/3273022508040002_pasfoto.jpg', 'verified' => true, 'catatan' => 'Sesuai ketentuan'],
                ]
            ],
            [
                'email' => 'budi.santoso@gmail.com',
                'username' => 'budi.santoso',
                'no_pendaftaran' => 'SPMB20260003',
                'nim' => null,
                'nama_lengkap' => 'Budi Santoso',
                'nik' => '3578031002040003',
                'tanggal_lahir' => '2004-02-10',
                'tempat_lahir' => 'Surabaya',
                'jenis_kelamin' => 'L',
                'no_hp' => '081234567803',
                'alamat' => 'Jl. Pemuda No. 88, Genteng, Kota Surabaya (Kode Pos: 60271)',
                'asal_sekolah' => 'SMKN 1 Surabaya',
                'jurusan_sekolah' => 'Rekayasa Perangkat Lunak',
                'nilai_rata_rapor' => 85.75,
                'tahun_lulus' => '2024',
                'nama_wali' => 'Slamet Santoso (Karyawan Swasta)',
                'telepon_wali' => '081234567803',
                'gelombang_id' => $gelombang1->id,
                'program_studi_id' => $prodiSI->id,
                'program_studi_pilihan2_id' => $prodiTI->id,
                'status' => PendaftaranCalonMhs::STATUS_VERIFIED,
                'status_pembayaran' => PendaftaranCalonMhs::STATUS_PEMBAYARAN_LUNAS,
                'catatan_verifikasi' => 'Berkas berhasil diverifikasi oleh panitia SPMB. Siap mengikuti tahap evaluasi nilai.',
                'hasil_seleksi' => null,
                'dokumen' => [
                    ['jenis' => 'ktp', 'file' => 'spmb/dokumen/3578031002040003_ktp.pdf', 'verified' => true, 'catatan' => 'Valid'],
                    ['jenis' => 'ijazah', 'file' => 'spmb/dokumen/3578031002040003_ijazah.pdf', 'verified' => true, 'catatan' => 'Valid'],
                    ['jenis' => 'rapor', 'file' => 'spmb/dokumen/3578031002040003_rapor.pdf', 'verified' => true, 'catatan' => 'Valid'],
                    ['jenis' => 'foto', 'file' => 'spmb/dokumen/3578031002040003_pasfoto.jpg', 'verified' => true, 'catatan' => 'Valid'],
                ]
            ],
            [
                'email' => 'clara.anindya@gmail.com',
                'username' => 'clara.anindya',
                'no_pendaftaran' => 'SPMB20260004',
                'nim' => null,
                'nama_lengkap' => 'Clara Anindya Kusuma',
                'nik' => '3374041811040004',
                'tanggal_lahir' => '2004-11-18',
                'tempat_lahir' => 'Semarang',
                'jenis_kelamin' => 'P',
                'no_hp' => '081234567804',
                'alamat' => 'Jl. Pandanaran No. 20, Semarang Selatan, Kota Semarang (Kode Pos: 50249)',
                'asal_sekolah' => 'SMA Loyola Semarang',
                'jurusan_sekolah' => 'MIPA',
                'nilai_rata_rapor' => 91.00,
                'tahun_lulus' => '2024',
                'nama_wali' => 'Rudi Kusuma (Dokter)',
                'telepon_wali' => '081234567804',
                'gelombang_id' => $gelombang1->id,
                'program_studi_id' => $prodiHukum->id,
                'program_studi_pilihan2_id' => $prodiManajemen->id,
                'status' => PendaftaranCalonMhs::STATUS_SUBMITTED,
                'status_pembayaran' => PendaftaranCalonMhs::STATUS_PEMBAYARAN_LUNAS,
                'catatan_verifikasi' => 'Formulir dan pembayaran telah dikirimkan pendaftar, dalam antrean verifikasi berkas.',
                'hasil_seleksi' => null,
                'dokumen' => [
                    ['jenis' => 'ktp', 'file' => 'spmb/dokumen/3374041811040004_ktp.pdf', 'verified' => false, 'catatan' => 'Belum diverifikasi'],
                    ['jenis' => 'ijazah', 'file' => 'spmb/dokumen/3374041811040004_ijazah.pdf', 'verified' => false, 'catatan' => 'Belum diverifikasi'],
                    ['jenis' => 'rapor', 'file' => 'spmb/dokumen/3374041811040004_rapor.pdf', 'verified' => false, 'catatan' => 'Belum diverifikasi'],
                    ['jenis' => 'foto', 'file' => 'spmb/dokumen/3374041811040004_pasfoto.jpg', 'verified' => false, 'catatan' => 'Belum diverifikasi'],
                ]
            ],
            [
                'email' => 'daffa.rizky@gmail.com',
                'username' => 'daffa.rizky',
                'no_pendaftaran' => 'SPMB20260005',
                'nim' => null,
                'nama_lengkap' => 'Daffa Rizky Pratama',
                'nik' => '3471050506040005',
                'tanggal_lahir' => '2004-06-05',
                'tempat_lahir' => 'Yogyakarta',
                'jenis_kelamin' => 'L',
                'no_hp' => '081234567805',
                'alamat' => 'Jl. Malioboro No. 101, Gedongtengen, Kota Yogyakarta (Kode Pos: 55271)',
                'asal_sekolah' => 'SMAN 1 Yogyakarta',
                'jurusan_sekolah' => 'IPS',
                'nilai_rata_rapor' => 84.00,
                'tahun_lulus' => '2024',
                'nama_wali' => 'Agus Pratama (TNI/Polri)',
                'telepon_wali' => '081234567805',
                'gelombang_id' => $gelombang1->id,
                'program_studi_id' => $prodiAkuntansi->id,
                'program_studi_pilihan2_id' => $prodiManajemen->id,
                'status' => PendaftaranCalonMhs::STATUS_DRAFT,
                'status_pembayaran' => PendaftaranCalonMhs::STATUS_PEMBAYARAN_BELUM,
                'catatan_verifikasi' => null,
                'hasil_seleksi' => null,
                'dokumen' => [
                    ['jenis' => 'foto', 'file' => 'spmb/dokumen/3471050506040005_pasfoto.jpg', 'verified' => false, 'catatan' => 'Draft foto'],
                ]
            ],
        ];

        // 6. Loop & Insert / Update
        foreach ($pendaftarList as $data) {
            // User calon mhs
            $user = User::withTrashed()->updateOrCreate(
                ['email' => $data['email']],
                [
                    'username' => $data['username'],
                    'password' => Hash::make('password123'),
                    'is_active' => true,
                    'is_verified' => true,
                ]
            );

            if ($user->trashed()) {
                $user->restore();
            }

            if ($roleCalonMhs) {
                DB::table('core_user_roles')->updateOrInsert(
                    ['user_id' => $user->id, 'role_id' => $roleCalonMhs->id],
                    ['assigned_by' => $adminUser ? $adminUser->id : $user->id, 'valid_from' => now()->toDateString(), 'created_at' => now()]
                );
            }

            // Pendaftaran Calon Mhs
            $pendaftaran = PendaftaranCalonMhs::withTrashed()->updateOrCreate(
                ['no_pendaftaran' => $data['no_pendaftaran']],
                [
                    'gelombang_id' => $data['gelombang_id'],
                    'user_id' => $user->id,
                    'program_studi_id' => $data['program_studi_id'],
                    'program_studi_pilihan2_id' => $data['program_studi_pilihan2_id'],
                    'nim' => $data['nim'],
                    'nama_lengkap' => $data['nama_lengkap'],
                    'nik' => $data['nik'],
                    'tanggal_lahir' => $data['tanggal_lahir'],
                    'tempat_lahir' => $data['tempat_lahir'],
                    'jenis_kelamin' => $data['jenis_kelamin'],
                    'alamat' => $data['alamat'],
                    'asal_sekolah' => $data['asal_sekolah'],
                    'jurusan_sekolah' => $data['jurusan_sekolah'],
                    'nilai_rata_rapor' => $data['nilai_rata_rapor'],
                    'tahun_lulus' => $data['tahun_lulus'],
                    'nama_wali' => $data['nama_wali'],
                    'telepon_wali' => $data['telepon_wali'],
                    'status' => $data['status'],
                    'status_pembayaran' => $data['status_pembayaran'],
                    'catatan_verifikasi' => $data['catatan_verifikasi'],
                    'diverifikasi_oleh' => ($data['status'] !== PendaftaranCalonMhs::STATUS_DRAFT && $adminUser) ? $adminUser->id : null,
                    'diverifikasi_at' => ($data['status'] !== PendaftaranCalonMhs::STATUS_DRAFT) ? now()->subDays(2) : null,
                ]
            );

            if ($pendaftaran->trashed()) {
                $pendaftaran->restore();
            }

            // Dokumen Pendaftaran
            foreach ($data['dokumen'] as $doc) {
                DokumenPendaftaran::updateOrCreate(
                    [
                        'pendaftaran_id' => $pendaftaran->id,
                        'jenis_dokumen' => $doc['jenis'],
                    ],
                    [
                        'file_path' => $doc['file'],
                        'is_verified' => $doc['verified'],
                        'catatan' => $doc['catatan'],
                    ]
                );
            }

            // Pembayaran SPMB
            if ($data['status_pembayaran'] === PendaftaranCalonMhs::STATUS_PEMBAYARAN_LUNAS) {
                PembayaranSpmb::updateOrCreate(
                    ['pendaftaran_id' => $pendaftaran->id],
                    [
                        'kode_bayar' => 'PAY-' . $data['no_pendaftaran'],
                        'jumlah_tagihan' => 350000.00,
                        'jumlah_bayar' => 350000.00,
                        'status' => 'lunas',
                        'metode_bayar' => 'Virtual Account Bank Mandiri',
                        'va_number' => '8801' . $data['nik'],
                        'paid_at' => now()->subDays(3),
                        'expired_at' => now()->addDays(2),
                        'gateway_response' => ['status' => 'SUCCESS', 'reference' => 'REF-' . rand(10000, 99999)],
                    ]
                );
            } else {
                PembayaranSpmb::updateOrCreate(
                    ['pendaftaran_id' => $pendaftaran->id],
                    [
                        'kode_bayar' => 'PAY-' . $data['no_pendaftaran'],
                        'jumlah_tagihan' => 350000.00,
                        'jumlah_bayar' => 0.00,
                        'status' => 'pending',
                        'metode_bayar' => 'Virtual Account Bank Mandiri',
                        'va_number' => '8801' . $data['nik'],
                        'paid_at' => null,
                        'expired_at' => now()->addDays(3),
                        'gateway_response' => ['status' => 'PENDING'],
                    ]
                );
            }

            // Hasil Seleksi
            if (!empty($data['hasil_seleksi'])) {
                HasilSeleksi::updateOrCreate(
                    ['pendaftaran_id' => $pendaftaran->id],
                    [
                        'program_studi_diterima_id' => $data['hasil_seleksi']['diterima_prodi_id'],
                        'nilai_total' => $data['hasil_seleksi']['nilai_total'],
                        'peringkat' => $data['hasil_seleksi']['peringkat'],
                        'status' => $data['hasil_seleksi']['status'],
                        'catatan' => $data['hasil_seleksi']['catatan'],
                        'diumumkan_at' => now()->subDay(),
                    ]
                );
            }

            // Status History Log
            SpmbStatusHistory::updateOrCreate(
                [
                    'pendaftaran_id' => $pendaftaran->id,
                    'status_baru' => $data['status'],
                ],
                [
                    'status_lama' => PendaftaranCalonMhs::STATUS_DRAFT,
                    'actor_id' => $adminUser ? $adminUser->id : $user->id,
                    'catatan' => 'Perubahan status pendaftaran menjadi ' . $data['status'],
                ]
            );
        }
    }
}
