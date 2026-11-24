<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $judul }} - {{ $prodi?->nama ?? 'Semua Program Studi' }}</title>
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
        .meta-table { width: 100%; border-collapse: collapse; margin-bottom: 5mm; }
        .meta-table td { padding: 1.5mm 2mm; border: 1px solid #d1d5db; }
        .meta-table .label { width: 22%; background: #f3f4f6; font-weight: bold; }
        .group-title {
            font-size: 10pt;
            font-weight: bold;
            margin: 5mm 0 2mm 0;
            padding: 1.5mm 2mm;
            background: #f3f4f6;
            border-left: 3px solid #374151;
        }
        table.data { width: 100%; border-collapse: collapse; }
        table.data th, table.data td { border: 1px solid #9ca3af; padding: 1.5mm 2mm; text-align: left; }
        table.data th { background: #f3f4f6; font-size: 8pt; text-transform: uppercase; }
        table.data td.number { text-align: center; }
        .bad { color: #b91c1c; font-weight: bold; }
        .footer { margin-top: 8mm; font-size: 8pt; color: #4b5563; border-top: 1px solid #d1d5db; padding-top: 2mm; }
    </style>
</head>
<body>
    <h1>{{ $judul }}</h1>
    <div class="subtitle">Sistem Informasi Terpadu Kampus &mdash; Modul LMS</div>

    <table class="meta-table">
        <tr>
            <td class="label">Program Studi</td>
            <td>{{ $prodi?->nama ?? 'Semua Program Studi yang Dapat Diakses' }}</td>
            <td class="label">Tahun Akademik</td>
            <td>{{ $tahunAkademik->nama ?? $tahunAkademik->kode }}</td>
        </tr>
        <tr>
            <td class="label">Jumlah Kelas</td>
            <td>{{ $jumlahKelas }}</td>
            <td class="label">Jumlah Mahasiswa</td>
            <td>{{ $jumlahBaris }}</td>
        </tr>
        <tr>
            <td class="label">Tanggal Cetak</td>
            <td colspan="3">{{ $tanggalCetak }}</td>
        </tr>
    </table>

    @forelse ($grupProdi as $grup)
        <div class="group-title">{{ $grup['kode'] }} &mdash; {{ $grup['nama'] }}</div>
        <table class="data">
            <thead>
                <tr>
                    <th style="width: 15%;">Kode Kelas</th>
                    <th>Mata Kuliah</th>
                    <th style="width: 10%;">Pertemuan</th>
                    <th style="width: 10%;">Mhs</th>
                    <th style="width: 11%;">Rata-rata %</th>
                    <th style="width: 11%;">Memenuhi</th>
                    <th style="width: 13%;">Tidak Memenuhi</th>
                </tr>
            </thead>
            <tbody>
            @foreach ($grup['kelas'] as $kelas)
                <tr>
                    <td>{{ $kelas['kode_kelas'] }}</td>
                    <td>{{ $kelas['mata_kuliah'] }}</td>
                    <td class="number">{{ $kelas['total_pertemuan'] }}</td>
                    <td class="number">{{ $kelas['total_mahasiswa'] }}</td>
                    <td class="number">{{ $kelas['rata_persen'] }}%</td>
                    <td class="number">{{ $kelas['memenuhi'] }}</td>
                    <td class="number {{ $kelas['tidak_memenuhi'] > 0 ? 'bad' : '' }}">{{ $kelas['tidak_memenuhi'] }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @empty
        <table class="data">
            <tr><td class="number">Tidak ada kelas pada program studi &amp; tahun akademik yang dipilih.</td></tr>
        </table>
    @endforelse

    <div class="footer">
        Rekap dihitung dari presensi yang telah disubmit pada setiap pertemuan. Persentase rata-rata kelas
        merupakan rata-rata kehadiran seluruh mahasiswa yang terdaftar via KRS.
    </div>
</body>
</html>
