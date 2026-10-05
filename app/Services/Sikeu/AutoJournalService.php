<?php

namespace App\Services\Sikeu;

use App\Models\Sikeu\AkunKeuangan;
use App\Models\Sikeu\JurnalUmum;
use App\Models\Sikeu\DetailJurnalUmum;
use App\Models\Sikeu\TagihanMahasiswa;
use Illuminate\Support\Facades\DB;

class AutoJournalService
{
    /**
     * Merekam Jurnal Umum otomatis saat pelunasan Tagihan Mahasiswa / SPMB.
     * Basis akrual: Dr Bank / Cr Piutang (pendapatan sudah diakui saat
     * penerbitan tagihan; potongan dicatat terpisah oleh alur potongan).
     */
    public static function recordStudentPaymentJournal(TagihanMahasiswa $tagihan, float $nominalBayar, ?\App\Models\Sikeu\UnitKas $unitKas = null, float $feeAmount = 0)
    {
        if ($nominalBayar <= 0) {
            return null;
        }

        try {
            DB::beginTransaction();

            $feeAmount = max(0, min($feeAmount, $nominalBayar));
            $netKas = $nominalBayar - $feeAmount;

            $nomorJurnal = JurnalSikeuService::prefix('pembayaran') . '-' . date('Ymd') . '-' . str_pad($tagihan->id, 5, '0', STR_PAD_LEFT) . '-' . strtoupper(\Illuminate\Support\Str::random(4));

            $akunBank = JurnalSikeuService::akunKasUnit($unitKas, '102.01');
            $akunPiutang = self::akunPiutang();
            $akunBebanPg = $feeAmount > 0 ? self::akunBebanPaymentGateway() : null;

            $jurnal = JurnalUmum::create([
                'nomor_jurnal' => $nomorJurnal,
                'tanggal_jurnal' => date('Y-m-d'),
                'jenis_sumber' => 'pembayaran_mahasiswa',
                'referensi_id' => $tagihan->id,
                'keterangan' => "Pelunasan Tagihan {$tagihan->nomor_tagihan} - Mhs ID: {$tagihan->mahasiswa_id}",
                'status_posting' => 'posted',
                'total_debet' => $nominalBayar,
                'total_kredit' => $nominalBayar,
                'created_by' => auth()->id() ?? 1,
                'posted_by' => auth()->id() ?? 1,
                'posted_at' => now(),
            ]);

            // Debet: Kas Bank (nominal bersih setelah biaya gateway)
            if ($akunBank && $netKas > 0) {
                DetailJurnalUmum::create([
                    'jurnal_id' => $jurnal->id,
                    'akun_id' => $akunBank->id,
                    'debet' => $netKas,
                    'kredit' => 0,
                    'keterangan' => 'Penerimaan Kas Bank Pembayaran Tagihan' . ($unitKas ? " ({$unitKas->nama_kas})" : ''),
                ]);
            }

            // Debet: Beban Biaya Payment Gateway (fee + PPN yang dipotong gateway)
            if ($akunBebanPg && $feeAmount > 0) {
                DetailJurnalUmum::create([
                    'jurnal_id' => $jurnal->id,
                    'akun_id' => $akunBebanPg->id,
                    'debet' => $feeAmount,
                    'kredit' => 0,
                    'keterangan' => 'Biaya payment gateway (fee + PPN)',
                ]);
            }

            // Kredit: Piutang (pelunasan, bukan pengakuan pendapatan)
            if ($akunPiutang) {
                DetailJurnalUmum::create([
                    'jurnal_id' => $jurnal->id,
                    'akun_id' => $akunPiutang->id,
                    'debet' => 0,
                    'kredit' => $nominalBayar,
                    'keterangan' => 'Pelunasan piutang tagihan mahasiswa',
                ]);
            }

            DB::commit();
            return $jurnal;
        } catch (\Throwable $e) {
            DB::rollBack();
            \Log::error('Gagal membuat Auto-Jurnal Pembayaran Tagihan: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Akun beban biaya payment gateway (dibuat otomatis bila belum ada).
     */
    protected static function akunBebanPaymentGateway(): ?AkunKeuangan
    {
        return AkunKeuangan::firstOrCreate(
            ['kode_akun' => '503.01'],
            [
                'nama_akun' => 'Beban Biaya Payment Gateway',
                'kelompok' => 'beban',
                'saldo_normal' => 'debet',
                'is_active' => true,
            ]
        );
    }

    /**
     * Akun piutang mahasiswa (dibuat otomatis bila belum ada).
     */
    protected static function akunPiutang(): ?AkunKeuangan
    {
        return AkunKeuangan::firstOrCreate(
            ['kode_akun' => '103.01'],
            [
                'nama_akun' => 'Piutang Tagihan Mahasiswa',
                'kelompok' => 'aset',
                'saldo_normal' => 'debet',
                'is_active' => true,
            ]
        );
    }

    /**
     * Merekam Jurnal Umum otomatis saat Pencairan Dana Kas / Hibah SIPPM.
     */
    public static function recordDisbursementJournal(string $sourceSystem, int $referensiId, float $nominal, string $keterangan, string $kodeBeban = '503.01', ?string $kodeKas = null)
    {
        if ($nominal <= 0) {
            return null;
        }

        try {
            DB::beginTransaction();

            $nomorJurnal = JurnalSikeuService::prefix('pengeluaran') . '-' . date('Ymd') . '-' . str_pad($referensiId, 5, '0', STR_PAD_LEFT);

            $akunBeban = AkunKeuangan::where('kode_akun', $kodeBeban)->first() ?? AkunKeuangan::where('kelompok', 'beban')->first();
            $akunKasUtama = $kodeKas
                ? (AkunKeuangan::where('kode_akun', $kodeKas)->first() ?? AkunKeuangan::where('kode_akun', '101.01')->first())
                : AkunKeuangan::where('kode_akun', '101.01')->first();
            $akunKasUtama ??= AkunKeuangan::where('kelompok', 'aset')->first();

            $jurnal = JurnalUmum::create([
                'nomor_jurnal' => $nomorJurnal,
                'tanggal_jurnal' => date('Y-m-d'),
                'jenis_sumber' => 'pencairan_kas',
                'referensi_id' => $referensiId,
                'keterangan' => $keterangan,
                'status_posting' => 'posted',
                'total_debet' => $nominal,
                'total_kredit' => $nominal,
                'created_by' => auth()->id() ?? 1,
                'posted_by' => auth()->id() ?? 1,
                'posted_at' => now(),
            ]);

            // Debet: Beban (SIPPM / Operasional Unit)
            if ($akunBeban) {
                DetailJurnalUmum::create([
                    'jurnal_id' => $jurnal->id,
                    'akun_id' => $akunBeban->id,
                    'debet' => $nominal,
                    'kredit' => 0,
                    'keterangan' => 'Pengakuan Beban Pencairan Dana',
                ]);
            }

            // Kredit: Kas Utama Rektorat
            if ($akunKasUtama) {
                DetailJurnalUmum::create([
                    'jurnal_id' => $jurnal->id,
                    'akun_id' => $akunKasUtama->id,
                    'debet' => 0,
                    'kredit' => $nominal,
                    'keterangan' => 'Pengeluaran ' . ($akunKasUtama->nama_akun ?? 'Kas'),
                ]);
            }

            DB::commit();
            return $jurnal;
        } catch (\Throwable $e) {
            DB::rollBack();
            \Log::error('Gagal membuat Auto-Jurnal Pencairan: ' . $e->getMessage());
            return null;
        }
    }
}
