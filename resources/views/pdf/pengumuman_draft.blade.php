<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Surat Pengumuman Hibah {{ $p->tahun_anggaran }} - Politeknik Indonusa Surakarta</title>
    <style>
        @page {
            size: A4;
            margin: 15mm 20mm 20mm 20mm;
        }

        * {
            box-sizing: border-box;
        }

        body {
            background-color: #525659;
            margin: 0;
            padding: 20px 0;
            font-family: 'Times New Roman', Times, serif;
            color: #000000;
            -webkit-print-color-adjust: exact;
        }

        .no-print {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background: #0f172a;
            color: #ffffff;
            padding: 12px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-family: system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            box-shadow: 0 2px 10px rgba(0,0,0,0.3);
            z-index: 9999;
        }

        .btn-print {
            background: #2563eb;
            color: white;
            border: none;
            padding: 9px 20px;
            font-size: 13px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn-print:hover {
            background: #1d4ed8;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.4);
        }

        .preview-container {
            margin-top: 55px;
        }

        .page {
            width: 210mm;
            min-height: 297mm;
            padding: 18mm 20mm 20mm 20mm;
            margin: 0 auto 30px auto;
            background: #ffffff;
            box-shadow: 0 4px 18px rgba(0, 0, 0, 0.25);
            position: relative;
            font-size: 11.5pt;
            line-height: 1.45;
            color: #000000;
        }

        .kop-header {
            width: 100%;
            text-align: center;
            margin-bottom: 15px;
        }

        .kop-header img {
            width: 100%;
            max-height: 110px;
            object-fit: contain;
        }

        .header-top {
            text-align: right;
            margin-bottom: 12px;
            font-size: 11.5pt;
        }

        .meta-table {
            width: 100%;
            margin-bottom: 15px;
            border-collapse: collapse;
        }

        .meta-table td {
            vertical-align: top;
            padding: 2px 0;
            font-size: 11.5pt;
        }

        .meta-label {
            width: 90px;
        }

        .meta-colon {
            width: 18px;
        }

        .recipient {
            margin-top: 10px;
            margin-bottom: 15px;
            line-height: 1.4;
            font-size: 11.5pt;
        }

        .content-body {
            text-align: justify;
            text-indent: 35px;
            line-height: 1.5;
            font-size: 11.5pt;
            margin-bottom: 15px;
        }

        .content-closing {
            margin-top: 15px;
            margin-bottom: 25px;
            font-size: 11.5pt;
        }

        .signature-table {
            width: 100%;
            margin-top: 25px;
            page-break-inside: avoid;
        }

        .signature-table td {
            width: 50%;
            vertical-align: top;
            text-align: center;
            font-size: 11.5pt;
        }

        .sig-space {
            height: 70px;
        }

        .lampiran-title-header {
            font-weight: bold;
            font-size: 13pt;
            margin-bottom: 15px;
            color: #000000;
        }

        .doc-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            margin-bottom: 15px;
        }

        .doc-table th, .doc-table td {
            border: 1px solid #000000;
            padding: 7px 10px;
            font-size: 10.5pt;
        }

        .doc-table th {
            background-color: #3b82f6;
            color: #ffffff;
            text-align: center;
            font-weight: bold;
        }

        ol.custom-list {
            padding-left: 22px;
            margin-top: 8px;
            margin-bottom: 12px;
        }

        ol.custom-list li {
            margin-bottom: 8px;
            text-align: justify;
            line-height: 1.5;
        }

        @media print {
            body {
                background-color: #ffffff;
                padding: 0;
            }

            .no-print {
                display: none !important;
            }

            .preview-container {
                margin-top: 0;
            }

            .page {
                width: 100%;
                min-height: auto;
                margin: 0;
                padding: 0;
                box-shadow: none;
                page-break-after: always;
            }

            .page:last-child {
                page-break-after: auto;
            }
        }
    </style>
