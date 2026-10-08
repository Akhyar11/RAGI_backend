@php
    $kopGapMm = 4;
    $kopHeightMm = $kopSuratHeightMm ?? 30;
    $pageTopMarginMm = $kopHeightMm + $kopGapMm;
    $pageSideMarginCm = 2;
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $judulSurat ?? 'Surat Keterangan Tanda Lulus' }} - {{ $pendaftaran->no_pendaftaran }}</title>
    <style>
        @page {
            size: A4 portrait;
            /* Top margin menyisakan ruang untuk kop yang diulang tiap halaman. */
            margin: {{ $pageTopMarginMm }}mm {{ $pageSideMarginCm }}cm 1.5cm {{ $pageSideMarginCm }}cm;
        }
        * {
            box-sizing: border-box;
        }
        body {
            font-family: 'DejaVu Serif', 'Times New Roman', serif;
            font-size: 11pt;
            line-height: 1.5;
            color: #000000;
            margin: 0;
            padding: 0;
        }

        /* KOP SURAT: menempel penuh kiri-kanan (full-bleed) & diulang setiap halaman.
           Offset negatif menarik elemen fixed ke tepi halaman (Dompdf: fixed relatif area konten). */
        .kop-fixed {
            position: fixed;
            top: -{{ $pageTopMarginMm }}mm;
            left: -{{ $pageSideMarginCm }}cm;
            width: 210mm;
        }
        .kop-fixed img.kop-image {
            width: 210mm;
            height: auto;
            display: block;
        }
        .kop-fixed .header-kop {
            width: 210mm;
            padding: 0 {{ $pageSideMarginCm }}cm;
            text-align: center;
        }
        .header-kop .inst-name {
            font-size: 14pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin: 0;
        }
        .header-kop .inst-sub {
            font-size: 12pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin: 2px 0 0 0;
        }
        .header-kop .inst-desc {
            font-size: 9pt;
            margin: 3px 0 0 0;
            line-height: 1.35;
        }
        .kop-line-thick {
            border-top: 2px solid #000000;
            margin-top: 4px;
        }
        .kop-line-thin {
            border-top: 1px solid #000000;
            margin-top: 1px;
        }

        /* JUDUL & NOMOR */
        .doc-title-box {
            text-align: center;
            margin: 0 0 16px 0;
        }
        .doc-title {
            font-size: 14pt;
            font-weight: bold;
            text-transform: uppercase;
            text-decoration: underline;
            letter-spacing: 0.5px;
            margin: 0;
        }
        .doc-nomor {
            font-size: 12pt;
            margin: 2px 0 0 0;
        }

        /* ISI SURAT */
        .intro-text {
            text-align: justify;
            margin: 0 0 10px 0;
        }
        .statement {
            text-align: justify;
            margin: 10px 0 8px 0;
        }
        table.biodata-table {
            width: 100%;
            border-collapse: collapse;
            margin: 0 0 10px 24px;
        }
        table.biodata-table td {
            padding: 1.5px 4px;
            vertical-align: top;
            font-size: 11pt;
        }
        table.biodata-table td.label-col {
            width: 30%;
        }
        table.biodata-table td.colon-col {
            width: 3%;
        }
        table.biodata-table td.val-col {
            width: 67%;
            font-weight: bold;
        }

        /* KEPUTUSAN */
        .decision-box {
            border: 1.5px solid #000000;
            padding: 10px 14px;
            text-align: center;
            margin: 14px 0;
        }
        .decision-box .status-label {
            font-size: 10pt;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin: 0;
        }
        .decision-box .status-main {
            font-size: 15pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin: 4px 0 6px 0;
        }
        .decision-box .prodi-box {
            font-size: 11pt;
            margin: 0;
        }

        /* PETUNJUK */
        .guidelines-box {
            margin: 12px 0 18px 0;
            font-size: 10.5pt;
        }
        .guidelines-box h4 {
            font-size: 10.5pt;
            font-weight: bold;
            margin: 0 0 4px 0;
            text-transform: uppercase;
        }
        .guidelines-content {
            white-space: pre-line;
            text-align: justify;
            line-height: 1.5;
        }

        /* TANDA TANGAN */
        .signature-wrap {
            width: 100%;
            margin-top: 28px;
        }
        .signature-block {
            width: 62mm;
            margin-left: auto;
            text-align: center;
        }
        .signature-block .place-date {
            margin: 0 0 2px 0;
        }
        .signature-block .role {
            margin: 0 0 2px 0;
        }
        .signature-space {
            height: 22mm;
        }
        .signature-name {
            font-weight: bold;
            text-decoration: underline;
            margin: 0;
        }
        .signature-nip {
            font-size: 10pt;
            margin: 1px 0 0 0;
        }

        /* FOOTER */
        .footer-note {
            margin-top: 22px;
            border-top: 1px solid #000000;
            padding-top: 5px;
            font-size: 8.5pt;
            text-align: center;
            line-height: 1.4;
        }
    </style>
