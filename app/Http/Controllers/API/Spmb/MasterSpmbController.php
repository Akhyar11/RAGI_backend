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
     * Show Jalur Masuk
     */
    public function showJalurMasuk($id): JsonResponse
    {
        $jalur = JalurMasuk::findOrFail($id);
        return response()->json([
            'status' => 'success',
            'data' => $jalur
        ]);
    }

    /**
     * Store Jalur Masuk
     */
    public function storeJalurMasuk(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'kode' => 'required|string|unique:jalur_masuk,kode',
            'nama' => 'required|string',
            'deskripsi' => 'nullable|string',
            'tipe' => 'required|in:reguler,transfer,beasiswa,internasional,rpla',
            'ada_ujian_tulis' => 'required|boolean',
            'ada_ujian_praktik' => 'required|boolean',
            'ada_wawancara' => 'required|boolean',
            'is_active' => 'required|boolean',
        ]);

        $jalur = JalurMasuk::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Jalur masuk berhasil dibuat.',
            'data' => $jalur
        ], 201);
    }

    /**
     * Update Jalur Masuk
     */
    public function updateJalurMasuk(Request $request, $id): JsonResponse
    {
        $jalur = JalurMasuk::findOrFail($id);

        $validated = $request->validate([
            'kode' => 'required|string|unique:jalur_masuk,kode,' . $jalur->id,
            'nama' => 'required|string',
            'deskripsi' => 'nullable|string',
            'tipe' => 'required|in:reguler,transfer,beasiswa,internasional,rpla',
            'ada_ujian_tulis' => 'required|boolean',
            'ada_ujian_praktik' => 'required|boolean',
            'ada_wawancara' => 'required|boolean',
            'is_active' => 'required|boolean',
        ]);

        $jalur->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Jalur masuk berhasil diperbarui.',
            'data' => $jalur
        ]);
    }

    /**
     * Destroy Jalur Masuk
     */
    public function destroyJalurMasuk($id): JsonResponse
    {
        $jalur = JalurMasuk::findOrFail($id);
        $jalur->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Jalur masuk berhasil dihapus.'
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
     * Show Gelombang
     */
    public function showGelombang($id): JsonResponse
    {
        $gelombang = GelombangPenerimaan::with('jalurMasuk')->findOrFail($id);
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

    /**
     * Update Gelombang
     */
    public function updateGelombang(Request $request, $id): JsonResponse
    {
        $gelombang = GelombangPenerimaan::findOrFail($id);

        $validated = $request->validate([
            'jalur_masuk_id' => 'required|exists:jalur_masuk,id',
            'tahun_akademik_id' => 'required|integer',
            'nama' => 'required|string',
            'tanggal_buka' => 'required|date',
            'tanggal_tutup' => 'required|date|after_or_equal:tanggal_buka',
            'tanggal_ujian' => 'nullable|date',
            'tanggal_pengumuman' => 'nullable|date',
            'kuota_total' => 'required|integer|min:1',
            'biaya_pendaftaran' => 'required|numeric|min:0',
            'status' => 'required|in:draft,aktif,ditutup,selesai',
        ]);

        $gelombang->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Gelombang penerimaan berhasil diperbarui.',
            'data' => $gelombang
        ]);
    }

    /**
     * Destroy Gelombang
     */
    public function destroyGelombang($id): JsonResponse
    {
        $gelombang = GelombangPenerimaan::findOrFail($id);
        $gelombang->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Gelombang penerimaan berhasil dihapus.'
        ]);
    }
}
