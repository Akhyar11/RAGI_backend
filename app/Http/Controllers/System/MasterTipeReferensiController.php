<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Http\Requests\System\StoreMasterTipeReferensiRequest;
use App\Http\Requests\System\UpdateMasterTipeReferensiRequest;
use App\Models\System\MasterTipeReferensi;
use App\Models\System\MasterReferensi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MasterTipeReferensiController extends Controller
{
    /**
     * Display a listing of master tipe referensi.
     */
    public function index(Request $request): JsonResponse
    {
        $query = MasterTipeReferensi::withCount('items');

        if ($request->filled('modul') && $request->modul !== 'all') {
            $query->forModule($request->modul);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('kode', 'like', "%{$search}%")
                  ->orWhere('nama', 'like', "%{$search}%")
                  ->orWhere('deskripsi', 'like', "%{$search}%");
            });
        }

        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }

        $query->orderBy('urutan')
            ->orderBy('id');

        if ($request->has('page') || $request->filled('per_page') || $request->filled('limit')) {
            $perPage = (int) $request->input('per_page', $request->input('limit', 20));
            $items = $query->paginate($perPage);
        } else {
            $items = $query->get();
        }

        return response()->json([
            'status' => 'success',
            'data' => $items,
        ]);
    }

    /**
     * Store a newly created master tipe referensi.
     */
    public function store(StoreMasterTipeReferensiRequest $request): JsonResponse
    {
        $data = $request->validated();
        if (!isset($data['urutan'])) {
            $data['urutan'] = (MasterTipeReferensi::max('urutan') ?? 0) + 1;
        }

        $tipe = MasterTipeReferensi::create($data);

        return response()->json([
            'status' => 'success',
            'message' => 'Tipe referensi berhasil ditambahkan.',
            'data' => $tipe->loadCount('items'),
        ], 201);
    }

    /**
     * Display the specified master tipe referensi.
     */
    public function show($id): JsonResponse
    {
        $tipe = MasterTipeReferensi::withCount('items')
            ->where('id', $id)
            ->orWhere('kode', $id)
            ->firstOrFail();

        return response()->json([
            'status' => 'success',
            'data' => $tipe,
        ]);
    }

    /**
     * Update the specified master tipe referensi.
     */
    public function update(UpdateMasterTipeReferensiRequest $request, $id): JsonResponse
    {
        $tipe = MasterTipeReferensi::where('id', $id)
            ->orWhere('kode', $id)
            ->firstOrFail();

        $oldKode = $tipe->kode;
        $validated = $request->validated();

        DB::transaction(function () use ($tipe, $oldKode, $validated) {
            $tipe->update($validated);

            // Jika kode tipe diubah, update relasi kolom tipe pada master referensi yang menggunakan kode lama
            if (isset($validated['kode']) && $validated['kode'] !== $oldKode) {
                MasterReferensi::where('tipe', $oldKode)->update(['tipe' => $validated['kode']]);
            }
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Tipe referensi berhasil diperbarui.',
            'data' => $tipe->fresh()->loadCount('items'),
        ]);
    }

    /**
     * Toggle active status.
     */
    public function toggleActive($id): JsonResponse
    {
        $tipe = MasterTipeReferensi::where('id', $id)
            ->orWhere('kode', $id)
            ->firstOrFail();

        $tipe->is_active = !$tipe->is_active;
        $tipe->save();

        return response()->json([
            'status' => 'success',
            'message' => "Status tipe referensi {$tipe->nama} berhasil diubah.",
            'data' => $tipe->loadCount('items'),
        ]);
    }

    /**
     * Remove the specified master tipe referensi.
     */
    public function destroy($id): JsonResponse
    {
        $tipe = MasterTipeReferensi::withCount('items')
            ->where('id', $id)
            ->orWhere('kode', $id)
            ->firstOrFail();

        if ($tipe->items_count > 0) {
            return response()->json([
                'status' => 'error',
                'message' => "Tipe referensi '{$tipe->nama}' tidak dapat dihapus karena masih memiliki {$tipe->items_count} data item referensi. Harap hapus atau alihkan item terlebih dahulu.",
            ], 422);
        }

        $tipe->delete();

        return response()->json([
            'status' => 'success',
            'message' => "Tipe referensi '{$tipe->nama}' berhasil dihapus.",
        ]);
    }
}
