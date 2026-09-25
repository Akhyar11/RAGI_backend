<?php

namespace App\Http\Controllers\Sikeu;

use App\Events\Sikeu\PembayaranSpmbLunas;
use App\Events\Spmb\MahasiswaDiterima;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sikeu\SpmbPaymentCallbackRequest;
use App\Models\Sikeu\Pembayaran;
use App\Models\Sikeu\TagihanMahasiswa;
use App\Models\Sikeu\VirtualAccount;
use App\Models\Spmb\HasilSeleksi;
use App\Models\Spmb\PembayaranSpmb;
use App\Models\Spmb\PendaftaranCalonMhs;
use App\Services\Sikeu\AutoJournalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

class SpmBSikeuCallbackController extends Controller
{
    /**
     * POST /api/v1/sikeu/callback/spmb/{calonMahasiswaId}
     * Webhook/Callback handler for SPMB registration fee payment completion.
     */
    public function handleSpmbPaymentCallback(SpmbPaymentCallbackRequest $request, $calonMahasiswaId)
    {
        return $this->processPayment($request->validated(), (int) $calonMahasiswaId);
    }

    /**
     * POST /api/v1/sikeu/callback/spmb/{calonMahasiswaId}/simulate
     * Simulasi pembayaran untuk pengujian lokal. Hanya aktif di environment
     * local/testing, dan hanya boleh mensimulasikan tagihan milik sendiri
     * (atau oleh admin).
     */
    public function simulateSpmbPayment(SpmbPaymentCallbackRequest $request, $calonMahasiswaId)
    {
        abort_unless(app()->environment(['local', 'testing']), 404);

        $pendaftaran = PendaftaranCalonMhs::findOrFail($calonMahasiswaId);

        Gate::authorize('simulate-spmb-payment', $pendaftaran);

        return $this->processPayment($request->validated(), (int) $calonMahasiswaId);
    }

    /**
     * Inti pemrosesan pembayaran SPMB (dipakai webhook & simulasi).
     * Multi-tulis dibungkus DB::transaction agar rollback otomatis;
     * error tak terduga didelegasikan ke Global Exception Handler.
     */
    protected function processPayment(array $validated, int $calonMahasiswaId)
    {
        $outcome = DB::transaction(function () use ($validated, $calonMahasiswaId) {
            // Idempotency guard: pastikan order_id belum pernah diproses sebelumnya.
            $existingPembayaran = Pembayaran::where('kode_transaksi', $validated['order_id'])->first();
            if ($existingPembayaran) {
                Log::info("SPMB Payment Callback duplikat diabaikan untuk order_id {$validated['order_id']}");

                return [
                    'http_status' => 200,
                    'payload' => [
                        'status' => 'success',
                        'message' => 'Callback sudah diproses sebelumnya (idempotent).',
                        'is_spmb_unlocked' => true,
                        'data' => [
                            'tagihan' => $existingPembayaran->tagihan,
                            'pembayaran' => $existingPembayaran,
                        ],
                    ],
                ];
            }

            // Cari tagihan SPMB yang belum lunas milik calon mahasiswa ini.
            // Prioritas: tagihan pendaftaran (tipe_referensi spmb_pendaftaran).
            $tagihan = TagihanMahasiswa::where(function ($q) use ($calonMahasiswaId) {
                $q->where('calon_mahasiswa_id', $calonMahasiswaId)
                    ->orWhere('mahasiswa_id', $calonMahasiswaId);
            })
                ->where('source_system', 'SPMB')
                ->where('status', '!=', 'lunas')
                ->orderByRaw("CASE WHEN tipe_referensi = 'spmb_pendaftaran' THEN 0 ELSE 1 END")
                ->orderBy('created_at')
                ->first();

            if (! $tagihan) {
                return [
                    'http_status' => 404,
                    'payload' => [
                        'status' => 'error',
                        'message' => 'Tagihan SPMB yang belum lunas tidak ditemukan untuk pendaftar ini.',
                    ],
                ];
            }

            // Hitung status pembayaran secara proporsional (mendukung pembayaran sebagian).
            $totalBersih = (float) $tagihan->total_tagihan + (float) $tagihan->total_denda - (float) $tagihan->total_potongan;
            $newTotalBayar = (float) $tagihan->total_bayar + (float) $validated['nominal'];
            $newStatus = $newTotalBayar >= $totalBersih ? 'lunas' : ($newTotalBayar > 0 ? 'sebagian' : 'belum_bayar');

            $tagihan->update([
                'status' => $newStatus,
                'total_bayar' => min($newTotalBayar, max($totalBersih, 0)),
            ]);

            // Trigger Auto Journal (Debet Kas Bank, Kredit Pendapatan SPMB)
            AutoJournalService::recordStudentPaymentJournal($tagihan, (float) $validated['nominal']);

            // Sinkronkan status pendaftaran / daftar ulang berdasarkan tipe tagihan.
            $this->syncSpmbStatus($tagihan, $newStatus, (float) $validated['nominal']);

            // Create Pembayaran Record
            $pembayaran = Pembayaran::create([
                'tagihan_id' => $tagihan->id,
                'kode_transaksi' => $validated['order_id'],
                'jumlah_bayar' => $validated['nominal'],
                'waktu_bayar' => now(),
                'channel_bayar' => $validated['channel'] ?? $validated['bank_kode'] ?? 'BANK_VA',
                'status' => 'success',
            ]);

            return [
                'http_status' => 200,
                'payload' => [
                    'status' => 'success',
                    'message' => $newStatus === 'lunas'
                        ? 'Pembayaran SPMB lunas dan dicatat ke jurnal keuangan.'
                        : 'Pembayaran sebagian diterima dan dicatat ke jurnal keuangan.',
                    'is_spmb_unlocked' => $newStatus === 'lunas',
                    'data' => [
                        'tagihan' => $tagihan->fresh(),
                        'pembayaran' => $pembayaran,
                    ],
                ],
                'lunas_event' => ($newStatus === 'lunas' && $tagihan->calon_mahasiswa_id)
                    ? [$tagihan->calon_mahasiswa_id, $tagihan, $pembayaran]
                    : null,
            ];
        });

        // Event listener hanya saat tagihan benar-benar lunas (setelah commit).
        if (! empty($outcome['lunas_event'])) {
            [$eventCalonId, $eventTagihan, $eventPembayaran] = $outcome['lunas_event'];
            event(new PembayaranSpmbLunas($eventCalonId, $eventTagihan, $eventPembayaran));
        }

        if (($outcome['http_status'] ?? 200) !== 200) {
            return response()->json($outcome['payload'], $outcome['http_status']);
        }

        Log::info("SPMB Payment Callback processed for Calon Mhs #{$calonMahasiswaId}", $validated);

        return response()->json($outcome['payload']);
    }

