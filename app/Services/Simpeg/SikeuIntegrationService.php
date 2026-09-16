<?php

namespace App\Services\Simpeg;

use App\Models\Simpeg\GajiPegawai;
use App\Models\Sikeu\AkunKeuangan;
use App\Models\Sikeu\JurnalUmum;
use App\Models\Sikeu\DetailJurnalUmum;
use App\Models\Sikeu\PengeluaranKampus;
use App\Models\Sikeu\UnitKas;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SikeuIntegrationService
{
    /**
     * Post payroll transaction to SIKEU Accounting Journal, Cash Deduction & Tax Obligation.
     *
     * Skema jurnal penggajian berimbang:
     *   DEBET  : 501.01 — Beban Gaji & Honorarium (gaji_pokok + total_honor_sks)
     *   DEBET  : 501.02 — Beban Tunjangan & Insentif SDM (total_tunjangan - total_honor_sks)
     *   KREDIT : 102.01 — Kas / Bank Operasional (gaji_bersih)
     *   KREDIT : 202.01 — Utang Pajak PPh 21 Pegawai/Dosen (total_pph21)
     *   KREDIT : 201.01 — Utang Jangka Pendek BPJS & Potongan (total_potongan - total_pph21)
     */
    public static function postPayrollJournal(GajiPegawai $gaji): array
    {
        try {
            return DB::transaction(function () use ($gaji) {
                $gajiPokok       = (float) $gaji->gaji_pokok;
                $totalTunjangan  = (float) ($gaji->total_tunjangan ?? 0);
                $totalHonorSks   = (float) ($gaji->total_honor_sks ?? 0);
                $tunjanganLain   = max(0, $totalTunjangan - $totalHonorSks);
                $gajiBersih      = (float) $gaji->gaji_bersih;
                $totalPotongan   = (float) ($gaji->total_potongan ?? 0);
                $pph21           = (float) ($gaji->total_pph21 ?? 0);
                $potonganNonPajak = max(0, $totalPotongan - $pph21);

                $totalDebet = $gajiPokok + $totalTunjangan;

                // --- Cari COA Accounts ---
                $akunBebanGaji = AkunKeuangan::whereIn('kode_akun', ['501.01', '5.1.01.01'])->first()
                    ?? AkunKeuangan::where('kelompok', 'beban')->first();

                $akunBebanTunjangan = AkunKeuangan::whereIn('kode_akun', ['501.02', '5.1.01.02'])->first()
                    ?? $akunBebanGaji;

                $akunKas = AkunKeuangan::whereIn('kode_akun', ['102.01', '1.1.01.02', '101.01'])->first()
                    ?? AkunKeuangan::where('kelompok', 'aset')->whereRaw("LOWER(nama_akun) LIKE '%kas%'")->first();

                $akunUtangPajak = AkunKeuangan::whereIn('kode_akun', ['202.01', '2.1.03.01'])->first()
                    ?? AkunKeuangan::where('kelompok', 'liabilitas')->first();

                $akunUtangNonPajak = AkunKeuangan::whereIn('kode_akun', ['201.01', '2.1.01.01'])->first()
                    ?? AkunKeuangan::where('kelompok', 'liabilitas')->first();

                $nomorJurnal = 'JRN-SIMPEG-' . now()->format('Ymd') . '-' . sprintf('%04d', $gaji->id);

                // Buat Header Jurnal Umum
                $jurnal = JurnalUmum::create([
                    'nomor_jurnal'   => $nomorJurnal,
                    'tanggal_jurnal' => now()->toDateString(),
                    'jenis_sumber'   => 'pengeluaran_manual',
                    'referensi_id'   => $gaji->id,
                    'keterangan'     => "Beban Penggajian & Tunjangan SIMPEG Periode {$gaji->periode_bulan_tahun} — Pegawai ID #{$gaji->pegawai_id} ({$gaji->pegawai?->nama_lengkap})",
                    'status_posting' => 'posted',
                    'total_debet'    => $totalDebet,
                    'total_kredit'   => $totalDebet, // Balanced
                    'created_by'     => auth()->id() ?? 1,
                    'posted_by'      => auth()->id() ?? 1,
                    'posted_at'      => now(),
                ]);

                // 1. DEBET — Beban Gaji Pokok & Honorarium
                $bebanGajiNominal = $gajiPokok + $totalHonorSks;
                if ($akunBebanGaji && $bebanGajiNominal > 0) {
                    DetailJurnalUmum::create([
                        'jurnal_id'   => $jurnal->id,
                        'akun_id'     => $akunBebanGaji->id,
                        'debet'       => $bebanGajiNominal,
                        'kredit'      => 0,
                        'keterangan'  => "Beban Gaji Pokok & Honor SKS Pegawai ID #{$gaji->pegawai_id}",
                    ]);
                }

                // 2. DEBET — Beban Tunjangan & Insentif SDM
                if ($akunBebanTunjangan && $tunjanganLain > 0) {
                    DetailJurnalUmum::create([
                        'jurnal_id'   => $jurnal->id,
                        'akun_id'     => $akunBebanTunjangan->id,
                        'debet'       => $tunjanganLain,
                        'kredit'      => 0,
                        'keterangan'  => "Beban Tunjangan Fungsional & Transport Pegawai ID #{$gaji->pegawai_id}",
                    ]);
                }

                // 3. KREDIT — Kas / Bank (sebesar gaji bersih yang dibayarkan)
                if ($akunKas && $gajiBersih > 0) {
                    DetailJurnalUmum::create([
                        'jurnal_id'   => $jurnal->id,
                        'akun_id'     => $akunKas->id,
                        'debet'       => 0,
                        'kredit'      => $gajiBersih,
                        'keterangan'  => "Pembayaran Gaji Bersih Transfer Pegawai ID #{$gaji->pegawai_id}",
                    ]);

                    // Kurangi saldo unit kas operasional
                    $unitKas = UnitKas::first();
                    if ($unitKas) {
                        $unitKas->decrement('saldo_saat_ini', $gajiBersih);
                    }
                }

                // 4. KREDIT — Utang Pajak PPh 21
                if ($akunUtangPajak && $pph21 > 0) {
                    DetailJurnalUmum::create([
                        'jurnal_id'   => $jurnal->id,
                        'akun_id'     => $akunUtangPajak->id,
                        'debet'       => 0,
                        'kredit'      => $pph21,
                        'keterangan'  => "Utang Pemotongan PPh 21 Pegawai ID #{$gaji->pegawai_id}",
                    ]);
                }

                // 5. KREDIT — Utang Potongan BPJS & Koperasi
                if ($akunUtangNonPajak && $potonganNonPajak > 0) {
                    DetailJurnalUmum::create([
                        'jurnal_id'   => $jurnal->id,
                        'akun_id'     => $akunUtangNonPajak->id,
                        'debet'       => 0,
                        'kredit'      => $potonganNonPajak,
                        'keterangan'  => "Utang Potongan BPJS & Simpanan Pegawai ID #{$gaji->pegawai_id}",
                    ]);
                }

                // 6. Sinkronisasi Pajak Kampus SIKEU (Catat ke sikeu_pengeluaran_kampus)
                $pengeluaranPajak = null;
                if ($pph21 > 0) {
                    $nomorTxPajak = 'TAX-SIMPEG-' . now()->format('Ym') . '-' . sprintf('%04d', $gaji->id);
                    $pengeluaranPajak = PengeluaranKampus::updateOrCreate(
                        ['nomor_transaksi' => $nomorTxPajak],
                        [
                            'kategori' => 'PPh 21 Penggajian',
                            'akun_beban_id' => $akunBebanGaji?->id,
                            'akun_kas_id' => $akunKas?->id,
                            'nominal' => $totalDebet,
                            'keterangan' => "Kewajiban PPh 21 Gaji Pegawai ID #{$gaji->pegawai_id} ({$gaji->pegawai?->nama_lengkap}) Periode {$gaji->periode_bulan_tahun}",
                            'tanggal_transaksi' => now()->toDateString(),
                            'nama_vendor' => $gaji->pegawai?->nama_lengkap ?? "Pegawai #{$gaji->pegawai_id}",
                            'npwp_vendor' => $gaji->pegawai?->nik ?? '-',
                            'jenis_pajak' => 'pph_21',
                            'tarif_pajak_persen' => $totalDebet > 0 ? round(($pph21 / $totalDebet) * 100, 2) : 0,
                            'nominal_pajak' => $pph21,
                            'net_dibayarkan' => $gajiBersih,
                            'status_pembayaran' => 'pending',
                            'created_by' => auth()->id() ?? 1,
                        ]
                    );
                }

                // Update relasi jurnal_id dan pengeluaran_kampus_id pada GajiPegawai
                $gaji->update([
                    'jurnal_id' => $jurnal->id,
                    'pengeluaran_kampus_id' => $pengeluaranPajak?->id,
                ]);

                Log::info("SIMPEG → SIKEU: Jurnal {$nomorJurnal} berhasil diposting. Total Beban: Rp " . number_format($totalDebet, 2));

                return [
                    'nomor_jurnal'   => $nomorJurnal,
                    'jurnal_id'      => $jurnal->id,
                    'pengeluaran_kampus_id' => $pengeluaranPajak?->id,
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
