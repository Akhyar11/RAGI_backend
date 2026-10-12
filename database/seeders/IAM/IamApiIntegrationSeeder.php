<?php

namespace Database\Seeders\IAM;

use App\Models\IamApiIntegration;
use Illuminate\Database\Seeder;

class IamApiIntegrationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $integrations = [
            [
                'key' => 'spmb_pendaftaran',
                'name' => 'SPMB Pendaftaran',
                'module' => 'spmb',
                'http_method' => 'GET',
                'description' => 'Endpoint API migrasi data calon mahasiswa baru / pendaftaran dari sistem lama',
                'expected_schema' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            // ── Identitas Pendaftaran & Akun ──
                            'no_pendaftaran' => ['type' => 'string', 'description' => 'Nomor unik pendaftaran pendaftar'],
                            'email' => ['type' => 'string', 'description' => 'Email aktif pendaftar'],
                            'no_hp' => ['type' => 'string', 'description' => 'Nomor WhatsApp / HP aktif'],
                            'used_referral_code' => ['type' => 'string', 'description' => 'Kode referral yang digunakan (opsional)'],

                            // ── Pilihan Akademik & Jalur ──
                            'jalur_pendaftaran' => ['type' => 'string', 'description' => 'Kode atau nama jalur masuk / tipe jalur'],
                            'gelombang' => ['type' => 'string', 'description' => 'Kode atau nama gelombang penerimaan'],
                            'kelas' => ['type' => 'string', 'description' => 'Jenis kelas (misal: Reguler, Karyawan)'],
                            'pilihan_prodi_1' => ['type' => 'string', 'description' => 'Kode atau nama program studi pilihan 1'],
                            'pilihan_prodi_2' => ['type' => 'string', 'description' => 'Kode atau nama program studi pilihan 2 (opsional)'],

                            // ── Biodata Pribadi ──
                            'nama_lengkap' => ['type' => 'string', 'description' => 'Nama lengkap calon mahasiswa sesuai KTP/Ijazah'],
                            'nik' => ['type' => 'string', 'description' => 'Nomor Induk Kependudukan (16 digit)'],
                            'tempat_lahir' => ['type' => 'string', 'description' => 'Kota / tempat lahir'],
                            'tanggal_lahir' => ['type' => 'string', 'description' => 'Tanggal lahir format YYYY-MM-DD'],
                            'jenis_kelamin' => ['type' => 'string', 'description' => 'Jenis kelamin: L (Laki-laki) atau P (Perempuan)'],
                            'agama' => ['type' => 'string', 'description' => 'Agama pendaftar'],
                            'status_sipil' => ['type' => 'string', 'description' => 'Status pernikahan (belum_menikah, menikah, dll)'],
                            'kewarganegaraan' => ['type' => 'string', 'description' => 'Kewarganegaraan (WNI/WNA)'],

                            // ── Alamat & Domisili ──
                            'alamat' => ['type' => 'string', 'description' => 'Alamat domisili lengkap'],
                            'provinsi' => ['type' => 'string', 'description' => 'Nama / ID provinsi domisili'],
                            'kota_kabupaten' => ['type' => 'string', 'description' => 'Nama / ID kota atau kabupaten'],
                            'kecamatan' => ['type' => 'string', 'description' => 'Nama / ID kecamatan'],
                            'kode_pos' => ['type' => 'string', 'description' => 'Kode pos domisili'],

                            // ── Riwayat Pendidikan Asal ──
                            'asal_sekolah' => ['type' => 'string', 'description' => 'Nama SMA / SMK / MA / Perguruan Tinggi asal'],
                            'npsn_sekolah' => ['type' => 'string', 'description' => 'NPSN sekolah asal (opsional)'],
                            'jurusan_sekolah' => ['type' => 'string', 'description' => 'Jurusan sekolah (misal: IPA, IPS, RPL)'],
                            'tahun_lulus' => ['type' => 'string', 'description' => 'Tahun kelulusan sekolah asal'],
                            'nilai_rata_rapor' => ['type' => 'number', 'description' => 'Nilai rata-rata rapor atau ijazah'],

                            // ── Data Orang Tua / Wali ──
                            'nama_ayah' => ['type' => 'string', 'description' => 'Nama lengkap ayah kandung'],
                            'pekerjaan_ayah' => ['type' => 'string', 'description' => 'Pekerjaan ayah'],
                            'nama_ibu' => ['type' => 'string', 'description' => 'Nama lengkap ibu kandung'],
                            'pekerjaan_ibu' => ['type' => 'string', 'description' => 'Pekerjaan ibu'],
                            'penghasilan_ortu' => ['type' => 'string', 'description' => 'Rentang penghasilan orang tua'],
                            'telp_ortu' => ['type' => 'string', 'description' => 'Nomor telepon kontak orang tua'],
                            'nama_wali' => ['type' => 'string', 'description' => 'Nama wali (opsional jika ada)'],
                            'telepon_wali' => ['type' => 'string', 'description' => 'Nomor telepon wali (opsional)'],

                            // ── Status Alur Pendaftaran ──
                            'status' => ['type' => 'string', 'description' => 'Status administrasi: draft, submitted, verified, lulus_administrasi, gagal_administrasi, mahasiswa_baru'],
                            'status_pembayaran' => ['type' => 'string', 'description' => 'Status pembayaran biaya formulir: belum_bayar, sebagian, lunas, gratis'],
                            'catatan_verifikasi' => ['type' => 'string', 'description' => 'Catatan verifikator atas berkas calon mahasiswa'],
                        ],
                        'required' => [
                            'no_pendaftaran',
                            'nama_lengkap',
                            'nik',
                            'email',
                            'no_hp',
                            'tempat_lahir',
                            'tanggal_lahir',
                            'jenis_kelamin',
                            'pilihan_prodi_1',
                        ],
                    ],
                ],
                'auth_type' => 'bearer',
                'is_active' => true,
            ],
        ];

        foreach ($integrations as $item) {
            IamApiIntegration::updateOrCreate(
                ['key' => $item['key']],
                [
                    'name' => $item['name'],
                    'module' => $item['module'],
                    'http_method' => $item['http_method'],
                    'description' => $item['description'],
                    'expected_schema' => $item['expected_schema'],
                    'auth_type' => $item['auth_type'],
                    'is_active' => $item['is_active'],
                ]
            );
        }
    }
}
