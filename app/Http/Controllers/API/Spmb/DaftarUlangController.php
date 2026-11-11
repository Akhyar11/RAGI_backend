<?php

namespace App\Http\Controllers\API\Spmb;

use App\Events\Spmb\MahasiswaDiterima;
use App\Http\Controllers\Controller;
use App\Models\Sikeu\TagihanMahasiswa;
use App\Models\Spmb\HasilSeleksi;
use App\Models\Spmb\PendaftaranCalonMhs;
use App\Services\Sikeu\ExternalTagihanService;
use App\Services\Spmb\MasterBiayaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DaftarUlangController extends Controller
{
    public function generateTagihan(Request $request, $pendaftaran_id): JsonResponse
    {
        $pendaftaran = PendaftaranCalonMhs::with(['gelombang_penerimaan', 'hasilSeleksi'])->findOrFail($pendaftaran_id);
        abort_unless($pendaftaran->user_id === $request->user()->id || $request->user()->can('spmb.manage'), 403);
        $hasil = HasilSeleksi::where('pendaftaran_id', $pendaftaran_id)->firstOrFail();

        if ($hasil->status !== 'lulus') {
            return response()->json([
                'status' => 'error',
                'message' => 'Peserta belum lulus seleksi.',
            ], 400);
        }

        if ($hasil->status_daftar_ulang === 'lunas') {
            return response()->json([
                'status' => 'error',
                'message' => 'Sudah menyelesaikan daftar ulang.',
            ], 400);
        }

        if ($hasil->status_daftar_ulang === 'menunggu_pembayaran') {
            $bills = app(\App\Services\Spmb\SpmbBillingService::class)->stageQuery((int) $pendaftaran_id, true)->orderBy('id')->get();
            return response()->json([
                'status' => 'success',
                'message' => 'Tagihan daftar ulang sudah dibuat, silakan lanjutkan pembayaran.',
                'data' => app(\App\Services\Spmb\SpmbBillingService::class)->envelope($bills->all()),
            ]);
        }

        // Biaya daftar ulang disusun oleh service (fallback otomatis ke SIKEU).
        $prodiId = $hasil->program_studi_diterima_id ?? $pendaftaran->program_studi_id;
        $masterBiayaService = app(MasterBiayaService::class);
        $details = $masterBiayaService->buildDetailBebanDaftarUlang($pendaftaran->master_tipe_jalur_id, $prodiId);

        // Potongan daftar ulang = diskon default gelombang + potongan kustom calon (stacking).
        $potongan = app(\App\Services\Spmb\SpmbPotonganCalonService::class)
            ->buildDaftarUlangPotonganPayload($pendaftaran, $details);

        $payload = [
            'calon_mahasiswa_id' => $pendaftaran_id,
            'tipe_referensi' => 'spmb_daftar_ulang',
            'source_system' => 'SPMB',
            'requires_approval' => false,
            'keterangan' => 'Tagihan Daftar Ulang - Pendaftaran ID '.$pendaftaran_id,
            'details' => $details,
            'potongan' => $potongan,
        ];

        $issued = app(\App\Services\Spmb\SpmbBillingService::class)->issue($payload);
        $vaData = [
            'tagihan' => $issued['tagihan'],
            'virtual_account' => $issued['virtual_account'],
            'tagihan_list' => $issued['tagihan_list'],
        ];

        if ($hasil->fresh()->status_daftar_ulang !== 'lunas') {
            $hasil->update(['status_daftar_ulang' => 'menunggu_pembayaran']);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Tagihan Daftar Ulang berhasil dibuat.',
            'data' => $vaData,
        ]);
    }

    public function konfirmasi(Request $request, $pendaftaran_id): JsonResponse
    {
        $pendaftaran = PendaftaranCalonMhs::findOrFail($pendaftaran_id);
        abort_unless($pendaftaran->user_id === $request->user()->id || $request->user()->can('spmb.manage'), 403);
        $hasil = HasilSeleksi::where('pendaftaran_id', $pendaftaran_id)->firstOrFail();

        if ($hasil->status_daftar_ulang === 'lunas') {
            return response()->json([
                'status' => 'error',
                'message' => 'Sudah melakukan daftar ulang.',
            ], 400);
        }

        // Pastikan tagihan daftar ulang benar-benar sudah lunas sebelum konversi.
        $bills = app(\App\Services\Spmb\SpmbBillingService::class)->stageQuery((int) $pendaftaran_id, true)->get();
        $tagihanLunas = $bills->isNotEmpty() && $bills->every(fn ($bill) => $bill->status === 'lunas');

        if (! $tagihanLunas) {
            return response()->json([
                'status' => 'error',
                'message' => 'Tagihan daftar ulang belum lunas. Selesaikan pembayaran terlebih dahulu.',
            ], 400);
        }

        $hasil->update(['status_daftar_ulang' => 'lunas']);

        // Trigger Event ke SIAKAD
        event(new MahasiswaDiterima($pendaftaran));

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar Ulang selesai. Mahasiswa berhasil dikonversi ke SIAKAD.',
        ]);
    }
}
