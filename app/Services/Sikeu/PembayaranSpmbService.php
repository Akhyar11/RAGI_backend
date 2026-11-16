<?php

namespace App\Services\Sikeu;

use App\Models\Spmb\PendaftaranCalonMhs;
use App\Models\Spmb\PembayaranSpmb;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PembayaranSpmbService
{
    /**
     * Generate tagihan pendaftaran untuk SPMB
     */
    public function generateTagihanPendaftaran(PendaftaranCalonMhs $pendaftaran): PembayaranSpmb
    {
        throw ValidationException::withMessages([
            'biaya' => 'Penerbitan tagihan SPMB harus melalui Master Biaya SPMB dan kanal pembayaran resmi.',
        ]);
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
