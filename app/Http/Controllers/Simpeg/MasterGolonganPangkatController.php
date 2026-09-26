<?php

namespace App\Http\Controllers\Simpeg;

use App\Http\Controllers\Controller;
use App\Http\Requests\Simpeg\DestroyMasterGolonganPangkatRequest;
use App\Http\Requests\Simpeg\StoreMasterGolonganPangkatRequest;
use App\Http\Requests\Simpeg\UpdateMasterGolonganPangkatRequest;
use App\Models\Simpeg\MasterGolonganPangkat;
use App\Services\Simpeg\MasterGolonganPangkatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MasterGolonganPangkatController extends Controller
{
    public function __construct(
        private MasterGolonganPangkatService $masterGolonganPangkatService
    ) {}

    /**
     * Tampilkan daftar master jenjang golongan & pangkat dengan pagination dan filter.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user || (!$user->isAdmin() && !$user->hasRole('admin') && !$user->hasPermission('simpeg.read') && !$user->hasPermission('simpeg.jabatan.manage'))) {
            abort(403, 'Anda tidak memiliki hak untuk melihat data master golongan.');
        }

        $perPage = min(100, $request->integer('per_page', 15));

        $allowedSorts = ['created_at', 'updated_at', 'nama', 'kode', 'urutan', 'pangkat', 'ruang', 'is_active'];
        $sortBy = in_array($request->get('sort_by'), $allowedSorts, true) ? $request->get('sort_by') : 'created_at';
        $sortOrder = $request->get('sort_order') === 'asc' ? 'asc' : 'desc';

        $filters = [
            'search' => $request->get('search'),
            'is_active' => $request->get('is_active'),
            'ruang' => $request->get('ruang'),
            'sort_by' => $sortBy,
            'sort_order' => $sortOrder,
        ];

        $paginated = $this->masterGolonganPangkatService->list($filters, $perPage);

        return response()->json([
            'status' => 'success',
            'message' => 'Data master jenjang golongan & pangkat berhasil dimuat.',
            'data' => $paginated->items(),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
                'last_page' => $paginated->lastPage(),
                'from' => $paginated->firstItem() ?? 0,
                'to' => $paginated->lastItem() ?? 0,
            ],
            'filters' => [
                'search' => $filters['search'] ?? '',
                'sort_by' => $sortBy,
                'sort_order' => $sortOrder,
            ],
        ]);
    }

    /**
     * Simpan data master jenjang golongan baru.
     */
    public function store(StoreMasterGolonganPangkatRequest $request): JsonResponse
    {
        $data = $this->masterGolonganPangkatService->create($request->validated());

        return response()->json([
            'status' => 'success',
            'message' => 'Data master jenjang golongan berhasil dibuat.',
            'data' => $data,
        ], 201);
    }

    /**
     * Tampilkan detail data master jenjang golongan.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        if (!$user || (!$user->isAdmin() && !$user->hasRole('admin') && !$user->hasPermission('simpeg.read') && !$user->hasPermission('simpeg.jabatan.manage'))) {
            abort(403, 'Anda tidak memiliki hak untuk melihat detail master golongan.');
        }

        $data = MasterGolonganPangkat::findOrFail($id);

        return response()->json([
            'status' => 'success',
            'message' => 'Detail master jenjang golongan berhasil dimuat.',
            'data' => $data,
        ]);
    }

    /**
     * Perbarui data master jenjang golongan.
     */
    public function update(UpdateMasterGolonganPangkatRequest $request, int $id): JsonResponse
    {
        $golongan = MasterGolonganPangkat::findOrFail($id);
        $data = $this->masterGolonganPangkatService->update($golongan, $request->validated());

        return response()->json([
            'status' => 'success',
            'message' => 'Data master jenjang golongan berhasil diperbarui.',
            'data' => $data,
        ]);
    }

    /**
     * Hapus data master jenjang golongan (soft delete).
     */
    public function destroy(DestroyMasterGolonganPangkatRequest $request, int $id): JsonResponse
    {
        $golongan = MasterGolonganPangkat::findOrFail($id);
        $this->masterGolonganPangkatService->delete($golongan);

        return response()->json([
            'status' => 'success',
            'message' => 'Data master jenjang golongan berhasil dihapus.',
        ]);
    }
}
