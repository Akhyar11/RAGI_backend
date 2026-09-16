<?php

namespace App\Http\Controllers\Simpeg;

use App\Http\Controllers\Controller;
use App\Http\Requests\Simpeg\EvaluateSkpRequest;
use App\Http\Requests\Simpeg\StoreSkpRequest;
use App\Http\Requests\Simpeg\SubmitRealisasiRequest;
use App\Models\Simpeg\PenilaianKinerja;
use App\Services\Simpeg\SkpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PenilaianKinerjaController extends Controller
{
    public function __construct(
        protected SkpService $skpService
    ) {}

    /**
     * Tampilkan daftar SKP & Penilaian Kinerja (Server-side Pagination & Filtering)
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasPermission('simpeg.kinerja.read') && 
            !$user->hasPermission('simpeg.kinerja.evaluate') && 
            !$user->hasPermission('simpeg.kinerja.manage') &&
            !$user->isAdmin()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses untuk melihat Penilaian Kinerja.',
            ], 403);
        }

        $result = $this->skpService->list($request->all(), $user);

        return response()->json([
            'status' => 'success',
            'data' => $result->items(),
            'meta' => [
                'current_page' => $result->currentPage(),
                'per_page' => $result->perPage(),
                'total' => $result->total(),
                'last_page' => $result->lastPage(),
            ],
        ]);
    }

    /**
     * Dapatkan master kategori SKP dan pejabat penilai
     */
    public function masters(): JsonResponse
    {
        $masters = $this->skpService->getMasters();

        return response()->json([
            'status' => 'success',
            'data' => $masters,
        ]);
    }

    /**
     * Detail SKP beserta butir items target dan realisasi
     */
    public function show(int $id, Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasPermission('simpeg.kinerja.read') && 
            !$user->hasPermission('simpeg.kinerja.evaluate') && 
            !$user->hasPermission('simpeg.kinerja.manage') &&
            !$user->isAdmin()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses melihat rincian SKP.',
            ], 403);
        }

        $skp = $this->skpService->getDetail($id);

        // Security check scoping untuk non-admin
        if (!$user->isAdmin() && !$user->hasPermission('simpeg.kinerja.manage')) {
            $userPegawaiId = $user->pegawai?->id;
            if ($skp->pegawai_id !== $userPegawaiId && $skp->pejabat_penilai_id !== $userPegawaiId) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Anda tidak memiliki wewenang mengakses dokumen SKP ini.',
                ], 403);
            }
        }

        return response()->json([
            'status' => 'success',
            'data' => $skp,
        ]);
    }

    /**
     * Simpan susunan Sasaran Kinerja Pegawai (SKP) baru
     */
    public function store(StoreSkpRequest $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasPermission('simpeg.kinerja.create') && 
            !$user->hasPermission('simpeg.kinerja.manage') && 
            !$user->isAdmin()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses menyusun SKP.',
            ], 403);
        }

        $skp = $this->skpService->createSkp($request->validated(), $user);

        return response()->json([
            'status' => 'success',
            'message' => 'Sasaran Kinerja Pegawai (SKP) berhasil dibuat (Draft).',
            'data' => $skp,
        ], 201);
    }

    /**
     * Perbarui draf Sasaran Kinerja Pegawai (SKP)
     */
    public function update(int $id, StoreSkpRequest $request): JsonResponse
    {
        $user = $request->user();
        $skp = PenilaianKinerja::findOrFail($id);

        if (!$user->isAdmin() && !$user->hasPermission('simpeg.kinerja.manage')) {
            if ($skp->pegawai_id !== $user->pegawai?->id) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Anda hanya dapat mengubah SKP milik Anda sendiri.',
                ], 403);
            }
            if ($skp->status !== 'draft') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Hanya SKP berstatus Draft yang dapat diperbarui susunan butirnya.',
                ], 422);
            }
        }

        $updated = $this->skpService->updateSkp($skp, $request->validated());

        return response()->json([
            'status' => 'success',
            'message' => 'Sasaran Kinerja Pegawai (SKP) berhasil diperbarui.',
            'data' => $updated,
        ]);
    }

    /**
     * Pegawai mengajukan target SKP ke atasan langsung / pejabat penilai
     */
    public function submitTarget(int $id, Request $request): JsonResponse
    {
        $user = $request->user();
        $skp = PenilaianKinerja::findOrFail($id);

        if (!$user->isAdmin() && !$user->hasPermission('simpeg.kinerja.manage')) {
            if ($skp->pegawai_id !== $user->pegawai?->id) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Anda hanya dapat mengajukan SKP milik Anda sendiri.',
                ], 403);
            }
        }

        if ($skp->status !== 'draft') {
            return response()->json([
                'status' => 'error',
                'message' => 'Hanya SKP berstatus Draft yang dapat diajukan.',
            ], 422);
        }

        try {
            $submitted = $this->skpService->submitTarget($skp);
            return response()->json([
                'status' => 'success',
                'message' => 'Sasaran kinerja berhasil diajukan kepada Pejabat Penilai.',
                'data' => $submitted,
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Pejabat penilai menyetujui target SKP
     */
    public function approveTarget(int $id, Request $request): JsonResponse
    {
        $user = $request->user();
        $skp = PenilaianKinerja::findOrFail($id);

        $canApprove = $user->isAdmin() || 
                      $user->hasPermission('simpeg.kinerja.evaluate') || 
                      $user->hasPermission('simpeg.kinerja.manage') ||
                      ($user->pegawai && $skp->pejabat_penilai_id === $user->pegawai->id);

        if (!$canApprove) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki wewenang menyetujui target SKP ini.',
            ], 403);
        }

        if ($skp->status !== 'diajukan') {
            return response()->json([
                'status' => 'error',
                'message' => 'Hanya SKP berstatus Diajukan yang dapat disetujui targetnya.',
            ], 422);
        }

        $approved = $this->skpService->approveTarget($skp);

        return response()->json([
            'status' => 'success',
            'message' => 'Target Sasaran Kinerja Pegawai berhasil disetujui.',
            'data' => $approved,
        ]);
    }

    /**
     * Pegawai mengisi realisasi capaian & bukti fisik luaran
     */
    public function submitRealisasi(int $id, SubmitRealisasiRequest $request): JsonResponse
    {
        $user = $request->user();
        $skp = PenilaianKinerja::findOrFail($id);

        if (!$user->isAdmin() && !$user->hasPermission('simpeg.kinerja.manage')) {
            if ($skp->pegawai_id !== $user->pegawai?->id) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Anda hanya dapat mengisikan realisasi SKP milik Anda sendiri.',
                ], 403);
            }
        }

        if ($skp->status === 'draft') {
            return response()->json([
                'status' => 'error',
                'message' => 'Target SKP harus disetujui terlebih dahulu sebelum mengisi realisasi.',
            ], 422);
        }

        $uploadedFiles = [];
        if ($request->has('items') && is_array($request->items)) {
            foreach ($request->items as $idx => $itemInput) {
                $fileKey = "items.{$idx}.berkas_bukti";
                if ($request->hasFile($fileKey)) {
                    $itemId = $itemInput['id'] ?? $idx;
                    $uploadedFiles[$itemId] = $request->file($fileKey);
                }
            }
        }

        $updated = $this->skpService->submitRealisasi($skp, $request->input('items', []), $uploadedFiles);

        return response()->json([
            'status' => 'success',
            'message' => 'Realisasi capaian dan bukti fisik luaran SKP berhasil disimpan.',
            'data' => $updated,
        ]);
    }

    /**
     * Evaluator / Atasan menilai SKP, BKD, dan menetapkan predikat akhir
     */
    public function evaluate(int $id, EvaluateSkpRequest $request): JsonResponse
    {
        $user = $request->user();
        $skp = PenilaianKinerja::findOrFail($id);

        $canEvaluate = $user->isAdmin() || 
                       $user->hasPermission('simpeg.kinerja.evaluate') || 
                       $user->hasPermission('simpeg.kinerja.manage') ||
                       ($user->pegawai && $skp->pejabat_penilai_id === $user->pegawai->id);

        if (!$canEvaluate) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses mengevaluasi capaian SKP ini.',
            ], 403);
        }

        $evaluated = $this->skpService->evaluate($skp, $request->validated(), $user);

        return response()->json([
            'status' => 'success',
            'message' => 'Evaluasi capaian kinerja SKP berhasil disimpan.',
            'data' => $evaluated,
        ]);
    }

    /**
     * Hapus berkas / dokumen SKP
     */
    public function destroy(int $id, Request $request): JsonResponse
    {
        $user = $request->user();
        $skp = PenilaianKinerja::findOrFail($id);

        if (!$user->isAdmin() && !$user->hasPermission('simpeg.kinerja.delete') && !$user->hasPermission('simpeg.kinerja.manage')) {
            if ($skp->pegawai_id !== $user->pegawai?->id) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Anda tidak memiliki wewenang menghapus SKP ini.',
                ], 403);
            }
            if ($skp->status !== 'draft') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Hanya SKP berstatus Draft yang dapat dihapus oleh pegawai.',
                ], 422);
            }
        }

        $this->skpService->deleteSkp($skp);

        return response()->json([
            'status' => 'success',
            'message' => 'Dokumen Sasaran Kinerja Pegawai (SKP) berhasil dihapus.',
        ]);
    }
}
