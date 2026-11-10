<?php

namespace Database\Seeders\Sikeu;

use App\Models\Sikeu\AkunKeuangan;
use Illuminate\Database\Seeder;

/**
 * Bagan Akun Standar (COA) Lengkap SIKEU Kampus
 * Diselaraskan dengan audit data operasional & legacy (sikeudb & indonusa).
 *
 * Idempoten: aman dijalankan berulang kali tanpa merusak ID yang sudah ada.
 */
class SikeuAkuntansiSeeder extends Seeder
{
    public function run(): void
    {
        $akun = [
            // ==========================================
            // KELOMPOK 1: ASET (Saldo Normal: Debet)
            // ==========================================
            // 101 - Kas Tunai
            ['kode_akun' => '101.01', 'nama_akun' => 'Kas Tunai Kampus', 'kelompok' => 'aset', 'saldo_normal' => 'debet'],
            ['kode_akun' => '101.02', 'nama_akun' => 'Kas Unit Fakultas / Petty Cash', 'kelompok' => 'aset', 'saldo_normal' => 'debet'],

            // 102 - Kas Bank
            ['kode_akun' => '102.01', 'nama_akun' => 'Bank Operasional Utama', 'kelompok' => 'aset', 'saldo_normal' => 'debet'],
            ['kode_akun' => '102.02', 'nama_akun' => 'Bank Penerimaan UKT Mandiri', 'kelompok' => 'aset', 'saldo_normal' => 'debet'],
            ['kode_akun' => '102.03', 'nama_akun' => 'Bank BSN Manual Kampus (BTN Syariah)', 'kelompok' => 'aset', 'saldo_normal' => 'debet'],
            ['kode_akun' => '102.04', 'nama_akun' => 'Bank BSN Host-to-Host Kampus', 'kelompok' => 'aset', 'saldo_normal' => 'debet'],
            ['kode_akun' => '102.05', 'nama_akun' => 'Xendit Penampungan Disbursement', 'kelompok' => 'aset', 'saldo_normal' => 'debet'],
            ['kode_akun' => '102.06', 'nama_akun' => 'Bank BNI Operasional', 'kelompok' => 'aset', 'saldo_normal' => 'debet'],

            // 103 - Piutang
            ['kode_akun' => '103.01', 'nama_akun' => 'Piutang UKT / Mahasiswa', 'kelompok' => 'aset', 'saldo_normal' => 'debet'],
            ['kode_akun' => '103.02', 'nama_akun' => 'Piutang Dana Hibah Riset (SIPPM)', 'kelompok' => 'aset', 'saldo_normal' => 'debet'],
            ['kode_akun' => '103.03', 'nama_akun' => 'Piutang Karyawan / Kasbon', 'kelompok' => 'aset', 'saldo_normal' => 'debet'],

            // 150 & 105 - Aset Tetap & Akumulasi Penyusutan
            ['kode_akun' => '150.01', 'nama_akun' => 'Aset Tetap Gedung & Fasilitas Kampus', 'kelompok' => 'aset', 'saldo_normal' => 'debet'],
            ['kode_akun' => '150.02', 'nama_akun' => 'Aset Tetap Peralatan Laboratorium & TI', 'kelompok' => 'aset', 'saldo_normal' => 'debet'],
            ['kode_akun' => '150.03', 'nama_akun' => 'Aset Tetap Kendaraan Operasional', 'kelompok' => 'aset', 'saldo_normal' => 'debet'],
            ['kode_akun' => '105.01', 'nama_akun' => 'Akumulasi Penyusutan Aset Tetap', 'kelompok' => 'aset', 'saldo_normal' => 'kredit'],

            // ==========================================
            // KELOMPOK 2: LIABILITAS (Saldo Normal: Kredit)
            // ==========================================
            ['kode_akun' => '201.01', 'nama_akun' => 'Utang Potongan & Titipan Karyawan', 'kelompok' => 'liabilitas', 'saldo_normal' => 'kredit'],
            ['kode_akun' => '202.01', 'nama_akun' => 'Utang PPh Pasal 21', 'kelompok' => 'liabilitas', 'saldo_normal' => 'kredit'],
            ['kode_akun' => '202.02', 'nama_akun' => 'Utang Pajak PPh 23 Jasa Vendor', 'kelompok' => 'liabilitas', 'saldo_normal' => 'kredit'],
            ['kode_akun' => '202.03', 'nama_akun' => 'Utang Pajak PPN 11%', 'kelompok' => 'liabilitas', 'saldo_normal' => 'kredit'],
            ['kode_akun' => '203.01', 'nama_akun' => 'Titipan Beasiswa Mahasiswa', 'kelompok' => 'liabilitas', 'saldo_normal' => 'kredit'],
            ['kode_akun' => '204.01', 'nama_akun' => 'Pendapatan Diterima Dimuka (Uang Muka Kuliah)', 'kelompok' => 'liabilitas', 'saldo_normal' => 'kredit'],

            // ==========================================
            // KELOMPOK 3: EKUITAS (Saldo Normal: Kredit)
            // ==========================================
            ['kode_akun' => '301.01', 'nama_akun' => 'Ekuitas Dana Terikat Institusi', 'kelompok' => 'ekuitas', 'saldo_normal' => 'kredit'],
            ['kode_akun' => '301.02', 'nama_akun' => 'Saldo Laba / Defisit Ditahan (Surplus)', 'kelompok' => 'ekuitas', 'saldo_normal' => 'kredit'],
            ['kode_akun' => '301.03', 'nama_akun' => 'Ekuitas Saldo Awal', 'kelompok' => 'ekuitas', 'saldo_normal' => 'kredit'],
            ['kode_akun' => '302.01', 'nama_akun' => 'Mutasi / Dropping Kas Antar Unit', 'kelompok' => 'ekuitas', 'saldo_normal' => 'kredit'],

            // ==========================================
            // KELOMPOK 4: PENDAPATAN (Saldo Normal: Kredit)
            // ==========================================
            ['kode_akun' => '401.01', 'nama_akun' => 'Pendapatan Pendidikan (UKT / SPP)', 'kelompok' => 'pendapatan', 'saldo_normal' => 'kredit'],
            ['kode_akun' => '401.02', 'nama_akun' => 'Pendapatan Seleksi & Registrasi PMB', 'kelompok' => 'pendapatan', 'saldo_normal' => 'kredit'],
            ['kode_akun' => '401.03', 'nama_akun' => 'Pendapatan Dana Pengembangan Institusi', 'kelompok' => 'pendapatan', 'saldo_normal' => 'kredit'],
            ['kode_akun' => '401.04', 'nama_akun' => 'Pendapatan Semester Pendek, Inhal & Remidi', 'kelompok' => 'pendapatan', 'saldo_normal' => 'kredit'],
            ['kode_akun' => '401.05', 'nama_akun' => 'Pendapatan Praktik Industri, Lab & TEFA', 'kelompok' => 'pendapatan', 'saldo_normal' => 'kredit'],
            ['kode_akun' => '401.06', 'nama_akun' => 'Pendapatan Tugas Akhir & Skripsi', 'kelompok' => 'pendapatan', 'saldo_normal' => 'kredit'],
            ['kode_akun' => '401.07', 'nama_akun' => 'Pendapatan Wisuda', 'kelompok' => 'pendapatan', 'saldo_normal' => 'kredit'],
            ['kode_akun' => '401.08', 'nama_akun' => 'Pendapatan Legalisir & Denda Pengambilan Ijazah', 'kelompok' => 'pendapatan', 'saldo_normal' => 'kredit'],
            ['kode_akun' => '401.09', 'nama_akun' => 'Pendapatan Uji Kompetensi & Sumpah Profesi', 'kelompok' => 'pendapatan', 'saldo_normal' => 'kredit'],
            ['kode_akun' => '401.10', 'nama_akun' => 'Pendapatan Atribut, Seragam, & Wearpack', 'kelompok' => 'pendapatan', 'saldo_normal' => 'kredit'],
            ['kode_akun' => '401.11', 'nama_akun' => 'Pendapatan Tes Kesehatan & Sertifikasi Bahasa', 'kelompok' => 'pendapatan', 'saldo_normal' => 'kredit'],
            ['kode_akun' => '401.12', 'nama_akun' => 'Pendapatan Cuti Kuliah & Iuran Mahasiswa', 'kelompok' => 'pendapatan', 'saldo_normal' => 'kredit'],
            ['kode_akun' => '402.01', 'nama_akun' => 'Pendapatan Dana Hibah Riset & PkM (SIPPM)', 'kelompok' => 'pendapatan', 'saldo_normal' => 'kredit'],
            ['kode_akun' => '402.02', 'nama_akun' => 'Pendapatan Kerjasama Donatur & Instansi', 'kelompok' => 'pendapatan', 'saldo_normal' => 'kredit'],
            ['kode_akun' => '403.01', 'nama_akun' => 'Pendapatan Layanan Unit Bisnis & Kursus', 'kelompok' => 'pendapatan', 'saldo_normal' => 'kredit'],
            ['kode_akun' => '404.01', 'nama_akun' => 'Pendapatan Operasional Lainnya', 'kelompok' => 'pendapatan', 'saldo_normal' => 'kredit'],

            // ==========================================
            // KELOMPOK 5: BEBAN (Saldo Normal: Debet)
            // ==========================================
            // 501 - Beban Pegawai & SDM
            ['kode_akun' => '501.01', 'nama_akun' => 'Beban Gaji & Honorarium Karyawan Tetap', 'kelompok' => 'beban', 'saldo_normal' => 'debet'],
            ['kode_akun' => '501.02', 'nama_akun' => 'Beban Honorarium Kelebihan Mengajar / Dosen LB', 'kelompok' => 'beban', 'saldo_normal' => 'debet'],
            ['kode_akun' => '501.03', 'nama_akun' => 'Beban Honorarium Pembimbing & Penguji TA', 'kelompok' => 'beban', 'saldo_normal' => 'debet'],
            ['kode_akun' => '501.04', 'nama_akun' => 'Beban Honorarium Magang & Pengawas Ujian', 'kelompok' => 'beban', 'saldo_normal' => 'debet'],
            ['kode_akun' => '501.05', 'nama_akun' => 'Beban Lembur & Insentif Tambahan Karyawan', 'kelompok' => 'beban', 'saldo_normal' => 'debet'],
            ['kode_akun' => '501.06', 'nama_akun' => 'Beban Gaji Outsourcing, OB, & Penjaga Kampus', 'kelompok' => 'beban', 'saldo_normal' => 'debet'],
            ['kode_akun' => '501.07', 'nama_akun' => 'Beban BPJS Ketenagakerjaan & Kesehatan', 'kelompok' => 'beban', 'saldo_normal' => 'debet'],
            ['kode_akun' => '501.08', 'nama_akun' => 'Beban Seragam & Kesejahteraan Karyawan', 'kelompok' => 'beban', 'saldo_normal' => 'debet'],
            ['kode_akun' => '501.09', 'nama_akun' => 'Beban Pelatihan, Diklat & Workshop SDM', 'kelompok' => 'beban', 'saldo_normal' => 'debet'],

            // 502 - Beban Pembelajaran & Kemahasiswaan
            ['kode_akun' => '502.01', 'nama_akun' => 'Beban Belanja ATK, Fotocopy & Percetakan', 'kelompok' => 'beban', 'saldo_normal' => 'debet'],
            ['kode_akun' => '502.02', 'nama_akun' => 'Beban Bahan Praktikum & Laboratorium', 'kelompok' => 'beban', 'saldo_normal' => 'debet'],
            ['kode_akun' => '502.03', 'nama_akun' => 'Beban Kegiatan Ujian Semester', 'kelompok' => 'beban', 'saldo_normal' => 'debet'],
            ['kode_akun' => '502.04', 'nama_akun' => 'Beban Kegiatan Wisuda', 'kelompok' => 'beban', 'saldo_normal' => 'debet'],
            ['kode_akun' => '502.05', 'nama_akun' => 'Beban Kegiatan Kemahasiswaan, BEM & Outbound', 'kelompok' => 'beban', 'saldo_normal' => 'debet'],
            ['kode_akun' => '502.06', 'nama_akun' => 'Beban Promosi, Pameran, & Brosur PMB', 'kelompok' => 'beban', 'saldo_normal' => 'debet'],
            ['kode_akun' => '502.07', 'nama_akun' => 'Beban Uji Kompetensi, Sertifikasi & Sumpah Profesi', 'kelompok' => 'beban', 'saldo_normal' => 'debet'],
            ['kode_akun' => '502.08', 'nama_akun' => 'Beban Praktik Industri & Tugas Akhir', 'kelompok' => 'beban', 'saldo_normal' => 'debet'],
            ['kode_akun' => '502.09', 'nama_akun' => 'Beban Akreditasi, Borang, & Penjaminan Mutu', 'kelompok' => 'beban', 'saldo_normal' => 'debet'],
            ['kode_akun' => '502.10', 'nama_akun' => 'Beban Pengembalian Biaya Kuliah Mahasiswa', 'kelompok' => 'beban', 'saldo_normal' => 'debet'],
            ['kode_akun' => '502.11', 'nama_akun' => 'Beban Buku & Langganan Perpustakaan', 'kelompok' => 'beban', 'saldo_normal' => 'debet'],
            ['kode_akun' => '502.12', 'nama_akun' => 'Beban Atribut, Jaket & Seragam Mahasiswa', 'kelompok' => 'beban', 'saldo_normal' => 'debet'],
            ['kode_akun' => '502.13', 'nama_akun' => 'Beban MBKM & Pertukaran Mahasiswa', 'kelompok' => 'beban', 'saldo_normal' => 'debet'],

            // 503 - Beban Operasional Umum Kampus
            ['kode_akun' => '503.01', 'nama_akun' => 'Beban Listrik', 'kelompok' => 'beban', 'saldo_normal' => 'debet'],
            ['kode_akun' => '503.02', 'nama_akun' => 'Beban Telepon, Internet & PDAM', 'kelompok' => 'beban', 'saldo_normal' => 'debet'],
            ['kode_akun' => '503.03', 'nama_akun' => 'Beban Bahan Bakar & Transportasi Kampus', 'kelompok' => 'beban', 'saldo_normal' => 'debet'],
            ['kode_akun' => '503.04', 'nama_akun' => 'Beban Kebersihan & Retribusi Sampah', 'kelompok' => 'beban', 'saldo_normal' => 'debet'],
            ['kode_akun' => '503.05', 'nama_akun' => 'Beban Pemeliharaan Sarana, Prasarana & Servis', 'kelompok' => 'beban', 'saldo_normal' => 'debet'],
            ['kode_akun' => '503.06', 'nama_akun' => 'Beban Perjalanan Dinas', 'kelompok' => 'beban', 'saldo_normal' => 'debet'],
            ['kode_akun' => '503.07', 'nama_akun' => 'Beban Konsumsi Rapat & Penerimaan Tamu', 'kelompok' => 'beban', 'saldo_normal' => 'debet'],
            ['kode_akun' => '503.08', 'nama_akun' => 'Beban Pajak, Retribusi & Perizinan', 'kelompok' => 'beban', 'saldo_normal' => 'debet'],
            ['kode_akun' => '503.09', 'nama_akun' => 'Beban Sumbangan Sosial, Duka & Dies Natalis', 'kelompok' => 'beban', 'saldo_normal' => 'debet'],
            ['kode_akun' => '503.10', 'nama_akun' => 'Beban Operasional Umum Lainnya', 'kelompok' => 'beban', 'saldo_normal' => 'debet'],

            // 504 - Beban Riset & Pengabdian
            ['kode_akun' => '504.01', 'nama_akun' => 'Beban Hibah Penelitian & PkM Dosen (SIPPM)', 'kelompok' => 'beban', 'saldo_normal' => 'debet'],
            ['kode_akun' => '504.02', 'nama_akun' => 'Beban Publikasi Ilmiah, Jurnal & HKI', 'kelompok' => 'beban', 'saldo_normal' => 'debet'],

            // 505 - Beban Beasiswa & Potongan
            ['kode_akun' => '505.01', 'nama_akun' => 'Beban Beasiswa & Potongan Mahasiswa', 'kelompok' => 'beban', 'saldo_normal' => 'debet'],

            // 506 - Beban Administrasi Bank
            ['kode_akun' => '506.01', 'nama_akun' => 'Beban Administrasi Bank & Payment Gateway', 'kelompok' => 'beban', 'saldo_normal' => 'debet'],

            // 507 - Beban Penyusutan
            ['kode_akun' => '507.01', 'nama_akun' => 'Beban Penyusutan Aset Tetap', 'kelompok' => 'beban', 'saldo_normal' => 'debet'],
        ];

        foreach ($akun as $row) {
            AkunKeuangan::updateOrCreate(
                ['kode_akun' => $row['kode_akun']],
                array_merge($row, ['is_active' => true])
            );
        }
    }
}
