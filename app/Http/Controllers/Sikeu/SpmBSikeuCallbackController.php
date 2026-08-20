<?php

namespace App\Http\Controllers\Sikeu;

use App\Http\Controllers\Controller;
use App\Models\Sikeu\TagihanMahasiswa;
use App\Models\Sikeu\AkunKeuangan;
use App\Models\Sikeu\JurnalUmum;
use App\Models\Sikeu\DetailJurnalUmum;
use App\Models\Sikeu\UnitKas;
use App\Models\Sikeu\Pembayaran;
use App\Events\Sikeu\PembayaranSpmbLunas;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SpmBSikeuCallbackController extends Controller
{
    /**
     * POST /api/v1/sikeu/callback/spmb/{calonMahasiswaId}
     * Webhook/Callback handler for SPMB registration fee payment completion.
     */
    public function handleSpmbPaymentCallback(Request $request, $calonMahasiswaId)
    {
        $validated = $request->validate([
            'order_id' => 'required|string',
            'nominal' => 'required|numeric|min:1000',
            'status' => 'required|in:paid,success,settlement',
            'bank_kode' => 'nullable|string',
            'channel' => 'nullable|string',
        ]);

        try {
            DB::beginTransaction();

            $tagihan = TagihanMahasiswa::where(function ($q) use ($calonMahasiswaId) {
                $q->where('calon_mahasiswa_id', $calonMahasiswaId)
                  ->orWhere('mahasiswa_id', $calonMahasiswaId);
            })
            ->where('source_system', 'SPMB')
            ->where('status', '!=', 'lunas')
            ->first();

            if (!$tagihan) {
                // If bill doesn't exist, create a lunas bill for calon mahasiswa
                $tagihan = TagihanMahasiswa::create([
                    'mahasiswa_id' => $calonMahasiswaId,
                    'calon_mahasiswa_id' => $calonMahasiswaId,
                    'tipe_referensi' => 'calon_mahasiswa',
                    'tahun_akademik_id' => 1,
                    'nomor_tagihan' => 'INV-SPMB-' . date('Ymd') . '-' . Str::random(4),
                    'total_tagihan' => $validated['nominal'],
                    'total_bayar' => $validated['nominal'],
                    'status' => 'lunas',
                    'source_system' => 'SPMB',
                ]);
            } else {
                $tagihan->update([
                    'calon_mahasiswa_id' => $tagihan->calon_mahasiswa_id ?? $calonMahasiswaId,
                    'status' => 'lunas',
                    'total_bayar' => $validated['nominal'],
                ]);
            }

            // Trigger Xendit Server API to record transaction and update Xendit balance in Xendit Dashboard!
            $pgConfig = \App\Models\Sikeu\PaymentGatewayConfig::where('is_active', true)->where('gateway_name', 'xendit')->first();
            $apiKey = $pgConfig->api_key_encrypted ?? $pgConfig->public_key_encrypted ?? null;

            if ($pgConfig && !empty($apiKey) && !empty($tagihan->nomor_tagihan)) {
                try {
                    \Illuminate\Support\Facades\Http::withoutVerifying()
                        ->withBasicAuth($apiKey, '')
                        ->post('https://api.xendit.co/callback_virtual_accounts/external_id=' . $tagihan->nomor_tagihan . '/simulate_payment', [
                            'amount' => (int) $validated['nominal'],
                        ]);
                } catch (\Throwable $ex) {
                    Log::warning("Xendit API simulate_payment warning: " . $ex->getMessage());
                }
            }

            // Instantly update PendaftaranCalonMhs status to lunas & promote draft -> submitted
            \App\Models\Spmb\PendaftaranCalonMhs::where('id', $calonMahasiswaId)
                ->orWhere('user_id', $calonMahasiswaId)
                ->update([
                    'status_pembayaran' => 'lunas',
                    'status' => DB::raw("CASE WHEN status = 'draft' THEN 'submitted' ELSE status END"),
                ]);

            // Create Pembayaran Record
            $pembayaran = Pembayaran::create([
                'tagihan_id' => $tagihan->id,
                'kode_transaksi' => $validated['order_id'],
                'jumlah_bayar' => $validated['nominal'],
                'waktu_bayar' => now(),
                'channel_bayar' => $validated['channel'] ?? $validated['bank_kode'] ?? 'BANK_VA',
                'status' => 'success',
            ]);

            // Auto Journal Entry
            $akunKas = AkunKeuangan::where('kode_akun', '102.01')->first()
                ?? AkunKeuangan::where('kelompok', 'aset')->first();
            $akunSpmb = AkunKeuangan::where('kode_akun', '401.03')->first()
                ?? AkunKeuangan::where('kelompok', 'pendapatan')->first();

            $unitKas = UnitKas::first();
            if ($unitKas) {
                $unitKas->increment('saldo_saat_ini', $validated['nominal']);
            }

            if ($akunKas && $akunSpmb) {
                $jurnal = JurnalUmum::create([
                    'nomor_jurnal' => 'JRN-SPMB-' . date('Ymd') . '-' . sprintf('%04d', $pembayaran->id),
                    'tanggal_jurnal' => now()->toDateString(),
                    'jenis_sumber' => 'pembayaran_mahasiswa',
                    'referensi_id' => $pembayaran->id,
                    'keterangan' => "Penerimaan Biaya Pendaftaran SPMB Calon Mahasiswa ID #{$calonMahasiswaId}",
                    'status_posting' => 'posted',
                    'total_debet' => $validated['nominal'],
                    'total_kredit' => $validated['nominal'],
                    'created_by' => 1,
                    'posted_by' => 1,
                    'posted_at' => now(),
                ]);

                DetailJurnalUmum::create([
                    'jurnal_id' => $jurnal->id,
                    'akun_id' => $akunKas->id,
                    'debet' => $validated['nominal'],
                    'kredit' => 0,
                    'keterangan' => "Penerimaan Kas/Bank Pendaftaran SPMB ID #{$calonMahasiswaId}",
                ]);

                DetailJurnalUmum::create([
                    'jurnal_id' => $jurnal->id,
                    'akun_id' => $akunSpmb->id,
                    'debet' => 0,
                    'kredit' => $validated['nominal'],
                    'keterangan' => "Pendapatan Pendaftaran SPMB ID #{$calonMahasiswaId}",
                ]);
            }

            DB::commit();

            // Trigger Laravel Event for SPMB system listeners
            event(new PembayaranSpmbLunas($calonMahasiswaId, $tagihan, $pembayaran));

            Log::info("SPMB Payment Callback processed for Calon Mhs #{$calonMahasiswaId}", $validated);

            return response()->json([
                'status' => 'success',
                'message' => 'Pembayaran SPMB berhasil diproses dan dicatat ke jurnal keuangan.',
                'data' => [
                    'tagihan' => $tagihan,
                    'pembayaran' => $pembayaran,
                ],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("SPMB Payment Callback Error: " . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Gagal memproses callback pembayaran: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /api/v1/sikeu/checkout/lookup-va?va_number=...
     * Automatically look up VA bill details from DB/Xendit to populate checkout page dynamically.
     */
    public function lookupVa(Request $request)
    {
        $vaNumber = trim($request->query('va_number', ''));
        if (!$vaNumber) {
            return response()->json([
                'status' => 'error',
                'message' => 'Nomor Virtual Account wajib diisi.',
            ], 400);
        }

        $cleanVa = preg_replace('/[^0-9]/', '', $vaNumber);

        $va = \App\Models\Sikeu\VirtualAccount::where('va_number', $cleanVa)->first();

        if (!$va && strlen($cleanVa) >= 6) {
            $va = \App\Models\Sikeu\VirtualAccount::where('va_number', 'like', '%' . $cleanVa . '%')->first();
        }

        if (!$va) {
            return response()->json([
                'status' => 'error',
                'message' => 'Nomor Virtual Account (' . $vaNumber . ') tidak ditemukan di Xendit / Server SIKEU.',
            ], 404);
        }

        $tagihan = \App\Models\Sikeu\TagihanMahasiswa::find($va->tagihan_id);
        $pendaftaran = $tagihan ? \App\Models\Spmb\PendaftaranCalonMhs::with('programStudi')->find($tagihan->calon_mahasiswa_id) : null;

        return response()->json([
            'status' => 'success',
            'data' => [
                'va_number' => $va->va_number,
                'bank_kode' => $va->bank_kode ?? 'BNI',
                'bank_nama' => $va->bank_nama ?? 'Bank BNI',
                'nominal' => (float) ($va->nominal ?? $tagihan->total_bayar ?? 250000),
                'total_bayar' => (float) ($tagihan->total_bayar ?? $va->nominal ?? 250000),
                'expired_at' => $va->expired_at,
                'status_va' => $va->status ?? 'aktif',
                'status_pembayaran' => $pendaftaran->status_pembayaran ?? $tagihan->status ?? 'belum_bayar',
                'tagihan_id' => $tagihan->id ?? null,
                'nomor_tagihan' => $tagihan->nomor_tagihan ?? 'INV-SPMB',
                'calon_mahasiswa_id' => $tagihan->calon_mahasiswa_id ?? $pendaftaran->id ?? 1,
                'nama_pendaftar' => $pendaftaran->nama_lengkap ?? 'Calon Mahasiswa',
                'no_pendaftaran' => $pendaftaran->no_pendaftaran ?? 'REG-2026-SPMB',
                'program_studi' => $pendaftaran->programStudi->nama ?? 'S1 Informatika',
                'system' => $tagihan->source_system ?? 'SPMB',
            ]
        ]);
    }
}
