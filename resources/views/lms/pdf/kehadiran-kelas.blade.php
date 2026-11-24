<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $judul }} - {{ $kelas->kode_kelas }}</title>
    <style>
        @page { size: A4 portrait; margin: 14mm 12mm; }
        * { box-sizing: border-box; }
        body {
            font-family: 'DejaVu Sans', 'Helvetica', sans-serif;
            font-size: 9pt;
            color: #111827;
            margin: 0;
        }
        h1 { font-size: 15pt; margin: 0 0 2mm 0; text-align: center; text-transform: uppercase; }
        .subtitle { text-align: center; font-size: 8pt; color: #4b5563; margin-bottom: 6mm; }
        .info-table { width: 100%; border-collapse: collapse; margin-bottom: 5mm; }
        .info-table td { padding: 1.5mm 2mm; border: 1px solid #d1d5db; vertical-align: top; }
        .info-table .label { width: 26%; background: #f3f4f6; font-weight: bold; }
        .section-title { font-size: 10pt; font-weight: bold; margin: 5mm 0 2mm 0; }
        table.data { width: 100%; border-collapse: collapse; }
        table.data th, table.data td { border: 1px solid #9ca3af; padding: 1.5mm 2mm; text-align: left; }
        table.data th { background: #f3f4f6; font-size: 8pt; text-transform: uppercase; }
        table.data td.number { text-align: center; }
        .bad { color: #b91c1c; font-weight: bold; }
        .good { color: #166534; }
        .footer { margin-top: 8mm; font-size: 8pt; color: #4b5563; border-top: 1px solid #d1d5db; padding-top: 2mm; }
        .signature { margin-top: 10mm; text-align: right; }
        .signature .name { margin-top: 12mm; font-weight: bold; text-decoration: underline; }
    </style>
</head>
<body>
    <h1>{{ $judul }}</h1>
    <div class="subtitle">Sistem Informasi Terpadu Kampus &mdash; Modul LMS</div>

    <table class="info-table">
        <tr>
            <td class="label">Kode Kelas</td>
            <td>{{ $kelas->kode_kelas }}</td>
            <td class="label">Program Studi</td>
            <td>{{ $kelas->programStudi?->nama ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label">Mata Kuliah</td>
            <td>{{ $kelas->mataKuliah?->nama ?? '-' }} ({{ $kelas->mataKuliah?->kode_mk ?? '-' }})</td>
            <td class="label">Tahun Akademik</td>
            <td>{{ $kelas->tahunAkademik?->nama ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label">Nama Kelas</td>
            <td>{{ $kelas->nama_kelas }}</td>
            <td class="label">Total Pertemuan</td>
            <td>{{ $totalPertemuan }}</td>
        </tr>
        <tr>
            <td class="label">Batas Min. Hadir</td>
            <td>{{ $batasMinHadir }}%</td>
            <td class="label">Tanggal Cetak</td>
            <td>{{ $tanggalCetak }}</td>
        </tr>
    </table>

    <div class="section-title">A. Rekapitulasi Mahasiswa</div>
    <table class="data">
        <thead>
            <tr>
                <th style="width: 6%;">No</th>
                <th style="width: 18%;">NIM</th>
                <th>Nama Mahasiswa</th>
                <th style="width: 8%;">Hadir</th>
                <th style="width: 8%;">Sakit</th>
                <th style="width: 8%;">Izin</th>
                <th style="width: 8%;">Alfa</th>
                <th style="width: 12%;">%</th>
                <th style="width: 16%;">Keterangan</th>
            </tr>
        </thead>
        <tbody>
        @forelse ($rekapitulasi as $index => $item)
            <tr>
                <td class="number">{{ $index + 1 }}</td>
                <td>{{ $item['nim'] }}</td>
                <td>{{ $item['nama_lengkap'] }}</td>
                <td class="number">{{ $item['hadir'] }}</td>
                <td class="number">{{ $item['sakit'] }}</td>
                <td class="number">{{ $item['izin'] }}</td>
                <td class="number">{{ $item['alfa'] }}</td>
                <td class="number">{{ $item['persen_kehadiran'] }}%</td>
                <td class="number {{ $item['memenuhi_syarat'] ? 'good' : 'bad' }}">
                    {{ $item['memenuhi_syarat'] ? 'Memenuhi' : 'Tidak Memenuhi' }}
                </td>
            </tr>
        @empty
            <tr><td colspan="9" class="number">Belum ada mahasiswa yang terdaftar pada kelas ini.</td></tr>
        @endforelse
        </tbody>
    </table>

    <div class="section-title">B. Ringkasan Pertemuan</div>
    <table class="data">
        <thead>
            <tr>
                <th style="width: 12%;">Pertemuan</th>
                <th style="width: 22%;">Tanggal</th>
                <th style="width: 16%;">Hadir</th>
                <th style="width: 16%;">Sakit</th>
                <th style="width: 16%;">Izin</th>
                <th style="width: 18%;">Alfa</th>
            </tr>
        </thead>
        <tbody>
        @forelse ($pertemuans as $pertemuan)
            <tr>
                <td class="number">{{ $pertemuan['pertemuan_ke'] }}</td>
                <td class="number">{{ $pertemuan['tanggal'] }}</td>
                <td class="number">{{ $pertemuan['hadir'] }}</td>
                <td class="number">{{ $pertemuan['sakit'] }}</td>
                <td class="number">{{ $pertemuan['izin'] }}</td>
                <td class="number">{{ $pertemuan['alfa'] }}</td>
            </tr>
        @empty
            <tr><td colspan="6" class="number">Belum ada pertemuan pada kelas ini.</td></tr>
        @endforelse
        </tbody>
    </table>

    <div class="footer">
        Laporan ini dihasilkan otomatis dari data jurnal &amp; presensi perkuliahan yang tersimpan di RAG.
        Status kehadiran mengikuti data presensi yang telah disubmit.
    </div>
    <div class="signature">
        Dosen Pengampu Kelas<br>
        <span class="name">&nbsp;</span>
    </div>
</body>
</html>
