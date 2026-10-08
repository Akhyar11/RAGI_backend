<?php

namespace App\Http\Controllers\API\Siakad;

use App\Http\Controllers\Controller;
use App\Models\Siakad\AdminProdi;
use App\Models\Siakad\ProgramStudi;
use App\Http\Requests\Siakad\AssignAdminProdiRequest;
use App\Http\Requests\Siakad\UpdateAdminProdiRequest;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AdminProdiController extends Controller
{
    /**
     * Menampilkan daftar penugasan Admin OBE / Tim Kurikulum per Program Studi.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user || (!$user->isSuperAdmin() && !$user->hasPermission('siakad.master.manage'))) {
            return response()->json(['status' => 'error', 'message' => 'Anda tidak memiliki hak akses.'], 403);
        }

        $perPage = min(100, $request->integer('per_page', 15));
        $query = AdminProdi::with([
            'user.pegawai',
            'programStudi.fakultas',
            'assigner:id,name,username'
        ]);

        if ($request->filled('program_studi_id')) {
            $query->where('program_studi_id', $request->program_studi_id);
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->whereHas('user', function ($uq) use ($search) {
                    $uq->where('name', 'like', "%{$search}%")
                       ->orWhere('username', 'like', "%{$search}%")
                       ->orWhere('email', 'like', "%{$search}%");
                })->orWhereHas('programStudi', function ($pq) use ($search) {
                    $pq->where('nama', 'like', "%{$search}%")
                       ->orWhere('kode_prodi', 'like', "%{$search}%");
                })->orWhere('jabatan', 'like', "%{$search}%");
            });
        }

        if ($request->filled('can_approve_rps')) {
            $query->where('can_approve_rps', filter_var($request->can_approve_rps, FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }

        $allowedSort = ['created_at', 'id', 'jabatan', 'user', 'program_studi', 'is_active'];
        $sortBy = in_array($request->sort_by, $allowedSort) ? $request->sort_by : 'created_at';
        $sortOrder = $request->sort_order === 'asc' ? 'asc' : 'desc';

        if ($sortBy === 'user') {
            $query->join('core_users', 'siakad_admin_prodi.user_id', '=', 'core_users.id')
                  ->orderBy('core_users.name', $sortOrder)
                  ->select('siakad_admin_prodi.*');
        } elseif ($sortBy === 'program_studi') {
            $query->join('siakad_program_studi', 'siakad_admin_prodi.program_studi_id', '=', 'siakad_program_studi.id')
                  ->orderBy('siakad_program_studi.nama', $sortOrder)
                  ->select('siakad_admin_prodi.*');
        } else {
            $query->orderBy($sortBy, $sortOrder);
        }

        $data = $query->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar penugasan Admin OBE program studi berhasil diambil',
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
                'program_studi_id' => $request->program_studi_id,
                'user_id' => $request->user_id,
                'sort_by' => $sortBy,
                'sort_order' => $sortOrder,
            ],
        ]);
    }

    /**
     * Menugaskan user sebagai Admin OBE Homebase Program Studi.
     */
    public function store(AssignAdminProdiRequest $request): JsonResponse
    {
        $user = $request->user();
        if (!$user || !($user->isSuperAdmin() || $user->hasPermission('siakad.master.manage'))) {
            return response()->json(['status' => 'error', 'message' => 'Anda tidak memiliki hak akses.'], 403);
        }

        $validated = $request->validated();

        $adminProdi = AdminProdi::updateOrCreate(
            [
                'program_studi_id' => $validated['program_studi_id'],
                'user_id' => $validated['user_id'],
            ],
            [
                'jabatan' => $validated['jabatan'] ?? 'Admin OBE / Tim Kurikulum',
                'can_approve_rps' => $validated['can_approve_rps'] ?? true,
                'is_active' => $validated['is_active'] ?? true,
                'assigned_by' => $user?->id,
            ]
        );

        try {
            AuditLogService::record(
                module: 'SIAKAD',
                action: 'create',
                tableName: 'siakad_admin_prodi',
                recordId: $adminProdi->id,
                oldValues: null,
                newValues: $adminProdi->toArray(),
                request: $request
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Gagal audit log: ' . $e->getMessage());
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Penugasan Admin OBE Program Studi berhasil disimpan',
            'data' => $adminProdi->load(['user.pegawai', 'programStudi.fakultas', 'assigner:id,name,username']),
        ], 201);
    }

    /**
     * Detail penugasan Admin OBE.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        if (!$user || (!$user->isSuperAdmin() && !$user->hasPermission('siakad.master.manage'))) {
            return response()->json(['status' => 'error', 'message' => 'Anda tidak memiliki hak akses.'], 403);
        }

        $adminProdi = AdminProdi::with(['user.pegawai', 'programStudi.fakultas', 'assigner:id,name,username'])->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'message' => 'Detail penugasan Admin OBE berhasil diambil',
            'data' => $adminProdi,
        ]);
    }

    /**
     * Perbarui penugasan Admin OBE.
     */
    public function update(UpdateAdminProdiRequest $request, int $id): JsonResponse
    {
        $user = $request->user();
        if (!$user || !($user->isSuperAdmin() || $user->hasPermission('siakad.master.manage'))) {
            return response()->json(['status' => 'error', 'message' => 'Anda tidak memiliki hak akses.'], 403);
        }

        $adminProdi = AdminProdi::findOrFail($id);
        $old = $adminProdi->getOriginal();

        $adminProdi->update($request->validated());
        $new = $adminProdi->getChanges();

        try {
            AuditLogService::record(
                module: 'SIAKAD',
                action: 'update',
                tableName: 'siakad_admin_prodi',
                recordId: $adminProdi->id,
                oldValues: $old,
                newValues: $new,
                request: $request
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Gagal audit log: ' . $e->getMessage());
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Data penugasan Admin OBE Program Studi berhasil diperbarui',
            'data' => $adminProdi->load(['user.pegawai', 'programStudi.fakultas', 'assigner:id,name,username']),
        ]);
    }

    /**
     * Hapus penugasan Admin OBE Program Studi.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        if (!$user || !($user->isSuperAdmin() || $user->hasPermission('siakad.master.manage'))) {
            return response()->json(['status' => 'error', 'message' => 'Anda tidak memiliki hak akses untuk menghapus penugasan.'], 403);
        }

        $adminProdi = AdminProdi::findOrFail($id);
        $old = $adminProdi->getOriginal();
        $adminProdi->delete();

        try {
            AuditLogService::record(
                module: 'SIAKAD',
                action: 'delete',
                tableName: 'siakad_admin_prodi',
                recordId: $id,
                oldValues: $old,
                newValues: null,
                request: $request
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Gagal audit log: ' . $e->getMessage());
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Penugasan Admin OBE Program Studi berhasil dihapus',
            'data' => null,
        ]);
    }

    /**
     * Merasuki Admin OBE / Personel Prodi yang ditugaskan (khusus lingkup SIAKAD).
     */
    public function impersonate(Request $request, int $id, \App\Services\IAM\UserService $userService): JsonResponse
    {
        $admin = $request->user();
        if (!$admin || !($admin->isSuperAdmin() || $admin->hasPermission('siakad.master.manage'))) {
            return response()->json(['status' => 'error', 'message' => 'Anda tidak memiliki hak akses untuk merasuki Admin OBE.'], 403);
        }

        $adminProdi = AdminProdi::with(['user', 'programStudi'])->findOrFail($id);
        $targetUser = $adminProdi->user;

        if (!$targetUser) {
            return response()->json(['status' => 'error', 'message' => 'Akun pengguna untuk penugasan ini tidak ditemukan.'], 404);
        }

        $adminTokenId = $admin->currentAccessToken()?->id;

        $result = $userService->impersonate(
            $targetUser,
            $admin,
            $adminTokenId ? (string) $adminTokenId : null,
            $request->ip(),
            $request->userAgent()
        );

        try {
            AuditLogService::record(
                module: 'SIAKAD',
                action: 'impersonate',
                tableName: 'siakad_admin_prodi',
                recordId: $adminProdi->id,
                oldValues: null,
                newValues: [
                    'impersonator_id' => $admin->id,
                    'target_user_id' => $targetUser->id,
                    'program_studi_id' => $adminProdi->program_studi_id,
                ],
                request: $request
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Gagal audit log impersonate Admin OBE: ' . $e->getMessage());
        }

        return response()->json([
            'status' => 'success',
            'message' => "Berhasil merasuki {$targetUser->name} ({$adminProdi->programStudi?->nama}).",
            'data' => $result,
        ]);
    }

    /**
     * Dapatkan daftar role yang relevan untuk penugasan Admin OBE (Dosen, Tendik, dll).
     */
    public function getAdminObeEligibleRoles(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user || (!$user->isSuperAdmin() && !$user->hasPermission('siakad.master.manage'))) {
            return response()->json(['status' => 'error', 'message' => 'Anda tidak memiliki hak akses.'], 403);
        }

        $roles = \App\Models\Role::where('is_active', true)
            ->where('slug', '!=', 'superadmin')
            ->orderBy('name')
            ->get();

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar role eligible Admin OBE berhasil diambil',
            'data' => $roles,
        ]);
    }

    /**
     * Dapatkan daftar menu yang diplot untuk Role Admin OBE tertentu.
     */
    public function getAdminObeRoleMenus(Request $request, int $roleId): JsonResponse
    {
        $user = $request->user();
        if (!$user || (!$user->isSuperAdmin() && !$user->hasPermission('siakad.master.manage'))) {
            return response()->json(['status' => 'error', 'message' => 'Anda tidak memiliki hak akses.'], 403);
        }

        $role = \App\Models\Role::findOrFail($roleId);
        
        $assignedMenuIds = \Illuminate\Support\Facades\DB::table('siakad_admin_obe_role_menus')
            ->where('role_id', $roleId)
            ->pluck('menu_id')
            ->toArray();

        // Fallback jika belum di-custom khusus OBE: ambil dari core_menu_role
        if (empty($assignedMenuIds)) {
            $assignedMenuIds = \Illuminate\Support\Facades\DB::table('core_menu_role')
                ->where('role_id', $roleId)
                ->pluck('menu_id')
                ->toArray();
        }

        $menus = \App\Models\Menu::whereIn('id', $assignedMenuIds)->get();

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar menu Role Admin OBE berhasil diambil',
            'data' => $menus,
        ]);
    }

    /**
     * Plot / simpan menu akses khusus Admin OBE berdasarkan Role.
     */
    public function assignAdminObeRoleMenus(Request $request, int $roleId): JsonResponse
    {
        $user = $request->user();
        if (!$user || (!$user->isSuperAdmin() && !$user->hasPermission('siakad.master.manage'))) {
            return response()->json(['status' => 'error', 'message' => 'Anda tidak memiliki hak akses.'], 403);
        }

        $request->validate([
            'menu_ids' => 'present|array',
            'menu_ids.*' => 'exists:core_menus,id',
        ]);

        $role = \App\Models\Role::findOrFail($roleId);

        \Illuminate\Support\Facades\DB::transaction(function () use ($roleId, $request) {
            \Illuminate\Support\Facades\DB::table('siakad_admin_obe_role_menus')->where('role_id', $roleId)->delete();
            $inserts = array_map(function ($menuId) use ($roleId) {
                return [
                    'role_id' => $roleId,
                    'menu_id' => $menuId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }, $request->menu_ids);

            if (!empty($inserts)) {
                \Illuminate\Support\Facades\DB::table('siakad_admin_obe_role_menus')->insert($inserts);
            }
        });

        try {
            AuditLogService::record(
                module: 'SIAKAD',
                action: 'update',
                tableName: 'core_roles',
                recordId: $roleId,
                oldValues: null,
                newValues: ['menu_ids' => $request->menu_ids],
                request: $request
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Gagal audit log: ' . $e->getMessage());
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Plotting menu Admin OBE berdasarkan Role berhasil disimpan',
            'data' => [
                'role_id' => $roleId,
                'menu_ids' => $request->menu_ids,
            ],
        ]);
    }
}
