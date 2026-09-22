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
    public static function recordStudentPaymentJournal(TagihanMahasiswa $tagihan, float $nominalBayar, ?\App\Models\Sikeu\UnitKas $unitKas = null)
    {
        if ($nominalBayar <= 0) {
            return null;
        }

        try {
            DB::beginTransaction();

            $nomorJurnal = JurnalSikeuService::prefix('pembayaran') . '-' . date('Ymd') . '-' . str_pad($tagihan->id, 5, '0', STR_PAD_LEFT);

            $akunBank = JurnalSikeuService::akunKasUnit($unitKas, '102.01');
            $akunPiutang = AkunKeuangan::where('kode_akun', '103.01')->first();

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

            // Debet: Kas Bank (nominal yang dibayar)
            if ($akunBank) {
                DetailJurnalUmum::create([
                    'jurnal_id' => $jurnal->id,
                    'akun_id' => $akunBank->id,
                    'debet' => $nominalBayar,
                    'kredit' => 0,
                    'keterangan' => 'Penerimaan Kas Bank Pembayaran Tagihan' . ($unitKas ? " ({$unitKas->nama_kas})" : ''),
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
