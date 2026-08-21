<?php

namespace App\Http\Controllers\API\Spmb;

use App\Http\Controllers\Controller;
use App\Models\Spmb\JalurMasuk;
use App\Models\Spmb\GelombangPenerimaan;
use App\Models\Spmb\MasterTipeJalur;
use App\Models\System\MasterReferensi;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class MasterSpmbController extends Controller
{
    /**
     * Get referensi by tipe
     */
    public function getReferensi($tipe): JsonResponse
    {
        $data = MasterReferensi::where('tipe', $tipe)
            ->where('is_active', true)
            ->orderBy('urutan')
            ->get();
            
        return response()->json([
            'status' => 'success',
            'data' => $data
        ]);
    }

    /**
     * Get all Master Tipe Jalur
     */
    public function getMasterTipeJalur(): JsonResponse
    {
        $tipeJalur = MasterTipeJalur::all();
        return response()->json([
            'status' => 'success',
            'data' => $tipeJalur
        ]);
    }

    /**
     * Get all Jalur Masuk
     */
    public function getJalurMasuk(): JsonResponse
    {
        $jalur = JalurMasuk::with('masterTipeJalur')->orderBy('created_at', 'desc')->get();
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
            'master_tipe_jalur_id' => 'required|exists:master_tipe_jalur,id',
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
            'master_tipe_jalur_id' => 'required|exists:master_tipe_jalur,id',
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

    /**
     * Get all active Program Studi for SPMB
     */
    public function getProgramStudi(): JsonResponse
    {
        $prodi = collect();
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('master_program_studi')) {
                $prodi = \App\Models\Spmb\MasterProgramStudi::where('is_active', true)->get();
            } elseif (\Illuminate\Support\Facades\Schema::hasTable('program_studi')) {
                $prodi = \Illuminate\Support\Facades\DB::table('program_studi')->get();
            }
        } catch (\Throwable $e) {
            // Graceful fallback to static collection if table not migrated yet
        }

        if ($prodi->isEmpty()) {
            $prodi = collect([
                ['id' => 1, 'kode_prodi' => 'TI-S1', 'nama' => 'S1 Teknik Informatika', 'jenjang' => 'S1'],
                ['id' => 2, 'kode_prodi' => 'SI-S1', 'nama' => 'S1 Sistem Informasi', 'jenjang' => 'S1'],
                ['id' => 3, 'kode_prodi' => 'DKV-S1', 'nama' => 'S1 Desain Komunikasi Visual', 'jenjang' => 'S1'],
                ['id' => 4, 'kode_prodi' => 'MI-D3', 'nama' => 'D3 Manajemen Informatika', 'jenjang' => 'D3'],
            ]);
        }

        return response()->json([
            'status' => 'success',
            'data' => $prodi
        ]);
    }

    public function getMasterTipeJalur(): JsonResponse
    {
        $tipe = \App\Models\MasterTipeJalur::orderBy('nama', 'asc')->get();
        return response()->json([
            'status' => 'success',
            'data' => $tipe
        ]);
    }

    /**
     * Get all active Tahun Akademik for SPMB
     */
    public function getTahunAkademik(): JsonResponse
    {
        $tahun = collect();
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('master_tahun_akademik')) {
                $tahun = \App\Models\Spmb\MasterTahunAkademik::orderBy('id', 'desc')->get();
            }
        } catch (\Throwable $e) {
            // Graceful fallback if table not migrated yet
        }

        if ($tahun->isEmpty()) {
            $tahun = collect([
                ['id' => 1, 'nama' => '2026/2027 Ganjil', 'tahun_mulai' => 2026, 'tahun_selesai' => 2027, 'is_active' => true],
                ['id' => 2, 'nama' => '2025/2026 Genap', 'tahun_mulai' => 2025, 'tahun_selesai' => 2026, 'is_active' => false],
            ]);
        }

        return response()->json([
            'status' => 'success',
            'data' => $tahun
        ]);
    }
}
