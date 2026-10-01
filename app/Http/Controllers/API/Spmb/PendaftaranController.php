<?php

namespace App\Http\Controllers\API\Spmb;

use App\Http\Controllers\Controller;
use App\Models\Spmb\PendaftaranCalonMhs;
use App\Models\Spmb\DokumenPendaftaran;
use App\Models\Spmb\HasilSeleksi;
use App\Services\AuditLogService;
use App\Services\Spmb\SpmbPendaftaranService;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

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
            'hasilSeleksi',
            'referrer:id,username,name,referral_code',
        ])->findOrFail($id);

        // Ringkasan daftar ulang (tagihan & pembayaran dikelola modul SIKEU).
        $daftarUlang = $this->pendaftaranService->daftarUlangSummary($pendaftaran);

        return response()->json([
            'status' => 'success',
            'message' => 'Detail pendaftaran berhasil diambil',
            'data' => array_merge($pendaftaran->toArray(), [
                'daftar_ulang' => $daftarUlang,
            ])
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

    /**
     * Unduh Surat Keterangan Tanda Lulus (SK Tanda Lulus) PDF
     */
    public function downloadSkLulus(Request $request, $id)
    {
        $user = $request->user();
        if (! $user) {
            abort(401, 'Unauthenticated.');
        }

        if ($id === 'me') {
            $pendaftaran = PendaftaranCalonMhs::where('user_id', $user->id)->firstOrFail();
        } else {
            $pendaftaran = PendaftaranCalonMhs::findOrFail($id);
        }

        $isAdmin = $user->hasPermission('spmb.manage')
            || $user->hasPermission('spmb.pendaftaran.read');

        if ($pendaftaran->user_id !== $user->id && ! $isAdmin) {
            abort(403, 'Anda tidak memiliki hak akses untuk mengunduh SK pendaftaran ini.');
        }

        $isLulus = $pendaftaran->status === PendaftaranCalonMhs::STATUS_LULUS_ADMINISTRASI
            || $pendaftaran->status === PendaftaranCalonMhs::STATUS_MAHASISWA_BARU
            || ($pendaftaran->hasilSeleksi && $pendaftaran->hasilSeleksi->status === HasilSeleksi::STATUS_LULUS);

        if (! $isLulus) {
            return response()->json([
                'status' => 'error',
                'message' => 'SK Tanda Lulus belum dapat diunduh karena pendaftaran belum dinyatakan lulus seleksi administrasi.',
            ], 400);
        }

        $pdf = $this->pendaftaranService->generateSkLulusPdf($pendaftaran);
        $filename = 'SK-Tanda-Lulus-'.$pendaftaran->no_pendaftaran.'.pdf';

        try {
            AuditLogService::record(
                module: 'SPMB',
                action: 'export',
                tableName: $pendaftaran->getTable(),
                recordId: $pendaftaran->id,
                oldValues: null,
                newValues: [
                    'no_pendaftaran' => $pendaftaran->no_pendaftaran,
                    'jenis_dokumen' => 'SK_TANDA_LULUS',
                ],
                request: $request
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal mencatat audit log: ' . $e->getMessage());
        }

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
}
