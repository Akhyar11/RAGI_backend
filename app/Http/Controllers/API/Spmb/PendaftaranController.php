<?php

namespace App\Http\Controllers\API\Spmb;

use App\Http\Controllers\Controller;
use App\Models\Spmb\PendaftaranCalonMhs;
use App\Models\Spmb\DokumenPendaftaran;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PendaftaranController extends Controller
{
    /**
     * Get all Pendaftaran with filters
     */
    public function index(Request $request): JsonResponse
    {
        $query = PendaftaranCalonMhs::with(['gelombangPenerimaan', 'programStudi']);

        // Filter by Status
        if ($request->has('status') && $request->status !== '') {
            $query->where('status', $request->status);
        }

        // Search by Nama Lengkap or No Pendaftaran
        if ($request->has('search') && $request->search !== '') {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('nama_lengkap', 'like', "%{$search}%")
                  ->orWhere('no_pendaftaran', 'like', "%{$search}%");
            });
        }

        // Sorting
        $orderBy = $request->input('order_by', 'created_at');
        $orderDir = $request->input('order_dir', 'desc');
        $query->orderBy($orderBy, $orderDir);

        $data = $query->paginate($request->input('per_page', 15));

        return response()->json([
            'status' => 'success',
            'data' => $data
        ]);
    }

    /**
     * Get detail Pendaftaran
     */
    public function show($id): JsonResponse
    {
        $pendaftaran = PendaftaranCalonMhs::with([
            'gelombangPenerimaan',
            'programStudi',
            'programStudiPilihan2',
            'dokumenPendaftaran'
        ])->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => $pendaftaran
        ]);
    }

    /**
     * Verify a specific Dokumen Pendaftaran
     */
    public function verifyBerkas(Request $request, $id): JsonResponse
    {
        $validated = $request->validate([
            'is_verified' => 'required|boolean',
            'catatan' => 'nullable|string'
        ]);

        $dokumen = DokumenPendaftaran::findOrFail($id);
        $dokumen->is_verified = $validated['is_verified'];
        if (isset($validated['catatan'])) {
            $dokumen->catatan = $validated['catatan'];
        }
        $dokumen->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Status berkas berhasil diperbarui',
            'data' => $dokumen
        ]);
    }

    /**
     * Update overall Status Pendaftaran
     */
    public function updateStatus(Request $request, $id): JsonResponse
    {
        $pendaftaran = PendaftaranCalonMhs::findOrFail($id);

        $validated = $request->validate([
            'status' => 'required|in:draft,submitted,verified,lulus_administrasi,gagal_administrasi',
            'catatan_verifikasi' => 'nullable|string'
        ]);

        $pendaftaran->status = $validated['status'];
        if (isset($validated['catatan_verifikasi'])) {
            $pendaftaran->catatan_verifikasi = $validated['catatan_verifikasi'];
        }
        $pendaftaran->diverifikasi_oleh = auth()->id();
        $pendaftaran->diverifikasi_at = now();
        $pendaftaran->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Status pendaftaran berhasil diperbarui',
            'data' => $pendaftaran
        ]);
    }
}
