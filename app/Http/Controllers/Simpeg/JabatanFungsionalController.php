<?php

namespace App\Http\Controllers\Simpeg;

use App\Http\Controllers\Controller;
use App\Models\Simpeg\JabatanFungsionalAkademik;
use App\Http\Requests\Simpeg\StoreJabatanFungsionalRequest;
use App\Services\Simpeg\JabatanFungsionalService;
use Illuminate\Http\Request;

class JabatanFungsionalController extends Controller
{
    public function __construct(
        private JabatanFungsionalService $jabatanFungsionalService
    ) {}

    public function index(Request $request)
    {
        $query = JabatanFungsionalAkademik::query();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                  ->orWhere('golongan', 'like', "%{$search}%");
            });
        }

        if ($request->filled('golongan')) {
            $query->where('golongan', $request->golongan);
        }

        $sortBy = in_array($request->sort_by, ['nama', 'golongan', 'angka_kredit_min', 'created_at'], true) ? $request->sort_by : 'nama';
        $sortDir = ($request->sort_dir === 'desc' || $request->sort_order === 'desc') ? 'desc' : 'asc';
        $query->orderBy($sortBy, $sortDir);

        if ($request->has('page') || $request->has('limit') || $request->has('per_page')) {
            $perPage = min(100, $request->integer('per_page', $request->integer('limit', 15)));
            $paginated = $query->paginate($perPage);
            return response()->json([
                'status' => 'success',
                'data' => $paginated->items(),
                'meta' => [
                    'current_page' => $paginated->currentPage(),
                    'per_page' => $paginated->perPage(),
                    'total' => $paginated->total(),
                    'last_page' => $paginated->lastPage(),
                    'from' => $paginated->firstItem() ?? 0,
                    'to' => $paginated->lastItem() ?? 0,
                ],
            ]);
        }

        return response()->json([
            'status' => 'success',
            'data' => $query->get(),
        ]);
    }

    public function store(StoreJabatanFungsionalRequest $request)
    {
        $jafung = $this->jabatanFungsionalService->create($request->validated());

        return response()->json([
            'status' => 'success',
            'message' => 'Jabatan Fungsional berhasil dibuat.',
            'data' => $jafung,
        ], 201);
    }

    /**
     * GET /api/v1/simpeg/jabatan-fungsional/master/golongan
     * Return distinct golongan values from master table with standard envelope.
     */
    public function getGolongan(Request $request)
    {
        $perPage = min(100, $request->integer('per_page', 15));
        $query = \App\Models\Simpeg\MasterGolonganPangkat::where('is_active', true);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                  ->orWhere('kode', 'like', "%{$search}%");
            });
        }

        $allowed = ['kode', 'nama', 'urutan', 'created_at'];
        $sortBy = in_array($request->sort_by, $allowed, true) ? $request->sort_by : 'urutan';
        $sortOrder = $request->sort_order === 'desc' ? 'desc' : 'asc';
        $query->orderBy($sortBy, $sortOrder)->orderBy('id', 'asc');

        $paginated = $query->paginate($perPage);

        $items = collect($paginated->items())->map(fn($item) => [
            'id' => $item->id,
            'value' => $item->kode,
            'label' => $item->nama,
            'pangkat' => $item->pangkat,
            'ruang' => $item->ruang,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar master golongan jabatan fungsional berhasil dimuat.',
            'data' => $items,
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
                'last_page' => $paginated->lastPage(),
                'from' => $paginated->firstItem() ?? 0,
                'to' => $paginated->lastItem() ?? 0,
            ],
            'filters' => [
                'search' => $request->get('search', ''),
                'sort_by' => $sortBy,
                'sort_order' => $sortOrder,
            ],
        ]);
    }
}
