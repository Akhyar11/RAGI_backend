<?php

namespace App\Http\Controllers\API\Spmb;

use App\Http\Controllers\Controller;
use App\Models\SpmbSekolahMitra; // I will just assume the model name based on standard
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class SpmbSekolahMitraController extends Controller
{
    /**
     * Get list of Sekolah Mitra
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min(100, $request->integer('per_page', 15));
        $query = DB::table('spmb_sekolah_mitra');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nama_sekolah', 'like', "%{$search}%")
                  ->orWhere('npsn', 'like', "%{$search}%");
            });
        }

        $allowedSortColumns = ['created_at', 'updated_at', 'nama_sekolah', 'npsn'];
        $sortBy = in_array($request->sort_by, $allowedSortColumns) ? $request->sort_by : 'created_at';
        $sortOrder = $request->sort_order === 'asc' ? 'asc' : 'desc';
        
        $query->orderBy($sortBy, $sortOrder);
        
        // Since we don't have the Model class guaranteed to exist right now, we use Query Builder pagination
        $data = $query->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'message' => 'Data retrieved successfully',
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
                'search' => $request->search,
                'sort_by' => $sortBy,
                'sort_order' => $sortOrder
            ]
        ]);
    }

    /**
     * Store new Sekolah Mitra
     */
    public function store(Request $request): JsonResponse
    {
        // Standarnya harus pakai Form Request, karena ini bypass sementara kita validate di sini atau buat FormRequest
        // Idealnya: public function store(StoreSekolahMitraRequest $request)
        $validated = $request->validate([
            'npsn' => 'nullable|string|max:20|unique:spmb_sekolah_mitra,npsn',
            'nama_sekolah' => 'required|string|max:255',
            'alamat' => 'nullable|string',
            'akreditasi' => 'nullable|string|max:10',
            'telepon' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'is_active' => 'boolean',
        ]);

        $validated['created_at'] = now();
        $validated['updated_at'] = now();

        $id = DB::table('spmb_sekolah_mitra')->insertGetId($validated);
        $data = DB::table('spmb_sekolah_mitra')->find($id);

        return response()->json([
            'status' => 'success',
            'message' => 'Sekolah mitra berhasil ditambahkan',
            'data' => $data
        ], 201);
    }
}