</head>
<body>

    <!-- Print Control Bar -->
    <div class="no-print">
        <div>
            📄 <b>DRAF SURAT PENGUMUMAN RESMI + LAMPIRAN 1 S.D 6</b> — POLITEKNIK INDONUSA SURAKARTA
        </div>
        <button onclick="window.print()" class="btn-print">
            🖨️ Cetak / Simpan PDF (1 Surat Utuh)
        </button>
    </div>

    <div class="preview-container">
        <!-- ============================================================ -->
        <!-- HALAMAN 1: SURAT RESMI PENGUMUMAN HIBAH                      -->
        <!-- ============================================================ -->
        <div class="page">
            <div class="kop-header">
                <img src="{{ $kopSuratBase64 }}" alt="Kop Surat Kampus Politeknik Indonusa Surakarta" />
            </div>

            <div class="header-top">
                Surakarta, {{ $tglFormatted }}
            </div>

            <table class="meta-table">
                <tr>
                    <td class="meta-label">Nomor</td>
                    <td class="meta-colon">:</td>
                    <td><b>{{ $p->nomor_surat }}</b></td>
                </tr>
                <tr>
                    <td class="meta-label">Hal</td>
                    <td class="meta-colon">:</td>
                    <td><b>{{ $p->hal_surat }}</b></td>
                </tr>
                <tr>
                    <td class="meta-label">Lampiran</td>
                    <td class="meta-colon">:</td>
                    <td><b>6 (Enam) Berkas Lampiran Resmi</b></td>
                </tr>
            </table>

            <div class="recipient">
                Yth.<br>
                1. Ketua Program Studi<br>
                2. Bapak/Ibu Dosen Tetap<br>
                di Politeknik Indonusa Surakarta
            </div>

            <p style="margin-bottom: 12px;"><i>Assalamualaikum warohmatullahi wa barakatuh</i></p>

            <div class="content-body">
                Dengan hormat disampaikan bahwa Unit Penelitian dan Pengabdian kepada Masyarakat (UPPM) Politeknik Indonusa Surakarta memberi kesempatan kepada <b>{{ $p->kualifikasi_dosen }}</b> untuk mengajukan proposal program PPM Hibah Institusi pendanaan tahun <b>{{ $p->tahun_anggaran }}</b>, yang {{ $p->kategori_pendanaan }} dengan pagu anggaran sesuai skema. Penetapan besaran pendanaan bergantung pada luaran yang dihasilkan. Luaran wajib adalah artikel ilmiah yang dipublikasikan dalam jurnal (nasional/internasional) dan luaran tambahan lainnya. Tema proposal program PPM harus mengacu pada Rencana Induk Penelitian (RIP) dan Renstra Pengabdian Politeknik Indonusa Surakarta dengan mengikuti perkembangan teknologi kekinian setiap prodi masing-masing. Mekanisme pengusulan proposal PPM dilakukan secara daring (online/by system) yang dapat diakses melalui sistem SIPPM Politeknik Indonusa Surakarta. Sehubungan dengan hal tersebut, dimohon Bapak/Ibu KAPRODI berkenan menginformasikan program dimaksud kepada dosen di prodi masing-masing. Demikian untuk diketahui dan ditindaklanjuti. Atas perhatian dan kerja samanya, diucapkan terima kasih.
            </div>

            <div class="content-closing">
                <i>Wassalamualaikum warohmatullahi wabarakatuh.</i>
            </div>

            <table class="signature-table">
                <tr>
                    <td>
                        Mengetahui,<br>
                        <b>Direktur</b>
                        <div class="sig-space"></div>
                        <u><b>{{ $p->nama_direktur }}</b></u>
                    </td>
                    <td>
                        <b>Ketua UPPM</b>
                        <div class="sig-space"></div>
                        <u><b>{{ $p->nama_ketua_uppm }}</b></u>
                    </td>
                </tr>
            </table>
        </div>

        <!-- ============================================================ -->
        <!-- HALAMAN 2: LAMPIRAN 1 - ALOKASI WAKTU                        -->
        <!-- ============================================================ -->
        <div class="page">
            <div class="kop-header">
                <img src="{{ $kopSuratBase64 }}" alt="Kop Surat Kampus Politeknik Indonusa Surakarta" />
            </div>

            <div class="lampiran-title-header">
                LAMPIRAN 1. Alokasi Waktu
            </div>

            <table class="doc-table">
                <thead>
                    <tr>
                        <th style="width: 45px;">No</th>
                        <th style="width: 190px;">Waktu</th>
                        <th>Kegiatan</th>
                    </tr>
                </thead>
                <tbody>
                    @if(!empty($p->lampiran_jadwal) && is_array($p->lampiran_jadwal))
                        @foreach($p->lampiran_jadwal as $index => $item)
                            <tr>
                                <td style="text-align: center;">{{ $index + 1 }}.</td>
                                <td style="font-weight: bold;">
                                    @if(isset($item['waktu']))
                                        {{ $item['waktu'] }}
                                    @elseif(isset($item['tgl_mulai']) && isset($item['tgl_selesai']))
                                        {{ \Carbon\Carbon::parse($item['tgl_mulai'])->locale('id')->isoFormat('D MMMM') }} – {{ \Carbon\Carbon::parse($item['tgl_selesai'])->locale('id')->isoFormat('D MMMM') }}
                                    @else
                                        -
                                    @endif
                                </td>
                                <td>{!! nl2br(e($item['kegiatan'] ?? '-')) !!}</td>
                            </tr>
                        @endforeach
                    @else
                        <tr><td style="text-align: center;">1.</td><td style="font-weight: bold;">20 Maret – 22 Maret</td><td>Pengumuman Penerimaan proposal PPM</td></tr>
                        <tr><td style="text-align: center;">2.</td><td style="font-weight: bold;">23 Maret - 23 April</td><td>Unggah proposal melalui system <u>http://sippm.poltekindonusa.ac.id</u><br>Username : nidn<br>Password : nidn</td></tr>
                        <tr><td style="text-align: center;">3.</td><td style="font-weight: bold;">24 April – 28 April</td><td>Penilaian oleh tim reviewer</td></tr>
                        <tr><td style="text-align: center;">4.</td><td style="font-weight: bold;">29 April</td><td>Penetapan pemenang</td></tr>
                        <tr><td style="text-align: center;">5.</td><td style="font-weight: bold;">30 April</td><td>Pengumuman proposal yang didanai</td></tr>
                        <tr><td style="text-align: center;">6.</td><td style="font-weight: bold;">4 Mei</td><td>Kontrak dan Pencairan dana 70%</td></tr>
                        <tr><td style="text-align: center;">7.</td><td style="font-weight: bold;">4 Mei – 4 Juli</td><td>Pelaksanaan PPM</td></tr>
                        <tr><td style="text-align: center;">8.</td><td style="font-weight: bold;">6 – 7 Juli</td><td>Monev kemajuan pelaksanaan PPM melalui sistem</td></tr>
                        <tr><td style="text-align: center;">9.</td><td style="font-weight: bold;">14 – 15 Agustus</td><td>Unggah Laporan akhir dan Luaran yang sesuai dalam proposal melalui sistem</td></tr>
                        <tr><td style="text-align: center;">10.</td><td style="font-weight: bold;">Akhir Agustus</td><td>Seminar Hasil dan pencairan dana 30%</td></tr>
                    @endif
                </tbody>
            </table>
        </div>

        <!-- ============================================================ -->
        <!-- HALAMAN 3: LAMPIRAN 2 - KRITERIA PENGUSUL                   -->
        <!-- ============================================================ -->
        <div class="page">
            <div class="kop-header">
                <img src="{{ $kopSuratBase64 }}" alt="Kop Surat Kampus Politeknik Indonusa Surakarta" />
            </div>

            <div class="lampiran-title-header">
                LAMPIRAN 2. Kriteria Pengusul
            </div>

            <ol class="custom-list" style="font-size: 11.5pt; line-height: 1.6;">
                <li>
                    Pengusul adalah dosen ber-NIDN atau belum ber-NIDN dengan status dosen tetap Yayasan Indonesia Membangun Surakarta.
                </li>
                <li>
                    Pengusul dapat mengajukan usulan secara kelompok (tim), minimal 2 anggota tim. Tim pengusul wajib melibatkan mahasiswa (minimal 2 mahasiswa), baik dalam satu prodi maupun lintas prodi.
                </li>
                <li>
                    Jika pada periode yang sama pengusul mendapatkan dana hibah dari Dikti, otomatis usulan dinyatakan gugur.
                </li>
            </ol>
        </div>

        <!-- ============================================================ -->
        <!-- HALAMAN 4: LAMPIRAN 3 - SISTEMATIKA PROPOSAL PENELITIAN      -->
        <!-- ============================================================ -->
        <div class="page">
            <div class="kop-header">
                <img src="{{ $kopSuratBase64 }}" alt="Kop Surat Kampus Politeknik Indonusa Surakarta" />
            </div>

            <div class="lampiran-title-header">
                LAMPIRAN 3. Sistematika Proposal Penelitian
            </div>

            <p style="text-align: justify; line-height: 1.5; font-size: 11pt; margin-bottom: 14px;">
                Usulan Penelitian Dosen maksimum berjumlah 20 halaman (tidak termasuk halaman sampul, halaman pengesahan, dan lampiran), yang ditulis menggunakan Times New Roman ukuran 12 dengan jarak baris 1,5 spasi kecuali ringkasan satu spasi dan ukuran kertas A-4 serta mengikuti sistematika dengan urutan sebagai berikut:
            </p>

            <div style="font-size: 11pt; line-height: 1.5; text-align: justify;">
                <p style="margin-bottom: 6px;"><b>HALAMAN SAMPUL</b></p>
                <p style="margin-bottom: 6px;"><b>IDENTITAS DAN URAIAN UMUM</b> (Lihat: Lampiran 5 di bawah)</p>
                <p style="margin-bottom: 12px;"><b>DAFTAR ISI</b></p>
                
                <div style="margin-bottom: 12px;">
                    <p style="margin-bottom: 3px;"><b>RINGKASAN</b> (maksimum satu halaman)</p>
                    <p style="margin-left: 15px; margin-top: 0;">Kemukakan tujuan jangka panjang dan target khusus yang ingin dicapai serta metode yang akan dipakai dalam pencapaian tujuan tersebut. Ringkasan harus mampu menguraikan secara cermat dan singkat tentang rencana kegiatan yang diusulkan.</p>
                </div>

                <div style="margin-bottom: 12px;">
                    <p style="margin-bottom: 3px;"><b>BAB 1. PENDAHULUAN</b></p>
                    <p style="margin-left: 15px; margin-top: 0;">Uraikan latar belakang pemilihan topik penelitian yang dilandasi oleh keingintahuan peneliti dalam mengungkapkan suatu gejala/konsep/dugaan untuk mencapai suatu tujuan. Perlu dikemukakan hal-hal yang melandasi atau argumentasi yang menguatkan bahwa penelitian tersebut penting untuk dilaksanakan. Masalah yang akan diteliti harus dirumuskan secara jelas disertai dengan pendekatan dan konsep untuk menjawab permasalahan, pengujian hipotesis atau dugaan yang akan dibuktikan. Dalam perumusan masalah dapat dijelaskan definisi, asumsi, dan lingkup yang menjadi batasan penelitian. Pada bagian ini juga perlu dijelaskan tujuan penelitian secara ringkas dan target luaran yang ingin dicapai.</p>
                </div>

                <div style="margin-bottom: 12px;">
                    <p style="margin-bottom: 3px;"><b>BAB 2. TINJAUAN PUSTAKA</b></p>
                    <p style="margin-left: 15px; margin-top: 0;">Uraikan secara jelas kajian pustaka yang melandasi timbulnya gagasan dan permasalahan yang akan diteliti dengan menguraikan teori, temuan, dan bahan penelitian lain yang diperoleh dari acuan untuk dijadikan landasan dalam pelaksanaan penelitian. Pustaka yang digunakan sebaiknya mutakhir (maksimum 10 tahun terakhir) dengan mengutamakan artikel pada jurnal ilmiah yang relevan.</p>
                </div>

                <div style="margin-bottom: 12px;">
                    <p style="margin-bottom: 3px;"><b>BAB 3. METODE PENELITIAN</b></p>
                    <p style="margin-left: 15px; margin-top: 0;">Uraikan secara rinci metode yang akan digunakan meliputi tahapan-tahapan penelitian, lokasi penelitian, peubah yang diamati/diukur, model yang digunakan, rancangan penelitian, serta teknik pengumpulan dan analisis data.</p>
                </div>

                <div style="margin-bottom: 12px;">
                    <p style="margin-bottom: 3px;"><b>BAB 4. BIAYA DAN JADWAL PENELITIAN</b></p>
                    <div style="margin-left: 15px;">
                        <p style="margin-bottom: 3px;"><b>4.1 Anggaran Biaya</b></p>
                        <p style="margin-top: 0; margin-bottom: 6px;">Besarnya anggaran penelitian yang diusulkan harus diperinci dengan memasukkan biaya pencapaian luaran wajib dan atau luaran tambahan yang akan dicapai.</p>
                        <p style="margin-bottom: 3px;"><b>4.2 Jadwal Penelitian</b></p>
                        <p style="margin-top: 0;">Jadwal pelaksanaan penelitian dibuat dengan tahapan yang jelas untuk dalam bentuk diagram batang (bar chart), maksimal 6 bulan.</p>
                    </div>
                </div>

                <div style="margin-bottom: 12px;">
                    <p style="margin-bottom: 3px;"><b>DAFTAR PUSTAKA</b></p>
                    <p style="margin-left: 15px; margin-top: 0;">Daftar Pustaka disusun berdasarkan sistem nama dan tahun dengan urutan abjad nama pengarang, tahun penerbitan, judul tulisan, dan sumber atau penerbit. Untuk pustaka yang berasal dari jurnal ilmiah, perlu juga mencantumkan nama jurnal, volume dan nomor penerbitan, serta halaman dimana artikel tersebut dimuat. Hanya pustaka yang disitasi dalam usulan penelitian yang dicantumkan dalam Daftar Pustaka. Wajib menggunakan referensi Mendeley dengan style APA.</p>
                </div>
            </div>
        </div>

        <!-- ============================================================ -->
        <!-- HALAMAN 5: LAMPIRAN 4 - SISTEMATIKA USULAN PKM              -->
        <!-- ============================================================ -->
        <div class="page">
            <div class="kop-header">
                <img src="{{ $kopSuratBase64 }}" alt="Kop Surat Kampus Politeknik Indonusa Surakarta" />
            </div>

            <div class="lampiran-title-header">
                LAMPIRAN 4. Sistematika Usulan Pengabdian kepada Masyarakat
            </div>

            <p style="text-align: justify; line-height: 1.5; font-size: 11pt; margin-bottom: 12px;">
                Usulan Program Pengabdian kepada Masyarakat (PkM) maksimum berjumlah 20 halaman (tidak termasuk halaman sampul, halaman pengesahan, dan lampiran), yang ditulis menggunakan Times New Roman ukuran 12 dengan jarak baris 1,5 spasi dan ukuran kertas A-4 serta mengikuti sistematika dengan urutan sebagai berikut:
            </p>

            <div style="font-size: 10.5pt; line-height: 1.45; text-align: justify;">
                <p style="margin-bottom: 4px;"><b>HALAMAN SAMPUL</b></p>
                <p style="margin-bottom: 4px;"><b>IDENTITAS DAN URAIAN UMUM</b> (Lihat: Lampiran 6)</p>
                <p style="margin-bottom: 8px;"><b>DAFTAR ISI</b></p>
                
                <div style="margin-bottom: 8px;">
                    <p style="margin-bottom: 2px;"><b>RINGKASAN PROPOSAL</b> (maksimum satu halaman)</p>
                    <p style="margin-left: 15px; margin-top: 0;">Kemukakan mitra, masalah mitra, solusi dan target luaran yang ingin dicapai serta metode yang akan dipakai dalam pencapaian tujuan tersebut. Ringkasan proposal harus mampu menguraikan secara cermat dan singkat tentang rencana kegiatan yang diusulkan dan ditulis dengan jarak satu spasi.</p>
                </div>

                <div style="margin-bottom: 8px;">
                    <p style="margin-bottom: 2px;"><b>BAB 1 PENDAHULUAN</b></p>
                    <div style="margin-left: 15px;">
                        <p style="margin-bottom: 2px;"><b>1.1 Analisis Situasi</b></p>
                        <p style="margin-top: 0; margin-bottom: 4px;">Pada bagian ini diuraikan analisis situasi fokus kepada kondisi terkini mitra yang mencakup hal-hal berikut:</p>
                        <ul style="margin-top: 0; margin-bottom: 6px; padding-left: 20px;">
                            <li><b>a. Untuk Pengusaha Mikro/Jasa Layanan:</b> Tampilkan profil mitra dengan foto/data, uraikan segi produksi & manajemen, serta ungkapkan persoalan mitra.</li>
                            <li><b>b. Untuk Masyarakat Calon Pengusaha:</b> Tampilkan profil mitra dengan foto/data, jelaskan potensi & peluang usaha, uraikan segi produksi & manajemen, serta ungkapkan persoalan sumber daya.</li>
                            <li><b>c. Untuk Masyarakat Umum:</b> Uraikan lokasi & kasus dengan foto/data, jelaskan segi sosial, budaya, religi, kesehatan, mutu layanan, serta ungkapkan persoalan khusus mitra.</li>
                        </ul>

                        <p style="margin-bottom: 2px;"><b>1.2 Permasalahan Mitra</b></p>
                        <p style="margin-top: 0; margin-bottom: 4px;">Mengacu pada Analisis Situasi, uraikan permasalahan mitra mencakup:</p>
                        <ul style="margin-top: 0; margin-bottom: 6px; padding-left: 20px;">
                            <li>a. Untuk Pengusaha Mikro: penentuan permasalahan prioritas produksi/manajemen yang disepakati bersama.</li>
                            <li>b. Untuk kelompok calon wirausaha baru: penentuan permasalahan prioritas berwirausaha.</li>
                            <li>c. Untuk Masyarakat Umum: persoalan prioritas sosial, budaya, keagamaan, atau mutu layanan.</li>
                            <li>d. Justifikasi pengusul bersama mitra dalam menentukan persoalan prioritas.</li>
                            <li>e. Permasalahan prioritas bersifat spesifik, konkret, & sesuai kebutuhan mitra.</li>
                        </ul>
                    </div>
                </div>

                <div style="margin-bottom: 8px;">
                    <p style="margin-bottom: 2px;"><b>BAB 2. SOLUSI DAN TARGET LUARAN</b></p>
                    <ul style="margin-top: 0; margin-bottom: 6px; padding-left: 20px;">
                        <li>a. Tuliskan semua solusi yang ditawarkan secara sistematis sesuai prioritas permasalahan mitra.</li>
                        <li>b. Tuliskan jenis luaran yang dihasilkan dari masing-masing solusi (produksi/manajemen/sosial).</li>
                        <li>c. Setiap solusi mempunyai luaran tersendiri dan sedapat mungkin terukur/kuantitatif.</li>
                        <li>d. Jika luaran berupa produk/barang/sertifikat, nyatakan spesifikasinya.</li>
                        <li>e. Buat rencana capaian luaran sesuai target yang ditetapkan.</li>
                    </ul>
                </div>

                <div style="margin-bottom: 8px;">
                    <p style="margin-bottom: 2px;"><b>BAB 3. METODE PELAKSANAAN</b></p>
                    <ol style="margin-top: 0; margin-bottom: 6px; padding-left: 20px;">
                        <li>Tahapan pelaksanaan solusi minimal dalam 2 bidang berbeda (produksi, manajemen, pemasaran, dll).</li>
                        <li>Langkah-langkah pelaksanaan solusi spesifik bidang sosial, budaya, keagamaan, atau mutu layanan.</li>
                        <li>Metode pendekatan yang disepakati bersama mitra.</li>
                        <li>Bentuk partisipasi mitra dalam pelaksanaan program.</li>
                        <li>Langkah evaluasi dan keberlanjutan program di lapangan setelah PkM selesai.</li>
                    </ol>
                </div>

                <div style="margin-bottom: 8px;">
                    <p style="margin-bottom: 2px;"><b>BAB 4. KELAYAKAN PERGURUAN TINGGI</b></p>
                    <ol style="margin-top: 0; margin-bottom: 6px; padding-left: 20px;">
                        <li>Uraikan kinerja lembaga PkM minimal 1 tahun terakhir.</li>
                        <li>Jelaskan jenis kepakaran yang diperlukan dalam menyelesaikan persoalan mitra.</li>
                        <li>Tuliskan nama tim pengusul, kepakaran, dan tugas masing-masing (dibuat dalam tabel).</li>
                    </ol>
                </div>

                <div style="margin-bottom: 8px;">
                    <p style="margin-bottom: 2px;"><b>BAB 5. BIAYA DAN JADWAL KEGIATAN</b></p>
                    <div style="margin-left: 15px;">
                        <p style="margin-bottom: 3px;"><b>5.1 Anggaran Biaya</b></p>
                        <table class="doc-table" style="margin-top: 4px; margin-bottom: 6px;">
                            <thead>
                                <tr>
                                    <th style="width: 35px; background-color: #3b82f6; color: #fff;">No.</th>
                                    <th style="background-color: #3b82f6; color: #fff;">Komponen Anggaran</th>
                                    <th style="width: 180px; background-color: #3b82f6; color: #fff;">Biaya yang Diusulkan (%)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr><td style="text-align: center;">1</td><td>Honorarium pelaksana</td><td style="text-align: center;">Maksimal 30%</td></tr>
                                <tr><td style="text-align: center;">2</td><td>Pembelian bahan habis pakai</td><td style="text-align: center;">Sesuai Kebutuhan</td></tr>
                                <tr><td style="text-align: center;">3</td><td>Perjalanan</td><td style="text-align: center;">Sesuai Kebutuhan</td></tr>
                                <tr><td style="text-align: center;">4</td><td>Sewa alat dan bahan</td><td style="text-align: center;">Sesuai Kebutuhan</td></tr>
                            </tbody>
                        </table>

                        <p style="margin-bottom: 2px;"><b>5.2 Jadwal Kegiatan</b></p>
                        <p style="margin-top: 0;">Disusun dalam bentuk bar chart sesuai rencana pelaksanaan, maksimal 6 bulan.</p>
                    </div>
                </div>

                <div style="margin-bottom: 8px;">
                    <p style="margin-bottom: 2px;"><b>E. REFERENSI</b></p>
                    <p style="margin-left: 15px; margin-top: 0;">80% dari pustaka adalah jurnal ilmiah mutakhir (maksimal 10 tahun terakhir) dengan sistem APA Style. Hanya pustaka yang diacu yang dicantumkan. Wajib menggunakan referensi Mendeley.</p>
                </div>
            </div>
        </div>

        <!-- ============================================================ -->
        <!-- HALAMAN 6: LAMPIRAN 5 - FORMAT IDENTITAS DAN URAIAN UMUM     -->
        <!-- ============================================================ -->
        <div class="page">
            <div class="kop-header">
                <img src="{{ $kopSuratBase64 }}" alt="Kop Surat Kampus Politeknik Indonusa Surakarta" />
            </div>

            <div class="lampiran-title-header">
                Lampiran 5. Format Identitas dan Uraian Umum
            </div>

            <div style="text-align: center; font-weight: bold; font-size: 12pt; margin-bottom: 20px;">
                IDENTITAS DAN URAIAN UMUM
            </div>

            <div style="font-size: 11pt; line-height: 1.6;">
                <div style="margin-bottom: 10px;">
                    1. Judul Penelitian : ...........................................................................................................................................
                </div>

                <div style="margin-bottom: 10px;">
                    2. Nama Tim Peneliti :
                    <div style="margin-left: 20px; margin-top: 4px;">
                        - Ketua : ...........................................................................................................................................<br>
                        - Anggota : ...........................................................................................................................................<br>
                        - Mahasiswa : ......... orang
                    </div>
                </div>

                <div style="margin-bottom: 10px;">
                    3. Objek Penelitian (jenis material yang akan diteliti dan segi penelitian):<br>
                    .........................................................................................................................................................................
                </div>

                <div style="margin-bottom: 10px;">
                    4. Masa Pelaksanaan<br>
                    &nbsp;&nbsp;&nbsp;&nbsp;Mulai : bulan: ............................................ tahun: ............................................<br>
                    &nbsp;&nbsp;&nbsp;&nbsp;Berakhir : bulan: ............................................ tahun: ............................................
                </div>

                <div style="margin-bottom: 10px;">
                    5. Usulan Biaya : Rp ...........................................................................................................................................
                </div>

                <div style="margin-bottom: 10px;">
                    6. Lokasi Penelitian (lab/studio/lapangan) ................................................................................................................
                </div>

                <div style="margin-bottom: 10px;">
                    7. Instansi lain yang terlibat (jika ada, dan uraikan apa kontribusinya)<br>
                    .........................................................................................................................................................................
                </div>

                <div style="margin-bottom: 10px;">
                    8. Temuan yang ditargetkan (penjelasan gejala atau kaidah, metode, teori, produk, atau rekayasa)<br>
                    .........................................................................................................................................................................
                </div>

                <div style="margin-bottom: 10px;">
                    9. Kontribusi mendasar pada suatu bidang ilmu<br>
                    .........................................................................................................................................................................
                </div>

                <div style="margin-bottom: 10px;">
                    10. Jurnal ilmiah yang menjadi sasaran (tuliskan nama terbitan berkala ilmiah internasional bereputasi, nasional terakreditasi, atau nasional tidak terakreditasi dan rencana publikasi)<br>
                    .........................................................................................................................................................................
                </div>

                <div style="margin-bottom: 10px;">
                    11. Rencana luaran lainnya (HKI, buku ajar, atau luaran lainnya yang ditargetkan, tahun rencana perolehan atau penyelesaiannya)<br>
                    .........................................................................................................................................................................
                </div>
            </div>
        </div>

        <!-- ============================================================ -->
        <!-- HALAMAN 7: LAMPIRAN 6 - FORMAT IDENTITAS PKM                 -->
        <!-- ============================================================ -->
        <div class="page">
            <div class="kop-header">
                <img src="{{ $kopSuratBase64 }}" alt="Kop Surat Kampus Politeknik Indonusa Surakarta" />
            </div>

            <div class="lampiran-title-header">
                Lampiran 6: Format Identitas dan Uraian Umum
            </div>

            <div style="text-align: center; font-weight: bold; font-size: 12pt; margin-bottom: 20px;">
                IDENTITAS DAN URAIAN UMUM
            </div>

            <div style="font-size: 11pt; line-height: 1.6;">
                <div style="margin-bottom: 10px;">
                    1. Judul Pengabdian kepada Masyarakat: ................................................................................................................
                </div>

                <div style="margin-bottom: 10px;">
                    2. Nama Tim Pelaksana
                    <div style="margin-left: 20px; margin-top: 4px;">
                        - Ketua : ...........................................................................................................................................<br>
                        - Anggota : ...........................................................................................................................................<br>
                        - Mahasiswa : ......... orang
                    </div>
                </div>

                <div style="margin-bottom: 10px;">
                    3. Objek (khalayak sasaran) Pengabdian kepada Masyarakat:<br>
                    .........................................................................................................................................................................
                </div>

                <div style="margin-bottom: 10px;">
                    4. Masa Pelaksanaan<br>
                    &nbsp;&nbsp;&nbsp;&nbsp;Mulai : bulan: ............................................ tahun: ............................................<br>
                    &nbsp;&nbsp;&nbsp;&nbsp;Berakhir : bulan: ............................................ tahun: ............................................
                </div>

                <div style="margin-bottom: 10px;">
                    5. Usulan Biaya : Rp ...........................................................................................................................................
                </div>

                <div style="margin-bottom: 10px;">
                    6. Lokasi Pengabdian kepada Masyarakat: ................................................................................................................
                </div>

                <div style="margin-bottom: 10px;">
                    7. Mitra yang terlibat (uraikan apa kontribusinya)<br>
                    .........................................................................................................................................................................
                </div>

                <div style="margin-bottom: 10px;">
                    8. Permasalahan yang ditemukan dan solusi yang ditawarkan:<br>
                    .........................................................................................................................................................................
                </div>

                <div style="margin-bottom: 10px;">
                    9. Kontribusi mendasar pada khalayak sasaran (uraikan tidak lebih dari 50 kata):<br>
                    .........................................................................................................................................................................
                </div>

                <div style="margin-bottom: 10px;">
                    10. Rencana luaran berupa jasa, sistem, produk/barang, atau luaran lainnya<br>
                    .........................................................................................................................................................................
                </div>
            </div>
        </div>
    </div>

</body>
</html>
