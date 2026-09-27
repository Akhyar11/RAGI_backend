<?php

namespace App\Services\Sikeu;

use App\Models\Spmb\PayoutReferral;
use App\Models\Sikeu\AkunKeuangan;
use App\Models\Sikeu\DetailJurnalUmum;
use App\Models\Sikeu\JurnalUmum;
use App\Models\Sikeu\PengeluaranKampus;
use App\Models\Sikeu\UnitKas;
use App\Models\User;
use App\Services\Sikeu\JurnalSikeuService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SikeuReferralPencairanService
{
    /**
     * Bayar invoice payout referral: catat sebagai pengeluaran honorarium
     * (jurnal otomatis + decrement kas), lalu tandai payout dibayar.
     */
    public function pay(PayoutReferral $payout, User $admin, array $data = []): PayoutReferral
    {
        if (! in_array($payout->status, [
            PayoutReferral::STATUS_MENUNGGU_VERIFIKASI,
            PayoutReferral::STATUS_TERVERIFIKASI,
        ], true)) {
            throw ValidationException::withMessages([
                'status' => ['Payout referral pada status ini tidak dapat dibayar.'],
            ]);
        }

        return DB::transaction(function () use ($payout, $admin, $data) {
            $nominal = (float) $payout->total_nominal;
            $tanggal = $data['tanggal_bayar'] ?? now()->toDateString();

            $unitKas = UnitKas::findOrFail($data['unit_kas_id']);
            $saldoLama = (float) $unitKas->saldo_saat_ini;
            $unitKas->decrement('saldo_saat_ini', $nominal);

            $akunBeban = null;
            if (! empty($data['akun_beban_id'])) {
                $akunBeban = AkunKeuangan::where('id', $data['akun_beban_id'])
                    ->where('kelompok', 'beban')
                    ->first();

                if (! $akunBeban) {
                    throw ValidationException::withMessages([
                        'akun_beban_id' => ['Akun beban yang dipilih tidak valid (harus akun kelompok beban).'],
                    ]);
                }
            }

            if (! $akunBeban) {
                $akunBeban = AkunKeuangan::where('kode_akun', '501.01')->first()
                    ?? AkunKeuangan::where('kelompok', 'beban')->first();
            }

            $akunKas = JurnalSikeuService::akunKasUnit($unitKas, '101.01');

            $nomorTransaksi = 'EXP-HON-'.date('Ymd').'-'.strtoupper(Str::random(4));

            $namaReferrer = $payout->referrer?->name
                ?? $payout->referrer?->nama_lengkap
                ?? 'Referrer #'.$payout->referrer_user_id;

            $pengeluaran = PengeluaranKampus::create([
                'nomor_transaksi' => $nomorTransaksi,
                'kategori' => 'honorarium',
                'akun_beban_id' => $akunBeban?->id,
                'akun_kas_id' => $akunKas?->id,
                'nominal' => $nominal,
                'keterangan' => "Reward referral SPMB {$payout->nomor_bukti} untuk {$namaReferrer}".(! empty($data['catatan']) ? ' — '.$data['catatan'] : ''),
                'tanggal_transaksi' => $tanggal,
                'nama_vendor' => $namaReferrer,
                'jenis_pajak' => 'tanpa_pajak',
                'tarif_pajak_persen' => 0,
                'nominal_pajak' => 0,
                'net_dibayarkan' => $nominal,
                'status_pembayaran' => 'lunas',
                'created_by' => $admin->id,
            ]);

            if ($akunBeban && $akunKas) {
                $jurnal = JurnalUmum::create([
                    'nomor_jurnal' => JurnalSikeuService::prefix('pengeluaran').'-'.date('Ymd').'-'.Str::random(4),
                    'tanggal_jurnal' => $tanggal,
                    'jenis_sumber' => 'pengeluaran_manual',
                    'referensi_id' => $pengeluaran->id,
                    'keterangan' => "Reward referral SPMB {$payout->nomor_bukti} - {$namaReferrer}",
                    'status_posting' => 'posted',
                    'total_debet' => $nominal,
                    'total_kredit' => $nominal,
                    'created_by' => $admin->id,
                    'posted_by' => $admin->id,
                    'posted_at' => now(),
                ]);

                DetailJurnalUmum::create([
                    'jurnal_id' => $jurnal->id,
                    'akun_id' => $akunBeban->id,
                    'debet' => $nominal,
                    'kredit' => 0,
                    'keterangan' => "Beban reward referral {$payout->nomor_bukti}",
                ]);

                DetailJurnalUmum::create([
                    'jurnal_id' => $jurnal->id,
                    'akun_id' => $akunKas->id,
                    'debet' => 0,
                    'kredit' => $nominal,
                    'keterangan' => "Pembayaran kas/bank ke {$namaReferrer}",
                ]);
            }

            $payout->update([
                'status' => PayoutReferral::STATUS_DIBAYAR,
                'paid_by' => $admin->id,
                'paid_at' => now(),
                'sikeu_reference' => $nomorTransaksi,
                'nomor_referensi_transfer' => $data['nomor_referensi_transfer'] ?? null,
                'catatan_penolakan' => null,
            ]);

            // Jejak audit transaksi finansial (non-blocking).
            try {
                \App\Services\AuditLogService::record(
                    module: 'SIKEU',
                    action: 'create',
                    tableName: $pengeluaran->getTable(),
                    recordId: $pengeluaran->id,
                    oldValues: null,
                    newValues: $pengeluaran->toArray(),
                    request: request()
                );
                \App\Services\AuditLogService::record(
                    module: 'SIKEU',
                    action: 'update',
                    tableName: $unitKas->getTable(),
                    recordId: $unitKas->id,
                    oldValues: ['saldo_saat_ini' => $saldoLama],
                    newValues: ['saldo_saat_ini' => $unitKas->fresh()->saldo_saat_ini],
                    request: request()
                );
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Gagal mencatat audit log pengeluaran referral: '.$e->getMessage());
            }

            return $payout->fresh(['referrer:id,name,username,email', 'usages']);
        });
    }
}
