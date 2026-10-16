<?php

namespace App\Http\Controllers\Sikeu;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sikeu\StoreUmkRequest;
use App\Http\Requests\Sikeu\UpdateUmkRequest;
use App\Models\Sikeu\UangMukaKerja;
use App\Services\Sikeu\UmkService;
use Illuminate\Http\Request;

/**
 * Uang Muka Kerja (UMK) SIKEU — parity legacy transaksi_umk.
 *
 * Aksi dikendalikan permission RBAC existing:
 * - sikeu.kaskecil.read     : list/detail
 * - sikeu.kaskecil.pengajuan: create/update
 * - sikeu.kaskecil.approve  : status selesai/aktif + delete
 */
class UmkController extends Controller
{
    public function __construct(private UmkService $service) {}

    public function index(Request $request)
    {
        $user = $request->user();
        if (!$user->isSuperAdmin() && !$user->isAdmin() && !$user->hasPermission('sikeu.kaskecil.read')) {
            return response()->json(['status' => 'error', 'message' => 'Anda tidak memiliki akses UMK.'], 403);
        }

        $filters = $request->only(['search', 'status', 'unit_kas_id', 'tanggal_awal', 'tanggal_akhir', 'sort_by', 'sort_order']);
        $perPage = min(100, $request->integer('per_page', 15));
        $data = $this->service->index($filters, $perPage);

        return response()->json([
            'status' => 'success',
            'message' => 'Data UMK berhasil dimuat',
            'data' => $data->items(),
            'meta' => [
                'current_page' => $data->currentPage(),
                'per_page' => $data->perPage(),
                'total' => $data->total(),
                'last_page' => $data->lastPage(),
                'from' => $data->firstItem(),
                'to' => $data->lastItem(),
            ],
            'filters' => [
                'search' => $request->query('search'),
                'sort_by' => $request->query('sort_by', 'tanggal'),
                'sort_order' => $request->query('sort_order', 'desc'),
            ],
        ]);
    }

    public function show(Request $request, $id)
    {
        $user = $request->user();
        if (!$user->isSuperAdmin() && !$user->isAdmin() && !$user->hasPermission('sikeu.kaskecil.read')) {
            return response()->json(['status' => 'error', 'message' => 'Anda tidak memiliki akses UMK.'], 403);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Detail UMK berhasil dimuat',
            'data' => UangMukaKerja::with('unitKas:id,nama_kas,tipe_kas')->findOrFail($id),
        ]);
    }

    public function store(StoreUmkRequest $request)
    {
        $user = $request->user();
        if (!$user->isSuperAdmin() && !$user->isAdmin() && !$user->hasPermission('sikeu.kaskecil.pengajuan')) {
            return response()->json(['status' => 'error', 'message' => 'Anda tidak memiliki izin mencatat UMK.'], 403);
        }

        try {
            $umk = $this->service->store($request->validated(), $request);

            return response()->json([
                'status' => 'success',
                'message' => 'UMK berhasil dicatat',
                'data' => $umk,
            ], 201);
        } catch (\RuntimeException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }
    }

    public function update(UpdateUmkRequest $request, $id)
    {
        $user = $request->user();
        if (!$user->isSuperAdmin() && !$user->isAdmin() && !$user->hasPermission('sikeu.kaskecil.pengajuan')) {
            return response()->json(['status' => 'error', 'message' => 'Anda tidak memiliki izin mengubah UMK.'], 403);
        }

        try {
            $umk = $this->service->update(UangMukaKerja::findOrFail($id), $request->validated());

            return response()->json([
                'status' => 'success',
                'message' => 'UMK berhasil diperbarui',
                'data' => $umk,
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }
    }

    public function updateStatus(Request $request, $id)
    {
        $user = $request->user();
        if (!$user->isSuperAdmin() && !$user->isAdmin() && !$user->hasPermission('sikeu.kaskecil.approve')) {
            return response()->json(['status' => 'error', 'message' => 'Anda tidak memiliki izin menyelesaikan UMK.'], 403);
        }

        $validated = $request->validate([
            'status' => 'required|in:aktif,selesai',
            'tanggal_selesai' => 'nullable|date|before_or_equal:today',
        ]);

        try {
            $umk = $this->service->updateStatus(
                UangMukaKerja::findOrFail($id),
                $validated['status'],
                $validated['tanggal_selesai'] ?? null
            );

            return response()->json([
                'status' => 'success',
                'message' => 'Status UMK berhasil diperbarui',
                'data' => $umk,
            ]);
        } catch (\RuntimeException | \InvalidArgumentException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }
    }

    public function destroy(Request $request, $id)
    {
        $user = $request->user();
        if (!$user->isSuperAdmin() && !$user->isAdmin() && !$user->hasPermission('sikeu.kaskecil.approve')) {
            return response()->json(['status' => 'error', 'message' => 'Anda tidak memiliki izin menghapus UMK.'], 403);
        }

        try {
            $this->service->destroy(UangMukaKerja::findOrFail($id));

            return response()->json([
                'status' => 'success',
                'message' => 'UMK berhasil dihapus',
                'data' => null,
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }
    }
}
