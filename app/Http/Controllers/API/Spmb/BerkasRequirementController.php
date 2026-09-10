<?php

namespace App\Http\Controllers\API\Spmb;

use App\Http\Controllers\Controller;
use App\Models\Spmb\BerkasRequirement;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class BerkasRequirementController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = BerkasRequirement::with('jalurMasuk');

        if ($request->filled('jalur_masuk_id')) {
            $query->where('jalur_masuk_id', $request->jalur_masuk_id);
        }

        if ($request->has('is_active') && $request->is_active !== '') {
            $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->filled('search')) {
            $query->where('label', 'like', '%' . $request->search . '%');
        }

        $perPage = min(100, $request->integer('per_page', $request->integer('limit', 10)));

        $allowedSortColumns = ['label', 'jalur_masuk_id', 'urutan', 'is_active', 'created_at'];
        $sortBy = in_array($request->sort_by, $allowedSortColumns) ? $request->sort_by : 'urutan';
        $sortDir = strtolower((string) ($request->sort_dir ?? $request->sort_order)) === 'desc' ? 'desc' : 'asc';

        $data = $query->orderBy($sortBy, $sortDir)->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'data' => $data->items(),
            'meta' => [
                'current_page' => $data->currentPage(),
                'last_page' => $data->lastPage(),
                'per_page' => $data->perPage(),
                'total' => $data->total(),
            ]
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'jalur_masuk_id' => 'required|exists:spmb_jalur_masuk,id',
            'label' => 'required|string|max:255',
            'wajib' => 'required|boolean',
            'urutan' => 'nullable|integer|min:0',
            'is_active' => 'required|boolean',
        ]);

        // Auto-generate jenis_dokumen dari label jika tidak dikirim
        $validated['jenis_dokumen'] = $request->input('jenis_dokumen') ?: Str::slug($validated['label']);

        // Auto-fill urutan tampil jika tidak dikirim: max urutan pada jalur + 1
        if (!isset($validated['urutan']) || $validated['urutan'] === null) {
            $validated['urutan'] = (BerkasRequirement::where('jalur_masuk_id', $validated['jalur_masuk_id'])->max('urutan') ?? 0) + 1;
        }

        $berkas = BerkasRequirement::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Berkas requirement berhasil ditambahkan.',
            'data' => $berkas
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show($id): JsonResponse
    {
        $berkas = BerkasRequirement::findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => $berkas
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id): JsonResponse
    {
        $berkas = BerkasRequirement::findOrFail($id);

        $validated = $request->validate([
            'jalur_masuk_id' => 'required|exists:spmb_jalur_masuk,id',
            'label' => 'required|string|max:255',
            'wajib' => 'required|boolean',
            'urutan' => 'sometimes|integer|min:0',
            'is_active' => 'required|boolean',
        ]);

        // Auto-generate jenis_dokumen dari label jika tidak dikirim
        $validated['jenis_dokumen'] = $request->input('jenis_dokumen') ?: Str::slug($validated['label']);

        $berkas->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Berkas requirement berhasil diperbarui.',
            'data' => $berkas
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id): JsonResponse
    {
        $berkas = BerkasRequirement::findOrFail($id);
        $berkas->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Berkas requirement berhasil dihapus.'
        ]);
    }
}
