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
        DB::table('core_role_permissions')->truncate();
        DB::table('core_permissions')->truncate();
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
            ['name' => 'Lihat Laporan SPMB', 'slug' => 'spmb.laporan.read', 'module' => 'spmb', 'action' => 'read', 'description' => 'Melihat statistik dan laporan SPMB'],
            ['name' => 'Export Laporan SPMB', 'slug' => 'spmb.laporan.export', 'module' => 'spmb', 'action' => 'read', 'description' => 'Mengekspor laporan SPMB'],

            // ── MODUL SIMPEG ──────────────────────────────────────
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
            ['name' => 'Lihat Kompetensi Pegawai', 'slug' => 'simpeg.kompetensi.read', 'module' => 'simpeg', 'action' => 'read', 'description' => 'Melihat sertifikasi, tes & riwayat pelatihan'],
            ['name' => 'Tambah Kompetensi Pegawai', 'slug' => 'simpeg.kompetensi.create', 'module' => 'simpeg', 'action' => 'create', 'description' => 'Menambahkan data sertifikasi, tes, atau pelatihan baru'],
            ['name' => 'Ubah Kompetensi Pegawai', 'slug' => 'simpeg.kompetensi.update', 'module' => 'simpeg', 'action' => 'update', 'description' => 'Mengubah data riwayat kompetensi pegawai'],
            ['name' => 'Hapus Kompetensi Pegawai', 'slug' => 'simpeg.kompetensi.delete', 'module' => 'simpeg', 'action' => 'delete', 'description' => 'Menghapus data riwayat kompetensi pegawai'],
            ['name' => 'Kelola & Rekap Kompetensi Seluruh Pegawai', 'slug' => 'simpeg.kompetensi.manage', 'module' => 'simpeg', 'action' => 'read', 'description' => 'Mengakses pencarian dan rekap kompetensi untuk akreditasi'],
            ['name' => 'Lihat Surat Tugas & LPJ', 'slug' => 'simpeg.surat_tugas.read', 'module' => 'simpeg', 'action' => 'read', 'description' => 'Melihat daftar pengajuan surat tugas & laporan LPJ'],
            ['name' => 'Tambah Surat Tugas', 'slug' => 'simpeg.surat_tugas.create', 'module' => 'simpeg', 'action' => 'create', 'description' => 'Mengajukan permohonan surat tugas dinas luar'],
            ['name' => 'Ubah Surat Tugas', 'slug' => 'simpeg.surat_tugas.update', 'module' => 'simpeg', 'action' => 'update', 'description' => 'Mengubah data surat tugas atau mengunggah LPJ'],
            ['name' => 'Hapus Surat Tugas', 'slug' => 'simpeg.surat_tugas.delete', 'module' => 'simpeg', 'action' => 'delete', 'description' => 'Menghapus pengajuan surat tugas'],
            ['name' => 'Approve & Terbitkan Surat Tugas', 'slug' => 'simpeg.surat_tugas.approve', 'module' => 'simpeg', 'action' => 'approve', 'description' => 'Menyetujui, memberi nomor resmi, dan menerbitkan surat tugas'],
            ['name' => 'Lihat Izin Jam Kerja', 'slug' => 'simpeg.izin_kerja.read', 'module' => 'simpeg', 'action' => 'read', 'description' => 'Melihat permohonan izin jam kerja / dinas parsial'],
            ['name' => 'Tambah Izin Jam Kerja', 'slug' => 'simpeg.izin_kerja.create', 'module' => 'simpeg', 'action' => 'create', 'description' => 'Mengajukan izin keluar kantor / terlambat / pulang awal'],
            ['name' => 'Ubah Izin Jam Kerja', 'slug' => 'simpeg.izin_kerja.update', 'module' => 'simpeg', 'action' => 'update', 'description' => 'Mengubah draf permohonan izin jam kerja'],
            ['name' => 'Hapus Izin Jam Kerja', 'slug' => 'simpeg.izin_kerja.delete', 'module' => 'simpeg', 'action' => 'delete', 'description' => 'Menghapus permohonan izin jam kerja'],
            ['name' => 'Persetujuan Izin Jam Kerja', 'slug' => 'simpeg.izin_kerja.approve', 'module' => 'simpeg', 'action' => 'approve', 'description' => 'Menyetujui atau menolak permohonan izin jam kerja'],
            ['name' => 'Lihat Arsip SK Pegawai', 'slug' => 'simpeg.sk_pegawai.read', 'module' => 'simpeg', 'action' => 'read', 'description' => 'Melihat repositori SK kepegawaian & penugasan'],
            ['name' => 'Tambah / Laporkan SK Pegawai', 'slug' => 'simpeg.sk_pegawai.create', 'module' => 'simpeg', 'action' => 'create', 'description' => 'Mengunggah dan melaporkan SK baru secara mandiri'],
            ['name' => 'Ubah SK Pegawai', 'slug' => 'simpeg.sk_pegawai.update', 'module' => 'simpeg', 'action' => 'update', 'description' => 'Mengubah data arsip SK pegawai'],
            ['name' => 'Hapus SK Pegawai', 'slug' => 'simpeg.sk_pegawai.delete', 'module' => 'simpeg', 'action' => 'delete', 'description' => 'Menghapus arsip SK pegawai'],
            ['name' => 'Verifikasi SK Pegawai', 'slug' => 'simpeg.sk_pegawai.verify', 'module' => 'simpeg', 'action' => 'approve', 'description' => 'Memverifikasi atau menolak keabsahan SK pegawai'],

            // ── MODUL SIPPM ───────────────────────────────────────
            ['name' => 'Lihat Dashboard SIPPM', 'slug' => 'sippm.dashboard.read', 'module' => 'sippm', 'action' => 'read', 'description' => 'Melihat dashboard utama & metrik IKU SIPPM'],
            ['name' => 'Lihat Master Skema', 'slug' => 'sippm.skema.read', 'module' => 'sippm', 'action' => 'read', 'description' => 'Melihat daftar master skema kegiatan'],
            ['name' => 'Tambah Master Skema', 'slug' => 'sippm.skema.create', 'module' => 'sippm', 'action' => 'create', 'description' => 'Menambah master skema kegiatan baru'],
            ['name' => 'Ubah Master Skema', 'slug' => 'sippm.skema.update', 'module' => 'sippm', 'action' => 'update', 'description' => 'Mengubah data master skema kegiatan'],
            ['name' => 'Hapus Master Skema', 'slug' => 'sippm.skema.delete', 'module' => 'sippm', 'action' => 'delete', 'description' => 'Menghapus master skema kegiatan'],
            ['name' => 'Lihat Master Periode', 'slug' => 'sippm.periode.read', 'module' => 'sippm', 'action' => 'read', 'description' => 'Melihat daftar periode hibah tahunan'],
            ['name' => 'Tambah Master Periode', 'slug' => 'sippm.periode.create', 'module' => 'sippm', 'action' => 'create', 'description' => 'Menambah periode hibah baru'],
            ['name' => 'Ubah Master Periode', 'slug' => 'sippm.periode.update', 'module' => 'sippm', 'action' => 'update', 'description' => 'Mengubah jadwal & data periode hibah'],
            ['name' => 'Hapus Master Periode', 'slug' => 'sippm.periode.delete', 'module' => 'sippm', 'action' => 'delete', 'description' => 'Menghapus periode hibah'],
            ['name' => 'Lihat Pengumuman Hibah', 'slug' => 'sippm.pengumuman.read', 'module' => 'sippm', 'action' => 'read', 'description' => 'Melihat daftar pengumuman hibah'],
            ['name' => 'Tambah Pengumuman Hibah', 'slug' => 'sippm.pengumuman.create', 'module' => 'sippm', 'action' => 'create', 'description' => 'Membuat draf surat pengumuman hibah baru'],
            ['name' => 'Ubah Pengumuman Hibah', 'slug' => 'sippm.pengumuman.update', 'module' => 'sippm', 'action' => 'update', 'description' => 'Mengubah data pengumuman hibah'],
            ['name' => 'Publish Pengumuman Hibah', 'slug' => 'sippm.pengumuman.publish', 'module' => 'sippm', 'action' => 'update', 'description' => 'Mempublish pengumuman hibah'],
            ['name' => 'Hapus Pengumuman Hibah', 'slug' => 'sippm.pengumuman.delete', 'module' => 'sippm', 'action' => 'delete', 'description' => 'Menghapus pengumuman hibah'],
            ['name' => 'Lihat Standar IKU 5 Prodi', 'slug' => 'sippm.iku5.read', 'module' => 'sippm', 'action' => 'read', 'description' => 'Melihat daftar standar IKU 5 per program studi'],
            ['name' => 'Tambah Standar IKU 5 Prodi', 'slug' => 'sippm.iku5.create', 'module' => 'sippm', 'action' => 'create', 'description' => 'Menambah target standar IKU 5 prodi'],
            ['name' => 'Ubah Standar IKU 5 Prodi', 'slug' => 'sippm.iku5.update', 'module' => 'sippm', 'action' => 'update', 'description' => 'Mengubah target standar IKU 5 prodi'],
            ['name' => 'Hapus Standar IKU 5 Prodi', 'slug' => 'sippm.iku5.delete', 'module' => 'sippm', 'action' => 'delete', 'description' => 'Menghapus standar IKU 5 prodi'],
            ['name' => 'Lihat Rubrik Indikator Penilaian', 'slug' => 'sippm.rubrik.read', 'module' => 'sippm', 'action' => 'read', 'description' => 'Melihat daftar indikator rubrik penilaian'],
            ['name' => 'Tambah Rubrik Indikator Penilaian', 'slug' => 'sippm.rubrik.create', 'module' => 'sippm', 'action' => 'create', 'description' => 'Menambah indikator rubrik penilaian baru'],
            ['name' => 'Ubah Rubrik Indikator Penilaian', 'slug' => 'sippm.rubrik.update', 'module' => 'sippm', 'action' => 'update', 'description' => 'Mengubah indikator rubrik penilaian'],
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

            // ── MODUL SPMB ────────────────────────────────────────
            ['name' => 'Lihat Dashboard SPMB', 'slug' => 'spmb.dashboard.read', 'module' => 'spmb', 'action' => 'read', 'description' => 'Melihat dashboard & pengumuman SPMB'],
            ['name' => 'Pendaftaran SPMB', 'slug' => 'spmb.pendaftaran.create', 'module' => 'spmb', 'action' => 'create', 'description' => 'Mengisi formulir pendaftaran mahasiswa baru'],

            // ── MODUL SIKEU ───────────────────────────────────────
            ['name' => 'Lihat Dashboard SIKEU', 'slug' => 'sikeu.dashboard.read', 'module' => 'sikeu', 'action' => 'read', 'description' => 'Melihat dashboard utama keuangan'],
            ['name' => 'Lihat Tagihan', 'slug' => 'sikeu.tagihan.read', 'module' => 'sikeu', 'action' => 'read', 'description' => 'Melihat daftar & rincian tagihan mahasiswa'],
            ['name' => 'Generate Tagihan', 'slug' => 'sikeu.tagihan.create', 'module' => 'sikeu', 'action' => 'create', 'description' => 'Menerbitkan tagihan baru'],
            ['name' => 'Lihat Dispensasi', 'slug' => 'sikeu.dispensasi.read', 'module' => 'sikeu', 'action' => 'read', 'description' => 'Melihat permohonan dispensasi pembayaran'],
            ['name' => 'Pengajuan Dispensasi', 'slug' => 'sikeu.dispensasi.create', 'module' => 'sikeu', 'action' => 'create', 'description' => 'Mengajukan dispensasi penundaan/cicilan'],
            ['name' => 'Approve Dispensasi', 'slug' => 'sikeu.dispensasi.approve', 'module' => 'sikeu', 'action' => 'update', 'description' => 'Menyetujui pengajuan dispensasi'],
            ['name' => 'Reject Dispensasi', 'slug' => 'sikeu.dispensasi.reject', 'module' => 'sikeu', 'action' => 'update', 'description' => 'Menolak pengajuan dispensasi'],
            ['name' => 'Kelola Master Biaya & UKT', 'slug' => 'sikeu.master.manage', 'module' => 'sikeu', 'action' => 'update', 'description' => 'Mengelola jenis biaya & tarif UKT'],
            ['name' => 'Kelola Unit Kas', 'slug' => 'sikeu.kas.manage', 'module' => 'sikeu', 'action' => 'update', 'description' => 'Mengelola kas unit & petty cash'],
            ['name' => 'Kelola Akuntansi & Jurnal', 'slug' => 'sikeu.akuntansi.manage', 'module' => 'sikeu', 'action' => 'update', 'description' => 'Mengelola COA, Jurnal Umum, & Buku Besar'],
            ['name' => 'Lihat Pengajuan Operasional', 'slug' => 'sikeu.pengajuan.read', 'module' => 'sikeu', 'action' => 'read', 'description' => 'Melihat daftar & rincian pengajuan operasional'],
            ['name' => 'Buat Pengajuan Operasional', 'slug' => 'sikeu.pengajuan.create', 'module' => 'sikeu', 'action' => 'create', 'description' => 'Membuat pengajuan barang / non-barang baru'],
            ['name' => 'Approve Pengajuan Operasional', 'slug' => 'sikeu.pengajuan.approve', 'module' => 'sikeu', 'action' => 'update', 'description' => 'Menyetujui tahap sarpras / keuangan / direktur'],
            ['name' => 'Reject Pengajuan Operasional', 'slug' => 'sikeu.pengajuan.reject', 'module' => 'sikeu', 'action' => 'update', 'description' => 'Menolak pengajuan operasional'],
            ['name' => 'Verifikasi LPJ Operasional', 'slug' => 'sikeu.lpj.verify', 'module' => 'sikeu', 'action' => 'update', 'description' => 'Memverifikasi LPJ & menerbitkan jurnal realisasi'],
            ['name' => 'Lihat Akuntansi & Jurnal', 'slug' => 'sikeu.akuntansi.read', 'module' => 'sikeu', 'action' => 'read', 'description' => 'Melihat Jurnal Umum, Buku Besar, & COA'],
            ['name' => 'Lihat Laporan Keuangan', 'slug' => 'sikeu.laporan.read', 'module' => 'sikeu', 'action' => 'read', 'description' => 'Melihat laporan keuangan & neraca'],
            ['name' => 'Lihat Pengeluaran Kas', 'slug' => 'sikeu.pengeluaran.read', 'module' => 'sikeu', 'action' => 'read', 'description' => 'Melihat pengeluaran & beban operasional'],
            ['name' => 'Lihat Pemasukan Kas', 'slug' => 'sikeu.pemasukan.read', 'module' => 'sikeu', 'action' => 'read', 'description' => 'Melihat pemasukan kas non-akademik'],
            ['name' => 'Lihat Pajak', 'slug' => 'sikeu.pajak.read', 'module' => 'sikeu', 'action' => 'read', 'description' => 'Melihat data pajak & perpajakan'],
            ['name' => 'Lihat Unit Kas & Rekening', 'slug' => 'sikeu.unitkas.read', 'module' => 'sikeu', 'action' => 'read', 'description' => 'Melihat unit kas & rekening bank'],
            ['name' => 'Kelola Payment Gateway', 'slug' => 'sikeu.paymentgateway.manage', 'module' => 'sikeu', 'action' => 'update', 'description' => 'Mengatur koneksi Xendit / H2H / rekening manual'],
            ['name' => 'Lihat Approval Keuangan', 'slug' => 'sikeu.approval.read', 'module' => 'sikeu', 'action' => 'read', 'description' => 'Melihat antrean approval keuangan'],
            ['name' => 'Approve Keuangan', 'slug' => 'sikeu.approval.approve', 'module' => 'sikeu', 'action' => 'approve', 'description' => 'Menyetujui antrean approval keuangan'],
            ['name' => 'Reject Keuangan', 'slug' => 'sikeu.approval.reject', 'module' => 'sikeu', 'action' => 'approve', 'description' => 'Menolak antrean approval keuangan'],
            ['name' => 'Lihat Kas Kecil', 'slug' => 'sikeu.kaskecil.read', 'module' => 'sikeu', 'action' => 'read', 'description' => 'Melihat unit, saldo, transaksi, & pengajuan kas kecil'],
            ['name' => 'Input Transaksi Kas Kecil', 'slug' => 'sikeu.kaskecil.transaksi', 'module' => 'sikeu', 'action' => 'create', 'description' => 'Mencatat transaksi pengeluaran kas kecil (saldo & jurnal otomatis)'],
            ['name' => 'Ajukan Kas Langsung Kas Kecil', 'slug' => 'sikeu.kaskecil.pengajuan', 'module' => 'sikeu', 'action' => 'create', 'description' => 'Mengajukan pengisian kas langsung / top-up kas kecil'],
            ['name' => 'Approve Kas Langsung Kas Kecil', 'slug' => 'sikeu.kaskecil.approve', 'module' => 'sikeu', 'action' => 'approve', 'description' => 'Menyetujui / menolak pengajuan kas langsung kas kecil'],

            // ── MODUL SINAPRA (SARANA, PRASARANA, & ASET) ─────────────────────────
            ['name' => 'Lihat Dashboard SINAPRA', 'slug' => 'sinapra.dashboard.read', 'module' => 'sinapra', 'action' => 'read', 'description' => 'Melihat dashboard & ringkasan aset/ruangan'],
            ['name' => 'Lihat Gedung', 'slug' => 'sinapra.gedung.read', 'module' => 'sinapra', 'action' => 'read', 'description' => 'Melihat daftar & detail data gedung'],
            ['name' => 'Tambah Gedung', 'slug' => 'sinapra.gedung.create', 'module' => 'sinapra', 'action' => 'create', 'description' => 'Menambah data gedung baru'],
            ['name' => 'Ubah Gedung', 'slug' => 'sinapra.gedung.update', 'module' => 'sinapra', 'action' => 'update', 'description' => 'Mengubah data gedung'],
            ['name' => 'Hapus Gedung', 'slug' => 'sinapra.gedung.delete', 'module' => 'sinapra', 'action' => 'delete', 'description' => 'Menghapus data gedung'],
            ['name' => 'Lihat Ruangan', 'slug' => 'sinapra.ruangan.read', 'module' => 'sinapra', 'action' => 'read', 'description' => 'Melihat daftar & fasilitas ruangan'],
            ['name' => 'Tambah Ruangan', 'slug' => 'sinapra.ruangan.create', 'module' => 'sinapra', 'action' => 'create', 'description' => 'Menambah ruangan baru'],
            ['name' => 'Ubah Ruangan', 'slug' => 'sinapra.ruangan.update', 'module' => 'sinapra', 'action' => 'update', 'description' => 'Mengubah data & fasilitas ruangan'],
            ['name' => 'Hapus Ruangan', 'slug' => 'sinapra.ruangan.delete', 'module' => 'sinapra', 'action' => 'delete', 'description' => 'Menghapus data ruangan'],
            ['name' => 'Lihat Kategori Aset', 'slug' => 'sinapra.kategori_aset.read', 'module' => 'sinapra', 'action' => 'read', 'description' => 'Melihat kategori aset & tarif penyusutan'],
            ['name' => 'Tambah Kategori Aset', 'slug' => 'sinapra.kategori_aset.create', 'module' => 'sinapra', 'action' => 'create', 'description' => 'Menambah kategori aset baru'],
            ['name' => 'Ubah Kategori Aset', 'slug' => 'sinapra.kategori_aset.update', 'module' => 'sinapra', 'action' => 'update', 'description' => 'Mengubah data kategori aset'],
            ['name' => 'Hapus Kategori Aset', 'slug' => 'sinapra.kategori_aset.delete', 'module' => 'sinapra', 'action' => 'delete', 'description' => 'Menghapus kategori aset'],
            ['name' => 'Lihat Data Aset', 'slug' => 'sinapra.aset.read', 'module' => 'sinapra', 'action' => 'read', 'description' => 'Melihat daftar & detail inventaris aset'],
            ['name' => 'Tambah Data Aset', 'slug' => 'sinapra.aset.create', 'module' => 'sinapra', 'action' => 'create', 'description' => 'Menambah barang/aset baru'],
            ['name' => 'Ubah Data Aset', 'slug' => 'sinapra.aset.update', 'module' => 'sinapra', 'action' => 'update', 'description' => 'Mengubah kondisi, status, & nilai aset'],
            ['name' => 'Hapus Data Aset', 'slug' => 'sinapra.aset.delete', 'module' => 'sinapra', 'action' => 'delete', 'description' => 'Menghapus/menghapuskan data aset'],
            ['name' => 'Lihat Peminjaman Ruangan', 'slug' => 'sinapra.peminjaman_ruangan.read', 'module' => 'sinapra', 'action' => 'read', 'description' => 'Melihat permohonan & riwayat peminjaman ruangan'],
            ['name' => 'Ajukan Peminjaman Ruangan', 'slug' => 'sinapra.peminjaman_ruangan.create', 'module' => 'sinapra', 'action' => 'create', 'description' => 'Mengajukan peminjaman ruangan'],
            ['name' => 'Approve Peminjaman Ruangan', 'slug' => 'sinapra.peminjaman_ruangan.approve', 'module' => 'sinapra', 'action' => 'update', 'description' => 'Menyetujui/menolak peminjaman ruangan'],
            ['name' => 'Hapus Peminjaman Ruangan', 'slug' => 'sinapra.peminjaman_ruangan.delete', 'module' => 'sinapra', 'action' => 'delete', 'description' => 'Batalkan/hapus peminjaman ruangan'],
            ['name' => 'Lihat Peminjaman Aset', 'slug' => 'sinapra.peminjaman_aset.read', 'module' => 'sinapra', 'action' => 'read', 'description' => 'Melihat permohonan peminjaman aset'],
            ['name' => 'Ajukan Peminjaman Aset', 'slug' => 'sinapra.peminjaman_aset.create', 'module' => 'sinapra', 'action' => 'create', 'description' => 'Mengajukan peminjaman barang/aset'],
            ['name' => 'Approve Peminjaman Aset', 'slug' => 'sinapra.peminjaman_aset.approve', 'module' => 'sinapra', 'action' => 'update', 'description' => 'Menyetujui & memproses pengembalian aset'],
            ['name' => 'Hapus Peminjaman Aset', 'slug' => 'sinapra.peminjaman_aset.delete', 'module' => 'sinapra', 'action' => 'delete', 'description' => 'Batalkan/hapus peminjaman aset'],
            ['name' => 'Lihat Maintenance Log', 'slug' => 'sinapra.maintenance.read', 'module' => 'sinapra', 'action' => 'read', 'description' => 'Melihat log tiket perawatan & perbaikan'],
            ['name' => 'Buat Tiket Maintenance', 'slug' => 'sinapra.maintenance.create', 'module' => 'sinapra', 'action' => 'create', 'description' => 'Melaporkan kerusakan & buat tiket perbaikan'],
            ['name' => 'Ubah Status Maintenance', 'slug' => 'sinapra.maintenance.update', 'module' => 'sinapra', 'action' => 'update', 'description' => 'Mengubah status, biaya, & teknisi perbaikan'],
            ['name' => 'Hapus Maintenance Log', 'slug' => 'sinapra.maintenance.delete', 'module' => 'sinapra', 'action' => 'delete', 'description' => 'Menghapus tiket maintenance'],
            ['name' => 'Lihat Pengajuan Pengadaan', 'slug' => 'sinapra.pengadaan.read', 'module' => 'sinapra', 'action' => 'read', 'description' => 'Melihat usulan pengadaan barang baru'],
            ['name' => 'Buat Pengajuan Pengadaan', 'slug' => 'sinapra.pengadaan.create', 'module' => 'sinapra', 'action' => 'create', 'description' => 'Mengajukan usulan pengadaan barang baru'],
            ['name' => 'Ubah / Approve Pengadaan', 'slug' => 'sinapra.pengadaan.approve', 'module' => 'sinapra', 'action' => 'update', 'description' => 'Memproses & menyetujui usulan pengadaan'],
            ['name' => 'Hapus Pengajuan Pengadaan', 'slug' => 'sinapra.pengadaan.delete', 'module' => 'sinapra', 'action' => 'delete', 'description' => 'Menghapus usulan pengadaan barang'],
            ['name' => 'Kelola Penugasan Laboran', 'slug' => 'sinapra.laboran.manage', 'module' => 'sinapra', 'action' => 'update', 'description' => 'Menugaskan staf laboran ke ruangan laboratorium'],
            ['name' => 'Approve Peminjaman Aset Tahap Laboran', 'slug' => 'sinapra.peminjaman_aset.approve_laboran', 'module' => 'sinapra', 'action' => 'update', 'description' => 'Verifikasi dan persetujuan peminjaman aset lab oleh laboran'],
            ['name' => 'Approve Peminjaman Ruangan Tahap Laboran', 'slug' => 'sinapra.peminjaman_ruangan.approve_laboran', 'module' => 'sinapra', 'action' => 'update', 'description' => 'Verifikasi dan persetujuan peminjaman ruangan lab oleh laboran'],
            ['name' => 'Lihat BHP Laboratorium', 'slug' => 'sinapra.bhp.read', 'module' => 'sinapra', 'action' => 'read', 'description' => 'Melihat daftar bahan habis pakai lab & mutasi stok'],
            ['name' => 'Kelola BHP Laboratorium', 'slug' => 'sinapra.bhp.manage', 'module' => 'sinapra', 'action' => 'update', 'description' => 'CRUD & transaksi mutasi masuk/keluar BHP lab'],
            ['name' => 'Lihat Bebas Tanggungan Lab', 'slug' => 'sinapra.bebas_tanggungan.read', 'module' => 'sinapra', 'action' => 'read', 'description' => 'Melihat permohonan surat bebas tanggungan lab'],
            ['name' => 'Ajukan Bebas Tanggungan Lab', 'slug' => 'sinapra.bebas_tanggungan.create', 'module' => 'sinapra', 'action' => 'create', 'description' => 'Mengajukan permohonan surat bebas tanggungan lab'],
            ['name' => 'Approve Bebas Tanggungan Lab', 'slug' => 'sinapra.bebas_tanggungan.approve', 'module' => 'sinapra', 'action' => 'update', 'description' => 'Memverifikasi & menyetujui surat bebas tanggungan lab'],
            ['name' => 'Lihat Kalibrasi Alat Lab', 'slug' => 'sinapra.kalibrasi.read', 'module' => 'sinapra', 'action' => 'read', 'description' => 'Melihat jadwal & sertifikat kalibrasi alat presisi'],
            ['name' => 'Kelola Kalibrasi Alat Lab', 'slug' => 'sinapra.kalibrasi.manage', 'module' => 'sinapra', 'action' => 'update', 'description' => 'Mencatat & memperbarui riwayat kalibrasi alat presisi'],
            ['name' => 'Lihat Stock Opname', 'slug' => 'sinapra.opname.read', 'module' => 'sinapra', 'action' => 'read', 'description' => 'Melihat daftar & detail sesi stock opname aset'],
            ['name' => 'Kelola Stock Opname', 'slug' => 'sinapra.opname.manage', 'module' => 'sinapra', 'action' => 'update', 'description' => 'Membuat sesi & menginput hasil pemeriksaan stock opname'],
            ['name' => 'Lihat Mutasi Aset', 'slug' => 'sinapra.mutasi.read', 'module' => 'sinapra', 'action' => 'read', 'description' => 'Melihat riwayat & pengajuan mutasi aset antar-ruang'],
            ['name' => 'Kelola Mutasi Aset', 'slug' => 'sinapra.mutasi.manage', 'module' => 'sinapra', 'action' => 'update', 'description' => 'Mengajukan & menyetujui mutasi aset antar-ruang'],
            ['name' => 'Lihat Disposal Aset', 'slug' => 'sinapra.disposal.read', 'module' => 'sinapra', 'action' => 'read', 'description' => 'Melihat daftar BAP pemusnahan & pelelangan aset'],
            ['name' => 'Kelola Disposal Aset', 'slug' => 'sinapra.disposal.manage', 'module' => 'sinapra', 'action' => 'update', 'description' => 'Mengajukan & memproses BAP pemutihan/disposal aset'],

            // ── MODUL SPMB (PENERIMAAN MAHASISWA BARU) ──────────────────────
            ['name' => 'Portal Calon Mahasiswa', 'slug' => 'spmb.student.read', 'module' => 'spmb', 'action' => 'read', 'description' => 'Akses dashboard & formulir registrasi calon mahasiswa'],
            ['name' => 'Kelola Admin SPMB', 'slug' => 'spmb.manage', 'module' => 'spmb', 'action' => 'update', 'description' => 'Akses penuh pengelolaan data & administrasi SPMB'],

            // ── MODUL SIAKAD (GRANULAR LEVEL PERMISSION) ──────────
            ['name' => 'Lihat Dashboard Akademik', 'slug' => 'siakad.dashboard.read', 'module' => 'siakad', 'action' => 'read', 'description' => 'Melihat dashboard akademik sesuai role'],
            
            // Level Mahasiswa & Dosen & Admin
            ['name' => 'Lihat Jadwal Kuliah & RPS', 'slug' => 'siakad.kelas.read', 'module' => 'siakad', 'action' => 'read', 'description' => 'Melihat jadwal kuliah & dokumen RPS'],
            ['name' => 'Lihat KRS Mahasiswa', 'slug' => 'siakad.krs.read', 'module' => 'siakad', 'action' => 'read', 'description' => 'Melihat kartu rencana studi'],
            ['name' => 'Input KRS Mahasiswa', 'slug' => 'siakad.krs.create', 'module' => 'siakad', 'action' => 'create', 'description' => 'Mengisi rencana studi semester'],
            ['name' => 'Setujui KRS Mahasiswa', 'slug' => 'siakad.krs.approve', 'module' => 'siakad', 'action' => 'approve', 'description' => 'Menyetujui KRS mahasiswa bimbingan wali'],
            ['name' => 'Lihat KHS & Nilai', 'slug' => 'siakad.nilai.read', 'module' => 'siakad', 'action' => 'read', 'description' => 'Melihat kartu hasil studi & transkrip nilai'],
            ['name' => 'Input Nilai Mahasiswa', 'slug' => 'siakad.nilai.manage', 'module' => 'siakad', 'action' => 'update', 'description' => 'Menginput bobot nilai mahasiswa pengampu'],
            ['name' => 'Lihat Mahasiswa Bimbingan', 'slug' => 'siakad.mahasiswa.read', 'module' => 'siakad', 'action' => 'read', 'description' => 'Melihat direktori mahasiswa wali'],

            // Level Administrator / BAAK Only
            ['name' => 'Kelola Master Fakultas & Prodi', 'slug' => 'siakad.master.manage', 'module' => 'siakad', 'action' => 'update', 'description' => 'Mengelola fakultas, prodi, dan kurikulum OBE'],
            ['name' => 'Kelola Master Mata Kuliah', 'slug' => 'siakad.matakuliah.manage', 'module' => 'siakad', 'action' => 'update', 'description' => 'Mengelola mata kuliah dan prasyarat'],
            ['name' => 'Kelola Data Mahasiswa & NIM', 'slug' => 'siakad.mahasiswa.manage', 'module' => 'siakad', 'action' => 'update', 'description' => 'CRUD data mahasiswa dan generate NIM'],
            ['name' => 'Kelola Konversi Transfer', 'slug' => 'siakad.konversi.manage', 'module' => 'siakad', 'action' => 'update', 'description' => 'Input dan persetujuan penyetaraan nilai transfer'],
            ['name' => 'Kelola Data Dosen Pengajar', 'slug' => 'siakad.dosen.manage', 'module' => 'siakad', 'action' => 'update', 'description' => 'CRUD data dosen pengajar & NIDN'],
            ['name' => 'Kelola Buka Kelas & Ruang', 'slug' => 'siakad.kelas.manage', 'module' => 'siakad', 'action' => 'update', 'description' => 'Membuka kelas perkuliahan & alokasi ruang SINAPRA'],
            ['name' => 'Kelola Integrasi Neo Feeder', 'slug' => 'siakad.feeder.manage', 'module' => 'siakad', 'action' => 'update', 'description' => 'Sinkronisasi data ke Neo Feeder PDDIKTI'],
            ['name' => 'Kelola Penerima Beasiswa Mahasiswa', 'slug' => 'siakad.beasiswa.manage', 'module' => 'siakad', 'action' => 'update', 'description' => 'Menetapkan mahasiswa penerima beasiswa'],
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(
                ['slug' => $perm['slug']],
                [
                    'name' => $perm['name'],
                    'module' => $perm['module'],
                    'action' => $perm['action'],
                    'description' => $perm['description'],
                ]
            );
        }

        // ── AUTO MAP ROLE PERMISSIONS ───────────────────────────────
        $allPermissions = Permission::all();
        $adminRole = Role::where('slug', 'admin')->first();
        $superAdminRole = Role::where('slug', 'superadmin')->first();
        $dosenRole = Role::where('slug', 'dosen')->first();
        $tendikRole = Role::where('slug', 'tendik')->first();
        $mahasiswaRole = Role::where('slug', 'mahasiswa')->first();
        $operatorSdmRole = Role::where('slug', 'operator_sdm')->first();
        $operatorSikeuRole = Role::where('slug', 'operator_sikeu')->first();
        $adminLppmRole = Role::where('slug', 'admin_lppm')->first();
        $adminSimpegRole = Role::where('slug', 'admin_simpeg')->first();
        $adminSarprasRole = Role::where('slug', 'admin_sarpras')->first();
        $adminLaboratoriumRole = Role::where('slug', 'admin_laboratorium')->first();
        $calonMhsRole = Role::where('slug', 'calon_mhs')->first();
        $pimpinanRole = Role::where('slug', 'pimpinan')->first();
        $adminKeuAkuntansiRole = Role::where('slug', 'admin_keuangan_akuntansi')->first();
        $adminKeuPembayaranRole = Role::where('slug', 'admin_keuangan_pembayaran')->first();
        $petugasKasKecilRole = Role::where('slug', 'petugas_kas_kecil')->first();

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

        // 2. Mahasiswa -> Hanya level mahasiswa mandiri
        if ($mahasiswaRole) {
            $mhsSlugs = [
                'siakad.dashboard.read',
                'siakad.kelas.read',
                'siakad.krs.read',
                'siakad.krs.create',
                'siakad.nilai.read',
                'sikeu.tagihan.read',
                'sikeu.dispensasi.read',
                'sikeu.dispensasi.create',
                'sinapra.bebas_tanggungan.read',
                'sinapra.bebas_tanggungan.create',
            ];
            $perms = Permission::whereIn('slug', $mhsSlugs)->get();
            foreach ($perms as $p) {
                RolePermission::firstOrCreate(['role_id' => $mahasiswaRole->id, 'permission_id' => $p->id]);
            }
        }

        // 3. Dosen -> Dosen Wali & Pengampu
        if ($dosenRole) {
            $dosenSlugs = [
                'siakad.dashboard.read',
                'siakad.kelas.read',
                'siakad.krs.read',
                'siakad.krs.approve',
                'siakad.nilai.read',
                'siakad.nilai.manage',
                'siakad.mahasiswa.read',
                'siakad.konversi.manage',
                // SIMPEG mandiri
                'simpeg.dashboard.read',
                'simpeg.pegawai.read',
                'simpeg.dokumen.read', 'simpeg.dokumen.create',
                'simpeg.cuti.read', 'simpeg.cuti.create',
                'simpeg.presensi.read', 'simpeg.presensi.create',
                'simpeg.payroll.read',
                'simpeg.kompetensi.read', 'simpeg.kompetensi.create', 'simpeg.kompetensi.update', 'simpeg.kompetensi.delete',
                'simpeg.surat_tugas.read', 'simpeg.surat_tugas.create', 'simpeg.surat_tugas.update',
                'simpeg.izin_kerja.read', 'simpeg.izin_kerja.create', 'simpeg.izin_kerja.update', 'simpeg.izin_kerja.delete',
                'simpeg.sk_pegawai.read', 'simpeg.sk_pegawai.create', 'simpeg.sk_pegawai.update', 'simpeg.sk_pegawai.delete',
                'simpeg.usulan_jafung.read', 'simpeg.usulan_jafung.create',
                'simpeg.kinerja.read', 'simpeg.kinerja.create',
                // SIPPM mandiri
                'sippm.dashboard.read',
                'sippm.proposal.read', 'sippm.proposal.create', 'sippm.proposal.update', 'sippm.proposal.submit',
            ];
            $perms = Permission::whereIn('slug', $dosenSlugs)->get();
            foreach ($perms as $p) {
                RolePermission::create(['role_id' => $dosenRole->id, 'permission_id' => $p->id]);
            }
        }

        // 3b. Tenaga Kependidikan (Tendik) -> Portal Mandiri Tendik
        if ($tendikRole) {
            $tendikSlugs = [
                'simpeg.dashboard.read',
                'simpeg.pegawai.read',
                'simpeg.dokumen.read', 'simpeg.dokumen.create',
                'simpeg.cuti.read', 'simpeg.cuti.create',
                'simpeg.presensi.read', 'simpeg.presensi.create',
                'simpeg.payroll.read',
                'simpeg.kompetensi.read', 'simpeg.kompetensi.create', 'simpeg.kompetensi.update', 'simpeg.kompetensi.delete',
                'simpeg.surat_tugas.read', 'simpeg.surat_tugas.create', 'simpeg.surat_tugas.update',
                'simpeg.izin_kerja.read', 'simpeg.izin_kerja.create', 'simpeg.izin_kerja.update', 'simpeg.izin_kerja.delete',
                'simpeg.sk_pegawai.read', 'simpeg.sk_pegawai.create', 'simpeg.sk_pegawai.update', 'simpeg.sk_pegawai.delete',
                'simpeg.kinerja.read', 'simpeg.kinerja.create',
            ];
            $perms = Permission::whereIn('slug', $tendikSlugs)->get();
            foreach ($perms as $p) {
                RolePermission::create(['role_id' => $tendikRole->id, 'permission_id' => $p->id]);
            }
        }

        // 4. Operator SDM & Admin SIMPEG
        if ($operatorSdmRole) {
            $simpegPerms = Permission::where('module', 'simpeg')->get();
            foreach ($simpegPerms as $p) {
                RolePermission::create(['role_id' => $operatorSdmRole->id, 'permission_id' => $p->id]);
            }
        }
        if ($adminSimpegRole) {
            $simpegPerms = Permission::where('module', 'simpeg')->get();
            foreach ($simpegPerms as $p) {
                RolePermission::create(['role_id' => $adminSimpegRole->id, 'permission_id' => $p->id]);
            }
        }

        // 5. Operator SIKEU
        if ($operatorSikeuRole) {
            $sikeuPerms = Permission::where('module', 'sikeu')->get();
            foreach ($sikeuPerms as $p) {
                RolePermission::create(['role_id' => $operatorSikeuRole->id, 'permission_id' => $p->id]);
            }
        }

        // 6. Admin LPPM
        if ($adminLppmRole) {
            $sippmPerms = Permission::where('module', 'sippm')->get();
            foreach ($sippmPerms as $p) {
                RolePermission::create(['role_id' => $adminLppmRole->id, 'permission_id' => $p->id]);
            }
        }

        // 7. Admin SARPRAS
        if ($adminSarprasRole) {
            $sinapraPerms = Permission::where('module', 'sinapra')->get();
            foreach ($sinapraPerms as $p) {
                RolePermission::create(['role_id' => $adminSarprasRole->id, 'permission_id' => $p->id]);
            }
        }

        // 7b. Admin Laboratorium (Laboran)
        if ($adminLaboratoriumRole) {
            $laboranSlugs = [
                'sinapra.dashboard.read',
                'sinapra.ruangan.read',
                'sinapra.aset.read',
                'sinapra.aset.create',
                'sinapra.aset.update',
                'sinapra.aset.delete',
                'sinapra.peminjaman_ruangan.read',
                'sinapra.peminjaman_ruangan.approve_laboran',
                'sinapra.peminjaman_aset.read',
                'sinapra.peminjaman_aset.approve_laboran',
                'sinapra.maintenance.read',
                'sinapra.maintenance.create',
                'sinapra.maintenance.update',
                'sinapra.bhp.read',
                'sinapra.bhp.manage',
                'sinapra.bebas_tanggungan.read',
                'sinapra.bebas_tanggungan.approve',
                'sinapra.kalibrasi.read',
                'sinapra.kalibrasi.manage',
                'sinapra.opname.read',
                'sinapra.opname.manage',
                'sinapra.mutasi.read',
                'sinapra.mutasi.manage',
                'sinapra.disposal.read',
            ];
            $laboranPerms = Permission::whereIn('slug', $laboranSlugs)->get();
            foreach ($laboranPerms as $p) {
                RolePermission::firstOrCreate(['role_id' => $adminLaboratoriumRole->id, 'permission_id' => $p->id]);
            }
        }

        // 10. Calon Mahasiswa -> Akses Khusus Portal & Dashboard Calon Mahasiswa
        if ($calonMhsRole) {
            $studentSlugs = ['spmb.student.read', 'spmb.dashboard.read', 'spmb.pendaftaran.read'];
            $perms = Permission::whereIn('slug', $studentSlugs)->get();
            foreach ($perms as $p) {
                RolePermission::create(['role_id' => $calonMhsRole->id, 'permission_id' => $p->id]);
            }
        }

        // 11. Pimpinan -> Dashboard & Approvals Executive Summary
        if ($pimpinanRole) {
            $pimpinanSlugs = [
                'iam.audit_logs.read',
                'simpeg.dashboard.read',
                'simpeg.surat_tugas.read',
                'simpeg.surat_tugas.approve',
                'simpeg.izin_kerja.read',
                'simpeg.izin_kerja.approve',
                'simpeg.sk_pegawai.read',
                'simpeg.sk_pegawai.verify',
                'simpeg.cuti.read',
                'simpeg.cuti.update',
                'simpeg.kinerja.read',
                'simpeg.kinerja.update',
                'sippm.dashboard.read',
                'sikeu.dashboard.read',
                'sikeu.tagihan.read',
                'sikeu.akuntansi.read',
                'sikeu.approval.read',
                'sikeu.approval.approve',
                'sikeu.approval.reject',
                'sikeu.dispensasi.read',
                'sikeu.dispensasi.approve',
                'sikeu.dispensasi.reject',
                'sikeu.pengajuan.read',
                'sikeu.pengajuan.approve',
                'sikeu.pengajuan.reject',
                'sikeu.lpj.verify',
                'sikeu.laporan.read',
            ];
            $perms = Permission::whereIn('slug', $pimpinanSlugs)->get();
            foreach ($perms as $p) {
                RolePermission::create(['role_id' => $pimpinanRole->id, 'permission_id' => $p->id]);
            }
        }

        // 12. Admin Keuangan Akuntansi -> Jurnal, Buku Besar, COA, laporan, rekening
        if ($adminKeuAkuntansiRole) {
            $akuntansiSlugs = [
                'sikeu.dashboard.read',
                'sikeu.akuntansi.read',
                'sikeu.akuntansi.manage',
                'sikeu.laporan.read',
                'sikeu.unitkas.read',
                'sikeu.kas.manage',
                'sikeu.pengeluaran.read',
                'sikeu.pemasukan.read',
                'sikeu.pajak.read',
                'sikeu.kaskecil.read',
                'sikeu.kaskecil.approve',
            ];
            $perms = Permission::whereIn('slug', $akuntansiSlugs)->get();
            foreach ($perms as $p) {
                RolePermission::create(['role_id' => $adminKeuAkuntansiRole->id, 'permission_id' => $p->id]);
            }
        }

        // 13. Admin Keuangan Pembayaran Mahasiswa -> Tagihan, kasir, validasi, gateway
        if ($adminKeuPembayaranRole) {
            $pembayaranSlugs = [
                'sikeu.dashboard.read',
                'sikeu.tagihan.read',
                'sikeu.tagihan.create',
                'sikeu.dispensasi.read',
                'sikeu.master.manage',
                'sikeu.unitkas.read',
                'sikeu.kas.manage',
                'sikeu.paymentgateway.manage',
            ];
            $perms = Permission::whereIn('slug', $pembayaranSlugs)->get();
            foreach ($perms as $p) {
                RolePermission::create(['role_id' => $adminKeuPembayaranRole->id, 'permission_id' => $p->id]);
            }
        }

        // 14. Petugas Kas Kecil -> lihat unit sendiri, input transaksi keluar, ajukan kas langsung
        if ($petugasKasKecilRole) {
            $petugasKasKecilSlugs = [
                'sikeu.kaskecil.read',
                'sikeu.kaskecil.transaksi',
                'sikeu.kaskecil.pengajuan',
            ];
            $perms = Permission::whereIn('slug', $petugasKasKecilSlugs)->get();
            foreach ($perms as $p) {
                RolePermission::create(['role_id' => $petugasKasKecilRole->id, 'permission_id' => $p->id]);
            }
        }
    }
}
