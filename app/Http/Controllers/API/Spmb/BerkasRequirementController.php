<?php

namespace App\Http\Controllers\API\Spmb;

use App\Http\Controllers\Controller;
use App\Models\Spmb\BerkasRequirement;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

class BerkasRequirementController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = BerkasRequirement::with('jalurMasuk');

        if ($request->has('jalur_masuk_id') && $request->jalur_masuk_id) {
            $query->where('jalur_masuk_id', $request->jalur_masuk_id);
        }

        if ($request->has('search') && $request->search) {
            $query->where('label', 'like', '%' . $request->search . '%');
        }

        $limit = $request->input('limit', 10);
        
        $data = $query->orderBy('jalur_masuk_id')->orderBy('urutan')->paginate($limit);

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
            'jalur_masuk_id' => 'required|exists:jalur_masuk,id',
            'jenis_dokumen' => [
                'required',
                'string',
                Rule::exists('master_referensi', 'kode')->where(function ($query) {
                    return $query->where('tipe', 'jenis_dokumen');
                }),
            ],
            'label' => 'required|string|max:255',
            'wajib' => 'required|boolean',
            'urutan' => 'required|integer',
            'is_active' => 'required|boolean',
        ]);

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
            'jalur_masuk_id' => 'required|exists:jalur_masuk,id',
            'jenis_dokumen' => [
                'required',
                'string',
                Rule::exists('master_referensi', 'kode')->where(function ($query) {
                    return $query->where('tipe', 'jenis_dokumen');
                }),
            ],
            'label' => 'required|string|max:255',
            'wajib' => 'required|boolean',
            'urutan' => 'required|integer',
            'is_active' => 'required|boolean',
        ]);

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
