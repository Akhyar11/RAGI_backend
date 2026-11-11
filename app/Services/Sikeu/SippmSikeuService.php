<?php

namespace App\Services\Sikeu;

use App\Models\Sippm\KontrakKegiatan;
use App\Models\Sippm\PencairanDanaHibah;
use App\Models\Sikeu\PengeluaranKampus;
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
     *   1. Buat record `sikeu_pengeluaran_kampus` (kategori = hibah_penelitian).
     *   2. Decrement `unit_kas.saldo_saat_ini` sebesar nominal pencairan (pengeluaran dana kas).
     *   3. Auto-post `jurnal_umum` + 2 detail:
     *      DEBET  : Beban Penelitian & PkM LPPM (nominal pencairan)
     *      KREDIT : Kas/Bank Operasional        (nominal pencairan)
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
            $namaPenerima = $kontrak->proposal?->ketua?->nama_lengkap 
                ?? ($kontrak->proposal?->ketuaPegawai?->nama_lengkap ?? 'Dosen Peneliti');

            // Default Kas Utama
            $unitKas = UnitKas::first();

            // COA: Beban Hibah Riset & PkM (atau fallback ke beban operasional)
            $akunBeban = AkunKeuangan::where('kode_akun', '502.01')->first()
                ?? AkunKeuangan::where('kelompok', 'beban')->first();

            // COA: Kas/Bank
            $akunKas = AkunKeuangan::where('kode_akun', '102.01')->first()
                ?? AkunKeuangan::where('kelompok', 'aset')->whereRaw("LOWER(nama_akun) LIKE '%kas%'")->first();

            $creatorUserId = auth()->id() 
                ?? $kontrak->proposal?->ketuaPegawai?->user_id 
                ?? throw new \InvalidArgumentException('Tidak dapat membukukan jurnal SIPPM-SIKEU: User ID penanggung jawab tidak ditemukan.');

            // 1. Buat record Pengeluaran Kampus
            $nomorTransaksi = 'EXP-SIPPM-' . date('Ymd') . '-' . strtoupper(Str::random(4));
            $pengeluaran = PengeluaranKampus::create([
                'nomor_transaksi'     => $nomorTransaksi,
                'kategori'            => 'hibah_penelitian',
                'akun_beban_id'       => $akunBeban?->id,
                'akun_kas_id'         => $akunKas?->id,
                'nominal'             => $nominal,
                'net_dibayarkan'      => $nominal,
                'tanggal_transaksi'   => now()->toDateString(),
                'nama_vendor'         => $namaPenerima,
                'keterangan'          => "Pencairan Termin {$pencairan->termin_ke} Dana Hibah SIPPM ({$nomorKontrak}): {$namaKegiatan}",
                'status_pembayaran'   => 'dibayar',
                'created_by'          => $creatorUserId,
            ]);

            try {
                \App\Services\Core\AuditLogService::record(
                    action: 'create',
                    module: 'sikeu',
                    entityType: PengeluaranKampus::class,
                    entityId: $pengeluaran->id,
                    oldValues: null,
                    newValues: $pengeluaran->toArray(),
                    description: "Membukukan pengeluaran pencairan hibah SIPPM termin {$pencairan->termin_ke} untuk proposal: {$namaKegiatan}"
                );
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Gagal mencatat audit log pencairan hibah: ' . $e->getMessage());
            }

            // 2. Update saldo kas (pengeluaran mengurangi saldo kas)
            if ($unitKas) {
                $unitKas->decrement('saldo_saat_ini', $nominal);
            }

            // 3. Auto-journal
            $nomorJurnal = 'JRN-SIPPM-' . date('Ymd') . '-' . sprintf('%04d', $pencairan->id);
            $jurnal = JurnalUmum::create([
                'nomor_jurnal'   => $nomorJurnal,
                'tanggal_jurnal' => now()->toDateString(),
                'jenis_sumber'   => 'pengeluaran_hibah',
                'referensi_id'   => $pengeluaran->id,
                'keterangan'     => "Pencairan Termin {$pencairan->termin_ke} Kontrak {$nomorKontrak} — {$namaKegiatan}",
                'status_posting' => 'posted',
                'total_debet'    => $nominal,
                'total_kredit'   => $nominal,
                'created_by'     => $creatorUserId,
                'posted_by'      => $creatorUserId,
                'posted_at'      => now(),
            ]);

            // DEBET: Beban Penelitian & PkM LPPM
            if ($akunBeban) {
                DetailJurnalUmum::create([
                    'jurnal_id'  => $jurnal->id,
                    'akun_id'    => $akunBeban->id,
                    'debet'      => $nominal,
                    'kredit'     => 0,
                    'keterangan' => "Beban pencairan dana hibah penelitian termin {$pencairan->termin_ke}",
                ]);
            }

            // KREDIT: Kas/Bank Operasional
            if ($akunKas) {
                DetailJurnalUmum::create([
                    'jurnal_id'  => $jurnal->id,
                    'akun_id'    => $akunKas->id,
                    'debet'      => 0,
                    'kredit'     => $nominal,
                    'keterangan' => "Pengeluaran kas pencairan hibah LPPM — {$namaPenerima}",
                ]);
            }

            Log::info("SIPPM → SIKEU: Jurnal {$nomorJurnal} diposting. Pencairan Termin {$pencairan->termin_ke} Rp " . number_format($nominal, 2));

            return [
                'pengeluaran_id'  => $pengeluaran->id,
                'nomor_transaksi' => $nomorTransaksi,
                'jurnal_id'       => $jurnal->id,
                'nomor_jurnal'    => $nomorJurnal,
                'nominal'         => $nominal,
                'status'          => 'RECORDED_IN_SIKEU',
                'integrated_at'   => now()->toIso8601String(),
            ];
        });
    }
}
