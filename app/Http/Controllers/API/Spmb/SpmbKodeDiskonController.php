<?php

namespace App\Http\Controllers\API\Spmb;

use App\Http\Controllers\Controller;
use App\Http\Requests\Spmb\StoreSpmbKodeDiskonRequest;
use App\Http\Requests\Spmb\UpdateSpmbKodeDiskonRequest;
use App\Http\Requests\Spmb\ValidateSpmbKodeDiskonRequest;
use App\Models\Spmb\PendaftaranCalonMhs;
use App\Models\Spmb\SpmbKodeDiskon;
use App\Services\Spmb\SpmbKodeDiskonService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SpmbKodeDiskonController extends Controller
{
    public function __construct(private readonly SpmbKodeDiskonService $service) {}

    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()?->hasPermission('spmb.manage'), 403, 'Akses ditolak.');

        $query = SpmbKodeDiskon::query()->withCount('penggunaan');

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(fn ($q) => $q->where('kode', 'like', "%{$search}%")
                ->orWhere('nama', 'like', "%{$search}%"));
        }

        if ($request->filled('kode')) {
            $query->where('kode', 'like', '%'.$request->input('kode').'%');
        }
        if ($request->filled('nama')) {
            $query->where('nama', 'like', '%'.$request->input('nama').'%');
        }

        if ($request->has('is_active') && $request->input('is_active') !== '') {
            $query->where('is_active', filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->filled('tipe_diskon')) {
            $query->where('tipe_diskon', $request->input('tipe_diskon'));
        }
        if ($request->filled('nilai_min')) {
            $query->where('nilai', '>=', (float) $request->input('nilai_min'));
        }
        if ($request->filled('nilai_max')) {
            $query->where('nilai', '<=', (float) $request->input('nilai_max'));
        }
        if ($request->filled('maksimum_potongan_min')) {
            $query->where('maksimum_potongan', '>=', (float) $request->input('maksimum_potongan_min'));
        }
        if ($request->filled('maksimum_potongan_max')) {
            $query->where('maksimum_potongan', '<=', (float) $request->input('maksimum_potongan_max'));
        }
        if ($request->input('potongan_status') === 'berbatas') {
            $query->whereNotNull('maksimum_potongan');
        } elseif ($request->input('potongan_status') === 'tanpa_batas') {
            $query->whereNull('maksimum_potongan');
        }

        $kuotaStatus = $request->input('kuota_status');
        $usageCountSql = '(SELECT COUNT(*) FROM spmb_kode_diskon_penggunaan WHERE spmb_kode_diskon_penggunaan.kode_diskon_id = spmb_kode_diskon.id)';
        if ($kuotaStatus === 'tersedia') {
            $query->whereNotNull('batas_penggunaan')
                ->whereRaw($usageCountSql.' < spmb_kode_diskon.batas_penggunaan');
        } elseif ($kuotaStatus === 'penuh') {
            $query->whereNotNull('batas_penggunaan')
                ->whereRaw($usageCountSql.' >= spmb_kode_diskon.batas_penggunaan');
        } elseif ($kuotaStatus === 'tanpa_batas') {
            $query->whereNull('batas_penggunaan');
        }

        foreach (['berlaku_mulai', 'berlaku_sampai'] as $dateFilter) {
            if ($request->filled($dateFilter)) {
                $query->whereDate($dateFilter, $request->input($dateFilter));
            }
        }

        $allowedSorts = ['kode', 'nama', 'tipe_diskon', 'nilai', 'batas_penggunaan', 'berlaku_mulai', 'berlaku_sampai', 'is_active', 'created_at', 'updated_at'];
        $sortBy = in_array($request->input('sort_by'), $allowedSorts, true) ? $request->input('sort_by') : 'created_at';
        $sortOrder = $request->sort_order === 'asc' ? 'asc' : 'desc';
        $perPage = min(100, $request->integer('per_page', 15));
        $paginated = $query->orderBy($sortBy, $sortOrder)->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar kode diskon berhasil dimuat.',
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
                    'is_active' => $request->input('is_active'),
                    'tipe_diskon' => $request->input('tipe_diskon'),
                    'nilai_min' => $request->input('nilai_min'),
                    'nilai_max' => $request->input('nilai_max'),
                    'maksimum_potongan_min' => $request->input('maksimum_potongan_min'),
                    'maksimum_potongan_max' => $request->input('maksimum_potongan_max'),
                    'potongan_status' => $request->input('potongan_status'),
                    'kuota_status' => $kuotaStatus,
                    'berlaku_mulai' => $request->input('berlaku_mulai'),
                    'berlaku_sampai' => $request->input('berlaku_sampai'),
                    'sort_by' => $sortBy,
                    'sort_order' => $sortOrder,
                ],
            ],
        ]);
    }

    public function store(StoreSpmbKodeDiskonRequest $request): JsonResponse
    {
        $discountCode = $this->service->create($request->validated());

        return response()->json([
            'status' => 'success',
            'message' => 'Kode diskon berhasil dibuat.',
            'data' => $discountCode,
        ], 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        abort_unless($request->user()?->hasPermission('spmb.manage'), 403, 'Akses ditolak.');

        $discountCode = SpmbKodeDiskon::withCount('penggunaan')->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'message' => 'Detail kode diskon berhasil dimuat.',
            'data' => $discountCode,
        ]);
    }

    public function update(UpdateSpmbKodeDiskonRequest $request, int $id): JsonResponse
    {
        abort_unless($request->user()?->hasPermission('spmb.manage'), 403, 'Akses ditolak.');

        $discountCode = SpmbKodeDiskon::findOrFail($id);
        $updated = $this->service->update($discountCode, $request->validated());

        return response()->json([
            'status' => 'success',
            'message' => 'Kode diskon berhasil diperbarui.',
            'data' => $updated,
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        abort_unless($request->user()?->hasPermission('spmb.manage'), 403, 'Akses ditolak.');

        $discountCode = SpmbKodeDiskon::findOrFail($id);
        $this->service->delete($discountCode);

        return response()->json([
            'status' => 'success',
            'message' => 'Kode diskon berhasil dinonaktifkan dengan soft delete.',
            'data' => null,
        ]);
    }

    public function validateCode(ValidateSpmbKodeDiskonRequest $request): JsonResponse
    {
        $pendaftaran = PendaftaranCalonMhs::with('kodeDiskonUsage.kodeDiskon')
            ->where('user_id', $request->user()->id)
            ->first();

        $preview = $this->service->preview(
            $request->validated('kode'),
            (int) $request->validated('master_tipe_jalur_id'),
            (int) $request->validated('program_studi_id'),
            (int) $request->validated('gelombang_id'),
            $pendaftaran,
        );

        return response()->json([
            'status' => 'success',
            'message' => $preview['already_applied'] ? 'Kode diskon sudah diterapkan pada tagihan pendaftaran.' : 'Kode diskon valid.',
            'data' => $preview,
        ]);
    }
}
