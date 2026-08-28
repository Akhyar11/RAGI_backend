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
     */
    public static function recordStudentPaymentJournal(TagihanMahasiswa $tagihan, float $nominalBayar)
    {
        if ($nominalBayar <= 0) {
            return null;
        }

        try {
            DB::beginTransaction();

            $sourceSystem = strtoupper($tagihan->source_system ?? 'SIAKAD');
            $nomorJurnal = 'JRN-IN-' . date('Ymd') . '-' . str_pad($tagihan->id, 5, '0', STR_PAD_LEFT);

            // Tentukan Akun Pendapatan berdasarkan Source System
            $kodeAkunPendapatan = ($sourceSystem === 'SPMB') ? '401.02' : '401.01'; // 401.02 Registrasi SPMB, 401.01 UKT/SPP
            $akunBank = AkunKeuangan::where('kode_akun', '102.01')->first() ?? AkunKeuangan::where('kelompok', 'aset')->first();
            $akunPendapatan = AkunKeuangan::where('kode_akun', $kodeAkunPendapatan)->first() ?? AkunKeuangan::where('kelompok', 'pendapatan')->first();
            $akunPotongan = AkunKeuangan::where('kode_akun', '504.01')->first();

            $totalPotongan = (float)$tagihan->total_potongan;

            $jurnal = JurnalUmum::create([
                'nomor_jurnal' => $nomorJurnal,
                'tanggal_jurnal' => date('Y-m-d'),
                'jenis_sumber' => $sourceSystem . '_PEMBAYARAN',
                'referensi_id' => $tagihan->id,
                'keterangan' => "Pelunasan Tagihan {$tagihan->nomor_tagihan} ({$sourceSystem}) - Mhs ID: {$tagihan->mahasiswa_id}",
                'status_posting' => 'posted',
                'total_debet' => $nominalBayar + $totalPotongan,
                'total_kredit' => $nominalBayar + $totalPotongan,
                'created_by' => auth()->id() ?? 1,
                'posted_by' => auth()->id() ?? 1,
                'posted_at' => now(),
            ]);

            // Debet: Kas Bank BNI (Nominal yang dibayar)
            if ($akunBank) {
                DetailJurnalUmum::create([
                    'jurnal_id' => $jurnal->id,
                    'akun_id' => $akunBank->id,
                    'debet' => $nominalBayar,
                    'kredit' => 0,
                    'keterangan' => 'Penerimaan Kas Bank Pembayaran Tagihan',
                ]);
            }

            // Debet: Potongan Beasiswa (jika ada potongan)
            if ($totalPotongan > 0 && $akunPotongan) {
                DetailJurnalUmum::create([
                    'jurnal_id' => $jurnal->id,
                    'akun_id' => $akunPotongan->id,
                    'debet' => $totalPotongan,
                    'kredit' => 0,
                    'keterangan' => 'Alokasi Beasiswa / Potongan Tagihan',
                ]);
            }

            // Kredit: Pendapatan UKT / SPMB (Total Kotor)
            if ($akunPendapatan) {
                DetailJurnalUmum::create([
                    'jurnal_id' => $jurnal->id,
                    'akun_id' => $akunPendapatan->id,
                    'debet' => 0,
                    'kredit' => $nominalBayar + $totalPotongan,
                    'keterangan' => 'Pengakuan Pendapatan UKT / SPP Mahasiswa',
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
    public static function recordDisbursementJournal(string $sourceSystem, int $referensiId, float $nominal, string $keterangan, string $kodeBeban = '503.01')
    {
        if ($nominal <= 0) {
            return null;
        }

        try {
            DB::beginTransaction();

            $nomorJurnal = 'JRN-OUT-' . date('Ymd') . '-' . str_pad($referensiId, 5, '0', STR_PAD_LEFT);

            $akunBeban = AkunKeuangan::where('kode_akun', $kodeBeban)->first() ?? AkunKeuangan::where('kelompok', 'beban')->first();
            $akunKasUtama = AkunKeuangan::where('kode_akun', '101.01')->first() ?? AkunKeuangan::where('kelompok', 'aset')->first();

            $jurnal = JurnalUmum::create([
                'nomor_jurnal' => $nomorJurnal,
                'tanggal_jurnal' => date('Y-m-d'),
                'jenis_sumber' => strtoupper($sourceSystem) . '_PENCAIRAN',
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
                    'keterangan' => 'Pengeluaran Kas Utama Rektorat',
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
