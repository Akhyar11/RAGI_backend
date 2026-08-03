<?php

namespace App\Services\Sikeu;

use App\Models\Sippm\KontrakKegiatan;
use App\Models\Sippm\PencairanDanaHibah;
use App\Models\Sikeu\PemasukanKampus;
use App\Models\Sikeu\UnitKas;
use App\Models\Sikeu\AkunKeuangan;
use App\Models\Sikeu\JurnalUmum;
use App\Models\Sikeu\DetailJurnalUmum;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SippmSikeuService
{
    /**
     * Rekam pencairan dana hibah SIPPM ke modul SIKEU.
     *
     * Dipanggil secara otomatis setelah KontrakMonevController::requestPencairan() sukses.
     *
     * Alur:
     *   1. Buat record `pemasukan_kampus` (sumber = hibah_sippm).
     *   2. Increment `unit_kas.saldo_saat_ini` sebesar nominal pencairan.
     *   3. Auto-post `jurnal_umum` + 2 detail:
     *      DEBET  : Kas/Bank Operasional       (nominal pencairan)
     *      KREDIT : Pendapatan Hibah LPPM/SIPPM (nominal pencairan)
     */
    public function recordHibahDisbursement(
        KontrakKegiatan   $kontrak,
        PencairanDanaHibah $pencairan
    ): array {
        return DB::transaction(function () use ($kontrak, $pencairan) {
            $nominal = (float) $pencairan->nominal;

            // Proposal & Ketua Tim Penelitian
            $namaKegiatan = $kontrak->proposal?->judul ?? 'Kegiatan Riset/PkM LPPM';
            $nomorKontrak = $kontrak->nomor_kontrak ?? "KONTRAK-{$kontrak->id}";

            // Default Kas Utama
            $unitKas = UnitKas::first();

            // COA: Pendapatan Hibah Riset & PkM (atau fallback ke pendapatan pertama)
            $akunPendapatan = AkunKeuangan::where('kode_akun', '402.01')->first()
                ?? AkunKeuangan::where('kelompok', 'pendapatan')->first();

            // COA: Kas/Bank
            $akunKas = AkunKeuangan::where('kode_akun', '102.01')->first()
                ?? AkunKeuangan::where('kelompok', 'aset')->whereRaw("LOWER(nama_akun) LIKE '%kas%'")->first();

            // 1. Buat record Pemasukan Kampus
            $nomorTransaksi = 'INC-SIPPM-' . date('Ymd') . '-' . strtoupper(Str::random(4));
            $pemasukan = PemasukanKampus::create([
                'nomor_transaksi'     => $nomorTransaksi,
                'sumber_pemasukan'    => 'hibah_sippm',
                'unit_kas_id'         => $unitKas?->id,
                'akun_pendapatan_id'  => $akunPendapatan?->id,
                'nominal'             => $nominal,
                'tanggal_terima'      => now()->toDateString(),
                'nama_donor_instansi' => 'LPPM — ' . $namaKegiatan,
                'nomor_kontrak_ref'   => $nomorKontrak,
                'keterangan'          => "Pencairan Termin {$pencairan->termin_ke} Dana Hibah SIPPM: {$namaKegiatan}",
                'created_by'          => auth()->id() ?? 1,
            ]);

            // 2. Update saldo kas
            if ($unitKas) {
                $unitKas->increment('saldo_saat_ini', $nominal);
            }

            // 3. Auto-journal
            $nomorJurnal = 'JRN-SIPPM-' . date('Ymd') . '-' . sprintf('%04d', $pencairan->id);
            $jurnal = JurnalUmum::create([
                'nomor_jurnal'   => $nomorJurnal,
                'tanggal_jurnal' => now()->toDateString(),
                'jenis_sumber'   => 'pemasukan_hibah',
                'referensi_id'   => $pemasukan->id,
                'keterangan'     => "Pencairan Termin {$pencairan->termin_ke} Kontrak {$nomorKontrak} — {$namaKegiatan}",
                'status_posting' => 'posted',
                'total_debet'    => $nominal,
                'total_kredit'   => $nominal,
                'created_by'     => auth()->id() ?? 1,
                'posted_by'      => auth()->id() ?? 1,
                'posted_at'      => now(),
            ]);

            // DEBET: Kas/Bank
            if ($akunKas) {
                DetailJurnalUmum::create([
                    'jurnal_id'  => $jurnal->id,
                    'akun_id'    => $akunKas->id,
                    'debet'      => $nominal,
                    'kredit'     => 0,
                    'keterangan' => "Penerimaan pencairan hibah SIPPM termin {$pencairan->termin_ke}",
                ]);
            }

            // KREDIT: Pendapatan Hibah
            if ($akunPendapatan) {
                DetailJurnalUmum::create([
                    'jurnal_id'  => $jurnal->id,
                    'akun_id'    => $akunPendapatan->id,
                    'debet'      => 0,
                    'kredit'     => $nominal,
                    'keterangan' => "Pengakuan pendapatan hibah riset/pkm LPPM — {$namaKegiatan}",
                ]);
            }

            Log::info("SIPPM → SIKEU: Jurnal {$nomorJurnal} diposting. Pencairan Termin {$pencairan->termin_ke} Rp " . number_format($nominal, 2));

            return [
                'pemasukan_id'   => $pemasukan->id,
                'nomor_transaksi' => $nomorTransaksi,
                'jurnal_id'      => $jurnal->id,
                'nomor_jurnal'   => $nomorJurnal,
                'nominal'        => $nominal,
                'status'         => 'RECORDED_IN_SIKEU',
                'integrated_at'  => now()->toIso8601String(),
            ];
        });
    }
}
