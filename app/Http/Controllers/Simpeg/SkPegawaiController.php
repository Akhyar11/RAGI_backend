<?php

namespace App\Http\Controllers\Simpeg;

use App\Http\Controllers\Controller;
use App\Http\Requests\Simpeg\StoreSkPegawaiRequest;
use App\Http\Requests\Simpeg\VerifySkPegawaiRequest;
use App\Services\Simpeg\SkPegawaiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SkPegawaiController extends Controller
{
    public function __construct(
        protected SkPegawaiService $service
    ) {}

    /**
     * Dapatkan master referensi kategori SK pegawai
     */
    public function masters(): JsonResponse
    {
        $masters = $this->service->getMasters();

        return response()->json([
            'status' => 'success',
            'message' => 'Master kategori SK pegawai berhasil diambil',
            'data' => $masters,
        ]);
    }

    /**
     * Menampilkan daftar repositori SK pegawai
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasPermission('simpeg.sk_pegawai.read') && !$user->isAdmin()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses untuk melihat repositori SK pegawai.',
            ], 403);
        }

        $paginated = $this->service->list($request->all(), $user);

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar SK pegawai berhasil diambil',
            'data' => $paginated->items(),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
                'last_page' => $paginated->lastPage(),
                'from' => $paginated->firstItem(),
                'to' => $paginated->lastItem(),
            ],
        ]);
    }

    /**
     * Menampilkan detail rincian SK pegawai
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasPermission('simpeg.sk_pegawai.read') && !$user->isAdmin()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses untuk melihat rincian SK pegawai.',
            ], 403);
        }

        $sk = $this->service->getById($id, $user);

        return response()->json([
            'status' => 'success',
            'message' => 'Detail SK pegawai berhasil diambil',
            'data' => $sk,
        ]);
    }

    /**
     * Mengunggah / melaporkan SK pegawai baru
     */
    public function store(StoreSkPegawaiRequest $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasPermission('simpeg.sk_pegawai.create') && !$user->isAdmin()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses untuk melaporkan SK pegawai.',
            ], 403);
        }

        $validated = $request->validated();
        $fileSk = $request->file('file_sk');
        unset($validated['file_sk']);

        $sk = $this->service->create($validated, $fileSk, $user);

        return response()->json([
            'status' => 'success',
            'message' => 'Laporan SK pegawai berhasil disimpan',
            'data' => $sk,
        ], 201);
    }

    /**
     * Memperbarui data SK pegawai
     */
    public function update(StoreSkPegawaiRequest $request, string $id): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasPermission('simpeg.sk_pegawai.update') && !$user->isAdmin()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses untuk mengubah SK pegawai.',
            ], 403);
        }

        $validated = $request->validated();
        $fileSk = $request->file('file_sk');
        unset($validated['file_sk']);

        $updated = $this->service->update($id, $validated, $fileSk, $user);

        return response()->json([
            'status' => 'success',
            'message' => 'Data SK pegawai berhasil diperbarui',
            'data' => $updated,
        ]);
    }

    /**
     * Memverifikasi atau menolak SK pegawai oleh Tim HR / Verifikator
     */
    public function verify(VerifySkPegawaiRequest $request, string $id): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasPermission('simpeg.sk_pegawai.verify') && !$user->isAdmin()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses untuk memverifikasi SK pegawai.',
            ], 403);
        }

        $verified = $this->service->verify($id, $request->validated(), $user);

        return response()->json([
            'status' => 'success',
            'message' => 'Status verifikasi SK pegawai berhasil diperbarui',
            'data' => $verified,
        ]);
    }

    /**
     * Menghapus SK pegawai
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasPermission('simpeg.sk_pegawai.delete') && !$user->isAdmin()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses untuk menghapus SK pegawai.',
            ], 403);
        }

        $this->service->delete($id, $user);

        return response()->json([
            'status' => 'success',
            'message' => 'Arsip SK pegawai berhasil dihapus',
        ]);
    }
}
