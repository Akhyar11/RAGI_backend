<?php

namespace Database\Seeders\IAM;

use Illuminate\Database\Seeder;
use App\Models\Permission;
use App\Models\Role;
use App\Models\RolePermission;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        Schema::disableForeignKeyConstraints();
        DB::table('role_permissions')->truncate();
        DB::table('permissions')->truncate();
        Schema::enableForeignKeyConstraints();

        $permissions = [
            // ── MODUL SSO (IAM) ───────────────────────────────────
            ['name' => 'Lihat User SSO', 'slug' => 'iam.users.read', 'module' => 'iam', 'action' => 'read', 'description' => 'Melihat daftar pengguna portal SSO'],
            ['name' => 'Tambah User SSO', 'slug' => 'iam.users.create', 'module' => 'iam', 'action' => 'create', 'description' => 'Mendaftarkan akun pengguna baru'],
            ['name' => 'Ubah User SSO', 'slug' => 'iam.users.update', 'module' => 'iam', 'action' => 'update', 'description' => 'Mengubah profil & data pengguna'],
            ['name' => 'Hapus User SSO', 'slug' => 'iam.users.delete', 'module' => 'iam', 'action' => 'delete', 'description' => 'Menghapus akun pengguna dari portal SSO'],

            ['name' => 'Lihat Role Akses', 'slug' => 'iam.roles.read', 'module' => 'iam', 'action' => 'read', 'description' => 'Melihat daftar master role akses'],
            ['name' => 'Tambah Role Akses', 'slug' => 'iam.roles.create', 'module' => 'iam', 'action' => 'create', 'description' => 'Membuat struktur role baru'],
            ['name' => 'Ubah Role Akses', 'slug' => 'iam.roles.update', 'module' => 'iam', 'action' => 'update', 'description' => 'Mengubah deskripsi & data role'],
            ['name' => 'Hapus Role Akses', 'slug' => 'iam.roles.delete', 'module' => 'iam', 'action' => 'delete', 'description' => 'Menghapus master role dari sistem'],

            ['name' => 'Lihat Permission Akses', 'slug' => 'iam.permissions.read', 'module' => 'iam', 'action' => 'read', 'description' => 'Melihat daftar permission granular'],
            ['name' => 'Kelola Role ↔ Permission', 'slug' => 'iam.permissions.manage', 'module' => 'iam', 'action' => 'update', 'description' => 'Mengatur pemetaan permission pada setiap role'],
            ['name' => 'Kelola User ↔ Role', 'slug' => 'iam.user_roles.manage', 'module' => 'iam', 'action' => 'update', 'description' => 'Memasangkan role kepada akun pengguna'],

            ['name' => 'Monitor Sesi Perangkat', 'slug' => 'iam.sessions.read', 'module' => 'iam', 'action' => 'read', 'description' => 'Memantau sesi login & perangkat aktif'],
            ['name' => 'Force Logout Sesi', 'slug' => 'iam.sessions.delete', 'module' => 'iam', 'action' => 'delete', 'description' => 'Memutus secara paksa sesi perangkat terhubung'],
            ['name' => 'Lihat Audit Logs', 'slug' => 'iam.audit_logs.read', 'module' => 'iam', 'action' => 'read', 'description' => 'Melihat log rekam jejak aktivitas sistem'],

            // ── MODUL SIMPEG (GRANULAR CRUD UNTUK SETIAP FITUR) ──────────────────────
            ['name' => 'Lihat Dashboard SIMPEG', 'slug' => 'simpeg.dashboard.read', 'module' => 'simpeg', 'action' => 'read', 'description' => 'Membuka dashboard utama SIMPEG'],
            ['name' => 'Lihat Unit Kerja', 'slug' => 'simpeg.unit_kerja.read', 'module' => 'simpeg', 'action' => 'read', 'description' => 'Melihat daftar & hierarki unit kerja'],
            ['name' => 'Tambah Unit Kerja', 'slug' => 'simpeg.unit_kerja.create', 'module' => 'simpeg', 'action' => 'create', 'description' => 'Menambah unit kerja baru'],
            ['name' => 'Ubah Unit Kerja', 'slug' => 'simpeg.unit_kerja.update', 'module' => 'simpeg', 'action' => 'update', 'description' => 'Mengubah data unit kerja'],
            ['name' => 'Hapus Unit Kerja', 'slug' => 'simpeg.unit_kerja.delete', 'module' => 'simpeg', 'action' => 'delete', 'description' => 'Menghapus data unit kerja'],
            ['name' => 'Lihat Jabatan & Jafung', 'slug' => 'simpeg.jabatan.read', 'module' => 'simpeg', 'action' => 'read', 'description' => 'Melihat daftar master jabatan & jafung'],
            ['name' => 'Tambah Jabatan & Jafung', 'slug' => 'simpeg.jabatan.create', 'module' => 'simpeg', 'action' => 'create', 'description' => 'Menambah master jabatan & jafung baru'],
            ['name' => 'Ubah Jabatan & Jafung', 'slug' => 'simpeg.jabatan.update', 'module' => 'simpeg', 'action' => 'update', 'description' => 'Mengubah data master jabatan & jafung'],
            ['name' => 'Hapus Jabatan & Jafung', 'slug' => 'simpeg.jabatan.delete', 'module' => 'simpeg', 'action' => 'delete', 'description' => 'Menghapus data master jabatan & jafung'],
            ['name' => 'Lihat Data Pegawai', 'slug' => 'simpeg.pegawai.read', 'module' => 'simpeg', 'action' => 'read', 'description' => 'Melihat direktori & profil pegawai'],
            ['name' => 'Tambah Data Pegawai', 'slug' => 'simpeg.pegawai.create', 'module' => 'simpeg', 'action' => 'create', 'description' => 'Menambah data pegawai baru'],
            ['name' => 'Ubah Data Pegawai', 'slug' => 'simpeg.pegawai.update', 'module' => 'simpeg', 'action' => 'update', 'description' => 'Mengubah biodata & profil pegawai'],
            ['name' => 'Hapus Data Pegawai', 'slug' => 'simpeg.pegawai.delete', 'module' => 'simpeg', 'action' => 'delete', 'description' => 'Menghapus data pegawai'],
            ['name' => 'Lihat Dokumen E-File', 'slug' => 'simpeg.dokumen.read', 'module' => 'simpeg', 'action' => 'read', 'description' => 'Melihat berkas arsip digital pegawai'],
            ['name' => 'Tambah Dokumen E-File', 'slug' => 'simpeg.dokumen.create', 'module' => 'simpeg', 'action' => 'create', 'description' => 'Mengunggah arsip dokumen digital baru'],
            ['name' => 'Ubah Dokumen E-File', 'slug' => 'simpeg.dokumen.update', 'module' => 'simpeg', 'action' => 'update', 'description' => 'Mengubah metadata & verifikasi dokumen'],
            ['name' => 'Hapus Dokumen E-File', 'slug' => 'simpeg.dokumen.delete', 'module' => 'simpeg', 'action' => 'delete', 'description' => 'Menghapus arsip dokumen digital'],
            ['name' => 'Lihat Pengajuan Cuti', 'slug' => 'simpeg.cuti.read', 'module' => 'simpeg', 'action' => 'read', 'description' => 'Melihat permohonan & riwayat cuti'],
            ['name' => 'Tambah Pengajuan Cuti', 'slug' => 'simpeg.cuti.create', 'module' => 'simpeg', 'action' => 'create', 'description' => 'Mengajukan permohonan cuti baru'],
            ['name' => 'Ubah Pengajuan Cuti', 'slug' => 'simpeg.cuti.update', 'module' => 'simpeg', 'action' => 'update', 'description' => 'Memproses & mengubah status persetujuan cuti'],
            ['name' => 'Hapus Pengajuan Cuti', 'slug' => 'simpeg.cuti.delete', 'module' => 'simpeg', 'action' => 'delete', 'description' => 'Menghapus permohonan cuti'],
            ['name' => 'Lihat Absensi & Presensi', 'slug' => 'simpeg.presensi.read', 'module' => 'simpeg', 'action' => 'read', 'description' => 'Melihat rekap & log presensi harian'],
            ['name' => 'Tambah Absensi & Presensi', 'slug' => 'simpeg.presensi.create', 'module' => 'simpeg', 'action' => 'create', 'description' => 'Mencatat log presensi pegawai'],
            ['name' => 'Ubah Absensi & Presensi', 'slug' => 'simpeg.presensi.update', 'module' => 'simpeg', 'action' => 'update', 'description' => 'Mengubah log & koreksi absensi'],
            ['name' => 'Hapus Absensi & Presensi', 'slug' => 'simpeg.presensi.delete', 'module' => 'simpeg', 'action' => 'delete', 'description' => 'Menghapus log absensi pegawai'],
            ['name' => 'Lihat Slip Gaji & Payroll', 'slug' => 'simpeg.payroll.read', 'module' => 'simpeg', 'action' => 'read', 'description' => 'Melihat daftar & rincian slip gaji'],
            ['name' => 'Tambah Slip Gaji & Payroll', 'slug' => 'simpeg.payroll.create', 'module' => 'simpeg', 'action' => 'create', 'description' => 'Menerbitkan slip gaji baru'],
            ['name' => 'Ubah Slip Gaji & Payroll', 'slug' => 'simpeg.payroll.update', 'module' => 'simpeg', 'action' => 'update', 'description' => 'Mengubah status & rincian payroll'],
            ['name' => 'Hapus Slip Gaji & Payroll', 'slug' => 'simpeg.payroll.delete', 'module' => 'simpeg', 'action' => 'delete', 'description' => 'Menghapus data slip gaji'],
            ['name' => 'Lihat Usulan Jafung (KUM)', 'slug' => 'simpeg.usulan_jafung.read', 'module' => 'simpeg', 'action' => 'read', 'description' => 'Melihat daftar usulan jafung dosen'],
            ['name' => 'Tambah Usulan Jafung (KUM)', 'slug' => 'simpeg.usulan_jafung.create', 'module' => 'simpeg', 'action' => 'create', 'description' => 'Mengajukan usulan jafung dosen baru'],
            ['name' => 'Ubah Usulan Jafung (KUM)', 'slug' => 'simpeg.usulan_jafung.update', 'module' => 'simpeg', 'action' => 'update', 'description' => 'Memverifikasi & menilai usulan jafung'],
            ['name' => 'Hapus Usulan Jafung (KUM)', 'slug' => 'simpeg.usulan_jafung.delete', 'module' => 'simpeg', 'action' => 'delete', 'description' => 'Menghapus berkas usulan jafung'],
            ['name' => 'Lihat Kinerja SKP & BKD', 'slug' => 'simpeg.kinerja.read', 'module' => 'simpeg', 'action' => 'read', 'description' => 'Melihat hasil evaluasi kinerja SKP/BKD'],
            ['name' => 'Tambah Kinerja SKP & BKD', 'slug' => 'simpeg.kinerja.create', 'module' => 'simpeg', 'action' => 'create', 'description' => 'Menginput skor evaluasi kinerja baru'],
            ['name' => 'Ubah Kinerja SKP & BKD', 'slug' => 'simpeg.kinerja.update', 'module' => 'simpeg', 'action' => 'update', 'description' => 'Mengubah skor & predikat evaluasi kinerja'],
            ['name' => 'Hapus Kinerja SKP & BKD', 'slug' => 'simpeg.kinerja.delete', 'module' => 'simpeg', 'action' => 'delete', 'description' => 'Menghapus laporan evaluasi kinerja'],

            // ── MODUL SIPPM (PENELITIAN & PKM) ──────────────────────────────────
            ['name' => 'Lihat Dashboard SIPPM', 'slug' => 'sippm.dashboard.read', 'module' => 'sippm', 'action' => 'read', 'description' => 'Melihat dashboard utama & metrik IKU SIPPM'],
            ['name' => 'Lihat Master Skema', 'slug' => 'sippm.skema.read', 'module' => 'sippm', 'action' => 'read', 'description' => 'Melihat daftar master skema kegiatan'],
            ['name' => 'Tambah Master Skema', 'slug' => 'sippm.skema.create', 'module' => 'sippm', 'action' => 'create', 'description' => 'Menambah master skema kegiatan baru'],
            ['name' => 'Ubah Master Skema', 'slug' => 'sippm.skema.update', 'module' => 'sippm', 'action' => 'update', 'description' => 'Mengubah data master skema kegiatan'],
            ['name' => 'Hapus Master Skema', 'slug' => 'sippm.skema.delete', 'module' => 'sippm', 'action' => 'delete', 'description' => 'Menghapus master skema kegiatan'],
            ['name' => 'Lihat Master Periode', 'slug' => 'sippm.periode.read', 'module' => 'sippm', 'action' => 'read', 'description' => 'Melihat daftar periode hibah tahunan'],
            ['name' => 'Tambah Master Periode', 'slug' => 'sippm.periode.create', 'module' => 'sippm', 'action' => 'create', 'description' => 'Menambah periode hibah baru'],
            ['name' => 'Ubah Master Periode', 'slug' => 'sippm.periode.update', 'module' => 'sippm', 'action' => 'update', 'description' => 'Mengubah jadwal & data periode hibah'],
            ['name' => 'Hapus Master Periode', 'slug' => 'sippm.periode.delete', 'module' => 'sippm', 'action' => 'delete', 'description' => 'Menghapus periode hibah'],
            ['name' => 'Lihat Standar IKU 5 Prodi', 'slug' => 'sippm.iku5.read', 'module' => 'sippm', 'action' => 'read', 'description' => 'Melihat daftar standar IKU 5 per program studi'],
            ['name' => 'Tambah Standar IKU 5 Prodi', 'slug' => 'sippm.iku5.create', 'module' => 'sippm', 'action' => 'create', 'description' => 'Menambah target standar IKU 5 prodi'],
            ['name' => 'Ubah Standar IKU 5 Prodi', 'slug' => 'sippm.iku5.update', 'module' => 'sippm', 'action' => 'update', 'description' => 'Mengubah target standar IKU 5 prodi'],
            ['name' => 'Hapus Standar IKU 5 Prodi', 'slug' => 'sippm.iku5.delete', 'module' => 'sippm', 'action' => 'delete', 'description' => 'Menghapus standar IKU 5 prodi'],
            ['name' => 'Lihat Rubrik Indikator Penilaian', 'slug' => 'sippm.rubrik.read', 'module' => 'sippm', 'action' => 'read', 'description' => 'Melihat daftar indikator rubrik penilaian Kaprodi & Admin'],
            ['name' => 'Tambah Rubrik Indikator Penilaian', 'slug' => 'sippm.rubrik.create', 'module' => 'sippm', 'action' => 'create', 'description' => 'Menambah indikator rubrik penilaian baru'],
            ['name' => 'Ubah Rubrik Indikator Penilaian', 'slug' => 'sippm.rubrik.update', 'module' => 'sippm', 'action' => 'update', 'description' => 'Mengubah indikator & minimal score rubrik penilaian'],
            ['name' => 'Hapus Rubrik Indikator Penilaian', 'slug' => 'sippm.rubrik.delete', 'module' => 'sippm', 'action' => 'delete', 'description' => 'Menghapus indikator rubrik penilaian'],
            ['name' => 'Lihat Kontrak SPK', 'slug' => 'sippm.kontrak.read', 'module' => 'sippm', 'action' => 'read', 'description' => 'Melihat daftar kontrak perjanjian kerja hibah'],
            ['name' => 'Tambah Kontrak SPK', 'slug' => 'sippm.kontrak.create', 'module' => 'sippm', 'action' => 'create', 'description' => 'Menerbitkan kontrak perjanjian hibah baru'],
            ['name' => 'Ubah Kontrak SPK', 'slug' => 'sippm.kontrak.update', 'module' => 'sippm', 'action' => 'update', 'description' => 'Mengubah rincian & status kontrak hibah'],
            ['name' => 'Hapus Kontrak SPK', 'slug' => 'sippm.kontrak.delete', 'module' => 'sippm', 'action' => 'delete', 'description' => 'Menghapus kontrak perjanjian hibah'],
            ['name' => 'Lihat Proposal Usulan', 'slug' => 'sippm.proposal.read', 'module' => 'sippm', 'action' => 'read', 'description' => 'Melihat daftar & detail proposal usulan'],
            ['name' => 'Buat Proposal Usulan', 'slug' => 'sippm.proposal.create', 'module' => 'sippm', 'action' => 'create', 'description' => 'Mengajukan proposal riset/PkM baru'],
            ['name' => 'Ubah Proposal Usulan', 'slug' => 'sippm.proposal.update', 'module' => 'sippm', 'action' => 'update', 'description' => 'Mengubah draf proposal usulan'],
            ['name' => 'Hapus Proposal Usulan', 'slug' => 'sippm.proposal.delete', 'module' => 'sippm', 'action' => 'delete', 'description' => 'Menghapus draf proposal usulan'],
            ['name' => 'Submit Proposal Usulan', 'slug' => 'sippm.proposal.submit', 'module' => 'sippm', 'action' => 'update', 'description' => 'Mengirimkan proposal draf ke LPPM'],
            ['name' => 'Penugasan Reviewer Proposal', 'slug' => 'sippm.proposal.assign_reviewer', 'module' => 'sippm', 'action' => 'update', 'description' => 'Menugaskan reviewer ke proposal usulan'],
            ['name' => 'Finalisasi Status Proposal', 'slug' => 'sippm.proposal.finalize', 'module' => 'sippm', 'action' => 'update', 'description' => 'Memutuskan persetujuan & nominal dana hibah'],
            ['name' => 'Lihat Penugasan Reviewer', 'slug' => 'sippm.reviewer.read', 'module' => 'sippm', 'action' => 'read', 'description' => 'Melihat penugasan desk evaluation'],
            ['name' => 'Input Desk Evaluation', 'slug' => 'sippm.reviewer.evaluate', 'module' => 'sippm', 'action' => 'update', 'description' => 'Mengisi rubrik & rekomendasi penilaian proposal'],
            ['name' => 'Lihat Pencairan Dana', 'slug' => 'sippm.pencairan.read', 'module' => 'sippm', 'action' => 'read', 'description' => 'Melihat status pencairan dana & LPJ'],
            ['name' => 'Pengajuan Pencairan Dana', 'slug' => 'sippm.pencairan.request', 'module' => 'sippm', 'action' => 'create', 'description' => 'Mengajukan pencairan dana Termin 1/2'],
            ['name' => 'Verifikasi Pencairan LPJ', 'slug' => 'sippm.pencairan.verify', 'module' => 'sippm', 'action' => 'update', 'description' => 'Memverifikasi kelayakan LPJ & disbursement'],
            ['name' => 'Lihat Portofolio Luaran', 'slug' => 'sippm.luaran.read', 'module' => 'sippm', 'action' => 'read', 'description' => 'Melihat registry publikasi & HKI'],
            ['name' => 'Registrasi Luaran Baru', 'slug' => 'sippm.luaran.create', 'module' => 'sippm', 'action' => 'create', 'description' => 'Mendaftarkan artikel ilmiah atau HKI/paten baru'],
            ['name' => 'Verifikasi Luaran Riset', 'slug' => 'sippm.luaran.verify', 'module' => 'sippm', 'action' => 'update', 'description' => 'Memverifikasi keabsahan publikasi & HKI'],

            // ── MODUL SPMB ──────────────────────────────────
            ['name' => 'Lihat Dashboard SPMB', 'slug' => 'spmb.dashboard.read', 'module' => 'spmb', 'action' => 'read', 'description' => 'Melihat dashboard & pengumuman SPMB'],
            ['name' => 'Pendaftaran SPMB', 'slug' => 'spmb.pendaftaran.create', 'module' => 'spmb', 'action' => 'create', 'description' => 'Mengisi formulir pendaftaran mahasiswa baru'],

            // ── MODUL SIKEU (KEUANGAN & AKUNTANSI) ──────────────────────────────────
            ['name' => 'Lihat Dashboard SIKEU', 'slug' => 'sikeu.dashboard.read', 'module' => 'sikeu', 'action' => 'read', 'description' => 'Melihat dashboard utama & executive summary keuangan'],
            ['name' => 'Lihat Tagihan', 'slug' => 'sikeu.tagihan.read', 'module' => 'sikeu', 'action' => 'read', 'description' => 'Melihat daftar & rincian tagihan mahasiswa'],
            ['name' => 'Generate Tagihan', 'slug' => 'sikeu.tagihan.create', 'module' => 'sikeu', 'action' => 'create', 'description' => 'Menerbitkan tagihan baru'],
            ['name' => 'Buat Tagihan Eksternal API', 'slug' => 'sikeu.tagihan.create_external', 'module' => 'sikeu', 'action' => 'create', 'description' => 'Menerbitkan tagihan via API dari sistem eksternal'],
            ['name' => 'Lihat Dispensasi', 'slug' => 'sikeu.dispensasi.read', 'module' => 'sikeu', 'action' => 'read', 'description' => 'Melihat permohonan dispensasi pembayaran'],
            ['name' => 'Pengajuan Dispensasi', 'slug' => 'sikeu.dispensasi.create', 'module' => 'sikeu', 'action' => 'create', 'description' => 'Mengajukan dispensasi penundaan/cicilan'],
            ['name' => 'Approve Dispensasi', 'slug' => 'sikeu.dispensasi.approve', 'module' => 'sikeu', 'action' => 'update', 'description' => 'Menyetujui pengajuan dispensasi'],
            ['name' => 'Reject Dispensasi', 'slug' => 'sikeu.dispensasi.reject', 'module' => 'sikeu', 'action' => 'update', 'description' => 'Menolak pengajuan dispensasi'],
            ['name' => 'Lihat Antrean Approval Pimpinan', 'slug' => 'sikeu.approval.read', 'module' => 'sikeu', 'action' => 'read', 'description' => 'Melihat daftar tagihan & dispensasi yang membutuhkan approval'],
            ['name' => 'Approve Tagihan Eksternal', 'slug' => 'sikeu.approval.approve', 'module' => 'sikeu', 'action' => 'update', 'description' => 'Menyetujui tagihan eksternal'],
            ['name' => 'Reject Tagihan Eksternal', 'slug' => 'sikeu.approval.reject', 'module' => 'sikeu', 'action' => 'update', 'description' => 'Menolak tagihan eksternal'],
            ['name' => 'Kelola Master Biaya & UKT', 'slug' => 'sikeu.master.manage', 'module' => 'sikeu', 'action' => 'update', 'description' => 'Mengelola jenis biaya, tarif UKT, & beasiswa'],
            ['name' => 'Kelola Unit Kas', 'slug' => 'sikeu.kas.manage', 'module' => 'sikeu', 'action' => 'update', 'description' => 'Mengelola kas unit & petty cash'],
            ['name' => 'Pencairan Dana Kas', 'slug' => 'sikeu.pencairan.manage', 'module' => 'sikeu', 'action' => 'update', 'description' => 'Mengajukan & mencairkan dana kas unit'],
            ['name' => 'Kelola Pemasukan Kampus', 'slug' => 'sikeu.pemasukan.manage', 'module' => 'sikeu', 'action' => 'update', 'description' => 'Mencatat pemasukan dana hibah, donatur, & kerjasama'],
            ['name' => 'Kelola Pengeluaran Kampus', 'slug' => 'sikeu.pengeluaran.manage', 'module' => 'sikeu', 'action' => 'update', 'description' => 'Mencatat transaksi pengeluaran operasional kampus & pajak'],
            ['name' => 'Kelola Akuntansi & Jurnal', 'slug' => 'sikeu.akuntansi.manage', 'module' => 'sikeu', 'action' => 'update', 'description' => 'Mengelola COA, Jurnal Umum, Penyesuaian, & Buku Besar'],
            ['name' => 'Posting Jurnal Akuntansi', 'slug' => 'sikeu.akuntansi.posting', 'module' => 'sikeu', 'action' => 'update', 'description' => 'Memposting draf jurnal ke buku besar'],
            ['name' => 'Penutupan Periode Akuntansi', 'slug' => 'sikeu.akuntansi.closing', 'module' => 'sikeu', 'action' => 'update', 'description' => 'Menutup periode buku akuntansi bulanan/tahunan'],
            ['name' => 'Lihat Laporan Keuangan & Pajak', 'slug' => 'sikeu.laporan.read', 'module' => 'sikeu', 'action' => 'read', 'description' => 'Melihat & mengunduh 4 Laporan Keuangan serta Rekap Pajak'],
            ['name' => 'Verifikasi Bukti LPJ', 'slug' => 'sikeu.lpj.verify', 'module' => 'sikeu', 'action' => 'update', 'description' => 'Memverifikasi bukti pelaksanaan kuitansi/nota LPJ'],
        ];

        // Insert semua permissions ke database
        foreach ($permissions as $perm) {
            Permission::create([
                'name' => $perm['name'],
                'slug' => $perm['slug'],
                'module' => $perm['module'],
                'action' => $perm['action'],
                'description' => $perm['description'],
            ]);
        }

        // ── AUTO MAP ROLE PERMISSIONS ───────────────────────────────
        $allPermissions = Permission::all();
        $adminRole = Role::where('slug', 'admin')->first();
        $superAdminRole = Role::where('slug', 'superadmin')->first();
        $adminSimpegRole = Role::where('slug', 'admin_simpeg')->first();
        $adminLppmRole = Role::where('slug', 'admin_lppm')->first();
        $reviewerSippmRole = Role::where('slug', 'reviewer_sippm')->first();
        $operatorSdmRole = Role::where('slug', 'operator_sdm')->first();
        $dosenRole = Role::where('slug', 'dosen')->first();
        $tendikRole = Role::where('slug', 'tendik')->first();
        $calonMhsRole = Role::where('slug', 'calon_mhs')->first();
        $pimpinanRole = Role::where('slug', 'pimpinan')->first();
        $operatorSikeuRole = Role::where('slug', 'operator_sikeu')->first();
        $kabagKeuanganRole = Role::where('slug', 'kabag_keuangan')->first();
        $mahasiswaRole = Role::where('slug', 'mahasiswa')->first();

        // 1. Super Admin & Admin -> Semua permissions
        if ($superAdminRole) {
            foreach ($allPermissions as $p) {
                RolePermission::create(['role_id' => $superAdminRole->id, 'permission_id' => $p->id]);
            }
        }
        if ($adminRole) {
            foreach ($allPermissions as $p) {
                RolePermission::create(['role_id' => $adminRole->id, 'permission_id' => $p->id]);
            }
        }

        // 2. Operator SIKEU & Kabag Keuangan -> Semua permissions SIKEU
        if ($operatorSikeuRole) {
            foreach ($allPermissions as $p) {
                if ($p->module === 'sikeu' || str_starts_with($p->slug, 'iam.users.read')) {
                    RolePermission::create(['role_id' => $operatorSikeuRole->id, 'permission_id' => $p->id]);
                }
            }
        }
        if ($kabagKeuanganRole) {
            foreach ($allPermissions as $p) {
                if ($p->module === 'sikeu' || str_starts_with($p->slug, 'iam.users.read')) {
                    RolePermission::create(['role_id' => $kabagKeuanganRole->id, 'permission_id' => $p->id]);
                }
            }
        }
        if ($mahasiswaRole) {
            $mahasiswaSlugs = [
                'sikeu.dashboard.read',
                'sikeu.tagihan.read',
                'sikeu.dispensasi.read',
                'sikeu.dispensasi.create',
            ];
            $perms = Permission::whereIn('slug', $mahasiswaSlugs)->get();
            foreach ($perms as $p) {
                RolePermission::create(['role_id' => $mahasiswaRole->id, 'permission_id' => $p->id]);
            }
        }
    }
}
