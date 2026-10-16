<?php

namespace App\Http\Controllers\Sikeu;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sikeu\StoreTutupKasRequest;
use App\Models\Sikeu\TutupKasUnit;
use App\Services\Sikeu\TutupKasService;
use Illuminate\Http\Request;

/**
 * Tutup Kas per Unit SIKEU — parity legacy tutup_bukus per divisi.
 *
 * Global periode tetap berlaku; kunci ini menambah guard per unit_kas + tanggal.
 * Permission: sikeu.kas.manage (lihat/kelola), mengikuti UnitKas.
 */
class TutupKasController extends Controller
{
    public function __construct(private TutupKasService $service) {}

    public function index(Request $request)
    {
        $user = $request->user();
        if (!$user->isSuperAdmin() && !$user->isAdmin() && !$user->hasPermission('sikeu.kas.manage')) {
            return response()->json(['status' => 'error', 'message' => 'Anda tidak memiliki akses tutup kas.'], 403);
        }

        $filters = $request->only(['unit_kas_id', 'tanggal', 'search', 'sort_by', 'sort_order']);
        $perPage = min(100, $request->integer('per_page', 15));
        $data = $this->service->index($filters, $perPage);

        return response()->json([
            'status' => 'success',
            'message' => 'Data tutup kas berhasil dimuat',
            'data' => $data->items(),
            'meta' => [
                'current_page' => $data->currentPage(),
                'per_page' => $data->perPage(),
                'total' => $data->total(),
                'last_page' => $data->lastPage(),
                'from' => $data->firstItem(),
                'to' => $data->lastItem(),
            ],
        ]);
    }

    public function store(StoreTutupKasRequest $request)
    {
        $user = $request->user();
        if (!$user->isSuperAdmin() && !$user->isAdmin() && !$user->hasPermission('sikeu.kas.manage')) {
            return response()->json(['status' => 'error', 'message' => 'Anda tidak memiliki izin menutup kas.'], 403);
        }

        $tutup = $this->service->store($request->validated());

        return response()->json([
            'status' => 'success',
            'message' => 'Unit kas berhasil ditutup pada tanggal tersebut',
            'data' => $tutup->load('unitKas:id,nama_kas,tipe_kas'),
        ], 201);
    }

    public function destroy(Request $request, $id)
    {
        $user = $request->user();
        if (!$user->isSuperAdmin() && !$user->isAdmin() && !$user->hasPermission('sikeu.kas.manage')) {
            return response()->json(['status' => 'error', 'message' => 'Anda tidak memiliki izin membuka tutup kas.'], 403);
        }

        $this->service->destroy(TutupKasUnit::findOrFail($id));

        return response()->json([
            'status' => 'success',
            'message' => 'Kunci tutup kas berhasil dibuka',
            'data' => null,
        ]);
    }
}
