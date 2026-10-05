<?php

namespace App\Http\Controllers\API\Spmb;

use App\Http\Controllers\Controller;
use App\Http\Requests\Spmb\StorePotonganCalonRequest;
use App\Http\Requests\Spmb\UpdatePotonganCalonRequest;
use App\Models\Spmb\PendaftaranCalonMhs;
use App\Models\Spmb\PotonganCalon;
use App\Services\Spmb\SpmbPotonganCalonService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PotonganCalonController extends Controller
{
    public function __construct(
        private readonly SpmbPotonganCalonService $service
    ) {}

    /**
     * Daftar potongan kustom milik satu pendaftaran (calon mahasiswa).
     */
    public function index(Request $request, $id): JsonResponse
    {
        $pendaftaran = PendaftaranCalonMhs::findOrFail($id);

        $query = PotonganCalon::with(['komponenBiaya:id,kode,nama', 'pembuat:id,name,username'])
            ->where('pendaftaran_id', $pendaftaran->id);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('tahap')) {
            $query->untukTahap($request->input('tahap'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('nama_potongan', 'like', "%{$search}%")
                  ->orWhere('nomor_sk', 'like', "%{$search}%");
            });
        }

        $allowedSorts = ['created_at', 'updated_at', 'nama_potongan', 'nilai_potongan', 'id'];
        $sortBy = in_array($request->input('sort_by'), $allowedSorts, true) ? $request->input('sort_by') : 'created_at';
        $sortOrder = strtolower((string) $request->input('sort_order', 'desc')) === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortBy, $sortOrder);

        $perPage = min(100, $request->integer('per_page', 15));
        $paginated = $query->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'message' => 'Data potongan calon berhasil dimuat.',
            'data' => $paginated->items(),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
                'last_page' => $paginated->lastPage(),
                'from' => $paginated->firstItem(),
                'to' => $paginated->lastItem(),
                'filters' => [
                    'search' => $request->input('search'),
                    'status' => $request->input('status'),
                    'tahap' => $request->input('tahap'),
                    'sort_by' => $sortBy,
                    'sort_order' => $sortOrder,
                ],
            ],
        ]);
    }

    public function store(StorePotonganCalonRequest $request, $id): JsonResponse
    {
        $pendaftaran = PendaftaranCalonMhs::findOrFail($id);
        $potongan = $this->service->create($pendaftaran, $request->validated());

        return response()->json([
            'status' => 'success',
            'message' => 'Potongan calon berhasil ditambahkan.',
            'data' => $potongan,
        ], 201);
    }

    public function update(UpdatePotonganCalonRequest $request, $id): JsonResponse
    {
        $potongan = PotonganCalon::findOrFail($id);
        $updated = $this->service->update($potongan, $request->validated());

        return response()->json([
            'status' => 'success',
            'message' => 'Potongan calon berhasil diperbarui.',
            'data' => $updated,
        ]);
    }

    public function destroy(Request $request, $id): JsonResponse
    {
        if (! $request->user()?->hasPermission('spmb.potongan.delete')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses untuk menghapus potongan.',
            ], 403);
        }

        $potongan = PotonganCalon::findOrFail($id);
        $this->service->delete($potongan);

        return response()->json([
            'status' => 'success',
            'message' => 'Potongan calon berhasil dihapus (soft delete).',
            'data' => null,
        ]);
    }
}