</head>
<body>

    <!-- KOP SURAT RESMI (FULL-BLEED, DIULANG SETIAP HALAMAN) -->
    <div class="kop-fixed">
        @if(!empty($kopSuratDataUri))
            <img src="{{ $kopSuratDataUri }}" alt="Kop Surat" class="kop-image">
        @else
            <div class="header-kop">
                <h1 class="inst-name">{{ $kopInstitusi }}</h1>
                <h2 class="inst-sub">{{ $kopSub }}</h2>
                <p class="inst-desc">{!! nl2br(e($kopKontak)) !!}</p>
                <div class="kop-line-thick"></div>
                <div class="kop-line-thin"></div>
            </div>
        @endif
    </div>

    <!-- JUDUL & NOMOR SURAT -->
    <div class="doc-title-box">
        <h3 class="doc-title">{{ $judulSurat }}</h3>
        <p class="doc-nomor">Nomor: {{ $nomorSurat }}</p>
    </div>

    <!-- PENGANTAR -->
    <p class="intro-text">{!! nl2br(e($teksPembuka)) !!}</p>

    <!-- DATA CALON MAHASISWA -->
    <table class="biodata-table">
        <tr>
            <td class="label-col">Nomor Pendaftaran</td>
            <td class="colon-col">:</td>
            <td class="val-col">{{ $pendaftaran->no_pendaftaran }}</td>
        </tr>
        <tr>
            <td class="label-col">Nama Lengkap</td>
            <td class="colon-col">:</td>
            <td class="val-col">{{ strtoupper($pendaftaran->nama_lengkap ?? '-') }}</td>
        </tr>
        <tr>
            <td class="label-col">NIK</td>
            <td class="colon-col">:</td>
            <td class="val-col">{{ $pendaftaran->nik ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label-col">Tempat, Tanggal Lahir</td>
            <td class="colon-col">:</td>
            <td class="val-col">
                {{ $pendaftaran->tempat_lahir ?? '-' }}, {{ $pendaftaran->tanggal_lahir ? $pendaftaran->tanggal_lahir->translatedFormat('d F Y') : '-' }}
            </td>
        </tr>
        <tr>
            <td class="label-col">Asal Sekolah / Institusi</td>
            <td class="colon-col">:</td>
            <td class="val-col">{{ $pendaftaran->asal_sekolah ?? $pendaftaran->asal_pt ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label-col">Jalur Masuk</td>
            <td class="colon-col">:</td>
            <td class="val-col">{{ $pendaftaran->gelombangPenerimaan?->jalurMasuk?->nama ?? 'Reguler' }}</td>
        </tr>
        <tr>
            <td class="label-col">Gelombang Pendaftaran</td>
            <td class="colon-col">:</td>
            <td class="val-col">{{ $pendaftaran->gelombangPenerimaan?->nama ?? 'Gelombang Utama' }}</td>
        </tr>
    </table>

    <!-- PERNYATAAN -->
    <p class="statement">{!! nl2br(e($teksPernyataan)) !!}</p>

    <!-- KEPUTUSAN KELULUSAN -->
    <div class="decision-box">
        <p class="status-label">{{ $labelKeputusan }}</p>
        <p class="status-main">{{ $teksKeputusan }}</p>
        @if(($hasil ?? 'diterima') !== 'ditolak')
            <p class="prodi-box">{{ $teksProdi }}</p>
        @endif
    </div>

    <!-- PETUNJUK DAFTAR ULANG (hanya untuk keputusan diterima) -->
    @if(($hasil ?? 'diterima') !== 'ditolak' && !empty($petunjukDaftarUlang))
    <div class="guidelines-box">
        <h4>{{ $judulPetunjuk }}</h4>
        <div class="guidelines-content">{!! nl2br(e($petunjukDaftarUlang)) !!}</div>
    </div>
    @endif

    <p class="intro-text">{!! nl2br(e($teksPenutup)) !!}</p>

    <!-- TANDA TANGAN & PENGESAHAN -->
    <div class="signature-wrap">
        <div class="signature-block">
            <p class="place-date">{{ $kotaPenetapan }}, {{ $tanggalPenetapan }}</p>
            <p class="role">{{ $jabatanPenandatangan }}</p>
            <div class="signature-space"></div>
            <p class="signature-name">{{ $namaPenandatangan }}</p>
            @if(!empty($nipPenandatangan))
                <p class="signature-nip">NIP/NIDN: {{ $nipPenandatangan }}</p>
            @endif
        </div>
    </div>

    <!-- FOOTER RESMI -->
    @if(!empty($catatanKaki))
    <div class="footer-note">{{ $catatanKaki }}</div>
    @endif

</body>
</html>
