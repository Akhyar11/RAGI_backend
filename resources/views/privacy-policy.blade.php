<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Kebijakan Privasi Aplikasi myIn-U Staff - Politeknik Indonusa Surakarta">
    <title>Kebijakan Privasi — myIn-U Staff | Politeknik Indonusa Surakarta</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #0b52b3;
            --primary-dark: #071e4a;
            --primary-light: #2563eb;
            --accent: #f83734;
            --bg-body: #f8fafc;
            --card-bg: #ffffff;
            --text-main: #0f172a;
            --text-muted: #475569;
            --border: #e2e8f0;
            --badge-bg: #e0f2fe;
            --badge-text: #0369a1;
        }

        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
            background-color: var(--bg-body);
            color: var(--text-main);
            line-height: 1.7;
            padding-bottom: 60px;
        }

        /* HEADER */
        header {
            background: linear-gradient(135deg, #071e4a 0%, #0d47a1 50%, #1e40af 100%);
            color: white;
            padding: 50px 20px 40px;
            text-align: center;
            position: relative;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0,0,0,0.12);
        }

        header::before {
            content: '';
            position: absolute;
            top: -60px;
            right: -60px;
            width: 220px;
            height: 220px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.06);
            pointer-events: none;
        }

        header::after {
            content: '';
            position: absolute;
            bottom: -40px;
            left: -40px;
            width: 180px;
            height: 180px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.04);
            pointer-events: none;
        }

        .header-container {
            max-width: 800px;
            margin: 0 auto;
            position: relative;
            z-index: 2;
        }

        .badge-app {
            display: inline-block;
            background: rgba(255, 255, 255, 0.16);
            backdrop-filter: blur(8px);
            border: 1px solid rgba(255, 255, 255, 0.28);
            color: #ffffff;
            font-size: 13px;
            font-weight: 600;
            padding: 6px 16px;
            border-radius: 99px;
            margin-bottom: 16px;
            letter-spacing: 0.5px;
        }

        h1 {
            font-size: clamp(26px, 4.5vw, 36px);
            font-weight: 800;
            margin-bottom: 12px;
            letter-spacing: -0.5px;
        }

        .subtitle {
            font-size: 15px;
            color: rgba(255, 255, 255, 0.85);
            max-width: 600px;
            margin: 0 auto;
        }

        /* MAIN CONTENT */
        main {
            max-width: 860px;
            margin: -25px auto 0;
            padding: 0 20px;
            position: relative;
            z-index: 10;
        }

        .card {
            background: var(--card-bg);
            border-radius: 20px;
            box-shadow: 0 10px 30px -5px rgba(0, 0, 0, 0.05), 0 0 0 1px var(--border);
            padding: 36px 32px;
            margin-bottom: 24px;
        }

        .last-updated {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #f1f5f9;
            color: var(--text-muted);
            padding: 6px 14px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 24px;
        }

        h2 {
            font-size: 20px;
            font-weight: 700;
            color: var(--primary-dark);
            margin-top: 32px;
            margin-bottom: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
            border-bottom: 2px solid #f1f5f9;
            padding-bottom: 8px;
        }

        h2:first-of-type {
            margin-top: 10px;
        }

        h3 {
            font-size: 16px;
            font-weight: 600;
            color: var(--text-main);
            margin: 18px 0 8px;
        }

        p {
            color: var(--text-muted);
            font-size: 15px;
            margin-bottom: 14px;
        }

        ul {
            list-style: none;
            padding-left: 0;
            margin-bottom: 16px;
        }

        li {
            position: relative;
            padding-left: 24px;
            color: var(--text-muted);
            font-size: 14.5px;
            margin-bottom: 10px;
        }

        li::before {
            content: '•';
            position: absolute;
            left: 8px;
            top: -2px;
            color: var(--primary-light);
            font-size: 18px;
            font-weight: bold;
        }

        .permission-box {
            background: #f8fafc;
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 16px 20px;
            margin-bottom: 16px;
        }

        .permission-box strong {
            color: var(--primary);
            display: block;
            margin-bottom: 4px;
            font-size: 15px;
        }

        .alert-box {
            background: #eff6ff;
            border-left: 4px solid var(--primary-light);
            padding: 16px 20px;
            border-radius: 0 12px 12px 0;
            margin: 20px 0;
            font-size: 14.5px;
            color: #1e3a8a;
        }

        .contact-card {
            background: linear-gradient(135deg, #f0fdf4 0%, #e0f2fe 100%);
            border: 1px solid #bae6fd;
            border-radius: 14px;
            padding: 22px 24px;
            margin-top: 24px;
        }

        .contact-card h3 {
            margin-top: 0;
            color: #0369a1;
        }

        footer {
            text-align: center;
            font-size: 13.5px;
            color: #94a3b8;
            margin-top: 40px;
        }

        footer a {
            color: var(--primary-light);
            text-decoration: none;
        }

        @media (max-width: 640px) {
            .card {
                padding: 26px 20px;
            }
            h2 {
                font-size: 18px;
            }
        }
    </style>
</head>
<body>

    <!-- HEADER -->
    <header>
        <div class="header-container">
            <span class="badge-app">Aplikasi Resmi Civitas Akademika</span>
            <h1>Kebijakan Privasi</h1>
            <p class="subtitle">Aplikasi myIn-U Staff • Politeknik Indonusa Surakarta</p>
        </div>
    </header>

    <!-- CONTENT -->
    <main>
        <div class="card">
            <div class="last-updated">
                <span>📅 Terakhir Diperbarui: 23 November 2026</span>
            </div>

            <p>
                Selamat datang di aplikasi <strong>myIn-U Staff</strong> ("Aplikasi"). Aplikasi ini dikembangkan dan dikelola secara resmi oleh <strong>Politeknik Indonusa Surakarta</strong> melalui Unit Teknologi Informasi (UTI) untuk menunjang kebutuhan presensi kepegawaian, administrasi akademik (SIAKAD), dan sistem informasi kepegawaian (SIMPEG) bagi Dosen dan Tenaga Kependidikan (Staff).
            </p>
            <p>
                Kami berkomitmen penuh untuk menghormati, melindungi, dan menjaga kerahasiaan data pribadi pengguna. Kebijakan Privasi ini menjelaskan bagaimana kami mengumpulkan, menggunakan, menyimpan, dan melindungi informasi Anda saat menggunakan Aplikasi myIn-U Staff.
            </p>

            <!-- 1. IDENTITAS PENGEMBANG -->
            <h2>1. Identitas Pengembang & Aplikasi</h2>
            <ul>
                <li><strong>Nama Aplikasi:</strong> myIn-U Staff</li>
                <li><strong>Nama Paket (Package ID):</strong> <code>com.minu.staff.myinu_staff</code></li>
                <li><strong>Pengembang / Institusi:</strong> Politeknik Indonusa Surakarta</li>
                <li><strong>Pengelola Teknis:</strong> Unit Teknologi Informasi (UTI) Politeknik Indonusa Surakarta</li>
                <li><strong>Alamat Kontak:</strong> uti@poltekindonusa.ac.id</li>
            </ul>

            <!-- 2. DATA YANG DIKUMPULKAN -->
            <h2>2. Informasi & Data yang Kami Kumpulkan</h2>
            <p>Untuk menjalankan fungsi aplikasi secara optimal dan akurat, kami mengumpulkan jenis data berikut:</p>

            <div class="permission-box">
                <strong>A. Informasi Akun & Data Kepegawaian</strong>
                <p style="margin-bottom:0">Nama lengkap, NIDN (Nomor Induk Dosen Nasional) atau NIP/Kode Pegawai, alamat email institusi, nomor telepon, status kepegawaian, jabatan/posisi, dan unit kerja/program studi yang terdaftar pada sistem SSO resmi Politeknik Indonusa Surakarta.</p>
            </div>

            <div class="permission-box">
                <strong>B. Data Lokasi (Geolokasi GPS)</strong>
                <p style="margin-bottom:0">Aplikasi mengakses lokasi akurat perangkat (<em>ACCESS_FINE_LOCATION</em> dan <em>ACCESS_COARSE_LOCATION</em>) <strong>hanya saat proses presensi kehadiran (check-in / check-out) dilakukan</strong>. Data lokasi ini digunakan untuk memvalidasi radius jarak fisik Anda terhadap batas koordinat kampus atau lokasi penugasan dinas resmi.</p>
            </div>

            <div class="permission-box">
                <strong>C. Data Kamera & Foto Wajah</strong>
                <p style="margin-bottom:0">Akses kamera digunakan untuk mengambil swafoto wajah saat verifikasi presensi kehadiran, pendaftaran sampel wajah biometrik, pemindaian QR Code sesi perkuliahan / absensi kelas, dan pembaruan foto profil pegawai.</p>
            </div>

            <div class="permission-box">
                <strong>D. Data Pengenalan Biometrik Wajah (AI Facial Recognition)</strong>
                <p style="margin-bottom:0">Aplikasi menggunakan teknologi pencocokan fitur wajah (Facial Mesh) secara lokal dan aman untuk mencocokkan wajah saat presensi. Data fitur vektor wajah hanya digunakan untuk verifikasi kehadiran pegawai dan tidak pernah dibagikan kepada pihak luar manapun.</p>
            </div>

            <div class="permission-box">
                <strong>E. Berkas & Dokumen Pendukung</strong>
                <p style="margin-bottom:0">Akses dokumen atau galeri digunakan apabila pegawai mengunggah dokumen pendukung seperti surat permohonan cuti, surat sakit dokter, atau laporan tugas kedinasan.</p>
            </div>

            <!-- 3. TUJUAN PENGGUNAAN -->
            <h2>3. Tujuan Penggunaan Informasi</h2>
            <p>Data pribadi yang dikumpulkan digunakan semata-mata untuk kepentingan internal kelembagaan, antara lain:</p>
            <ul>
                <li>Memverifikasi identitas pengguna melalui Single Sign-On (SSO) terpadu.</li>
                <li>Mencatat kehadiran kerja harian (presensi masuk dan pulang) secara valid, transparan, dan akuntabel.</li>
                <li>Pengelolaan administrasi perkuliahan seperti Jurnal Perkuliahan (BAP), input nilai, dan jadwal mengajar dosen.</li>
                <li>Pengelolaan permohonan cuti, perizinan, dan riwayat aktivitas kepegawaian (SIMPEG).</li>
                <li>Mengirimkan notifikasi penting terkait pengingat jadwal kuliah dan pengumuman resmi kampus.</li>
            </ul>

            <!-- 4. KEAMANAN & PENYIMPANAN -->
            <h2>4. Keamanan dan Penyimpanan Data</h2>
            <p>
                Kami menerapkan standar keamanan teknis dan operasional yang ketat untuk melindungi data Anda:
            </p>
            <ul>
                <li><strong>Enkripsi Transportasi Data (TLS/HTTPS):</strong> Seluruh pertukaran data antara aplikasi mobile dan server backend dilindungi protokol enkripsi HTTPS aman.</li>
                <li><strong>Penyimpanan Kredensial Aman:</strong> Token autentikasi disimpan menggunakan penyimpanan terenkripsi perangkat keras (<em>Android Keystore & EncryptedSharedPreferences</em>).</li>
                <li><strong>Akses Terbatas:</strong> Data hanya dapat diakses oleh sistem terverifikasi dan staf administrasi SDM/UTI yang berwenang.</li>
            </ul>

            <!-- 5. PEMBAGIAN KEPADA PIHAK KETIGA -->
            <h2>5. Pembagian Data kepada Pihak Ketiga</h2>
            <div class="alert-box">
                <strong>Komitmen Kerahasiaan Data:</strong> Politeknik Indonusa Surakarta <strong>TIDAK PERNAH</strong> menjual, menyewakan, memperdagangkan, atau membagikan data pribadi pengguna kepada pihak ketiga untuk kepentingan komersial, periklanan, atau pemasaran pihak luar.
            </div>

            <!-- 6. HAK PENGGUNA & PENGHAPUSAN DATA -->
            <h2>6. Hak Pengguna & Permohonan Penghapusan Data</h2>
            <p>
                Sesuai dengan ketentuan perlindungan data pribadi, setiap pengguna memiliki hak untuk:
            </p>
            <ul>
                <li>Melihat dan memeriksa data profil kepegawaian yang tersimpan dalam sistem.</li>
                <li>Meminta pembaruan atau koreksi apabila terdapat kesalahan data biodata.</li>
                <li>Meminta penghapusan data akun atau data biometrik wajah apabila status kepegawaian telah berakhir, dengan mengajukan permohonan tertulis ke Unit Teknologi Informasi Politeknik Indonusa Surakarta.</li>
            </ul>

            <!-- 7. HUBUNGI KAMI -->
            <h2>7. Hubungi Kami</h2>
            <p>
                Apabila Anda memiliki pertanyaan, klarifikasi, atau masukan mengenai Kebijakan Privasi ini, silakan menghubungi kami melalui:
            </p>

            <div class="contact-card">
                <h3>Unit Teknologi Informasi (UTI) — Politeknik Indonusa Surakarta</h3>
                <p style="margin-top:8px; margin-bottom:4px;"><strong>Email:</strong> uti@poltekindonusa.ac.id</p>
                <p style="margin-bottom:4px;"><strong>Website Resmi:</strong> <a href="https://poltekindonusa.ac.id" target="_blank" rel="noopener">https://poltekindonusa.ac.id</a></p>
                <p style="margin-bottom:0;"><strong>Alamat Kampus:</strong> Ruang IT Kampus 1 Gedung C, Jl. KH. Samanhudi No. 84-86, Surakarta, Jawa Tengah 57142</p>
            </div>
        </div>

        <footer>
            &copy; 2026 Politeknik Indonusa Surakarta. Hak Cipta Dilindungi Undang-Undang.
        </footer>
    </main>

</body>
</html>
