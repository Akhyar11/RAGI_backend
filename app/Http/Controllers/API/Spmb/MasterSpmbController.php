<?php

namespace App\Http\Controllers\API\Spmb;

use App\Http\Controllers\Controller;
use App\Models\Spmb\JalurMasuk;
use App\Models\Spmb\GelombangPenerimaan;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class MasterSpmbController extends Controller
{
    /**
     * Get all Jalur Masuk
     */
    public function getJalurMasuk(): JsonResponse
    {
        $jalur = JalurMasuk::all();
        return response()->json([
            'status' => 'success',
            'data' => $jalur
        ]);
    }

    /**
     * Get all Gelombang Penerimaan (Active)
     */
    public function getGelombang(): JsonResponse
    {
        $gelombang = GelombangPenerimaan::with('jalurMasuk')->orderBy('tanggal_buka', 'desc')->get();
        return response()->json([
            'status' => 'success',
            'data' => $gelombang
        ]);
    }

    /**
     * Store Gelombang
     */
    public function storeGelombang(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'jalur_masuk_id' => 'required|exists:jalur_masuk,id',
            'tahun_akademik_id' => 'required|integer', // assuming it exists
            'nama' => 'required|string',
            'tanggal_buka' => 'required|date',
            'tanggal_tutup' => 'required|date|after_or_equal:tanggal_buka',
            'tanggal_ujian' => 'nullable|date',
            'tanggal_pengumuman' => 'nullable|date',
            'kuota_total' => 'required|integer|min:1',
            'biaya_pendaftaran' => 'required|numeric|min:0',
            'status' => 'required|in:draft,aktif,ditutup,selesai',
        ]);

        $gelombang = GelombangPenerimaan::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Gelombang penerimaan berhasil dibuat.',
            'data' => $gelombang
        ], 201);
    }
}
