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

            Log::info("SPMB Callback -> SIKEU: Pembayaran SPMB Calon Mahasiswa #{$calonMahasiswaId} Lunas. Event PembayaranSpmbLunas dispatched!");

            return response()->json([
                'status' => 'success',
                'message' => 'Pembayaran SPMB berhasil diproses, saldo kas diperbarui, dan status pendaftaran SPMB dibuka (unlocked).',
                'spmb_unlock' => true,
                'data' => [
                    'calon_mahasiswa_id' => $calonMahasiswaId,
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