    /**
     * Sinkronkan status modul SPMB setelah pembayaran tagihan.
     */
    protected function syncSpmbStatus(TagihanMahasiswa $tagihan, string $newStatus, float $nominal): void
    {
        $pendaftaranId = $tagihan->calon_mahasiswa_id;
        if (! $pendaftaranId) {
            return;
        }

        $pendaftaran = PendaftaranCalonMhs::find($pendaftaranId);
        if (! $pendaftaran) {
            return;
        }

        $isDaftarUlang = $tagihan->tipe_referensi === 'spmb_daftar_ulang';

        if ($isDaftarUlang) {
            if ($newStatus === 'lunas') {
                $hasil = HasilSeleksi::where('pendaftaran_id', $pendaftaran->id)->first();
                if ($hasil && $hasil->status_daftar_ulang !== 'lunas') {
                    $hasil->update(['status_daftar_ulang' => 'lunas']);
                    event(new MahasiswaDiterima($pendaftaran));
                }
            }

            return;
        }

        // Tagihan pendaftaran: status pembayaran + promote draft -> submitted hanya saat lunas.
        $newStatusPendaftaran = $newStatus === 'lunas' ? 'lunas' : ($newStatus === 'sebagian' ? 'sebagian' : 'belum_bayar');
        $newStatusPendaftaranRecord = $pendaftaran->status;
        if ($newStatus === 'lunas' && $pendaftaran->status === PendaftaranCalonMhs::STATUS_DRAFT) {
            $newStatusPendaftaranRecord = PendaftaranCalonMhs::STATUS_SUBMITTED;
        }

        $pendaftaran->update([
            'status_pembayaran' => $newStatusPendaftaran,
            'status' => $newStatusPendaftaranRecord,
        ]);

        if ($newStatus === 'lunas') {
            PembayaranSpmb::where('pendaftaran_id', $pendaftaran->id)
                ->update([
                    'status' => 'paid',
                    'jumlah_bayar' => $nominal,
                    'paid_at' => now(),
                ]);
        }
    }

    /**
     * GET /api/v1/sikeu/checkout/lookup-va?va_number=...
     * Automatically look up VA bill details from DB/Xendit to populate checkout page dynamically.
     */
    public function lookupVa(Request $request)
    {
        $vaNumber = trim($request->query('va_number', ''));
        if (! $vaNumber) {
            return response()->json([
                'status' => 'error',
                'message' => 'Nomor Virtual Account wajib diisi.',
            ], 400);
        }

        $cleanVa = preg_replace('/[^0-9]/', '', $vaNumber);

        $va = VirtualAccount::where('va_number', $cleanVa)->first();

        if (! $va && strlen($cleanVa) >= 6) {
            $va = VirtualAccount::where('va_number', 'like', '%'.$cleanVa.'%')->first();
        }

        if (! $va) {
            return response()->json([
                'status' => 'error',
                'message' => 'Nomor Virtual Account ('.$vaNumber.') tidak ditemukan di Xendit / Server SIKEU.',
            ], 404);
        }

        $tagihan = TagihanMahasiswa::find($va->tagihan_id);
        $pendaftaran = $tagihan ? PendaftaranCalonMhs::with('programStudi')->find($tagihan->calon_mahasiswa_id) : null;

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
            ],
        ]);
    }
}
