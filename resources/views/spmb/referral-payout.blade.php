<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Bukti Pencairan Reward Referral</title>
    <style>
        * { font-family: DejaVu Sans, Arial, sans-serif; }
        body { font-size: 12px; color: #0f172a; }
        .header { border-bottom: 2px solid #0f172a; padding-bottom: 8px; margin-bottom: 16px; }
        .title { font-size: 18px; font-weight: bold; margin: 0; }
        .subtitle { font-size: 11px; color: #475569; margin-top: 2px; }
        .meta td { padding: 2px 6px 2px 0; vertical-align: top; }
        table.items { width: 100%; border-collapse: collapse; margin-top: 12px; }
        table.items th, table.items td { border: 1px solid #cbd5e1; padding: 6px 8px; text-align: left; }
        table.items th { background: #f1f5f9; font-size: 11px; }
        .right { text-align: right; }
        .total-row td { font-weight: bold; background: #f8fafc; }
        .total { font-size: 16px; font-weight: bold; margin-top: 12px; text-align: right; }
        .note { margin-top: 18px; font-size: 10px; color: #64748b; }
        .sign { margin-top: 40px; width: 100%; }
        .sign td { width: 50%; text-align: center; padding-top: 48px; }
    </style>
</head>
<body>
    <div class="header">
        <p class="title">BUKTI PENCAIRAN REWARD REFERRAL</p>
        <p class="subtitle">SPMB — Sistem Penerimaan Mahasiswa Baru</p>
    </div>

    <table class="meta">
        <tr><td>Nomor Bukti</td><td>: <strong>{{ $payout->nomor_bukti }}</strong></td></tr>
        <tr><td>Nama Referrer</td><td>: {{ $user->name ?? $user->username }}</td></tr>
        <tr><td>Username</td><td>: {{ $user->username }}</td></tr>
        <tr><td>Kode Referral</td><td>: {{ $user->referral_code ?? '-' }}</td></tr>
        <tr><td>Tanggal Cetak</td><td>: {{ $generatedAt->translatedFormat('d F Y H:i') }}</td></tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th style="width: 30px;">No</th>
                <th>No. Pendaftaran</th>
                <th>Nama Pendaftar</th>
                <th>Status</th>
                <th class="right">Nominal</th>
            </tr>
        </thead>
        <tbody>
            @php $total = 0; @endphp
            @forelse ($payout->usages as $i => $usage)
                @php
                    $nominal = (float) ($payout->referral_count > 0
                        ? $payout->total_nominal / max(1, $payout->referral_count)
                        : 0);
                    $total += $nominal;
                @endphp
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $usage->pendaftaran?->no_pendaftaran ?? '-' }}</td>
                    <td>{{ $usage->pendaftaran?->nama_lengkap ?? '-' }}</td>
                    <td>{{ $usage->status }}</td>
                    <td class="right">Rp {{ number_format($nominal, 0, ',', '.') }}</td>
                </tr>
            @empty
                <tr><td colspan="5">Tidak ada referral yang layak dicairkan.</td></tr>
            @endforelse
            <tr class="total-row">
                <td colspan="4" class="right">TOTAL ({{ $payout->referral_count }} referral)</td>
                <td class="right">Rp {{ number_format((float) $payout->total_nominal, 0, ',', '.') }}</td>
            </tr>
        </tbody>
    </table>

    <p class="total">Jumlah Dicairkan: Rp {{ number_format((float) $payout->total_nominal, 0, ',', '.') }}</p>

    <p class="note">
        Bukti ini dihasilkan otomatis oleh sistem sebagai dasar klaim pencairan reward referral.
        Status referral tidak berubah; referral yang tercantum ditandai telah di-payout.
    </p>

    <table class="sign">
        <tr>
            <td>Dibuat oleh,<br><br><br>________________________</td>
            <td>Disetujui oleh,<br><br><br>________________________</td>
        </tr>
    </table>
</body>
</html>
