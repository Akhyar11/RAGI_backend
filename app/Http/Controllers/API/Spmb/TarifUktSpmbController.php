<?php

namespace App\Http\Controllers\API\Spmb;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Spmb\TarifUktSpmb;

class TarifUktSpmbController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = TarifUktSpmb::with(['programStudi', 'tahunAkademik']);

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->whereHas('programStudi', fn($q) => $q->where('nama', 'like', "%{$search}%"))
                  ->orWhere('kelompok_ukt', 'like', "%{$search}%");
        }

        if ($request->filled('program_studi_id')) {
            $query->where('program_studi_id', $request->input('program_studi_id'));
        }

        if ($request->filled('tahun_akademik_id')) {
            $query->where('tahun_akademik_id', $request->input('tahun_akademik_id'));
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        $sortBy  = in_array($request->input('sort_by'), ['id', 'kelompok_ukt', 'nominal', 'created_at']) ? $request->input('sort_by') : 'created_at';
        $sortDir = strtolower($request->input('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortBy, $sortDir);

        $limit     = (int) $request->input('limit', 15);
        $paginated = $query->paginate($limit);

        return response()->json([
            'status' => 'success',
            'data'   => $paginated->items(),
            'meta'   => [
                'current_page' => $paginated->currentPage(),
                'last_page'    => $paginated->lastPage(),
                'per_page'     => $paginated->perPage(),
                'total'        => $paginated->total(),
                'from'         => $paginated->firstItem(),
                'to'           => $paginated->lastItem(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'program_studi_id'  => 'required|exists:master_program_studi,id',
            'tahun_akademik_id' => 'required|exists:master_tahun_akademik,id',
            'kelompok_ukt'      => 'required|string|max:100',
            'nominal'           => 'required|numeric|min:0',
            'is_active'         => 'boolean',
        ]);

        $tarif = TarifUktSpmb::create($validated);
        $tarif->load(['programStudi', 'tahunAkademik']);

        return response()->json([
            'status'  => 'success',
            'message' => 'Tarif UKT berhasil ditambahkan.',
            'data'    => $tarif,
        ], 201);
    }

    public function show($id): JsonResponse
    {
        $tarif = TarifUktSpmb::with(['programStudi', 'tahunAkademik'])->findOrFail($id);
        return response()->json(['status' => 'success', 'data' => $tarif]);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $tarif = TarifUktSpmb::findOrFail($id);

        $validated = $request->validate([
            'program_studi_id'  => 'required|exists:master_program_studi,id',
            'tahun_akademik_id' => 'required|exists:master_tahun_akademik,id',
            'kelompok_ukt'      => 'required|string|max:100',
            'nominal'           => 'required|numeric|min:0',
            'is_active'         => 'boolean',
        ]);

        $tarif->update($validated);
        $tarif->load(['programStudi', 'tahunAkademik']);

        return response()->json([
            'status'  => 'success',
            'message' => 'Tarif UKT berhasil diperbarui.',
            'data'    => $tarif,
        ]);
    }

    public function destroy($id): JsonResponse
    {
        $tarif = TarifUktSpmb::findOrFail($id);
        $tarif->delete();

        return response()->json(['status' => 'success', 'message' => 'Tarif UKT berhasil dihapus.']);
    }
}
