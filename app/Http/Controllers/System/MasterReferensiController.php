<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Models\System\MasterReferensi;
use App\Models\System\MasterTipeReferensi;
use App\Http\Requests\System\StoreMasterReferensiRequest;
use App\Http\Requests\System\UpdateMasterReferensiRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class MasterReferensiController extends Controller
{
    /**
     * Display a listing of the resource with filters.
     */
    public function index(Request $request): JsonResponse
    {
        $query = MasterReferensi::query();

        if ($request->filled('modul') && $request->modul !== 'all') {
            $modul = $request->modul;
            $query->where(function ($q) use ($modul) {
                $q->where('modul', $modul)
                  ->orWhere('modul', 'global');
            });
        }

        if ($request->filled('tipe') && $request->tipe !== 'all') {
            $query->where('tipe', $request->tipe);
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                  ->orWhere('kode', 'like', "%{$search}%")
                  ->orWhere('tipe', 'like', "%{$search}%");
            });
        }

        $query->orderBy('tipe', 'asc')
              ->orderBy('urutan', 'asc')
              ->orderBy('id', 'asc');

        if ($request->has('page') || $request->filled('per_page') || $request->filled('limit')) {
            $perPage = (int) $request->input('per_page', $request->input('limit', 20));
            $data = $query->paginate($perPage);
        } else {
            $data = $query->get();
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar master referensi berhasil diambil.',
            'data' => $data,
        ]);
    }

    /**
     * Get distinct categories (tipes) and modules for filter tabs.
     */
    public function getCategories(Request $request): JsonResponse
    {
        $query = MasterTipeReferensi::withCount('items')
            ->where('is_active', true);

        if ($request->filled('modul') && $request->modul !== 'all') {
            $query->forModule($request->modul);
        }

        $tipes = $query->orderBy('urutan')->orderBy('nama')->get();

        $categories = $tipes->map(function ($t) {
            return [
                'tipe' => $t->kode,
                'nama' => $t->nama,
                'modul' => $t->modul,
                'deskripsi' => $t->deskripsi,
                'total_items' => $t->items_count,
            ];
        });

        $modules = MasterReferensi::select('modul')
            ->selectRaw('COUNT(*) as total_items')
            ->groupBy('modul')
            ->orderBy('modul', 'asc')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => [
                'categories' => $categories,
                'modules' => $modules,
            ],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreMasterReferensiRequest $request): JsonResponse
    {
        $validated = $request->validated();
        
        if (!isset($validated['modul']) || empty($validated['modul'])) {
            $validated['modul'] = 'global';
        }

        if (!isset($validated['urutan'])) {
            $maxUrutan = MasterReferensi::where('tipe', $validated['tipe'])->max('urutan') ?? 0;
            $validated['urutan'] = $maxUrutan + 1;
        }

        if (!isset($validated['is_active'])) {
            $validated['is_active'] = true;
        }

        $referensi = MasterReferensi::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Data referensi berhasil ditambahkan.',
            'data' => $referensi,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show($id): JsonResponse
    {
        $referensi = MasterReferensi::findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => $referensi,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateMasterReferensiRequest $request, $id): JsonResponse
    {
        $referensi = MasterReferensi::findOrFail($id);
        $referensi->update($request->validated());

        return response()->json([
            'status' => 'success',
            'message' => 'Data referensi berhasil diperbarui.',
            'data' => $referensi,
        ]);
    }

    /**
     * Toggle active status.
     */
    public function toggleActive($id): JsonResponse
    {
        $referensi = MasterReferensi::findOrFail($id);
        $referensi->is_active = !$referensi->is_active;
        $referensi->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Status referensi berhasil diubah.',
            'data' => $referensi,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id): JsonResponse
    {
        $referensi = MasterReferensi::findOrFail($id);
        $referensi->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Data referensi berhasil dihapus.',
        ]);
    }

    /**
     * Public / Authenticated dropdown options provider by tipe.
     */
    public function getByTipe(Request $request, $tipe): JsonResponse
    {
        $query = MasterReferensi::where('tipe', $tipe)
            ->where('is_active', true);

        if ($request->filled('modul') && $request->modul !== 'all') {
            $modul = $request->modul;
            $query->where(function ($q) use ($modul) {
                $q->where('modul', $modul)
                  ->orWhere('modul', 'global');
            });
        }

        $data = $query->orderBy('urutan', 'asc')
                      ->orderBy('id', 'asc')
                      ->get();

        return response()->json([
            'status' => 'success',
            'data' => $data,
        ]);
    }
}
