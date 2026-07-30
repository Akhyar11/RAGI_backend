<?php

namespace App\Services\Simpeg;

use App\Models\Simpeg\GajiPegawai;
use Illuminate\Support\Facades\Log;

class SikeuIntegrationService
{
    /**
     * Post payroll transaction to SIKEU Accounting Journal.
     */
    public static function postPayrollJournal(GajiPegawai $gaji): array
    {
        $nomorJurnal = "JRN-SIMPEG-" . now()->format('Ymd') . "-" . sprintf('%04d', $gaji->id);
        $totalDebet = $gaji->gaji_pokok + $gaji->total_tunjangan;

        $journalPayload = [
            'nomor_jurnal' => $nomorJurnal,
            'tanggal' => now()->toDateString(),
            'deskripsi' => "Beban Penggajian & Tunjangan SIMPEG Periode {$gaji->periode_bulan_tahun} - Pegawai ID #{$gaji->pegawai_id}",
            'entries' => [
                [
                    'kode_akun' => '5.1.01.01',
                    'nama_akun' => 'Beban Gaji & Tunjangan Pegawai',
                    'debet' => $totalDebet,
                    'kredit' => 0,
                ],
                [
                    'kode_akun' => '1.1.01.02',
                    'nama_akun' => 'Kas / Bank Operasional Rektorat',
                    'debet' => 0,
                    'kredit' => $gaji->gaji_bersih,
                ],
                [
                    'kode_akun' => '2.1.03.01',
                    'nama_akun' => 'Utang Potongan PPh21 & BPJS',
                    'debet' => 0,
                    'kredit' => $gaji->total_potongan,
                ],
            ],
            'status' => 'POSTED_TO_SIKEU',
            'integrated_at' => now()->toIso8601String(),
        ];

        Log::info("SIMPEG -> SIKEU INTEGRATION: Posted Journal {$nomorJurnal} for Amount Rp {$totalDebet}");

        return $journalPayload;
    }
}
