<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Kwitansi Pembayaran {{ $kodeTransaksi }}</title>
</head>
<body style="font-family: Arial, sans-serif; font-size: 14px; color: #1e293b;">
    <p>Yth. {{ $namaPenerima }},</p>
    <p>Pembayaran Anda telah kami terima dengan rincian:</p>
    <table cellpadding="6" cellspacing="0" border="1" style="border-collapse: collapse;">
        <tr>
            <td><strong>Kode Transaksi</strong></td>
            <td>{{ $kodeTransaksi }}</td>
        </tr>
        <tr>
            <td><strong>Nomor Tagihan</strong></td>
            <td>{{ $nomorTagihan ?? '-' }}</td>
        </tr>
        <tr>
            <td><strong>Jumlah Dibayar</strong></td>
            <td>Rp {{ number_format($jumlahBayar, 0, ',', '.') }}</td>
        </tr>
        <tr>
            <td><strong>Waktu Bayar</strong></td>
            <td>{{ $waktuBayar }}</td>
        </tr>
        <tr>
            <td><strong>Kanal</strong></td>
            <td>{{ $channel }}</td>
        </tr>
        <tr>
            <td><strong>Sisa Setelah Bayar</strong></td>
            <td>Rp {{ number_format($sisaSetelahBayar, 0, ',', '.') }}</td>
        </tr>
    </table>
    <p>Verifikasi keaslian kwitansi ini melalui tautan berikut:</p>
    <p><a href="{{ $tautanValidasi }}">{{ $tautanValidasi }}</a></p>
    <p>Hormat kami,<br>Bagian Keuangan Kampus</p>
</body>
</html>
