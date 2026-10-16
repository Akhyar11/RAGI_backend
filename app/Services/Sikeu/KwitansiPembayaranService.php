<?php

namespace App\Services\Sikeu;

use App\Mail\KwitansiPembayaranMail;
use App\Models\Sikeu\Pembayaran;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Pengiriman kwitansi email non-blocking (parity legacy helpers/email.js).
 *
 * Dipanggil SETELAH DB::commit agar kegagalan email tidak membatalkan
 * transaksi keuangan. Alamat diambil dari mahasiswa/calon/user terkait.
 */
class KwitansiPembayaranService
{
    public static function kirimSatuan(Pembayaran $pembayaran, float $sisaSetelahBayar = 0): void
    {
        try {
            $pembayaran->loadMissing(['tagihan.mahasiswa', 'tagihan.calonMahasiswa']);
            $tagihan = $pembayaran->tagihan;

            $email = $tagihan?->mahasiswa?->email
                ?? $tagihan?->mahasiswa?->user?->email
                ?? null;

            if (!$email) {
                Log::info('Kwitansi email dilewati (tanpa alamat): ' . $pembayaran->kode_transaksi);
                return;
            }

            $nama = $tagihan?->mahasiswa?->nama_lengkap
                ?? $tagihan?->calonMahasiswa?->nama_lengkap
                ?? 'Mahasiswa';

            $tautan = rtrim((string) config('app.frontend_url', 'http://localhost:3000'), '/')
                . '/validasi-pembayaran/' . $pembayaran->kode_transaksi;

            Mail::to($email)->send(new KwitansiPembayaranMail(
                namaPenerima: $nama,
                kodeTransaksi: $pembayaran->kode_transaksi,
                jumlahBayar: (float) $pembayaran->jumlah_bayar,
                waktuBayar: $pembayaran->waktu_bayar?->format('d M Y H:i') ?? now()->format('d M Y H:i'),
                channel: $pembayaran->channel_bayar,
                nomorTagihan: $tagihan?->nomor_tagihan,
                sisaSetelahBayar: $sisaSetelahBayar,
                tautanValidasi: $tautan,
            ));
        } catch (\Throwable $e) {
            Log::warning('Gagal mengirim kwitansi email ' . $pembayaran->kode_transaksi . ': ' . $e->getMessage());
        }
    }

    /**
     * Satu email ringkas untuk batch kasir (hindari spam per tagihan).
     *
     * @param array<int, Pembayaran> $pembayarans
     */
    public static function kirimBatch(array $pembayarans, string $kodeBatch, float $totalBayar, float $sisaSetelahBayar): void
    {
        try {
            $pertama = $pembayarans[0] ?? null;
            if (!$pertama) {
                return;
            }
            $pertama->loadMissing(['tagihan.mahasiswa', 'tagihan.calonMahasiswa']);
            $tagihan = $pertama->tagihan;

            $email = $tagihan?->mahasiswa?->email
                ?? $tagihan?->mahasiswa?->user?->email
                ?? null;

            if (!$email) {
                Log::info('Kwitansi batch dilewati (tanpa alamat): ' . $kodeBatch);
                return;
            }

            $tautan = rtrim((string) config('app.frontend_url', 'http://localhost:3000'), '/')
                . '/validasi-pembayaran/' . $kodeBatch;

            Mail::to($email)->send(new KwitansiPembayaranMail(
                namaPenerima: $tagihan?->mahasiswa?->nama_lengkap ?? $tagihan?->calonMahasiswa?->nama_lengkap ?? 'Mahasiswa',
                kodeTransaksi: $kodeBatch,
                jumlahBayar: $totalBayar,
                waktuBayar: now()->format('d M Y H:i'),
                channel: $pertama->channel_bayar,
                nomorTagihan: $tagihan?->nomor_tagihan,
                sisaSetelahBayar: $sisaSetelahBayar,
                tautanValidasi: $tautan,
            ));
        } catch (\Throwable $e) {
            Log::warning('Gagal mengirim kwitansi batch ' . $kodeBatch . ': ' . $e->getMessage());
        }
    }
}
