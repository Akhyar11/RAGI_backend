<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $judulSurat ?? 'Surat Keterangan Tanda Lulus' }} - {{ $pendaftaran->no_pendaftaran }}</title>
    <style>
        @page {
            margin: 1.5cm 2cm 1.5cm 2cm;
            size: A4 portrait;
        }
        * {
            box-sizing: border-box;
            font-family: 'DejaVu Sans', Arial, Helvetica, sans-serif;
        }
        body {
            font-size: 11px;
            line-height: 1.5;
            color: #0f172a;
            margin: 0;
            padding: 0;
        }
        .header-kop {
            text-align: center;
            border-bottom: 2px solid #0f172a;
            padding-bottom: 12px;
            margin-bottom: 4px;
            position: relative;
        }
        .header-kop .inst-name {
            font-size: 15px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin: 0;
            color: #0f172a;
        }
        .header-kop .inst-sub {
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin: 2px 0 0 0;
            color: #1e293b;
        }
        .header-kop .inst-desc {
            font-size: 9px;
            color: #475569;
            margin: 4px 0 0 0;
            line-height: 1.3;
        }
        .kop-double-line {
            border-top: 1px solid #0f172a;
            margin-top: 2px;
            margin-bottom: 18px;
        }
        .doc-title-box {
            text-align: center;
            margin-bottom: 18px;
        }
        .doc-title {
            font-size: 13px;
            font-weight: bold;
            text-transform: uppercase;
            text-decoration: underline;
            margin: 0;
            letter-spacing: 0.5px;
        }
        .doc-nomor {
            font-size: 10px;
            color: #334155;
            margin-top: 3px;
        }
        .intro-text {
            text-align: justify;
            margin-bottom: 14px;
            font-size: 11px;
        }
        table.biodata-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
        }
        table.biodata-table td {
            padding: 3px 4px;
            vertical-align: top;
            font-size: 11px;
        }
        table.biodata-table td.label-col {
            width: 28%;
            color: #334155;
            font-weight: 500;
        }
        table.biodata-table td.colon-col {
            width: 3%;
            text-align: center;
        }
        table.biodata-table td.val-col {
            width: 69%;
            font-weight: bold;
            color: #0f172a;
        }
        .decision-box {
            border: 2px solid #059669;
            background-color: #ecfdf5;
            border-radius: 6px;
            padding: 12px 16px;
            text-align: center;
            margin: 16px 0;
        }
        .decision-box .status-label {
            font-size: 10px;
            text-transform: uppercase;
            color: #047857;
            font-weight: bold;
            letter-spacing: 1px;
            margin: 0;
        }
        .decision-box .status-main {
            font-size: 16px;
            font-weight: bold;
            color: #065f46;
            margin: 3px 0 6px 0;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .decision-box .prodi-box {
            font-size: 12px;
            color: #0f172a;
            font-weight: bold;
            margin: 0;
        }
        .guidelines-box {
            margin-top: 14px;
            margin-bottom: 20px;
            font-size: 10px;
            color: #334155;
        }
        .guidelines-box h4 {
            font-size: 10.5px;
            font-weight: bold;
            margin: 0 0 4px 0;
            color: #0f172a;
            text-transform: uppercase;
        }
        .guidelines-content {
            white-space: pre-line;
            line-height: 1.5;
            font-size: 10px;
            color: #334155;
            text-align: justify;
        }
        .signature-table {
            width: 100%;
            margin-top: 25px;
            border-collapse: collapse;
        }
        .signature-table td {
            vertical-align: top;
        }
        .qr-placeholder {
            width: 70px;
            height: 70px;
            border: 1px dashed #94a3b8;
            background-color: #f8fafc;
            padding: 6px;
            text-align: center;
            font-size: 8px;
            color: #64748b;
            border-radius: 4px;
            display: inline-block;
        }
        .footer-note {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            border-top: 1px solid #cbd5e1;
            padding-top: 6px;
            font-size: 8.5px;
            color: #64748b;
            text-align: center;
        }
    </style>
</head>
<body>

    <!-- KOP SURAT RESMI DINAMIS -->
    <div class="header-kop">
        <h1 class="inst-name">{{ $kopInstitusi }}</h1>
        <h2 class="inst-sub">{{ $kopSub }}</h2>
        <p class="inst-desc">{!! nl2br(e($kopKontak)) !!}</p>
    </div>
    <div class="kop-double-line"></div>

    <!-- JUDUL & NOMOR SURAT -->
    <div class="doc-title-box">
        <h3 class="doc-title">{{ $judulSurat }}</h3>
        <p class="doc-nomor">Nomor: {{ $nomorSurat }}</p>
    </div>

    <!-- PENGANTAR -->
    <p class="intro-text">
        {!! nl2br(e($teksPembuka)) !!}
    </p>

    <!-- DATA CALON MAHASISWA -->
    <table class="biodata-table">
        <tr>
            <td class="label-col">Nomor Registrasi / Pendaftaran</td>
            <td class="colon-col">:</td>
            <td class="val-col">{{ $pendaftaran->no_pendaftaran }}</td>
        </tr>
        <tr>
            <td class="label-col">Nama Lengkap</td>
            <td class="colon-col">:</td>
            <td class="val-col">{{ strtoupper($pendaftaran->nama_lengkap ?? '-') }}</td>
        </tr>
        <tr>
            <td class="label-col">Nomor Induk Kependudukan (NIK)</td>
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

    <!-- KEPUTUSAN KELULUSAN -->
    <div class="decision-box">
        <p class="status-label">Keputusan Hasil Seleksi Administrasi:</p>
        <p class="status-main">{{ $teksKeputusan }}</p>
        <p class="prodi-box">
            Program Studi: {{ $prodiDiterimaNama }} {{ !empty($jenjangDiterima) ? '('.$jenjangDiterima.')' : '' }}
        </p>
    </div>

    <!-- PETUNJUK DAFTAR ULANG -->
    @if(!empty($petunjukDaftarUlang))
    <div class="guidelines-box">
        <h4>Petunjuk & Ketentuan Daftar Ulang:</h4>
        <div class="guidelines-content">{!! nl2br(e($petunjukDaftarUlang)) !!}</div>
    </div>
    @endif

    <!-- TANDA TANGAN & PENGESAHAN -->
    <table class="signature-table">
        <tr>
            <td style="width: 50%;">
                <div class="qr-placeholder">
                    <br>
                    <strong>DOKUMEN VALID</strong><br>
                    <span>ID: {{ substr(md5(($pendaftaran->no_pendaftaran ?? 'PREVIEW') . ($pendaftaran->id ?? 9999)), 0, 10) }}</span>
                </div>
                <div style="font-size: 8.5px; color: #64748b; margin-top: 4px;">
                    Dicetak secara mandiri oleh sistem pada:<br>
                    <strong>{{ now()->translatedFormat('d F Y H:i:s') }} WIB</strong>
                </div>
            </td>
            <td style="width: 50%; text-align: right;">
                <div style="text-align: center; display: inline-block;">
                    <span>Ditetapkan di: {{ $kotaPenetapan }}<br>Pada tanggal: {{ $tanggalPenetapan }}</span><br>
                    <span style="font-weight: bold;">Panitia Penerimaan Mahasiswa Baru,</span><br><br><br><br>
                    <span style="font-weight: bold; text-decoration: underline;">
                        {{ $namaPenandatangan }}
                    </span><br>
                    <span style="font-size: 9.5px; color: #475569;">{{ $jabatanPenandatangan }}</span>
                    @if(!empty($nipPenandatangan))
                    <br><span style="font-size: 9px; color: #64748b;">NIP/NIDN: {{ $nipPenandatangan }}</span>
                    @endif
                </div>
            </td>
        </tr>
    </table>

    <!-- FOOTER RESMI -->
    <div class="footer-note">
        {{ $catatanKaki }}
    </div>

</body>
</html>
