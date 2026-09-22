<?php

namespace App\Http\Controllers\Simpeg;

use App\Http\Controllers\Controller;
use App\Http\Requests\Simpeg\StoreUsulanJafungRequest;
use App\Http\Requests\Simpeg\UpdateUsulanJafungRequest;
use App\Models\Simpeg\UsulanJafung;
use App\Services\Simpeg\UsulanJafungService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UsulanJafungController extends Controller
{
    public function __construct(
        protected UsulanJafungService $usulanJafungService
    ) {}

    public function index(Request $request): JsonResponse
    {
        if (!$request->user()->hasPermission('simpeg.usulan_jafung.read') &&
            !$request->user()->hasPermission('simpeg.usulan_jafung.request') &&
            !$request->user()->hasPermission('simpeg.usulan_jafung.verify')) {
            throw new AuthorizationException('Akses Ditolak: Peran Anda tidak memiliki hak akses Usulan Jafung.');
        }

        $query = UsulanJafung::with([
            'pegawai.unitKerja',
            'pegawai.dosen.programStudi',
            'jafungAsal',
            'jafungTujuan'
        ]);

        // Filter based on role / user scope
        if ($request->has('pegawai_id') && !empty($request->pegawai_id)) {
            $query->where('pegawai_id', $request->pegawai_id);
        } elseif (!$request->user()->hasPermission('simpeg.usulan_jafung.verify')) {
            $pegId = $request->user()->pegawai?->id;
            if ($pegId) {
                $query->where('pegawai_id', $pegId);
            }
        }

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('catatan_reviewer', 'like', "%{$search}%")
                    ->orWhereHas('pegawai', function ($qp) use ($search) {
                        $qp->where('nama_lengkap', 'like', "%{$search}%")
                            ->orWhere('nip', 'like', "%{$search}%")
                            ->orWhere('nidn', 'like', "%{$search}%")
                            ->orWhere('nuptk', 'like', "%{$search}%");
                    })
                    ->orWhereHas('jafungTujuan', function ($qj) use ($search) {
                        $qj->where('nama', 'like', "%{$search}%");
                    });
            });
        }

        // Filter status_usulan
        if ($request->filled('status_usulan')) {
            $query->where('status_usulan', $request->status_usulan);
        }

        // Filter jafung_tujuan_id
        if ($request->filled('jafung_tujuan_id')) {
            $query->where('jafung_tujuan_id', $request->jafung_tujuan_id);
        }

        // Sorting
        $allowedSort = ['created_at', 'angka_kredit_usulan', 'status_usulan', 'id'];
        $sortBy = in_array($request->sort_by, $allowedSort, true) ? $request->sort_by : 'created_at';
        $sortOrder = $request->sort_order === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortBy, $sortOrder);

        $perPage = min(100, $request->integer('per_page', 15));
        $paginated = $query->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'message' => 'Data usulan jafung berhasil diambil',
            'data' => $paginated->items(),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
                'last_page' => $paginated->lastPage(),
                'from' => $paginated->firstItem(),
                'to' => $paginated->lastItem(),
            ],
            'filters' => [
                'search' => (string) $request->input('search', ''),
                'sort_by' => $sortBy,
                'sort_order' => $sortOrder,
            ],
        ]);
    }

    public function show(Request $request, $id): JsonResponse
    {
        $canRead = $request->user()->hasPermission('simpeg.usulan_jafung.read') ||
                   $request->user()->hasPermission('simpeg.usulan_jafung.verify') ||
                   $request->user()->hasRole('superadmin') ||
                   $request->user()->hasRole('admin');

        $usulan = UsulanJafung::with([
            'pegawai.unitKerja',
            'pegawai.dosen.programStudi',
            'jafungAsal',
            'jafungTujuan'
        ])->findOrFail($id);

        if (!$canRead) {
            $pegId = $request->user()->pegawai?->id;
            if ($usulan->pegawai_id !== $pegId) {
                throw new AuthorizationException('Akses Ditolak: Anda tidak memiliki izin untuk melihat usulan jafung ini.');
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Detail usulan jafung berhasil diambil',
            'data' => $usulan,
        ]);
    }

    public function store(StoreUsulanJafungRequest $request): JsonResponse
    {
        $usulan = $this->usulanJafungService->create($request->validated());

        return response()->json([
            'status' => 'success',
            'message' => 'Usulan kenaikan Jafung berhasil diajukan',
            'data' => $usulan,
        ], 201);
    }

    public function update(UpdateUsulanJafungRequest $request, $id): JsonResponse
    {
        $canManage = $request->user()->hasPermission('simpeg.usulan_jafung.update') ||
                     $request->user()->hasPermission('simpeg.usulan_jafung.verify');

        $usulan = UsulanJafung::findOrFail($id);

        if (!$canManage) {
            $pegId = $request->user()->pegawai?->id;
            if ($usulan->pegawai_id !== $pegId) {
                throw new AuthorizationException('Akses Ditolak: Anda tidak dapat mengubah usulan ini.');
            }
        }

        $updated = $this->usulanJafungService->update($usulan, $request->validated());

        return response()->json([
            'status' => 'success',
            'message' => 'Usulan kenaikan Jafung berhasil diperbarui',
            'data' => $updated,
        ], 200);
    }

    public function destroy(Request $request, $id): JsonResponse
    {
        $canDelete = $request->user()->hasPermission('simpeg.usulan_jafung.delete');

        $usulan = UsulanJafung::findOrFail($id);

        if (!$canDelete) {
            $pegId = $request->user()->pegawai?->id;
            if ($usulan->pegawai_id !== $pegId) {
                throw new AuthorizationException('Akses Ditolak: Anda tidak dapat menghapus usulan ini.');
            }
        }

        $deletedId = (int) $usulan->id;
        $this->usulanJafungService->delete($usulan);

        return response()->json([
            'status' => 'success',
            'message' => 'Usulan jafung berhasil dihapus (soft delete)',
            'data' => [
                'id' => $deletedId,
                'deleted_at' => now()->toIso8601String(),
            ],
        ], 200);
    }
}
