<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Spmb\TemplateSuratSpmb;

class SpmbTemplateSuratSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        TemplateSuratSpmb::updateOrCreate(
            ['kode' => 'SK_LULUS_DEFAULT'],
            [
                'nama' => 'Template Standar SK Kelulusan SPMB',
                'jenis_surat' => 'sk_lulus',
                'jalur_masuk_id' => null,
                'gelombang_id' => null,
                'is_active' => true,
                'kop_nama_institusi' => config('app.institution_name', 'UNIVERSITAS INDONUSA'),
                'kop_nama_sub' => 'PANITIA PENERIMAAN MAHASISWA BARU (SPMB)',
                'kop_alamat_kontak' => "Sekretariat SPMB Kampus Terpadu • Email: spmb@kampus.ac.id • Website: spmb.kampus.ac.id\nTahun Akademik {tahun_akademik}",
                'format_nomor_surat' => 'SKL/SPMB/{tahun}/{romawi_bulan}/{no_pendaftaran}',
                'judul_surat' => 'SURAT KETERANGAN TANDA LULUS SELEKSI',
                'teks_pembuka' => 'Berdasarkan hasil evaluasi verifikasi kelengkapan berkas administrasi dan pemenuhan syarat seleksi penerimaan mahasiswa baru Tahun Akademik {tahun_akademik}, Panitia Penerimaan Mahasiswa Baru menyatakan bahwa:',
                'teks_keputusan' => 'DINYATAKAN LULUS / DITERIMA',
                'petunjuk_daftar_ulang' => "1. Calon mahasiswa yang dinyatakan lulus wajib melakukan Daftar Ulang melalui portal resmi SPMB pada menu Daftar Ulang.\n2. Selesaikan pembayaran biaya registrasi/UKT menggunakan nomor Virtual Account resmi yang tertera pada invoice tagihan Anda sebelum batas waktu yang ditentukan.\n3. Setelah pembayaran daftar ulang terkonfirmasi lunas, sistem akan menerbitkan Nomor Induk Mahasiswa (NIM) resmi dan akun akademik mahasiswa baru.\n4. Surat keterangan ini sah dan dihasilkan secara otomatis oleh Sistem Informasi Penerimaan Mahasiswa Baru terintegrasi.",
                'kota_penetapan' => 'Surakarta',
                'nama_penandatangan' => 'Panitia Seleksi SPMB',
                'jabatan_penandatangan' => 'Ketua Panitia SPMB / Direktur Admisi',
                'nip_penandatangan' => null,
                'catatan_kaki' => 'Dokumen ini merupakan bukti kelulusan seleksi SPMB yang sah. Keabsahan dokumen dapat diverifikasi langsung melalui database induk kampus terintegrasi.',
            ]
        );
    }
}
