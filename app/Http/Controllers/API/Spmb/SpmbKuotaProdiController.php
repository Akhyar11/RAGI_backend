<?php

namespace App\Http\Controllers\API\Spmb;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Spmb\SpmbKuotaProdi;
use App\Services\Spmb\SpmbKuotaProdiService;
use App\Http\Requests\Spmb\StoreSpmbKuotaProdiRequest;
use App\Http\Requests\Spmb\UpdateSpmbKuotaProdiRequest;

class SpmbKuotaProdiController extends Controller
{
    public function __construct(protected SpmbKuotaProdiService $kuotaService) {}

    public function index(Request $request): JsonResponse
    {
        $query = SpmbKuotaProdi::with(['tahunAkademik', 'programStudi']);
        
        if ($request->filled('tahun_akademik_id')) {
            $query->where('tahun_akademik_id', $request->tahun_akademik_id);
        }

        if ($request->filled('program_studi_id')) {
            $query->where('program_studi_id', $request->program_studi_id);
        }

        if ($request->filled('status_kuota')) {
            if ($request->status_kuota === 'tersedia') {
                $query->whereRaw('(kuota_total - COALESCE(kuota_terisi, 0)) > 0');
            } elseif ($request->status_kuota === 'penuh') {
                $query->whereRaw('(kuota_total - COALESCE(kuota_terisi, 0)) <= 0');
            }
        }

        if ($request->filled('min_kuota')) {
            $query->where('kuota_total', '>=', (int) $request->min_kuota);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('programStudi', function ($q) use ($search) {
                $q->where('nama_prodi', 'like', "%{$search}%");
            });
        }

        $perPage = min(100, $request->integer('per_page', $request->integer('limit', 15)));
        $allowedSortColumns = ['id', 'tahun_akademik_id', 'program_studi_id', 'kuota_total', 'kuota_terisi', 'created_at'];
        $sortBy = in_array($request->sort_by, $allowedSortColumns) ? $request->sort_by : 'created_at';
        $sortOrder = $request->sort_order === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortBy, $sortOrder);

        $paginated = $query->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'message' => 'Data kuota prodi berhasil dimuat.',
            'data' => $paginated->items(),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
                'last_page' => $paginated->lastPage(),
                'from' => $paginated->firstItem(),
                'to' => $paginated->lastItem(),
            ],
            'filters' => [
                'search' => $request->search,
                'tahun_akademik_id' => $request->tahun_akademik_id,
                'program_studi_id' => $request->program_studi_id,
                'status_kuota' => $request->status_kuota,
                'min_kuota' => $request->min_kuota,
                'sort_by' => $sortBy,
                'sort_order' => $sortOrder,
            ],
        ]);
    }

    public function store(StoreSpmbKuotaProdiRequest $request): JsonResponse
    {
        $kuota = $this->kuotaService->upsert($request->validated());

        return response()->json([
            'status' => 'success',
            'message' => 'Kuota prodi berhasil disimpan.',
            'data' => $kuota
        ], 201);
    }

    public function show($id): JsonResponse
    {
        $kuota = SpmbKuotaProdi::with(['tahunAkademik', 'programStudi'])->findOrFail($id);
        return response()->json([
            'status' => 'success',
            'message' => 'Data detail kuota prodi berhasil dimuat.',
            'data' => $kuota
        ]);
    }

    public function update(UpdateSpmbKuotaProdiRequest $request, $id): JsonResponse
    {
        $kuota = SpmbKuotaProdi::findOrFail($id);
        $kuota = $this->kuotaService->update($kuota, $request->validated());

        return response()->json([
            'status' => 'success',
            'message' => 'Kuota prodi berhasil diperbarui.',
            'data' => $kuota
        ]);
    }

    public function destroy($id): JsonResponse
    {
        $kuota = SpmbKuotaProdi::findOrFail($id);
        $this->kuotaService->delete($kuota);

        return response()->json([
            'status' => 'success',
            'message' => 'Kuota prodi berhasil dihapus.',
            'data' => [
                'id' => (int) $id,
            ],
        ]);
    }
}
