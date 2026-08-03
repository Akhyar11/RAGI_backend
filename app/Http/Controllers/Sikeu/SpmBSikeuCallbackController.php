<?php

namespace App\Http\Controllers\Sikeu;

use App\Http\Controllers\Controller;
use App\Models\Sikeu\TagihanMahasiswa;
use App\Models\Sikeu\AkunKeuangan;
use App\Models\Sikeu\JurnalUmum;
use App\Models\Sikeu\DetailJurnalUmum;
use App\Models\Sikeu\UnitKas;
use App\Models\Sikeu\Pembayaran;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SpmBSikeuCallbackController extends Controller
{
    /**
     * POST /api/v1/sikeu/callback/spmb/{mahasiswaId}
     * Webhook/Callback handler for SPMB registration fee payment completion.
     */
    public function handleSpmbPaymentCallback(Request $request, $mahasiswaId)
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

            $tagihan = TagihanMahasiswa::where('mahasiswa_id', $mahasiswaId)
                ->where('source_system', 'SPMB')
                ->where('status', '!=', 'lunas')
                ->first();

            if (!$tagihan) {
                // If bill doesn't exist, create a lunas bill
                $tagihan = TagihanMahasiswa::create([
                    'mahasiswa_id' => $mahasiswaId,
                    'tahun_akademik_id' => 1,
                    'nomor_tagihan' => 'INV-SPMB-' . date('Ymd') . '-' . Str::random(4),
                    'total_tagihan' => $validated['nominal'],
                    'total_bayar' => $validated['nominal'],
                    'status' => 'lunas',
                    'source_system' => 'SPMB',
                ]);
            } else {
                $tagihan->update([
                    'status' => 'lunas',
                    'total_bayar' => $validated['nominal'],
                ]);
            }

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
                    'keterangan' => "Penerimaan Biaya Pendaftaran SPMB Mahasiswa ID #{$mahasiswaId}",
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
                    'keterangan' => "Penerimaan Kas/Bank Pendaftaran SPMB ID #{$mahasiswaId}",
                ]);

                DetailJurnalUmum::create([
                    'jurnal_id' => $jurnal->id,
                    'akun_id' => $akunSpmb->id,
                    'debet' => 0,
                    'kredit' => $validated['nominal'],
                    'keterangan' => "Pendapatan Pendaftaran SPMB ID #{$mahasiswaId}",
                ]);
            }

            DB::commit();

            Log::info("SPMB Callback -> SIKEU: Pembayaran SPMB Mahasiswa #{$mahasiswaId} Lunas. SPMB Unlocked!");

            return response()->json([
                'status' => 'success',
                'message' => 'Pembayaran SPMB berhasil diproses, saldo kas diperbarui, dan status pendaftaran SPMB dibuka (unlocked).',
                'spmb_unlock' => true,
                'data' => [
                    'mahasiswa_id' => $mahasiswaId,
                    'tagihan_status' => 'lunas',
                    'pembayaran_id' => $pembayaran->id,
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("SPMB Callback Error: " . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal memproses callback pembayaran SPMB: ' . $e->getMessage(),
            ], 500);
        }
    }
}
