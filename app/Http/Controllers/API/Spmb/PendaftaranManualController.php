<?php

namespace App\Http\Controllers\API\Spmb;

use App\Http\Controllers\Controller;
use App\Http\Requests\Spmb\StorePendaftaranManualRequest;
use App\Http\Requests\Spmb\StorePendaftaranManualThirdPartyRequest;
use App\Models\Role;
use App\Models\SystemSetting;
use App\Models\Spmb\PendaftaranCalonMhs;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\Spmb\SpmbPendaftaranManualService;
use App\Services\Storage\FileStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PendaftaranManualController extends Controller
{
    public function __construct(
        private readonly SpmbPendaftaranManualService $service,
        private readonly FileStorageService $fileStorage,
    ) {}

    public function referrers(Request $request): JsonResponse
    {
        abort_unless($request->user()?->hasPermission('spmb.manage'), 403, 'Akses ditolak.');

        $query = User::query()
            ->where('is_active', true)
            ->whereNotNull('referral_code')
            ->where('referral_code', '!=', '');

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($builder) use ($search) {
                $builder->where('name', 'like', '%'.$search.'%')
                    ->orWhere('username', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%')
                    ->orWhere('referral_code', 'like', '%'.$search.'%');
            });
        }

        $users = $query->orderBy('name')->paginate(min(100, $request->integer('per_page', 15)));

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar referrer berhasil diambil.',
            'data' => collect($users->items())->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name ?: $user->username,
                'username' => $user->username,
                'email' => $user->email,
                'referral_code' => $user->referral_code,
            ]),
            'meta' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
            ],
        ]);
    }

    public function thirdParties(Request $request): JsonResponse
    {
        abort_unless($request->user()?->hasPermission('spmb.manage'), 403, 'Akses ditolak.');

        $roleSlug = SystemSetting::get('third_party_registration_role');
        $role = $roleSlug ? Role::query()->where('slug', $roleSlug)->where('is_active', true)->first() : null;
        if (! $role) {
            return response()->json(['status' => 'success', 'message' => 'Role pihak ketiga belum dikonfigurasi.', 'data' => [], 'meta' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 15, 'total' => 0]]);
        }

        $query = User::query()
            ->where('is_active', true)
            ->whereHas('roles', fn ($builder) => $builder->where('core_roles.id', $role->id));

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($builder) use ($search) {
                $builder->where('name', 'like', '%'.$search.'%')
                    ->orWhere('username', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%');
            });
        }

        $users = $query->orderBy('name')->paginate(min(100, $request->integer('per_page', 15)));

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar akun pihak ketiga berhasil diambil.',
            'data' => $users->items(),
            'meta' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
            ],
        ]);
    }

    public function storeThirdParty(StorePendaftaranManualThirdPartyRequest $request): JsonResponse
    {
        abort_unless($request->user()?->hasPermission('spmb.manage'), 403, 'Akses ditolak.');

        $account = $this->service->createThirdPartyAccount(
            $request->validated(),
            (int) $request->user()->id,
            $request,
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Akun pihak ketiga berhasil dibuat.',
            'data' => $account,
        ], 201);
    }

    public function store(StorePendaftaranManualRequest $request): JsonResponse
    {
        abort_unless($request->user()?->hasPermission('spmb.manage'), 403, 'Akses ditolak.');

        $created = $this->service->create($request->validated(), (int) $request->user()->id, $request);

        return response()->json([
            'status' => 'success',
            'message' => 'Pendaftaran manual berhasil dibuat. Simpan password sementara sebelum menutup halaman ini.',
            'data' => $created,
        ], 201);
    }

    public function downloadProof(Request $request, int $id, int $proofId)
    {
        abort_unless($request->user()?->hasPermission('spmb.manage'), 403, 'Akses ditolak.');

        $pendaftaran = PendaftaranCalonMhs::findOrFail($id);
        $proof = $pendaftaran->buktiPembayaranManual()->findOrFail($proofId);

        try {
            AuditLogService::record(
                module: 'SPMB',
                action: 'download_bukti_pembayaran',
                tableName: $proof->getTable(),
                recordId: $proof->id,
                newValues: ['pendaftaran_id' => $pendaftaran->id, 'file_name' => $proof->original_name],
                request: $request,
            );
        } catch (\Throwable $exception) {
            \Illuminate\Support\Facades\Log::warning('Gagal mencatat audit log download bukti transfer: '.$exception->getMessage());
        }

        return $this->fileStorage->download(
            $proof->file_path,
            $proof->original_name,
            null,
            true,
        );
    }
}
