<?php

// Source of truth hierarki menu. Di-generate dari MenuSeeder (dipindah ke file data).
// Dipakai oleh MenuSeeder, SyncMenuPermissionsCommand, dan migrasi sinkronisasi menu.

return [
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
                    ['name' => 'Master Pengaturan Presensi', 'url' => '/simpeg/master/presensi', 'icon' => 'FaClock', 'module' => 'simpeg', 'permission_slug' => 'simpeg.presensi.update', 'order_index' => 5],
                    ['name' => 'Master Penugasan Dinas', 'url' => '/simpeg/master/surat-tugas', 'icon' => 'FaFileSignature', 'module' => 'simpeg', 'permission_slug' => 'simpeg.surat_tugas.read', 'order_index' => 6],
                    ['name' => 'Master Kategori SKP', 'url' => '/simpeg/master/kategori-skp', 'icon' => 'FaChartBar', 'module' => 'simpeg', 'permission_slug' => 'simpeg.kinerja.read', 'order_index' => 7],
                    ['name' => 'Master Kompetensi', 'url' => '/simpeg/master/kompetensi', 'icon' => 'FaGraduationCap', 'module' => 'simpeg', 'permission_slug' => 'simpeg.kompetensi.read', 'order_index' => 8],
                    ['name' => 'Master Kategori SK', 'url' => '/simpeg/master/kategori-sk', 'icon' => 'FaFileSignature', 'module' => 'simpeg', 'permission_slug' => 'simpeg.sk_pegawai.read', 'order_index' => 9],
                    ['name' => 'Master Tanda Tangan', 'url' => '/simpeg/master/tanda-tangan', 'icon' => 'FaFileSignature', 'module' => 'simpeg', 'permission_slug' => 'simpeg.tanda_tangan.read', 'order_index' => 10],
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
                    ['name' => 'Standar IKU 5', 'url' => '/sippm/iku5-standards', 'icon' => 'FaChartPie', 'module' => 'sippm', 'permission_slug' => 'sippm.iku5.read', 'order_index' => 3],
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
                    ['name' => 'Laporan Prodi', 'url' => '/sippm/prodi', 'icon' => 'FaBuilding', 'module' => 'sippm', 'order_index' => 2],
                ]
            ],
            [
                'name' => 'MASTER DATA SIPPM',
                'url' => '#master_sippm',
                'icon' => 'FaDatabase',
                'module' => 'sippm',
                'order_index' => 5,
                'children' => [
                    ['name' => 'Periode Hibah', 'url' => '/sippm/periode', 'icon' => 'FaCalendar', 'module' => 'sippm', 'permission_slug' => 'sippm.periode.read', 'order_index' => 1],
                    ['name' => 'Skema Penelitian', 'url' => '/sippm/skema', 'icon' => 'FaList', 'module' => 'sippm', 'permission_slug' => 'sippm.skema.read', 'order_index' => 2],
                    ['name' => 'Rubrik Penilaian', 'url' => '/sippm/rubrik', 'icon' => 'FaCheckSquare', 'module' => 'sippm', 'permission_slug' => 'sippm.rubrik.read', 'order_index' => 3],
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
                'name' => 'PERKULIAHAN (AKADEMIK)',
                'url' => '#perkuliahan_siakad',
                'icon' => 'FaCalendarCheck',
                'module' => 'siakad',
                'order_index' => 4,
                'children' => [
                    ['name' => 'Kelas & Jadwal', 'url' => '/siakad/perkuliahan/kelas', 'icon' => 'FaCalendarCheck', 'module' => 'siakad', 'permission_slug' => 'siakad.kelas.read', 'order_index' => 1],
                    ['name' => 'KRS Mahasiswa', 'url' => '/siakad/krs', 'icon' => 'FaClipboardCheck', 'module' => 'siakad', 'permission_slug' => 'siakad.krs.read', 'order_index' => 2],
                    ['name' => 'Penilaian Kelas (OBE)', 'url' => '/siakad/nilai', 'icon' => 'FaPen', 'module' => 'siakad', 'permission_slug' => 'siakad.nilai.manage', 'order_index' => 3],
                    ['name' => 'Bimbingan PA', 'url' => '/siakad/bimbingan', 'icon' => 'FaHandshake', 'module' => 'siakad', 'permission_slug' => 'siakad.mahasiswa.read', 'order_index' => 4],
                ]
            ],
            [
                'name' => 'MASTER',
                'url' => '#obe_siakad',
                'icon' => 'FaDatabase',
                'module' => 'siakad',
                'order_index' => 5,
                'children' => [
                    ['name' => 'Tahun Kurikulum', 'url' => '/siakad/obe/tahun-kurikulum', 'icon' => 'FaCalendarCheck', 'module' => 'siakad', 'permission_slug' => 'siakad.kurikulum.read', 'order_index' => 1],
                    ['name' => 'Rumpun Mata Kuliah', 'url' => '/siakad/obe/rumpun-mk', 'icon' => 'FaLayers', 'module' => 'siakad', 'permission_slug' => 'siakad.kurikulum.read', 'order_index' => 2],
                    ['name' => 'Jenis CPL', 'url' => '/siakad/obe/jenis-cpl', 'icon' => 'FaAward', 'module' => 'siakad', 'permission_slug' => 'siakad.kurikulum.read', 'order_index' => 3],
                    ['name' => 'Mata Kuliah', 'url' => '/siakad/obe/matakuliah', 'icon' => 'FaList', 'module' => 'siakad', 'permission_slug' => 'siakad.kurikulum.read', 'order_index' => 4],
                    ['name' => 'Distribusi Mata Kuliah', 'url' => '/siakad/obe/distribusi-mk', 'icon' => 'FaThList', 'module' => 'siakad', 'permission_slug' => 'siakad.kurikulum.read', 'order_index' => 5],
                    ['name' => 'Rubrik Penilaian', 'url' => '/siakad/obe/rubrik', 'icon' => 'FaCheckSquare', 'module' => 'siakad', 'permission_slug' => 'siakad.kurikulum.read', 'order_index' => 6],
                    ['name' => 'CPMK Mata Kuliah', 'url' => '/siakad/obe/cpmk', 'icon' => 'FaList', 'module' => 'siakad', 'permission_slug' => 'siakad.kurikulum.read', 'order_index' => 7],
                ]
            ],
            [
                'name' => 'RUMUSAN SKL',
                'url' => '#skl_siakad',
                'icon' => 'FaAward',
                'module' => 'siakad',
                'order_index' => 55,
                'children' => [
                    ['name' => 'Profil Lulusan (PL)', 'url' => '/siakad/obe/profil-lulusan', 'icon' => 'FaUserGraduate', 'module' => 'siakad', 'permission_slug' => 'siakad.kurikulum.read', 'order_index' => 1],
                    ['name' => 'Profesi', 'url' => '/siakad/obe/profesi', 'icon' => 'FaBriefcase', 'module' => 'siakad', 'permission_slug' => 'siakad.kurikulum.read', 'order_index' => 2],
                    ['name' => 'Perumusan CPL Prodi', 'url' => '/siakad/obe/cpl', 'icon' => 'FaAward', 'module' => 'siakad', 'permission_slug' => 'siakad.kurikulum.read', 'order_index' => 3],
                    ['name' => 'Pemetaan CPL-PL', 'url' => '/siakad/obe/pemetaan-cpl-pl', 'icon' => 'FaTh', 'module' => 'siakad', 'permission_slug' => 'siakad.kurikulum.read', 'order_index' => 4],
                ]
            ],
            [
                'name' => 'PENETAPAN BAHAN KAJIAN',
                'url' => '#bahan_kajian_siakad',
                'icon' => 'FaBookOpen',
                'module' => 'siakad',
                'order_index' => 56,
                'children' => [
                    ['name' => 'Perumusan BK', 'url' => '/siakad/obe/bahan-kajian', 'icon' => 'FaBookOpen', 'module' => 'siakad', 'permission_slug' => 'siakad.kurikulum.read', 'order_index' => 1],
                    ['name' => 'Pemetaan CPL-BK', 'url' => '/siakad/obe/pemetaan-cpl-bk', 'icon' => 'FaTh', 'module' => 'siakad', 'permission_slug' => 'siakad.kurikulum.read', 'order_index' => 2],
                    ['name' => 'Pemetaan BK-MK', 'url' => '/siakad/obe/pemetaan-bk-mk', 'icon' => 'FaThList', 'module' => 'siakad', 'permission_slug' => 'siakad.kurikulum.read', 'order_index' => 3],
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
                    ['name' => 'Skala Nilai', 'url' => '/siakad/master/skala-nilai', 'icon' => 'FaAward', 'module' => 'siakad', 'permission_slug' => 'siakad.master.manage', 'order_index' => 3],
                    ['name' => 'Konfigurasi Penilaian & OBE', 'url' => '/siakad/master/konfigurasi-penilaian', 'icon' => 'FaSlidersH', 'module' => 'siakad', 'permission_slug' => 'siakad.master.manage', 'order_index' => 4],
                    ['name' => 'Master Referensi', 'url' => '/siakad/master/referensi', 'icon' => 'FaDatabase', 'module' => 'siakad', 'permission_slug' => 'siakad.master.manage', 'order_index' => 5],
                    ['name' => 'Master Tipe Referensi', 'url' => '/siakad/master/tipe-referensi', 'icon' => 'FaTags', 'module' => 'siakad', 'permission_slug' => 'siakad.master.manage', 'order_index' => 6],
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

            // ── MODUL LMS (STANDALONE, PISAH DARI SIAKAD) ─────
            [
                'name' => 'Dashboard LMS',
                'url' => '/lms',
                'icon' => 'FaBookOpen',
                'module' => 'lms',
                'permission_slug' => 'siakad.kelas.read',
                'order_index' => 1,
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
                    ['name' => 'Pendaftaran Mahasiswa Baru', 'url' => '/spmb/pendaftaran', 'icon' => 'FaUserPlus', 'module' => 'spmb', 'permission_slug' => 'spmb.manage', 'order_index' => 1],
                    ['name' => 'Verifikasi Daftar Ulang', 'url' => '/spmb/daftar-ulang', 'icon' => 'FaClipboardCheck', 'module' => 'spmb', 'permission_slug' => 'spmb.manage', 'order_index' => 2],
                    ['name' => 'Registrasi Online', 'url' => '/spmb/registrasi', 'icon' => 'FaPen', 'module' => 'spmb', 'permission_slug' => 'spmb.manage', 'order_index' => 3],
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
                    ['name' => 'Template SK & Surat', 'url' => '/spmb/master/template-surat', 'icon' => 'FaFileSignature', 'module' => 'spmb', 'permission_slug' => 'spmb.manage', 'order_index' => 6],
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
                    ['name' => 'Laporan Referral', 'url' => '/spmb/laporan/referral', 'icon' => 'FaUsers', 'module' => 'spmb', 'permission_slug' => 'spmb.laporan.read', 'order_index' => 2],
                ]
            ],

            // ── MODUL SINAPRA ─────────────────────────────────────
            [
                'name' => 'Dashboard Sarpras',
                'url' => '/sinapra',
                'icon' => 'FaChartPie',
                'module' => 'sinapra',
                'permission_slug' => 'sinapra.dashboard.read',
                'order_index' => 1,
            ],
            [
                'name' => 'Gedung & Ruangan',
                'url' => '/sinapra/gedung-ruangan',
                'icon' => 'FaBuilding',
                'module' => 'sinapra',
                'permission_slug' => 'sinapra.ruangan.read',
                'order_index' => 2,
            ],
            [
                'name' => 'Inventaris Aset',
                'url' => '/sinapra/aset',
                'icon' => 'FaBoxes',
                'module' => 'sinapra',
                'permission_slug' => 'sinapra.aset.read',
                'order_index' => 3,
            ],
            [
                'name' => 'Peminjaman',
                'url' => '/sinapra/peminjaman',
                'icon' => 'FaCalendarCheck',
                'module' => 'sinapra',
                'permission_slug' => 'sinapra.dashboard.read',
                'order_index' => 4,
            ],
            [
                'name' => 'Maintenance',
                'url' => '/sinapra/maintenance',
                'icon' => 'FaWrench',
                'module' => 'sinapra',
                'permission_slug' => 'sinapra.dashboard.read',
                'order_index' => 5,
            ],
            [
                'name' => 'Pengadaan Barang',
                'url' => '/sinapra/pengadaan',
                'icon' => 'FaShoppingCart',
                'module' => 'sinapra',
                'permission_slug' => 'sinapra.dashboard.read',
                'order_index' => 6,
            ],
            [
                'name' => 'Laboratorium & BHP',
                'url' => '/sinapra/laboratorium',
                'icon' => 'FaBoxes',
                'module' => 'sinapra',
                'permission_slug' => 'sinapra.dashboard.read',
                'order_index' => 7,
            ],
            [
                'name' => 'Audit & Mutasi',
                'url' => '/sinapra/audit-mutasi',
                'icon' => 'FaClipboardCheck',
                'module' => 'sinapra',
                'permission_slug' => 'sinapra.dashboard.read',
                'order_index' => 8,
            ],
            [
                'name' => 'Kalender Ruangan',
                'url' => '/sinapra/kalender',
                'icon' => 'FaCalendar',
                'module' => 'sinapra',
                'permission_slug' => 'sinapra.dashboard.read',
                'order_index' => 9,
            ],
            [
                'name' => 'MASTER DATA',
                'url' => '#master_sinapra',
                'icon' => 'FaDatabase',
                'module' => 'sinapra',
                'order_index' => 10,
                'children' => [
                    ['name' => 'Master Kategori Aset', 'url' => '/sinapra/master/kategori-aset', 'icon' => 'FaTags', 'module' => 'sinapra', 'order_index' => 1],
                    ['name' => 'Master Tipe Ruangan', 'url' => '/sinapra/master/tipe-ruangan', 'icon' => 'FaDoorOpen', 'module' => 'sinapra', 'order_index' => 2],
                    ['name' => 'Master Vendor & Rekanan', 'url' => '/sinapra/master/vendor', 'icon' => 'FaHandshake', 'module' => 'sinapra', 'order_index' => 3],
                    ['name' => 'Master Kategori BHP Lab', 'url' => '/sinapra/master/kategori-bhp', 'icon' => 'FaBoxes', 'module' => 'sinapra', 'order_index' => 4],
                    ['name' => 'Master Satuan Barang', 'url' => '/sinapra/master/satuan', 'icon' => 'FaRulerCombined', 'module' => 'sinapra', 'order_index' => 5],
                    ['name' => 'Master Referensi Status & Kondisi', 'url' => '/sinapra/master/referensi', 'icon' => 'FaDatabase', 'module' => 'sinapra', 'order_index' => 6],
                    ['name' => 'Plotting Role Program Studi', 'url' => '/sinapra/master/prodi-role', 'icon' => 'FaGraduationCap', 'module' => 'sinapra', 'order_index' => 7],
                ]
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

            // ── MODUL ARSIP (TATA PERSURATAN) ─────────────────────
            [
                'name' => 'Dashboard Arsip',
                'url' => '/arsip',
                'icon' => 'FaChartPie',
                'module' => 'arsip',
                'permission_slug' => 'arsip.dashboard.read',
                'order_index' => 1,
            ],
            [
                'name' => 'PERSURATAN & ARSIP',
                'url' => '#persuratan_arsip',
                'icon' => 'FaFileAlt',
                'module' => 'arsip',
                'order_index' => 2,
                'children' => [
                    ['name' => 'Daftar Nomor Surat', 'url' => '/arsip/nomor-surat', 'icon' => 'FaFileSignature', 'module' => 'arsip', 'permission_slug' => 'arsip.nomor_surat.read', 'order_index' => 1],
                    ['name' => 'Permohonan Masuk', 'url' => '/arsip/request-nomor', 'icon' => 'FaClipboardCheck', 'module' => 'arsip', 'permission_slug' => 'arsip.request.read', 'order_index' => 2],
                ]
            ],
            [
                'name' => 'MASTER DATA ARSIP',
                'url' => '#master_arsip',
                'icon' => 'FaDatabase',
                'module' => 'arsip',
                'order_index' => 3,
                'children' => [
                    ['name' => 'Master Kop Surat', 'url' => '/arsip/kop-surat', 'icon' => 'FaStamp', 'module' => 'arsip', 'permission_slug' => 'arsip.kop_surat.read', 'order_index' => 1],
                    ['name' => 'Klasifikasi & Kode Unit', 'url' => '/arsip/master/klasifikasi', 'icon' => 'FaTags', 'module' => 'arsip', 'permission_slug' => 'arsip.master.manage', 'order_index' => 2],
                ]
            ],
        ];
