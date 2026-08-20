<?php

namespace App\Http\Controllers\API\Spmb;

use App\Http\Controllers\Controller;
use App\Models\Spmb\PendaftaranCalonMhs;
use App\Services\Spmb\SpmbPendaftaranService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AdminSeleksiController extends Controller
{
    protected SpmbPendaftaranService $pendaftaranService;

    public function __construct(SpmbPendaftaranService $pendaftaranService)
    {
        $this->pendaftaranService = $pendaftaranService;
    }

    /**
     * Get all registrations (for admin table)
     */
    public function getPendaftar(Request $request): JsonResponse
    {
        $user = $request->user();
        $isPanitiaAdmin = $user && $user->roles()->whereIn('slug', ['superadmin', 'admin', 'admin_spmb', 'panitia_spmb', 'operator_spmb'])->exists();

        if (!$isPanitiaAdmin) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized access. Menu ini hanya untuk administrator/panitia SPMB.'
            ], 403);
        }

        $query = PendaftaranCalonMhs::with(['gelombangPenerimaan', 'hasilSeleksi']);
        
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('gelombang_id')) {
            $query->where('gelombang_id', $request->gelombang_id);
        }

        $pendaftar = $query->orderBy('created_at', 'desc')->paginate(15);

        return response()->json([
            'status' => 'success',
            'data' => $pendaftar
        ]);
    }

    /**
     * Verifikasi Administrasi Pendaftar
     */
    public function verifikasi(Request $request, $id): JsonResponse
    {
        $pendaftaran = PendaftaranCalonMhs::findOrFail($id);
        $adminId = $request->user()->id;

        $validated = $request->validate([
            'is_lulus' => 'required|boolean',
            'catatan' => 'nullable|string'
        ]);

        $this->pendaftaranService->verifikasiAdministrasi(
            $pendaftaran,
            $validated['is_lulus'],
            $validated['catatan'] ?? null,
            $adminId
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Verifikasi administrasi berhasil disimpan.'
        ]);
    }

    /**
     * Penetapan Kelulusan Akhir
     */
    public function tetapkanKelulusan(Request $request, $id): JsonResponse
    {
        $pendaftaran = PendaftaranCalonMhs::findOrFail($id);

        $validated = $request->validate([
            'program_studi_diterima_id' => 'required_if:status,lulus|integer|nullable',
            'nilai_total' => 'required|numeric|min:0|max:100',
            'peringkat' => 'nullable|integer',
            'status' => 'required|in:lulus,tidak_lulus,cadangan',
            'catatan' => 'nullable|string'
        ]);

        $hasil = $this->pendaftaranService->tetapkanKelulusan($pendaftaran, $validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Hasil seleksi berhasil ditetapkan.',
            'data' => $hasil
        ]);
    }
}
