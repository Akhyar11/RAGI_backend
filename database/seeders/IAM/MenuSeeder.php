<?php

namespace Database\Seeders\IAM;

use Illuminate\Database\Seeder;
use App\Models\Menu;
use App\Models\Permission;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MenuSeeder extends Seeder
{
    public function run(): void
    {
        Schema::disableForeignKeyConstraints();
        Menu::truncate();
        Schema::enableForeignKeyConstraints();

        $menus = [
            // ── MODUL SSO (IAM) ───────────────────────────────────
            [
                'name' => 'Dashboard Utama',
                'url' => '/dashboard',
                'icon' => 'FaHome',
                'module' => 'sso',
                'order_index' => 1,
            ],
            [
                'name' => 'KONTROL AKSES SSO',
                'url' => '#iam_section',
                'icon' => 'FaShieldAlt',
                'module' => 'sso',
                'order_index' => 2,
                'children' => [
                    ['name' => 'Pengguna Portal', 'url' => '/admin/users', 'icon' => 'FaUsers', 'module' => 'sso', 'permission_slug' => 'iam.users.read', 'order_index' => 1],
                    ['name' => 'Master Role', 'url' => '/admin/roles', 'icon' => 'FaShieldAlt', 'module' => 'sso', 'permission_slug' => 'iam.roles.read', 'order_index' => 2],
                    ['name' => 'Hak Akses (Permissions)', 'url' => '/admin/permissions', 'icon' => 'FaList', 'module' => 'sso', 'permission_slug' => 'iam.permissions.read', 'order_index' => 3],
                    ['name' => 'Plotting User Role', 'url' => '/admin/user-roles', 'icon' => 'FaUsers', 'module' => 'sso', 'permission_slug' => 'iam.user_roles.manage', 'order_index' => 4],
                    ['name' => 'Plotting Role Permission', 'url' => '/admin/role-permissions', 'icon' => 'FaClipboardCheck', 'module' => 'sso', 'permission_slug' => 'iam.permissions.manage', 'order_index' => 5],
                    ['name' => 'Plotting Role Menu', 'url' => '/admin/role-menus', 'icon' => 'FaList', 'module' => 'sso', 'permission_slug' => 'iam.roles.update', 'order_index' => 6],
                    ['name' => 'Master Menu', 'url' => '/admin/menus', 'icon' => 'FaList', 'module' => 'sso', 'permission_slug' => 'iam.roles.update', 'order_index' => 7],
                    ['name' => 'Master Modul', 'url' => '/admin/modules', 'icon' => 'FaList', 'module' => 'sso', 'permission_slug' => 'iam.roles.update', 'order_index' => 8],
                ]
            ],
            [
                'name' => 'LOG & AUDIT',
                'url' => '#audit_section',
                'icon' => 'FaFileAlt',
                'module' => 'sso',
                'order_index' => 3,
                'children' => [
                    ['name' => 'Sesi Login Aktif', 'url' => '/admin/sessions', 'icon' => 'FaUsers', 'module' => 'sso', 'permission_slug' => 'iam.sessions.read', 'order_index' => 1],
                    ['name' => 'Audit Log Aktivitas', 'url' => '/admin/audit-logs', 'icon' => 'FaFileAlt', 'module' => 'sso', 'permission_slug' => 'iam.audit_logs.read', 'order_index' => 2],
                    ['name' => 'Pengaturan Sistem', 'url' => '/iam/settings', 'icon' => 'FaCogs', 'module' => 'sso', 'permission_slug' => 'iam.roles.update', 'order_index' => 3],
                ]
            ],
            [
                'name' => 'AKUN & KEAMANAN',
                'url' => '#akun_keamanan',
                'icon' => 'FaShieldAlt',
                'module' => 'sso',
                'order_index' => 999,
                'children' => [
                    ['name' => 'Profil Saya', 'url' => '/profile', 'icon' => 'FaUser', 'module' => 'sso', 'order_index' => 1],
                    ['name' => 'Sesi Perangkat', 'url' => '/profile/sessions', 'icon' => 'FaSmartphone', 'module' => 'sso', 'order_index' => 2],
                    ['name' => 'Autentikasi 2FA', 'url' => '/profile/mfa', 'icon' => 'FaShieldCheck', 'module' => 'sso', 'order_index' => 3],
                ]
            ],

            // ── MODUL SIMPEG ───────────────────────────────────────
            [
                'name' => 'Dashboard SIMPEG',
                'url' => '/simpeg',
                'icon' => 'FaChartPie',
                'module' => 'simpeg',
                'permission_slug' => 'simpeg.dashboard.read',
                'order_index' => 1,
            ],
            [
                'name' => 'MANAJEMEN KEPEGAWAIAN',
                'url' => '#kepegawaian_simpeg',
                'icon' => 'FaUsers',
                'module' => 'simpeg',
                'order_index' => 2,
                'children' => [
                    ['name' => 'Data Pegawai', 'url' => '/simpeg/pegawai', 'icon' => 'FaUsers', 'module' => 'simpeg', 'permission_slug' => 'simpeg.pegawai.read', 'order_index' => 1],
                    ['name' => 'E-File & Dokumen', 'url' => '/simpeg/dokumen', 'icon' => 'FaFileAlt', 'module' => 'simpeg', 'permission_slug' => 'simpeg.dokumen.read', 'order_index' => 2],
                ]
            ],
            [
                'name' => 'LAYANAN & KINERJA',
                'url' => '#layanan_simpeg',
                'icon' => 'FaClipboardCheck',
                'module' => 'simpeg',
                'order_index' => 3,
                'children' => [
                    ['name' => 'Presensi & Absensi', 'url' => '/simpeg/presensi', 'icon' => 'FaClock', 'module' => 'simpeg', 'permission_slug' => 'simpeg.presensi.read', 'order_index' => 1],
                    ['name' => 'Pengajuan Cuti', 'url' => '/simpeg/cuti', 'icon' => 'FaCalendar', 'module' => 'simpeg', 'permission_slug' => 'simpeg.cuti.read', 'order_index' => 2],
                    ['name' => 'Payroll & Slip Gaji', 'url' => '/simpeg/payroll', 'icon' => 'FaMoneyBillWave', 'module' => 'simpeg', 'permission_slug' => 'simpeg.payroll.read', 'order_index' => 3],
                    ['name' => 'Usulan Jafung (KUM)', 'url' => '/simpeg/usulan-jafung', 'icon' => 'FaAward', 'module' => 'simpeg', 'permission_slug' => 'simpeg.usulan_jafung.read', 'order_index' => 4],
                    ['name' => 'Evaluasi Kinerja SKP', 'url' => '/simpeg/kinerja', 'icon' => 'FaChartPie', 'module' => 'simpeg', 'permission_slug' => 'simpeg.kinerja.read', 'order_index' => 5],
                ]
            ],
            [
                'name' => 'MASTER DATA SDM',
                'url' => '#master_simpeg',
                'icon' => 'FaDatabase',
                'module' => 'simpeg',
                'order_index' => 4,
                'children' => [
                    ['name' => 'Unit Kerja', 'url' => '/simpeg/unit-kerja', 'icon' => 'FaSitemap', 'module' => 'simpeg', 'permission_slug' => 'simpeg.unit_kerja.read', 'order_index' => 1],
                    ['name' => 'Jabatan & Jafung', 'url' => '/simpeg/jabatan', 'icon' => 'FaBriefcase', 'module' => 'simpeg', 'permission_slug' => 'simpeg.jabatan.read', 'order_index' => 2],
                ]
            ],

            // ── MODUL SIPPM ───────────────────────────────────────
            [
                'name' => 'Dashboard SIPPM',
                'url' => '/sippm',
                'icon' => 'FaChartPie',
                'module' => 'sippm',
                'permission_slug' => 'sippm.dashboard.read',
                'order_index' => 1,
            ],
            [
                'name' => 'MANAJEMEN PROPOSAL',
                'url' => '#proposal_sippm',
                'icon' => 'FaFileAlt',
                'module' => 'sippm',
                'order_index' => 2,
                'children' => [
                    ['name' => 'Daftar Proposal', 'url' => '/sippm/proposal', 'icon' => 'FaFileAlt', 'module' => 'sippm', 'permission_slug' => 'sippm.proposal.read', 'order_index' => 1],
                    ['name' => 'Kontrak Penelitian', 'url' => '/sippm/kontrak', 'icon' => 'FaClipboardCheck', 'module' => 'sippm', 'permission_slug' => 'sippm.kontrak.read', 'order_index' => 2],
                    ['name' => 'Pencairan Dana', 'url' => '/sippm/pencairan', 'icon' => 'FaCreditCard', 'module' => 'sippm', 'permission_slug' => 'sippm.pencairan.read', 'order_index' => 3],
                    ['name' => 'Pengumuman Hibah', 'url' => '/sippm/pengumuman', 'icon' => 'FaAward', 'module' => 'sippm', 'permission_slug' => 'sippm.pengumuman.read', 'order_index' => 4],
                ]
            ],
            [
                'name' => 'LUARAN & STANDAR IKU',
                'url' => '#luaran_sippm',
                'icon' => 'FaAward',
                'module' => 'sippm',
                'order_index' => 3,
                'children' => [
                    ['name' => 'Luaran Publikasi', 'url' => '/sippm/luaran/publikasi', 'icon' => 'FaBookOpen', 'module' => 'sippm', 'permission_slug' => 'sippm.luaran.read', 'order_index' => 1],
                    ['name' => 'Luaran HKI & Paten', 'url' => '/sippm/luaran/hki', 'icon' => 'FaAward', 'module' => 'sippm', 'permission_slug' => 'sippm.luaran.read', 'order_index' => 2],
                    ['name' => 'Standar IKU 5', 'url' => '/sippm/iku5-standards', 'icon' => 'FaChartPie', 'module' => 'sippm', 'permission_slug' => 'sippm.iku.read', 'order_index' => 3],
                ]
            ],
            [
                'name' => 'REVIEWER & PRODI',
                'url' => '#reviewer_sippm',
                'icon' => 'FaUsers',
                'module' => 'sippm',
                'order_index' => 4,
                'children' => [
                    ['name' => 'Evaluasi Reviewer', 'url' => '/sippm/reviewer', 'icon' => 'FaClipboardCheck', 'module' => 'sippm', 'permission_slug' => 'sippm.reviewer.read', 'order_index' => 1],
                    ['name' => 'Laporan Prodi', 'url' => '/sippm/prodi', 'icon' => 'FaBuilding', 'module' => 'sippm', 'permission_slug' => 'sippm.prodi.read', 'order_index' => 2],
                ]
            ],
            [
                'name' => 'MASTER DATA SIPPM',
                'url' => '#master_sippm',
                'icon' => 'FaDatabase',
                'module' => 'sippm',
                'order_index' => 5,
                'children' => [
                    ['name' => 'Periode Hibah', 'url' => '/sippm/periode', 'icon' => 'FaCalendar', 'module' => 'sippm', 'permission_slug' => 'sippm.master.manage', 'order_index' => 1],
                    ['name' => 'Skema Penelitian', 'url' => '/sippm/skema', 'icon' => 'FaList', 'module' => 'sippm', 'permission_slug' => 'sippm.master.manage', 'order_index' => 2],
                    ['name' => 'Rubrik Penilaian', 'url' => '/sippm/rubrik', 'icon' => 'FaCheckSquare', 'module' => 'sippm', 'permission_slug' => 'sippm.master.manage', 'order_index' => 3],
                ]
            ],

            // ── MODUL SIAKAD (MENU DENGAN PEMBATASAN LEVEL ROLE & PERMISSION) ─────
            [
                'name' => 'Dashboard Akademik',
                'url' => '/siakad',
                'icon' => 'FaGraduationCap',
                'module' => 'siakad',
                'permission_slug' => 'siakad.dashboard.read',
                'order_index' => 1,
            ],
            [
                'name' => 'KRS Semester Aktif',
                'url' => '/siakad/krs',
                'icon' => 'FaClipboardCheck',
                'module' => 'siakad',
                'permission_slug' => 'siakad.krs.read',
                'order_index' => 2,
            ],
            [
                'name' => 'Jadwal Kuliah & RPS',
                'url' => '/siakad/perkuliahan/kelas',
                'icon' => 'FaCalendarCheck',
                'module' => 'siakad',
                'permission_slug' => 'siakad.kelas.read',
                'order_index' => 3,
            ],
            [
                'name' => 'KHS & Transkrip Nilai',
                'url' => '/siakad/nilai',
                'icon' => 'FaAward',
                'module' => 'siakad',
                'permission_slug' => 'siakad.nilai.read',
                'order_index' => 4,
            ],
            [
                'name' => 'MASTER AKADEMIK (BAAK)',
                'url' => '#master_siakad',
                'icon' => 'FaDatabase',
                'module' => 'siakad',
                'order_index' => 5,
                'children' => [
                    ['name' => 'Fakultas & Prodi', 'url' => '/siakad/master/fakultas', 'icon' => 'FaBuilding', 'module' => 'siakad', 'permission_slug' => 'siakad.master.manage', 'order_index' => 1],
                    ['name' => 'Kurikulum OBE', 'url' => '/siakad/master/kurikulum', 'icon' => 'FaBookOpen', 'module' => 'siakad', 'permission_slug' => 'siakad.master.manage', 'order_index' => 2],
                    ['name' => 'Mata Kuliah & Bobot', 'url' => '/siakad/master/matakuliah', 'icon' => 'FaList', 'module' => 'siakad', 'permission_slug' => 'siakad.matakuliah.manage', 'order_index' => 3],
                ]
            ],
            [
                'name' => 'CIVITAS AKADEMIKA (BAAK)',
                'url' => '#civitas_siakad',
                'icon' => 'FaUsers',
                'module' => 'siakad',
                'order_index' => 6,
                'children' => [
                    ['name' => 'Direktori Mahasiswa', 'url' => '/siakad/civitas/mahasiswa', 'icon' => 'FaUserGraduate', 'module' => 'siakad', 'permission_slug' => 'siakad.mahasiswa.read', 'order_index' => 1],
                    ['name' => 'Konversi Mahasiswa Transfer', 'url' => '/siakad/civitas/konversi', 'icon' => 'FaExchangeAlt', 'module' => 'siakad', 'permission_slug' => 'siakad.konversi.manage', 'order_index' => 2],
                    ['name' => 'Direktori Dosen Pengajar', 'url' => '/siakad/civitas/dosen', 'icon' => 'FaChalkboardTeacher', 'module' => 'siakad', 'permission_slug' => 'siakad.dosen.manage', 'order_index' => 3],
                    ['name' => 'Biodata Mahasiswa (Kelas)', 'url' => '/siakad/civitas/biodata', 'icon' => 'FaUser', 'module' => 'siakad', 'permission_slug' => 'siakad.mahasiswa.read', 'order_index' => 4],
                ]
            ],
            [
                'name' => 'INTEGRASI DIKTI (BAAK)',
                'url' => '#feeder_siakad',
                'icon' => 'FaSyncAlt',
                'module' => 'siakad',
                'order_index' => 7,
                'children' => [
                    ['name' => 'Sinkronisasi Neo Feeder', 'url' => '/siakad/feeder-sync', 'icon' => 'FaCloudUploadAlt', 'module' => 'siakad', 'permission_slug' => 'siakad.feeder.manage', 'order_index' => 1],
                ]
            ],

            // Menu SPMB (Penerimaan Mahasiswa Baru)
            // A. Menu Portal Calon Mahasiswa
            [
                'name' => 'Dashboard Saya',
                'url' => '/spmb/dashboard',
                'icon' => 'FaChartPie',
                'module' => 'spmb',
                'permission_slug' => 'spmb.student.read',
                'order_index' => 1,
            ],
            [
                'name' => 'Formulir Pendaftaran',
                'url' => '/spmb/registrasi',
                'icon' => 'FaUserPlus',
                'module' => 'spmb',
                'permission_slug' => 'spmb.student.read',
                'order_index' => 2,
            ],

            // B. Menu Pengelola Admin SPMB
            [
                'name' => 'PENDAFTARAN & VERIFIKASI',
                'url' => '#layanan_spmb',
                'icon' => 'FaList',
                'module' => 'spmb',
                'permission_slug' => 'spmb.admin.manage',
                'order_index' => 3,
                'children' => [
                    ['name' => 'Data Pendaftar & Verifikasi', 'url' => '/spmb/pendaftaran', 'icon' => 'FaUsers', 'module' => 'spmb', 'permission_slug' => 'spmb.admin.manage', 'order_index' => 1],
                ]
            ],
            [
                'name' => 'SELEKSI ADMINISTRASI',
                'url' => '#seleksi_spmb',
                'icon' => 'FaCheckSquare',
                'module' => 'spmb',
                'permission_slug' => 'spmb.admin.manage',
                'order_index' => 4,
                'children' => [
                    ['name' => 'Hasil Seleksi Administrasi', 'url' => '/spmb/seleksi', 'icon' => 'FaTrophy', 'module' => 'spmb', 'permission_slug' => 'spmb.admin.manage', 'order_index' => 1],
                ]
            ],
            [
                'name' => 'MASTER DATA SPMB',
                'url' => '#master_spmb',
                'icon' => 'FaList',
                'module' => 'spmb',
                'permission_slug' => 'spmb.admin.manage',
                'order_index' => 5,
                'children' => [
                    ['name' => 'Jalur Masuk', 'url' => '/spmb/master/jalur', 'icon' => 'FaCogs', 'module' => 'spmb', 'permission_slug' => 'spmb.admin.manage', 'order_index' => 1],
                    ['name' => 'Gelombang Penerimaan', 'url' => '/spmb/master/gelombang', 'icon' => 'FaCalendar', 'module' => 'spmb', 'permission_slug' => 'spmb.admin.manage', 'order_index' => 2],
                    ['name' => 'Kuota Program Studi', 'url' => '/spmb/master/kuota', 'icon' => 'FaChartPie', 'module' => 'spmb', 'permission_slug' => 'spmb.admin.manage', 'order_index' => 3],
                    ['name' => 'Persyaratan Berkas', 'url' => '/spmb/master/berkas-requirement', 'icon' => 'FaFileAlt', 'module' => 'spmb', 'permission_slug' => 'spmb.admin.manage', 'order_index' => 4],
                ]
            ],

            // ── MODUL SINAPRA ─────────────────────────────────────
            [
                'name' => 'Gedung & Ruangan',
                'url' => '/sinapra/gedung-ruangan',
                'icon' => 'FaBuilding',
                'module' => 'sinapra',
                'permission_slug' => 'sinapra.ruangan.read',
                'order_index' => 1,
            ],
            [
                'name' => 'Inventaris Aset',
                'url' => '/sinapra/aset',
                'icon' => 'FaBoxes',
                'module' => 'sinapra',
                'permission_slug' => 'sinapra.aset.read',
                'order_index' => 2,
            ],
            [
                'name' => 'Peminjaman',
                'url' => '/sinapra/peminjaman',
                'icon' => 'FaCalendarCheck',
                'module' => 'sinapra',
                'permission_slug' => 'sinapra.dashboard.read',
                'order_index' => 3,
            ],
            [
                'name' => 'Maintenance',
                'url' => '/sinapra/maintenance',
                'icon' => 'FaWrench',
                'module' => 'sinapra',
                'permission_slug' => 'sinapra.dashboard.read',
                'order_index' => 4,
            ],
            [
                'name' => 'Pengadaan Barang',
                'url' => '/sinapra/pengadaan',
                'icon' => 'FaShoppingCart',
                'module' => 'sinapra',
                'permission_slug' => 'sinapra.dashboard.read',
                'order_index' => 5,
            ],

            // ── MODUL SIKEU ───────────────────────────────────────
            [
                'name' => 'Dashboard Keuangan',
                'url' => '/sikeu',
                'icon' => 'FaChartPie',
                'module' => 'sikeu',
                'permission_slug' => 'sikeu.dashboard.read',
                'order_index' => 1,
            ],
            [
                'name' => 'KEUANGAN MAHASISWA',
                'url' => '#mhs_sikeu',
                'icon' => 'FaGraduationCap',
                'module' => 'sikeu',
                'permission_slug' => 'sikeu.dashboard.read',
                'order_index' => 2,
                'children' => [
                    ['name' => 'Pengaturan Tarif & Beasiswa', 'url' => '/sikeu/mahasiswa/tarif', 'icon' => 'FaDollarSign', 'module' => 'sikeu', 'permission_slug' => 'sikeu.master.manage', 'order_index' => 1],
                    ['name' => 'Tagihan SPP & UKT', 'url' => '/sikeu/tagihan', 'icon' => 'FaCreditCard', 'module' => 'sikeu', 'permission_slug' => 'sikeu.tagihan.read', 'order_index' => 2],
                    ['name' => 'Pembayaran & Kasir Loket', 'url' => '/sikeu/pembayaran', 'icon' => 'FaMoneyBillWave', 'module' => 'sikeu', 'permission_slug' => 'sikeu.pembayaran.read', 'order_index' => 3],
                    ['name' => 'Piutang Mahasiswa', 'url' => '/sikeu/piutang', 'icon' => 'FaExclamationTriangle', 'module' => 'sikeu', 'permission_slug' => 'sikeu.tagihan.read', 'order_index' => 4],
                    ['name' => 'Dispensasi Pembayaran', 'url' => '/sikeu/dispensasi', 'icon' => 'FaClipboardCheck', 'module' => 'sikeu', 'permission_slug' => 'sikeu.dispensasi.read', 'order_index' => 5],
                ]
            ],
            [
                'name' => 'OPERASIONAL PENGELUARAN',
                'url' => '#pengeluaran_sikeu',
                'icon' => 'FaMoneyBillWave',
                'module' => 'sikeu',
                'permission_slug' => 'sikeu.dashboard.read',
                'order_index' => 3,
                'children' => [
                    ['name' => 'Pengeluaran Kas', 'url' => '/sikeu/pengeluaran', 'icon' => 'FaList', 'module' => 'sikeu', 'permission_slug' => 'sikeu.pengeluaran.read', 'order_index' => 1],
                    ['name' => 'Pemasukan Kas Non-Akademik', 'url' => '/sikeu/pemasukan', 'icon' => 'FaList', 'module' => 'sikeu', 'permission_slug' => 'sikeu.pemasukan.read', 'order_index' => 2],
                    ['name' => 'Approval Pimpinan', 'url' => '/sikeu/approval', 'icon' => 'FaShieldCheck', 'module' => 'sikeu', 'permission_slug' => 'sikeu.approval.manage', 'order_index' => 3],
                    ['name' => 'Pajak & Perpajakan', 'url' => '/sikeu/pajak', 'icon' => 'FaFileAlt', 'module' => 'sikeu', 'permission_slug' => 'sikeu.pajak.read', 'order_index' => 4],
                ]
            ],
            [
                'name' => 'AKUNTANSI & LAPORAN',
                'url' => '#akuntansi_sikeu',
                'icon' => 'FaBookOpen',
                'module' => 'sikeu',
                'permission_slug' => 'sikeu.akuntansi.read',
                'order_index' => 4,
                'children' => [
                    ['name' => 'Jurnal Umum', 'url' => '/sikeu/akuntansi/jurnal', 'icon' => 'FaFileAlt', 'module' => 'sikeu', 'permission_slug' => 'sikeu.akuntansi.read', 'order_index' => 1],
                    ['name' => 'Buku Besar', 'url' => '/sikeu/akuntansi/buku-besar', 'icon' => 'FaBookOpen', 'module' => 'sikeu', 'permission_slug' => 'sikeu.akuntansi.read', 'order_index' => 2],
                    ['name' => 'Chart of Accounts (COA)', 'url' => '/sikeu/akuntansi/coa', 'icon' => 'FaList', 'module' => 'sikeu', 'permission_slug' => 'sikeu.akuntansi.read', 'order_index' => 3],
                    ['name' => 'Laporan Keuangan', 'url' => '/sikeu/akuntansi/laporan', 'icon' => 'FaChartPie', 'module' => 'sikeu', 'permission_slug' => 'sikeu.akuntansi.read', 'order_index' => 4],
                ]
            ],
            [
                'name' => 'MASTER KEUANGAN GLOBAL',
                'url' => '#master_sikeu',
                'icon' => 'FaDatabase',
                'module' => 'sikeu',
                'permission_slug' => 'sikeu.master.manage',
                'order_index' => 5,
                'children' => [
                    ['name' => 'Katalog Komponen Biaya', 'url' => '/sikeu/master', 'icon' => 'FaBuilding', 'module' => 'sikeu', 'permission_slug' => 'sikeu.master.manage', 'order_index' => 1],
                    ['name' => 'Unit Kas & Rekening Bank', 'url' => '/sikeu/unit-kas', 'icon' => 'FaBuilding', 'module' => 'sikeu', 'permission_slug' => 'sikeu.unitkas.read', 'order_index' => 2],
                    ['name' => 'Master Tarif Gaji Pegawai', 'url' => '/sikeu/master/gaji-pegawai', 'icon' => 'FaMoneyBillWave', 'module' => 'sikeu', 'permission_slug' => 'sikeu.master.manage', 'order_index' => 3],
                    ['name' => 'Payment Gateway Bank', 'url' => '/sikeu/payment-gateway', 'icon' => 'FaCreditCard', 'module' => 'sikeu', 'permission_slug' => 'sikeu.paymentgateway.manage', 'order_index' => 4],
                ]
            ],
            [
                'name' => 'Panduan & Alur SIKEU',
                'url' => '/sikeu/panduan',
                'icon' => 'FaBookOpen',
                'module' => 'sikeu',
                'permission_slug' => 'sikeu.dashboard.read',
                'order_index' => 6,
            ],
        ];

        foreach ($menus as $menuData) {
            $permissionId = null;
            if (isset($menuData['permission_slug'])) {
                $permission = Permission::where('slug', $menuData['permission_slug'])->first();
                $permissionId = $permission ? $permission->id : null;
            }

            $parent = Menu::create([
                'name' => $menuData['name'],
                'url' => $menuData['url'],
                'icon' => $menuData['icon'],
                'module' => $menuData['module'],
                'permission_id' => $permissionId,
                'order_index' => $menuData['order_index'],
                'is_active' => true,
            ]);

            if (isset($menuData['children'])) {
                foreach ($menuData['children'] as $childData) {
                    $childPermissionId = null;
                    if (isset($childData['permission_slug'])) {
                        $childPermission = Permission::where('slug', $childData['permission_slug'])->first();
                        $childPermissionId = $childPermission ? $childPermission->id : null;
                    }

                    Menu::create([
                        'parent_id' => $parent->id,
                        'name' => $childData['name'],
                        'url' => $childData['url'],
                        'icon' => $childData['icon'],
                        'module' => $childData['module'],
                        'permission_id' => $childPermissionId,
                        'order_index' => $childData['order_index'],
                        'is_active' => true,
                    ]);
                }
            }
        }

        // Attach default role_menus
        $allMenuIds = Menu::pluck('id')->toArray();
        $akunKeamananIds = Menu::where('url', '#akun_keamanan')
            ->orWhere('url', 'like', '/profile%')
            ->pluck('id')
            ->toArray();

        $roles = \App\Models\Role::all();
        foreach ($roles as $role) {
            if (in_array($role->slug, ['superadmin', 'admin'])) {
                $role->menus()->sync($allMenuIds);
            } else {
                // Ensure profile/security menus are attached for all roles
                $role->menus()->syncWithoutDetaching($akunKeamananIds);

                // Sync module specific menus
                $moduleSlug = str_replace('admin_', '', $role->slug);
                $moduleSlug = str_replace('operator_', '', $role->slug);
                $roleMenuIds = Menu::whereIn('module', ['sso', $moduleSlug])
                    ->where(function($q) {
                        $q->whereNull('permission_id')
                          ->orWhere('url', 'like', '/profile%')
                          ->orWhere('url', '#akun_keamanan')
                          ->orWhere('url', '/dashboard');
                    })
                    ->pluck('id')
                    ->toArray();
                if (!empty($roleMenuIds)) {
                    $role->menus()->syncWithoutDetaching($roleMenuIds);
                }
            }
        }
    }
}
