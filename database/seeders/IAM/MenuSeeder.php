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
                'name' => 'MANAJEMEN PENGGUNA',
                'url' => '#users_section',
                'icon' => 'FaUsers',
                'module' => 'sso',
                'order_index' => 2,
                'children' => [
                    ['name' => 'Pengguna Portal', 'url' => '/admin/users', 'icon' => 'FaUsers', 'module' => 'sso', 'permission_slug' => 'iam.users.read', 'order_index' => 1],
                    ['name' => 'Plotting User Role', 'url' => '/admin/user-roles', 'icon' => 'FaUserCheck', 'module' => 'sso', 'permission_slug' => 'iam.user_roles.manage', 'order_index' => 2],
                ]
            ],
            [
                'name' => 'ROLE & HAK AKSES',
                'url' => '#roles_section',
                'icon' => 'FaShieldAlt',
                'module' => 'sso',
                'order_index' => 3,
                'children' => [
                    ['name' => 'Master Role', 'url' => '/admin/roles', 'icon' => 'FaShieldAlt', 'module' => 'sso', 'permission_slug' => 'iam.roles.read', 'order_index' => 1],
                    ['name' => 'Hak Akses (Permissions)', 'url' => '/admin/permissions', 'icon' => 'FaKey', 'module' => 'sso', 'permission_slug' => 'iam.permissions.read', 'order_index' => 2],
                    ['name' => 'Plotting Role Permission', 'url' => '/admin/role-permissions', 'icon' => 'FaClipboardCheck', 'module' => 'sso', 'permission_slug' => 'iam.permissions.manage', 'order_index' => 3],
                    ['name' => 'Plotting Role Menu', 'url' => '/admin/role-menus', 'icon' => 'FaSlidersH', 'module' => 'sso', 'permission_slug' => 'iam.roles.update', 'order_index' => 4],
                ]
            ],
            [
                'name' => 'MODUL & NAVIGASI',
                'url' => '#modules_section',
                'icon' => 'FaLayers',
                'module' => 'sso',
                'order_index' => 4,
                'children' => [
                    ['name' => 'Master Modul', 'url' => '/admin/modules', 'icon' => 'FaLayers', 'module' => 'sso', 'permission_slug' => 'iam.roles.update', 'order_index' => 1],
                    ['name' => 'Master Menu', 'url' => '/admin/menus', 'icon' => 'FaBars', 'module' => 'sso', 'permission_slug' => 'iam.roles.update', 'order_index' => 2],
                ]
            ],
            [
                'name' => 'DATA REFERENSI',
                'url' => '#referensi_section',
                'icon' => 'FaDatabase',
                'module' => 'sso',
                'order_index' => 5,
                'children' => [
                    ['name' => 'Master Data Referensi', 'url' => '/admin/master-referensi', 'icon' => 'FaDatabase', 'module' => 'sso', 'permission_slug' => 'iam.roles.update', 'order_index' => 1],
                    ['name' => 'Master Tipe Referensi', 'url' => '/admin/master-tipe-referensi', 'icon' => 'FaTags', 'module' => 'sso', 'permission_slug' => 'iam.roles.update', 'order_index' => 2],
                ]
            ],
            [
                'name' => 'LOG & AUDIT',
                'url' => '#audit_section',
                'icon' => 'FaHistory',
                'module' => 'sso',
                'order_index' => 6,
                'children' => [
                    ['name' => 'Sesi Login Aktif', 'url' => '/admin/sessions', 'icon' => 'FaDesktop', 'module' => 'sso', 'permission_slug' => 'iam.sessions.read', 'order_index' => 1],
                    ['name' => 'Audit Log Aktivitas', 'url' => '/admin/audit-logs', 'icon' => 'FaHistory', 'module' => 'sso', 'permission_slug' => 'iam.audit_logs.read', 'order_index' => 2],
                    ['name' => 'Pengaturan Sistem', 'url' => '/admin/settings', 'icon' => 'FaCogs', 'module' => 'sso', 'permission_slug' => 'iam.roles.update', 'order_index' => 3],
                ]
            ],
            [
                'name' => 'AKUN & KEAMANAN',
                'url' => '#akun_keamanan',
                'icon' => 'FaShieldCheck',
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
                    ['name' => 'Cuti & Izin Kerja', 'url' => '/simpeg/cuti', 'icon' => 'FaCalendar', 'module' => 'simpeg', 'permission_slug' => 'simpeg.cuti.read', 'order_index' => 2],
                    ['name' => 'Payroll & Slip Gaji', 'url' => '/simpeg/payroll', 'icon' => 'FaMoneyBillWave', 'module' => 'simpeg', 'permission_slug' => 'simpeg.payroll.read', 'order_index' => 3],
                    ['name' => 'Usulan Jafung (KUM)', 'url' => '/simpeg/usulan-jafung', 'icon' => 'FaAward', 'module' => 'simpeg', 'permission_slug' => 'simpeg.usulan_jafung.read', 'order_index' => 4],
                    ['name' => 'Evaluasi Kinerja SKP', 'url' => '/simpeg/kinerja', 'icon' => 'FaChartPie', 'module' => 'simpeg', 'permission_slug' => 'simpeg.kinerja.read', 'order_index' => 5],
                    ['name' => 'Kompetensi & Pelatihan', 'url' => '/simpeg/kompetensi', 'icon' => 'FaGraduationCap', 'module' => 'simpeg', 'permission_slug' => 'simpeg.kompetensi.read', 'order_index' => 6],
                    ['name' => 'Surat Tugas & LPJ', 'url' => '/simpeg/surat-tugas', 'icon' => 'FaBriefcase', 'module' => 'simpeg', 'permission_slug' => 'simpeg.surat_tugas.read', 'order_index' => 7],
                    ['name' => 'Arsip SK Pegawai', 'url' => '/simpeg/sk-pegawai', 'icon' => 'FaFileSignature', 'module' => 'simpeg', 'permission_slug' => 'simpeg.sk_pegawai.read', 'order_index' => 8],
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
                    ['name' => 'Master Jenis Cuti & Izin', 'url' => '/simpeg/master/jenis-cuti', 'icon' => 'FaCalendarCheck', 'module' => 'simpeg', 'permission_slug' => 'simpeg.cuti.read', 'order_index' => 3],
                    ['name' => 'Master Komponen Gaji', 'url' => '/simpeg/payroll/komponen', 'icon' => 'FaMoneyBillWave', 'module' => 'simpeg', 'permission_slug' => 'simpeg.payroll.read', 'order_index' => 4],
                    ['name' => 'Master Pengaturan Presensi', 'url' => '/simpeg/master/presensi', 'icon' => 'FaClock', 'module' => 'simpeg', 'permission_slug' => 'simpeg.presensi.manage', 'order_index' => 5],
                    ['name' => 'Master Penugasan Dinas', 'url' => '/simpeg/master/surat-tugas', 'icon' => 'FaFileSignature', 'module' => 'simpeg', 'permission_slug' => 'simpeg.surat_tugas.read', 'order_index' => 6],
                    ['name' => 'Master Kategori SKP', 'url' => '/simpeg/master/kategori-skp', 'icon' => 'FaChartBar', 'module' => 'simpeg', 'permission_slug' => 'simpeg.kinerja.read', 'order_index' => 7],
                    ['name' => 'Master Kompetensi', 'url' => '/simpeg/master/kompetensi', 'icon' => 'FaGraduationCap', 'module' => 'simpeg', 'permission_slug' => 'simpeg.kompetensi.read', 'order_index' => 8],
                    ['name' => 'Master Kategori SK', 'url' => '/simpeg/master/kategori-sk', 'icon' => 'FaFileSignature', 'module' => 'simpeg', 'permission_slug' => 'simpeg.sk_pegawai.read', 'order_index' => 9],
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
            // Catatan: KRS & Jadwal tidak dibuat top-level agar tidak aktif ganda —
            // sudah ada di grup PERKULIAHAN & OBE di bawah.
            [
                'name' => 'Hasil Studi (KHS & Transkrip)',
                'url' => '/siakad/hasil-studi',
                'icon' => 'FaAward',
                'module' => 'siakad',
                'permission_slug' => 'siakad.nilai.read',
                'order_index' => 4,
            ],
            [
                'name' => 'PERKULIAHAN & OBE',
                'url' => '#perkuliahan_siakad',
                'icon' => 'FaCalendarCheck',
                'module' => 'siakad',
                'order_index' => 5,
                'children' => [
                    ['name' => 'Kelas & Jadwal', 'url' => '/siakad/perkuliahan/kelas', 'icon' => 'FaCalendarCheck', 'module' => 'siakad', 'permission_slug' => 'siakad.kelas.read', 'order_index' => 1],
                    ['name' => 'KRS Mahasiswa', 'url' => '/siakad/krs', 'icon' => 'FaClipboardCheck', 'module' => 'siakad', 'permission_slug' => 'siakad.krs.read', 'order_index' => 2],
                    ['name' => 'Penilaian Kelas (OBE)', 'url' => '/siakad/nilai', 'icon' => 'FaPen', 'module' => 'siakad', 'permission_slug' => 'siakad.nilai.manage', 'order_index' => 3],
                    ['name' => 'Pemantauan OBE', 'url' => '/siakad/obe', 'icon' => 'FaChartBar', 'module' => 'siakad', 'permission_slug' => 'siakad.master.manage', 'order_index' => 4],
                    ['name' => 'CPL & Kurikulum', 'url' => '/siakad/obe/cpl', 'icon' => 'FaAward', 'module' => 'siakad', 'permission_slug' => 'siakad.master.manage', 'order_index' => 5],
                    ['name' => 'CPMK Mata Kuliah', 'url' => '/siakad/obe/cpmk', 'icon' => 'FaList', 'module' => 'siakad', 'permission_slug' => 'siakad.nilai.manage', 'order_index' => 6],
                    ['name' => 'RPS & Verifikasi', 'url' => '/siakad/obe/rps', 'icon' => 'FaFileAlt', 'module' => 'siakad', 'permission_slug' => 'siakad.nilai.manage', 'order_index' => 7],
                    ['name' => 'Ketertiban Dosen Nilai', 'url' => '/siakad/obe/kepatuhan', 'icon' => 'FaUserCheck', 'module' => 'siakad', 'permission_slug' => 'siakad.master.manage', 'order_index' => 8],
                ]
            ],
            [
                'name' => 'CIVITAS AKADEMIKA (BAAK)',
                'url' => '#civitas_siakad',
                'icon' => 'FaUsers',
                'module' => 'siakad',
                'order_index' => 6,
                'children' => [
                    ['name' => 'Mahasiswa & Plotting PA', 'url' => '/siakad/civitas/mahasiswa', 'icon' => 'FaUserGraduate', 'module' => 'siakad', 'permission_slug' => 'siakad.mahasiswa.read', 'order_index' => 1],
                    ['name' => 'Konversi Mahasiswa Transfer', 'url' => '/siakad/civitas/konversi', 'icon' => 'FaExchangeAlt', 'module' => 'siakad', 'permission_slug' => 'siakad.konversi.manage', 'order_index' => 2],
                    ['name' => 'Direktori Dosen Pengajar', 'url' => '/siakad/civitas/dosen', 'icon' => 'FaChalkboardTeacher', 'module' => 'siakad', 'permission_slug' => 'siakad.dosen.manage', 'order_index' => 3],
                    ['name' => 'Biodata Mahasiswa', 'url' => '/siakad/civitas/biodata', 'icon' => 'FaUser', 'module' => 'siakad', 'permission_slug' => 'siakad.mahasiswa.read', 'order_index' => 4],
                    ['name' => 'Penerima Beasiswa', 'url' => '/siakad/civitas/beasiswa', 'icon' => 'FaAward', 'module' => 'siakad', 'permission_slug' => 'siakad.beasiswa.manage', 'order_index' => 5],
                ]
            ],
            [
                'name' => 'MASTER AKADEMIK (BAAK)',
                'url' => '#master_siakad',
                'icon' => 'FaDatabase',
                'module' => 'siakad',
                'order_index' => 7,
                'children' => [
                    ['name' => 'Tahun Akademik', 'url' => '/siakad/master/tahun-akademik', 'icon' => 'FaCalendarCheck', 'module' => 'siakad', 'permission_slug' => 'siakad.master.manage', 'order_index' => 1],
                    ['name' => 'Fakultas & Prodi', 'url' => '/siakad/master/fakultas', 'icon' => 'FaBuilding', 'module' => 'siakad', 'permission_slug' => 'siakad.master.manage', 'order_index' => 2],
                    ['name' => 'Kurikulum OBE', 'url' => '/siakad/master/kurikulum', 'icon' => 'FaBookOpen', 'module' => 'siakad', 'permission_slug' => 'siakad.master.manage', 'order_index' => 3],
                    ['name' => 'Mata Kuliah', 'url' => '/siakad/master/matakuliah', 'icon' => 'FaList', 'module' => 'siakad', 'permission_slug' => 'siakad.matakuliah.manage', 'order_index' => 4],
                    ['name' => 'Skala Nilai', 'url' => '/siakad/master/skala-nilai', 'icon' => 'FaAward', 'module' => 'siakad', 'permission_slug' => 'siakad.master.manage', 'order_index' => 5],
                    ['name' => 'Konfigurasi Penilaian & OBE', 'url' => '/siakad/master/konfigurasi-penilaian', 'icon' => 'FaSlidersH', 'module' => 'siakad', 'permission_slug' => 'siakad.master.manage', 'order_index' => 6],
                    ['name' => 'Master Referensi', 'url' => '/siakad/master/referensi', 'icon' => 'FaDatabase', 'module' => 'siakad', 'permission_slug' => 'siakad.master.manage', 'order_index' => 7],
                    ['name' => 'Master Tipe Referensi', 'url' => '/siakad/master/tipe-referensi', 'icon' => 'FaTags', 'module' => 'siakad', 'permission_slug' => 'siakad.master.manage', 'order_index' => 8],
                ]
            ],
            [
                'name' => 'INTEGRASI DIKTI (BAAK)',
                'url' => '#feeder_siakad',
                'icon' => 'FaSyncAlt',
                'module' => 'siakad',
                'order_index' => 8,
                'children' => [
                    ['name' => 'Sinkronisasi Neo Feeder', 'url' => '/siakad/feeder-sync', 'icon' => 'FaCloudUploadAlt', 'module' => 'siakad', 'permission_slug' => 'siakad.feeder.manage', 'order_index' => 1],
                ]
            ],
            [
                'name' => 'Panduan & Alur SIAKAD',
                'url' => '/siakad/panduan',
                'icon' => 'FaBookOpen',
                'module' => 'siakad',
                'permission_slug' => 'siakad.dashboard.read',
                'order_index' => 9,
            ],

            // ── MODUL SPMB (PENERIMAAN MAHASISWA BARU) ─────────────
            [
                'name' => 'Dashboard SPMB',
                'url' => '/spmb',
                'icon' => 'FaChartPie',
                'module' => 'spmb',
                'permission_slug' => 'spmb.dashboard.read',
                'order_index' => 1,
            ],
            [
                'name' => 'ADMISI & PENDAFTARAN',
                'url' => '#admisi_spmb',
                'icon' => 'FaUserCheck',
                'module' => 'spmb',
                'permission_slug' => 'spmb.manage',
                'order_index' => 2,
                'children' => [
                    ['name' => 'Data Calon Mahasiswa', 'url' => '/spmb/pendaftar', 'icon' => 'FaUsers', 'module' => 'spmb', 'permission_slug' => 'spmb.manage', 'order_index' => 1],
                    ['name' => 'Pendaftaran Mahasiswa Baru', 'url' => '/spmb/pendaftaran', 'icon' => 'FaUserPlus', 'module' => 'spmb', 'permission_slug' => 'spmb.manage', 'order_index' => 2],
                    ['name' => 'Verifikasi Daftar Ulang', 'url' => '/spmb/daftar-ulang', 'icon' => 'FaClipboardCheck', 'module' => 'spmb', 'permission_slug' => 'spmb.manage', 'order_index' => 3],
                    ['name' => 'Registrasi Online', 'url' => '/spmb/registrasi', 'icon' => 'FaPen', 'module' => 'spmb', 'permission_slug' => 'spmb.manage', 'order_index' => 4],
                ]
            ],
            [
                'name' => 'MASTER PENERIMAAN SPMB',
                'url' => '#master_spmb',
                'icon' => 'FaDatabase',
                'module' => 'spmb',
                'permission_slug' => 'spmb.manage',
                'order_index' => 3,
                'children' => [
                    ['name' => 'Jalur Masuk', 'url' => '/spmb/master/jalur', 'icon' => 'FaCogs', 'module' => 'spmb', 'permission_slug' => 'spmb.manage', 'order_index' => 1],
                    ['name' => 'Tipe Jalur Masuk', 'url' => '/spmb/master/tipe-jalur', 'icon' => 'FaTags', 'module' => 'spmb', 'permission_slug' => 'spmb.manage', 'order_index' => 2],
                    ['name' => 'Gelombang Penerimaan', 'url' => '/spmb/master/gelombang', 'icon' => 'FaCalendar', 'module' => 'spmb', 'permission_slug' => 'spmb.manage', 'order_index' => 3],
                    ['name' => 'Kuota Program Studi', 'url' => '/spmb/master/kuota', 'icon' => 'FaChartPie', 'module' => 'spmb', 'permission_slug' => 'spmb.manage', 'order_index' => 4],
                ]
            ],
            [
                'name' => 'MASTER BIAYA & REFERENSI SPMB',
                'url' => '#master_biaya_referensi',
                'icon' => 'FaCoins',
                'module' => 'spmb',
                'permission_slug' => 'spmb.manage',
                'order_index' => 4,
                'children' => [
                    ['name' => 'Persyaratan Berkas', 'url' => '/spmb/master/berkas-requirement', 'icon' => 'FaFileAlt', 'module' => 'spmb', 'permission_slug' => 'spmb.manage', 'order_index' => 1],
                    ['name' => 'Master Biaya SPMB', 'url' => '/spmb/master/biaya', 'icon' => 'FaCoins', 'module' => 'spmb', 'permission_slug' => 'spmb.manage', 'order_index' => 2],
                    ['name' => 'Komponen Biaya', 'url' => '/spmb/master/komponen-biaya', 'icon' => 'FaTag', 'module' => 'spmb', 'permission_slug' => 'spmb.manage', 'order_index' => 3],
                    ['name' => 'Master Data Referensi', 'url' => '/spmb/master/referensi', 'icon' => 'FaDatabase', 'module' => 'spmb', 'permission_slug' => 'spmb.manage', 'order_index' => 4],
                    ['name' => 'Master Tipe Referensi', 'url' => '/spmb/master/tipe-referensi', 'icon' => 'FaLayers', 'module' => 'spmb', 'permission_slug' => 'spmb.manage', 'order_index' => 5],
                ]
            ],
            [
                'name' => 'LAPORAN & STATISTIK',
                'url' => '#laporan_spmb',
                'icon' => 'FaChartBar',
                'module' => 'spmb',
                'permission_slug' => 'spmb.laporan.read',
                'order_index' => 5,
                'children' => [
                    ['name' => 'Statistik Pendaftaran', 'url' => '/spmb/laporan/statistik', 'icon' => 'FaChartBar', 'module' => 'spmb', 'permission_slug' => 'spmb.laporan.read', 'order_index' => 1],
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
                'name' => 'PEMBAYARAN MAHASISWA',
                'url' => '#pembayaran_mhs_sikeu',
                'icon' => 'FaCreditCard',
                'module' => 'sikeu',
                'permission_slug' => 'sikeu.dashboard.read',
                'order_index' => 2,
                'children' => [
                    ['name' => 'Pengaturan Tarif', 'url' => '/sikeu/pembayaran-mahasiswa/tarif', 'icon' => 'FaDollarSign', 'module' => 'sikeu', 'permission_slug' => 'sikeu.master.manage', 'order_index' => 1],
                    ['name' => 'Input Tagihan', 'url' => '/sikeu/pembayaran-mahasiswa/tagihan', 'icon' => 'FaCreditCard', 'module' => 'sikeu', 'order_index' => 2],
                    ['name' => 'Bayar Kasir Loket', 'url' => '/sikeu/pembayaran-mahasiswa/bayar', 'icon' => 'FaMoneyBillWave', 'module' => 'sikeu', 'order_index' => 3],
                    ['name' => 'Potongan Mahasiswa', 'url' => '/sikeu/pembayaran-mahasiswa/potongan', 'icon' => 'FaCoins', 'module' => 'sikeu', 'permission_slug' => 'sikeu.master.manage', 'order_index' => 4],
                    ['name' => 'Piutang Mahasiswa', 'url' => '/sikeu/piutang', 'icon' => 'FaExclamationTriangle', 'module' => 'sikeu', 'permission_slug' => 'sikeu.tagihan.read', 'order_index' => 5],
                    ['name' => 'Dispensasi Pembayaran', 'url' => '/sikeu/dispensasi', 'icon' => 'FaClipboardCheck', 'module' => 'sikeu', 'order_index' => 6],
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
                    ['name' => 'Pengajuan Operasional', 'url' => '/sikeu/pengajuan', 'icon' => 'FaFileAlt', 'module' => 'sikeu', 'permission_slug' => 'sikeu.pengajuan.read', 'order_index' => 1],
                    ['name' => 'Pengeluaran Kas', 'url' => '/sikeu/pengeluaran', 'icon' => 'FaList', 'module' => 'sikeu', 'permission_slug' => 'sikeu.pengeluaran.read', 'order_index' => 2],
                    ['name' => 'Pemasukan Kas Non-Akademik', 'url' => '/sikeu/pemasukan', 'icon' => 'FaList', 'module' => 'sikeu', 'permission_slug' => 'sikeu.pemasukan.read', 'order_index' => 3],
                    ['name' => 'Kas Kecil', 'url' => '/sikeu/kas-kecil', 'icon' => 'FaCoins', 'module' => 'sikeu', 'permission_slug' => 'sikeu.kaskecil.read', 'order_index' => 4],
                    ['name' => 'Pajak & Perpajakan', 'url' => '/sikeu/pajak', 'icon' => 'FaFileAlt', 'module' => 'sikeu', 'permission_slug' => 'sikeu.pajak.read', 'order_index' => 5],
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
                    ['name' => 'Jurnal Umum', 'url' => '/sikeu/akuntansi/jurnal', 'icon' => 'FaFileAlt', 'module' => 'sikeu', 'order_index' => 1],
                    ['name' => 'Buku Besar', 'url' => '/sikeu/akuntansi/buku-besar', 'icon' => 'FaBookOpen', 'module' => 'sikeu', 'order_index' => 2],
                    // Visibilitas via role-menu (tanpa permission) agar tidak bocor
                    // ke role yang hanya memegang permission read umum.
                    ['name' => 'Chart of Accounts (COA)', 'url' => '/sikeu/akuntansi/coa', 'icon' => 'FaList', 'module' => 'sikeu', 'order_index' => 3],
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
                $moduleSlug = str_replace('operator_', '', $moduleSlug);
                if (in_array($role->slug, ['operator_sikeu', 'kabag_keuangan'])) {
                    $moduleSlug = 'sikeu';
                }
                $roleMenuIds = Menu::where(function ($mq) use ($moduleSlug) {
                        $mq->where('module', $moduleSlug)
                           ->whereNull('permission_id')
                           ->where('url', 'not like', '%/master/%')
                           ->where('url', 'not like', '#master_%');
                    })
                    ->orWhere(function ($ssoQ) {
                        $ssoQ->where('module', 'sso')
                             ->where(function ($sq) {
                                 $sq->where('url', 'like', '/profile%')
                                    ->orWhere('url', '#akun_keamanan')
                                    ->orWhere('url', '/dashboard');
                             });
                    })
                    ->pluck('id')
                    ->toArray();
                if (!empty($roleMenuIds)) {
                    $role->menus()->syncWithoutDetaching($roleMenuIds);
                }

                if (in_array($role->slug, ['operator_sikeu', 'kabag_keuangan'])) {
                    $sikeuMenuIds = Menu::where('module', 'sikeu')->pluck('id')->toArray();
                    $role->menus()->syncWithoutDetaching($sikeuMenuIds);
                }
            }
        }

        // Attach all SIMPEG menus to admin_simpeg and operator_sdm
        $simpegMenuIds = Menu::where('module', 'simpeg')->pluck('id')->toArray();
        $adminSimpegRole = \App\Models\Role::where('slug', 'admin_simpeg')->first();
        if ($adminSimpegRole) {
            $adminSimpegRole->menus()->syncWithoutDetaching($simpegMenuIds);
        }
        $operatorSdmRole = \App\Models\Role::where('slug', 'operator_sdm')->first();
        if ($operatorSdmRole) {
            $operatorSdmRole->menus()->syncWithoutDetaching($simpegMenuIds);
        }

        $dosenRole = \App\Models\Role::where('slug', 'dosen')->first();
        if ($dosenRole) {
            $dosenSimpegMenuIds = Menu::where('module', 'simpeg')
                ->whereIn('url', ['/simpeg', '/simpeg/presensi', '/simpeg/cuti', '/simpeg/payroll', '/simpeg/kompetensi', '/simpeg/surat-tugas', '/simpeg/izin-kerja', '/simpeg/sk-pegawai'])
                ->pluck('id')
                ->toArray();
            $dosenRole->menus()->syncWithoutDetaching($dosenSimpegMenuIds);
        }

        // Dosen: portal SIAKAD (jadwal, KRS bimbingan, nilai, CPMK/RPS, hasil studi, panduan)
        if ($dosenRole) {
            $dosenSiakadMenuIds = Menu::where('module', 'siakad')
                ->whereIn('url', [
                    '/siakad',
                    '#perkuliahan_siakad',
                    '/siakad/perkuliahan/kelas',
                    '/siakad/krs',
                    '/siakad/nilai',
                    '/siakad/obe/cpmk',
                    '/siakad/obe/rps',
                    '/siakad/hasil-studi',
                    '/siakad/civitas/mahasiswa',
                    '/siakad/panduan',
                ])
                ->pluck('id')
                ->toArray();
            $dosenRole->menus()->syncWithoutDetaching($dosenSiakadMenuIds);
        }

        // Portal mandiri mahasiswa: dashboard + tagihan via baseAllowed,
        // menu DB untuk dispensasi & panduan agar lolos CheckMenuAccess.
        $mahasiswaRole = \App\Models\Role::where('slug', 'mahasiswa')->first();
        if ($mahasiswaRole) {
            $mhsSikeuMenuIds = Menu::where('module', 'sikeu')
                ->whereIn('url', ['/sikeu', '/sikeu/dispensasi', '/sikeu/panduan'])
                ->pluck('id')
                ->toArray();
            $mahasiswaRole->menus()->syncWithoutDetaching($mhsSikeuMenuIds);
            // Portal SIAKAD mahasiswa: dashboard, KRS, jadwal, hasil studi, panduan
            $mhsSiakadMenuIds = Menu::where('module', 'siakad')
                ->whereIn('url', [
                    '/siakad',
                    '#perkuliahan_siakad',
                    '/siakad/perkuliahan/kelas',
                    '/siakad/krs',
                    '/siakad/hasil-studi',
                    '/siakad/panduan',
                ])
                ->pluck('id')
                ->toArray();
            $mahasiswaRole->menus()->syncWithoutDetaching($mhsSiakadMenuIds);
        }

        // Pimpinan: dashboard + approval direktur + laporan & pantauan (read-only eksekutif).
        // Tahap sarpras/keuangan disembunyikan; akuntansi hanya laporan; plus piutang.
        $pimpinanRole = \App\Models\Role::where('slug', 'pimpinan')->first();
        if ($pimpinanRole) {
            $pimpinanSikeuMenuIds = Menu::where('module', 'sikeu')
                ->whereIn('url', [
                    '/sikeu',
                    '#pengeluaran_sikeu',
                    '/sikeu/approval/direktur',
                    '#akuntansi_sikeu',
                    '/sikeu/akuntansi/laporan',
                    '/sikeu/dispensasi',
                    '/sikeu/pengajuan',
                    '/sikeu/piutang',
                    '/sikeu/panduan',
                ])
                ->pluck('id')
                ->toArray();
            $pimpinanRole->menus()->syncWithoutDetaching($pimpinanSikeuMenuIds);
        }

        // Admin Keuangan Akuntansi: jurnal, buku besar, COA, laporan, kas & pajak.
        $adminKeuAkuntansiRole = \App\Models\Role::where('slug', 'admin_keuangan_akuntansi')->first();
        if ($adminKeuAkuntansiRole) {
            $akuntansiMenuIds = Menu::where('module', 'sikeu')
                ->whereIn('url', [
                    '/sikeu',
                    '#pengeluaran_sikeu',
                    '/sikeu/kas-kecil',
                    '#akuntansi_sikeu',
                    '/sikeu/akuntansi/jurnal',
                    '/sikeu/akuntansi/buku-besar',
                    '/sikeu/akuntansi/coa',
                    '/sikeu/akuntansi/laporan',
                    '/sikeu/unit-kas',
                    '/sikeu/pengeluaran',
                    '/sikeu/pemasukan',
                    '/sikeu/pajak',
                    '/sikeu/panduan',
                ])
                ->pluck('id')
                ->toArray();
            $adminKeuAkuntansiRole->menus()->syncWithoutDetaching($akuntansiMenuIds);
        }

        // Petugas Kas Kecil: menu kas kecil + parent groupnya, tanpa menu keuangan lain.
        $petugasKasKecilRole = \App\Models\Role::where('slug', 'petugas_kas_kecil')->first();
        if ($petugasKasKecilRole) {
            $petugasKasKecilMenuIds = Menu::where('module', 'sikeu')
                ->whereIn('url', [
                    '/sikeu',
                    '#pengeluaran_sikeu',
                    '/sikeu/kas-kecil',
                ])
                ->pluck('id')
                ->toArray();
            $petugasKasKecilRole->menus()->syncWithoutDetaching($petugasKasKecilMenuIds);
        }

        // Admin Keuangan Pembayaran Mahasiswa: tagihan, kasir, validasi, gateway.
        $adminKeuPembayaranRole = \App\Models\Role::where('slug', 'admin_keuangan_pembayaran')->first();
        if ($adminKeuPembayaranRole) {
            $pembayaranMenuIds = Menu::where('module', 'sikeu')
                ->whereIn('url', [
                    '/sikeu',
                    '#pembayaran_mhs_sikeu',
                    '/sikeu/pembayaran-mahasiswa/tarif',
                    '/sikeu/pembayaran-mahasiswa/tagihan',
                    '/sikeu/pembayaran-mahasiswa/bayar',
                    '/sikeu/pembayaran-mahasiswa/potongan',
                    '/sikeu/piutang',
                    '/sikeu/dispensasi',
                    '/sikeu/pembayaran-mahasiswa/validasi-manual',
                    '/sikeu/dispensasi',
                    '/sikeu/unit-kas',
                    '/sikeu/payment-gateway',
                    '/sikeu/panduan',
                ])
                ->pluck('id')
                ->toArray();
            $adminKeuPembayaranRole->menus()->syncWithoutDetaching($pembayaranMenuIds);
        }

        // Attach SPMB menus to admin-spmb role
        $spmbMenuIds = Menu::where('module', 'spmb')->pluck('id')->toArray();
        $adminSpmbRole = \App\Models\Role::where('slug', 'admin-spmb')->first();
        if ($adminSpmbRole) {
            $adminSpmbRole->menus()->syncWithoutDetaching($spmbMenuIds);
        }
    }
}
