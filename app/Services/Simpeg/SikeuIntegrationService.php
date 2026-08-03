<?php

namespace App\Services\Simpeg;

use App\Models\Simpeg\GajiPegawai;
use App\Models\Sikeu\AkunKeuangan;
use App\Models\Sikeu\JurnalUmum;
use App\Models\Sikeu\DetailJurnalUmum;
use App\Models\Sikeu\UnitKas;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SikeuIntegrationService
{
    /**
     * Post payroll transaction to SIKEU Accounting Journal.
     * Menulis langsung ke tabel jurnal_umum & detail_jurnal_umum.
     *
     * Skema jurnal penggajian:
     *   DEBET  : 5.1.01.01 — Beban Gaji & Tunjangan Pegawai  (gaji_pokok + total_tunjangan)
     *   KREDIT : 1.1.01.02 — Kas / Bank Operasional Rektorat  (gaji_bersih)
     *   KREDIT : 2.1.03.01 — Utang Potongan PPh21 & BPJS      (total_potongan)
     */
    public static function postPayrollJournal(GajiPegawai $gaji): array
    {
        try {
            return DB::transaction(function () use ($gaji) {
                $totalDebet  = (float) $gaji->gaji_pokok + (float) ($gaji->total_tunjangan ?? 0);
                $gajiB       = (float) $gaji->gaji_bersih;
                $potongan    = (float) ($gaji->total_potongan ?? 0);

                // --- Cari / fallback COA accounts ---
                $akunBeban = AkunKeuangan::where('kode_akun', '5.1.01.01')->first()
                    ?? AkunKeuangan::where('kelompok', 'beban')->first();

                $akunKas = AkunKeuangan::where('kode_akun', '1.1.01.02')->first()
                    ?? AkunKeuangan::where('kode_akun', '102.01')->first()
                    ?? AkunKeuangan::where('kelompok', 'aset')->whereRaw("LOWER(nama_akun) LIKE '%kas%'")->first();

                $akunUtangPajak = AkunKeuangan::where('kode_akun', '2.1.03.01')->first()
                    ?? AkunKeuangan::where('kelompok', 'liabilitas')->first();

                $nomorJurnal = 'JRN-SIMPEG-' . now()->format('Ymd') . '-' . sprintf('%04d', $gaji->id);

                $jurnal = JurnalUmum::create([
                    'nomor_jurnal'   => $nomorJurnal,
                    'tanggal_jurnal' => now()->toDateString(),
                    'jenis_sumber'   => 'pengeluaran_manual',
                    'referensi_id'   => $gaji->id,
                    'keterangan'     => "Beban Penggajian & Tunjangan SIMPEG Periode {$gaji->periode_bulan_tahun} — Pegawai ID #{$gaji->pegawai_id}",
                    'status_posting' => 'posted',
                    'total_debet'    => $totalDebet,
                    'total_kredit'   => $totalDebet, // balanced (kredit kas + kredit utang PPh)
                    'created_by'     => auth()->id() ?? 1,
                    'posted_by'      => auth()->id() ?? 1,
                    'posted_at'      => now(),
                ]);

                // 1. DEBET — Beban Gaji & Tunjangan
                if ($akunBeban) {
                    DetailJurnalUmum::create([
                        'jurnal_id'   => $jurnal->id,
                        'akun_id'     => $akunBeban->id,
                        'debet'       => $totalDebet,
                        'kredit'      => 0,
                        'keterangan'  => "Beban gaji & tunjangan pegawai ID #{$gaji->pegawai_id} periode {$gaji->periode_bulan_tahun}",
                    ]);
                }

                // 2. KREDIT — Kas/Bank (sebesar gaji bersih yang dibayarkan)
                if ($akunKas && $gajiB > 0) {
                    DetailJurnalUmum::create([
                        'jurnal_id'   => $jurnal->id,
                        'akun_id'     => $akunKas->id,
                        'debet'       => 0,
                        'kredit'      => $gajiB,
                        'keterangan'  => "Pembayaran gaji bersih ke rekening pegawai ID #{$gaji->pegawai_id}",
                    ]);

                    // Kurangi saldo kas utama
                    $kasUtama = UnitKas::first();
                    if ($kasUtama) {
                        $kasUtama->decrement('saldo_saat_ini', $gajiB);
                    }
                }

                // 3. KREDIT — Utang Pajak PPh21 & BPJS (potongan yang belum disetor ke negara)
                if ($akunUtangPajak && $potongan > 0) {
                    DetailJurnalUmum::create([
                        'jurnal_id'   => $jurnal->id,
                        'akun_id'     => $akunUtangPajak->id,
                        'debet'       => 0,
                        'kredit'      => $potongan,
                        'keterangan'  => "Utang PPh21 & BPJS yang dipotong dari gaji pegawai ID #{$gaji->pegawai_id}",
                    ]);
                }

                Log::info("SIMPEG → SIKEU: Jurnal {$nomorJurnal} berhasil diposting. Total Beban: Rp " . number_format($totalDebet, 2));

                return [
                    'nomor_jurnal'   => $nomorJurnal,
                    'jurnal_id'      => $jurnal->id,
                    'total_debet'    => $totalDebet,
                    'status'         => 'POSTED_TO_SIKEU',
                    'integrated_at'  => now()->toIso8601String(),
                ];
            });

        } catch (\Exception $e) {
            Log::error("SIMPEG → SIKEU Integration FAILED: " . $e->getMessage());
            return [
                'status'  => 'INTEGRATION_FAILED',
                'message' => $e->getMessage(),
            ];
        }
    }
}
