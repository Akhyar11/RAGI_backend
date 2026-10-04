<?php

namespace App\Http\Controllers\Sinapra;

use App\Http\Controllers\Controller;
use App\Models\Siakad\ProgramStudi;
use App\Models\Role;
use App\Http\Requests\Sinapra\PlottingProdiRoleRequest;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ProdiRoleController extends Controller
{
    /**
     * Menampilkan daftar Program Studi (SIAKAD) beserta mapping Role Laboran SINAPRA.
     * Data prodi selalu membaca langsung dari tabel siakad_program_studi secara dinamis.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', \App\Models\Ruangan::class);

        $perPage = min(100, $request->integer('per_page', 15));
        $query = ProgramStudi::with([
            'fakultas:id,kode,nama',
            'sinapraRoles:id,name,slug,description',
        ]);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                  ->orWhere('kode_prodi', 'like', "%{$search}%");
            });
        }

        if ($request->filled('jenjang')) {
            $query->where('jenjang', $request->jenjang);
        }

        if ($request->filled('fakultas_id')) {
            $query->where('fakultas_id', $request->fakultas_id);
        }

        if ($request->filled('status_plotting')) {
            if ($request->status_plotting === 'terplot') {
                $query->has('sinapraRoles');
            } elseif ($request->status_plotting === 'belum_terplot') {
                $query->doesntHave('sinapraRoles');
            }
        }

        $allowedSort = ['kode_prodi', 'nama', 'jenjang', 'created_at', 'id'];
        $sortBy = in_array($request->sort_by, $allowedSort) ? $request->sort_by : 'nama';
        $sortOrder = $request->sort_order === 'desc' ? 'desc' : 'asc';
        $query->orderBy($sortBy, $sortOrder);

        $data = $query->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar plotting program studi ke role laboran berhasil diambil',
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
                'jenjang' => $request->jenjang,
                'fakultas_id' => $request->fakultas_id,
                'status_plotting' => $request->status_plotting,
                'sort_by' => $sortBy,
                'sort_order' => $sortOrder,
            ],
        ]);
    }

    /**
     * Mengambil daftar master role yang tersedia untuk opsi pemilihan plotting.
     */
    public function getAvailableRoles(): JsonResponse
    {
        $this->authorize('viewAny', \App\Models\Ruangan::class);

        $roles = Role::where('is_active', true)
            ->select('id', 'name', 'slug', 'description')
            ->orderBy('name', 'asc')
            ->get();

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar role laboran tersedia berhasil dimuat',
            'data' => $roles,
        ]);
    }

    /**
     * Memperbarui / menyimpan plotting role laboran untuk suatu Program Studi.
     * Hanya mengatur relasi role_ids, tidak mengubah identitas master program studi SIAKAD.
     */
    public function update(PlottingProdiRoleRequest $request, int $prodiId): JsonResponse
    {
        $this->authorize('update', \App\Models\Ruangan::class);

        $prodi = ProgramStudi::with('sinapraRoles')->findOrFail($prodiId);

        if ($request->has('role_id')) {
            $singleRoleId = $request->input('role_id');
            $roleIds = !empty($singleRoleId) ? [(int) $singleRoleId] : [];
        } else {
            $roleIds = $request->input('role_ids', []);
            if (count($roleIds) > 1) {
                $roleIds = [reset($roleIds)];
            }
        }

        $keterangan = $request->input('keterangan');
        $oldRoles = $prodi->sinapraRoles->pluck('id')->toArray();

        DB::transaction(function () use ($prodi, $roleIds, $keterangan, $oldRoles) {
            $syncData = [];
            foreach ($roleIds as $roleId) {
                $syncData[$roleId] = ['keterangan' => $keterangan];
            }

            $prodi->sinapraRoles()->sync($syncData);

            try {
                AuditLogService::record(
                    module: 'SINAPRA',
                    action: 'update_plotting_prodi_role',
                    tableName: 'sinapra_prodi_roles',
                    recordId: $prodi->id,
                    oldValues: ['role_ids' => $oldRoles],
                    newValues: ['role_ids' => $roleIds, 'keterangan' => $keterangan]
                );
            } catch (\Throwable $e) {
                \Log::warning('Gagal mencatat audit log prodi role: ' . $e->getMessage());
            }
        });

        $prodi->load(['fakultas:id,kode,nama', 'sinapraRoles:id,name,slug,description']);

        return response()->json([
            'status' => 'success',
            'message' => "Plotting role laboran untuk Program Studi {$prodi->nama} berhasil disimpan",
            'data' => $prodi,
        ]);
    }
}
