<?php

namespace App\Http\Controllers\API\Spmb;

use App\Http\Controllers\Controller;
use App\Models\Spmb\PendaftaranCalonMhs;
use App\Models\Spmb\DokumenPendaftaran;
use App\Services\Spmb\SpmbPendaftaranService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PendaftaranController extends Controller
{
    public function __construct(private SpmbPendaftaranService $pendaftaranService) {}

    /**
     * Get all Pendaftaran with filters
     */
    public function index(Request $request): JsonResponse
    {
        $query = PendaftaranCalonMhs::with([
            'gelombangPenerimaan.jalurMasuk',
            'programStudi',
            'programStudiPilihan2',
            'user'
        ]);

        // Filter by Status Pendaftaran
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by Status Pembayaran
        if ($request->filled('status_pembayaran')) {
            $query->where('status_pembayaran', $request->status_pembayaran);
        }

        // Filter by Gelombang
        if ($request->filled('gelombang_id')) {
            $query->where('gelombang_id', $request->gelombang_id);
        }

        // Filter by Kode Referral
        if ($request->filled('referral_code')) {
            $query->where('used_referral_code', 'like', '%' . $request->input('referral_code') . '%');
        }

        // Search by Nama Lengkap, No Pendaftaran, NIK, or User Account
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('nama_lengkap', 'like', "%{$search}%")
                  ->orWhere('no_pendaftaran', 'like', "%{$search}%")
                  ->orWhere('nik', 'like', "%{$search}%")
                  ->orWhereHas('user', function($uq) use ($search) {
                      $uq->where('email', 'like', "%{$search}%")
                         ->orWhere('username', 'like', "%{$search}%");
                  });
            });
        }

        // Sorting
        $orderBy = $request->input('order_by', 'created_at');
        $orderDir = $request->input('order_dir', 'desc');
        $query->orderBy($orderBy, $orderDir);

        $perPage = (int) $request->input('per_page', $request->input('limit', 15));
        $data = $query->paginate($perPage);

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
            'dokumenPendaftaran',
            'user',
            'referrer:id,username,name,referral_code',
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

        // Simpan status + sinkronkan referral dalam satu transaksi service.
        $pendaftaran = $this->pendaftaranService->updateStatusWithReferral($pendaftaran, $validated, auth()->id());

        return response()->json([
            'status' => 'success',
            'message' => 'Status pendaftaran berhasil diperbarui',
            'data' => $pendaftaran
        ]);
    }
}
