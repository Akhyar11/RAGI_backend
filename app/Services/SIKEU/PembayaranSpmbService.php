<?php

namespace App\Services\SIKEU;

use App\Models\PendaftaranCalonMhs;
use App\Models\Spmb\PembayaranSpmb;
use App\Notifications\TagihanVaNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class PembayaranSpmbService
{
    /**
     * Generate tagihan pendaftaran untuk SPMB
     */
    public function generateTagihanPendaftaran(PendaftaranCalonMhs $pendaftaran): PembayaranSpmb
    {
        return DB::transaction(function () use ($pendaftaran) {
            // 1. Dapatkan nominal dari Gelombang
            $gelombang = $pendaftaran->gelombang;
            $biaya = $gelombang->biaya_pendaftaran ?? 250000; // Mock default

            // 2. Generate VA Number (Mocking integrasi BNI/Mandiri)
            $vaNumber = '8' . rand(1000000000, 9999999999);

            // 3. Simpan ke database
            $pembayaran = PembayaranSpmb::create([
                'pendaftaran_id' => $pendaftaran->id,
                'kode_bayar' => 'INV-SPMB-' . time(),
                'jumlah_tagihan' => $biaya,
                'jumlah_bayar' => 0,
                'status' => 'pending', // VARCHAR status (not ENUM)
                'metode_bayar' => 'virtual_account',
                'va_number' => $vaNumber,
                'expired_at' => now()->addDays(2),
            ]);

            // 4. Kirim Notifikasi Tagihan via EMAIL
            try {
                // User yang mendaftar
                $user = $pendaftaran->user;
                if ($user) {
                    $user->notify(new TagihanVaNotification($pembayaran));
                }
            } catch (Exception $e) {
                Log::error('Gagal mengirim email tagihan VA: ' . $e->getMessage());
            }

            return $pembayaran;
        });
    }

    /**
     * Webhook konfirmasi pembayaran dari Payment Gateway
     */
    public function konfirmasiPembayaran(string $vaNumber, float $jumlahBayar): ?PembayaranSpmb
    {
        return DB::transaction(function () use ($vaNumber, $jumlahBayar) {
            $pembayaran = PembayaranSpmb::where('va_number', $vaNumber)
                                        ->where('status', 'pending')
                                        ->first();

            if (!$pembayaran) {
                return null;
            }

            if ($jumlahBayar >= $pembayaran->jumlah_tagihan) {
                $pembayaran->update([
                    'status' => 'paid',
                    'jumlah_bayar' => $jumlahBayar,
                    'paid_at' => now(),
                    'gateway_response' => json_encode(['status' => 'success', 'paid_amount' => $jumlahBayar]),
                ]);

                // Bisa trigger event PembayaranLunas event di sini
            }

            return $pembayaran;
        });
    }
}
