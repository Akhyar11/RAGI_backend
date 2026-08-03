<?php

namespace App\Http\Controllers\API\Spmb;

use App\Http\Controllers\Controller;
use App\Models\Spmb\PendaftaranCalonMhs;
use App\Services\Spmb\SpmbPendaftaranService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CalonMahasiswaController extends Controller
{
    protected SpmbPendaftaranService $pendaftaranService;

    public function __construct(SpmbPendaftaranService $pendaftaranService)
    {
        $this->pendaftaranService = $pendaftaranService;
    }

    /**
     * Get my registration data
     */
    public function myPendaftaran(Request $request): JsonResponse
    {
        $user = $request->user();
        $pendaftaran = PendaftaranCalonMhs::with(['gelombangPenerimaan', 'pembayaranSpmb', 'hasilSeleksi'])
            ->where('user_id', $user->id)
            ->first();

        return response()->json([
            'status' => 'success',
            'data' => $pendaftaran
        ]);
    }

    /**
     * Submit biodata pendaftaran (Draft)
     */
    public function storeBiodata(Request $request): JsonResponse
    {
        $user = $request->user();
        
        $validated = $request->validate([
            'gelombang_id' => 'required|exists:gelombang_penerimaan,id',
            'program_studi_id' => 'required|integer',
            'program_studi_pilihan2_id' => 'nullable|integer',
            'nama_lengkap' => 'required|string|max:255',
            'nik' => 'required|string|size:16',
            'tanggal_lahir' => 'required|date',
            'tempat_lahir' => 'required|string',
            'jenis_kelamin' => 'required|in:L,P',
            'alamat' => 'required|string',
            'asal_sekolah' => 'required|string',
            'jurusan_sekolah' => 'required|string',
        ]);

        $pendaftaran = PendaftaranCalonMhs::updateOrCreate(
            ['user_id' => $user->id, 'gelombang_id' => $validated['gelombang_id']],
            array_merge($validated, [
                'no_pendaftaran' => 'REG-' . date('Ymd') . '-' . rand(1000, 9999),
                'status' => 'draft'
            ])
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Biodata berhasil disimpan.',
            'data' => $pendaftaran
        ]);
    }

    /**
     * Submit formulir secara final (Lock)
     */
    public function finalize(Request $request): JsonResponse
    {
        $user = $request->user();
        $pendaftaran = PendaftaranCalonMhs::where('user_id', $user->id)->firstOrFail();

        $this->pendaftaranService->submitPendaftaran($pendaftaran);

        return response()->json([
            'status' => 'success',
            'message' => 'Pendaftaran berhasil disubmit.',
            'data' => $pendaftaran
        ]);
    }
}
