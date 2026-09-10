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
        $query = TarifUktSpmb::with(['programStudi', 'masterSikeuBiaya']);

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                  ->orWhereHas('masterSikeuBiaya', fn($q) => $q->where('nama', 'like', "%{$search}%"))
                  ->orWhereHas('programStudi', fn($q) => $q->where('nama', 'like', "%{$search}%"));
            });
        }

        if ($request->filled('master_program_studi_id')) {
            $query->where('master_program_studi_id', $request->input('master_program_studi_id'));
        }

        if ($request->filled('master_sikeu_biaya_id')) {
            $query->where('master_sikeu_biaya_id', $request->input('master_sikeu_biaya_id'));
        }

        $sortBy  = in_array($request->input('sort_by'), ['id', 'nama', 'created_at']) ? $request->input('sort_by') : 'created_at';
        $sortDir = strtolower($request->input('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortBy, $sortDir);

        $limit     = min(100, (int) $request->input('per_page', 15));
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
            'nama'                   => 'required|string|max:255',
            'deskripsi'              => 'nullable|string',
            'master_sikeu_biaya_id'  => 'required|exists:sikeu_master_biaya,id',
            'master_program_studi_id'=> 'required|exists:spmb_master_program_studi,id',
        ]);

        $tarif = TarifUktSpmb::create($validated);
        $tarif->load(['programStudi', 'masterSikeuBiaya']);

        return response()->json([
            'status'  => 'success',
            'message' => 'Biaya daftar ulang berhasil ditambahkan.',
            'data'    => $tarif,
        ], 201);
    }

    public function show($id): JsonResponse
    {
        $tarif = TarifUktSpmb::with(['programStudi', 'masterSikeuBiaya'])->findOrFail($id);
        return response()->json(['status' => 'success', 'data' => $tarif]);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $tarif = TarifUktSpmb::findOrFail($id);

        $validated = $request->validate([
            'nama'                   => 'required|string|max:255',
            'deskripsi'              => 'nullable|string',
            'master_sikeu_biaya_id'  => 'required|exists:sikeu_master_biaya,id',
            'master_program_studi_id'=> 'required|exists:spmb_master_program_studi,id',
        ]);

        $tarif->update($validated);
        $tarif->load(['programStudi', 'masterSikeuBiaya']);

        return response()->json([
            'status'  => 'success',
            'message' => 'Biaya daftar ulang berhasil diperbarui.',
            'data'    => $tarif,
        ]);
    }

    public function destroy($id): JsonResponse
    {
        $tarif = TarifUktSpmb::findOrFail($id);
        $tarif->delete();

        return response()->json(['status' => 'success', 'message' => 'Biaya daftar ulang berhasil dihapus.']);
    }
}