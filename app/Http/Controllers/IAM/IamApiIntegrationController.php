<?php

namespace App\Http\Controllers\IAM;

use App\Http\Controllers\Controller;
use App\Http\Requests\IAM\UpdateApiIntegrationRequest;
use App\Http\Requests\IAM\TestApiIntegrationRequest;
use App\Jobs\SyncPddiktiMahasiswaJob;
use App\Models\IamApiIntegration;
use App\Services\AuditLogService;
use App\Services\IAM\ApiIntegrationService;
use App\Services\IAM\ApiIntegrationSyncException;
use App\Services\IAM\DosenIntegrationSyncService;
use App\Services\IAM\KurikulumIntegrationSyncService;
use App\Services\IAM\KelasPddiktiIntegrationSyncService;
use App\Services\IAM\MataKuliahIntegrationSyncService;
use App\Services\IAM\ProgramStudiIntegrationSyncService;
use App\Services\IAM\SkalaNilaiIntegrationSyncService;
use App\Services\IAM\SiakadMahasiswaIntegrationSyncService;
use App\Services\IAM\SikeuIntegrationSyncService;
use App\Services\IAM\TahunAkademikIntegrationSyncService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Throwable;

class IamApiIntegrationController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('iam.api_integration.read');

        $perPage = min(100, $request->integer('per_page', 15));
        $query = IamApiIntegration::query();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('key', 'like', "%{$search}%")
                  ->orWhere('module', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($request->filled('module')) {
            $query->where('module', $request->module);
        }

        if ($request->has('is_active') && $request->is_active !== null && $request->is_active !== '') {
            $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }

        $allowedSorts = ['name', 'key', 'module', 'http_method', 'is_active', 'last_synced_at', 'created_at'];
        $sortBy = in_array($request->sort_by, $allowedSorts, true) ? $request->sort_by : 'created_at';
        $sortOrder = $request->sort_order === 'asc' ? 'asc' : 'desc';

        $query->orderBy($sortBy, $sortOrder);

        $paginator = $query->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar integrasi API berhasil diambil',
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
                'filters' => [
                    'search' => $request->search,
                    'module' => $request->module,
                    'is_active' => $request->is_active,
                    'sort_by' => $sortBy,
                    'sort_order' => $sortOrder,
                ],
            ],
        ]);
    }

    public function show($id)
    {
        Gate::authorize('iam.api_integration.read');

        $integration = IamApiIntegration::findOrFail($id);

        return response()->json([
            'status' => 'success',
            'message' => 'Detail integrasi API berhasil diambil',
            'data' => $integration,
        ]);
    }

    public function update(UpdateApiIntegrationRequest $request, $id)
    {
        Gate::authorize('iam.api_integration.manage');

        $integration = IamApiIntegration::findOrFail($id);
        $oldValues = $integration->getOriginal();
        $sourceType = $request->input('source_type', $integration->source_type ?? 'custom_api');
        $sourceSchema = $integration->source_schemas[$sourceType] ?? [];
        $connectionManaged = ($sourceSchema['connection_managed'] ?? false) === true;

        if ($sourceType === 'pddikti' && !in_array('pddikti', $integration->supported_sources ?? [], true)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Mode PDDIKTI Neo Feeder tidak didukung untuk integrasi ini.',
            ], 422);
        }

        $integration->fill([
            'source_type' => $sourceType,
            'url_api' => $connectionManaged ? null : $request->input('url_api', $integration->url_api),
            'auth_type' => $request->auth_type,
            'is_active' => $request->boolean('is_active'),
        ]);

        if ($request->filled('token')) {
            $integration->token = $request->token;
        }

        $integration->save();

        $newValues = $integration->getChanges();
        if (isset($oldValues['token'])) {
            $oldValues['token'] = '********';
        }
        if (isset($newValues['token'])) {
            $newValues['token'] = '********';
        }

        try {
            AuditLogService::record(
                module: 'IAM',
                action: 'update',
                tableName: 'iam_api_integrations',
                recordId: $integration->id,
                oldValues: $oldValues,
                newValues: $newValues,
                request: $request
            );
        } catch (Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Gagal mencatat audit log update integrasi API: ' . $e->getMessage());
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Konfigurasi integrasi API berhasil diperbarui',
            'data' => $integration,
        ]);
    }

    public function testConnection(TestApiIntegrationRequest $request, $id)
    {
        Gate::authorize('iam.api_integration.manage');

        $integration = IamApiIntegration::findOrFail($id);
        $oldValues = $integration->getOriginal();

        $requestedSource = $request->input('source_type', $integration->source_type ?? 'custom_api');
        if ($requestedSource === 'pddikti' && !in_array('pddikti', $integration->supported_sources ?? [], true)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Mode PDDIKTI Neo Feeder tidak didukung untuk integrasi ini.',
            ], 422);
        }

        if ($requestedSource === 'pddikti') {
            $sourceSchema = $integration->source_schemas[$requestedSource] ?? [];
            return $this->testPddiktiConnection(
                $request,
                $integration,
                $oldValues,
                $sourceSchema['action'] ?? null,
                $sourceSchema['target_entity'] ?? null
            );
        }

        $url = $request->filled('url_api') ? $request->url_api : $integration->url_api;
        $token = $request->has('token') ? $request->token : $integration->token;
        $authType = $request->filled('auth_type') ? $request->auth_type : ($integration->auth_type ?? 'bearer');

        if (empty($url)) {
            return response()->json([
                'status' => 'error',
                'message' => 'URL API belum ditentukan untuk pengujian koneksi.',
                'errors' => [
                    'url_api' => ['URL API belum ditentukan untuk pengujian koneksi.'],
                ],
            ], 422);
        }

        $tempIntegration = new IamApiIntegration([
            'url_api' => $url,
            'token' => $token,
            'auth_type' => $authType,
            'http_method' => $integration->http_method,
        ]);

        $startTime = microtime(true);

        try {
            $client = ApiIntegrationService::client($tempIntegration, timeoutSeconds: 8);
            $method = strtoupper($integration->http_method ?? 'GET');

            $res = ($method === 'POST')
                ? $client->post($url)
                : $client->get($url);

            $durationMs = round((microtime(true) - $startTime) * 1000, 2);
            $httpStatus = $res->status();
            $isSuccess = $res->successful();

            $safeHeaders = collect($res->headers())
                ->except(['authorization', 'set-cookie', 'x-api-key', 'cookie', 'server', 'x-powered-by'])
                ->all();
            $rawBody = (string) $res->body();
            $sampleBody = $res->json() ?? (strlen($rawBody) > 1000 ? substr($rawBody, 0, 1000) . '... [truncated]' : $rawBody);

            $newStatus = $isSuccess ? 'success' : 'failed';
            $integration->update([
                'last_synced_at' => now(),
                'last_status' => $newStatus,
            ]);

            try {
                AuditLogService::record(
                    module: 'IAM',
                    action: 'test_connection',
                    tableName: 'iam_api_integrations',
                    recordId: $integration->id,
                    oldValues: $oldValues,
                    newValues: $integration->getChanges(),
                    request: $request
                );
            } catch (Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Gagal mencatat audit log test koneksi API: ' . $e->getMessage());
            }

            return response()->json([
                'status' => 'success',
                'message' => $isSuccess
                    ? "Tes koneksi berhasil terhubung ({$httpStatus} OK dalam {$durationMs}ms)"
                    : "Tes koneksi terhubung namun endpoint mengembalikan HTTP {$httpStatus}",
                'data' => [
                    'http_status' => $httpStatus,
                    'is_success' => $isSuccess,
                    'duration_ms' => $durationMs,
                    'headers' => $safeHeaders,
                    'response_sample' => $sampleBody,
                    'expected_schema' => $integration->expected_schema,
                ],
            ]);
        } catch (Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Tes koneksi API gagal: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);

            $integration->update([
                'last_synced_at' => now(),
                'last_status' => 'failed',
            ]);

            try {
                AuditLogService::record(
                    module: 'IAM',
                    action: 'test_connection',
                    tableName: 'iam_api_integrations',
                    recordId: $integration->id,
                    oldValues: $oldValues,
                    newValues: $integration->getChanges(),
                    request: $request
                );
            } catch (Throwable $auditError) {
                \Illuminate\Support\Facades\Log::warning('Gagal mencatat audit log test koneksi API: ' . $auditError->getMessage());
            }

            return response()->json([
                'status' => 'error',
                'message' => 'Tes koneksi gagal terhubung ke server tujuan. Pastikan URL dapat dijangkau.',
            ], 502);
        }
    }

    private function testPddiktiConnection(
        TestApiIntegrationRequest $request,
        IamApiIntegration $integration,
        array $oldValues,
        ?string $action,
        ?string $targetEntity
    )
    {
        $startTime = microtime(true);

        try {
            $result = match ($targetEntity) {
                'program_studi' => app(ProgramStudiIntegrationSyncService::class)->testPddiktiConnection($action ?? ''),
                'kurikulum' => app(KurikulumIntegrationSyncService::class)->testPddiktiConnection($action ?? ''),
                'mahasiswa' => app(SiakadMahasiswaIntegrationSyncService::class)->testPddiktiConnection($action ?? ''),
                'dosen' => app(DosenIntegrationSyncService::class)->testPddiktiConnection($action ?? ''),
                'kelas' => app(KelasPddiktiIntegrationSyncService::class)->testPddiktiConnection($action ?? ''),
                'skala_nilai' => app(SkalaNilaiIntegrationSyncService::class)->testPddiktiConnection($action ?? ''),
                'tahun_akademik' => app(TahunAkademikIntegrationSyncService::class)->testPddiktiConnection($action ?? ''),
                default => app(MataKuliahIntegrationSyncService::class)->testPddiktiConnection($action ?? ''),
            };
            $this->recordIntegrationTestAudit($request, $integration, $oldValues, ['test_status' => 'success']);

            return response()->json([
                'status' => 'success',
                'message' => 'Tes koneksi Neo Feeder PDDikti berhasil.',
                'data' => [
                    'http_status' => 200,
                    'is_success' => true,
                    'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
                    'headers' => [],
                    'response_sample' => $result,
                    'expected_schema' => $integration->expected_schema,
                ],
            ]);
        } catch (ApiIntegrationSyncException $exception) {
            $this->recordIntegrationTestAudit($request, $integration, $oldValues, ['test_status' => 'failed']);

            return response()->json([
                'status' => 'error',
                'message' => $exception->getMessage(),
            ], $exception->statusCode);
        }
    }

    private function recordIntegrationTestAudit(Request $request, IamApiIntegration $integration, array $oldValues, array $testValues): void
    {
        try {
            AuditLogService::record(
                module: 'IAM',
                action: 'test_connection',
                tableName: 'iam_api_integrations',
                recordId: $integration->id,
                oldValues: $oldValues,
                newValues: $testValues,
                request: $request
            );
        } catch (Throwable $exception) {
            \Illuminate\Support\Facades\Log::warning('Gagal mencatat audit log test PDDIKTI API: ' . $exception->getMessage());
        }
    }

    public function sync(
        Request $request,
        int $id,
        ProgramStudiIntegrationSyncService $syncService,
        MataKuliahIntegrationSyncService $mataKuliahSyncService,
        KurikulumIntegrationSyncService $kurikulumSyncService,
        SikeuIntegrationSyncService $sikeuSyncService,
        SiakadMahasiswaIntegrationSyncService $siakadMahasiswaSyncService,
        TahunAkademikIntegrationSyncService $tahunAkademikSyncService,
        DosenIntegrationSyncService $dosenSyncService,
        KelasPddiktiIntegrationSyncService $kelasSyncService,
        SkalaNilaiIntegrationSyncService $skalaNilaiSyncService
    ) {
        Gate::authorize('iam.api_integration.manage');

        $integration = IamApiIntegration::findOrFail($id);
        $oldValues = $integration->only(['last_synced_at', 'last_status']);

        if ($integration->module === 'siakad'
            && $integration->key === 'siakad_mahasiswa'
            && $integration->source_type === 'pddikti'
            && !$request->boolean('preview')) {
            return $this->startPddiktiMahasiswaSync($request, $integration, $oldValues);
        }

        if ($integration->module === 'siakad'
            && $integration->source_type === 'pddikti'
            && ($integration->source_schemas['pddikti']['target_entity'] ?? null) === 'dosen') {
            return $this->startPddiktiDosenSync($request, $integration, $oldValues, $dosenSyncService);
        }

        if ($integration->module === 'siakad'
            && $integration->source_type === 'pddikti'
            && ($integration->source_schemas['pddikti']['target_entity'] ?? null) === 'kelas') {
            return $this->startPddiktiKelasSync($request, $integration, $oldValues, $kelasSyncService);
        }

        if ($integration->module === 'siakad'
            && $integration->source_type === 'pddikti'
            && $integration->key === 'siakad_program_studi') {
            return $this->startPddiktiQueuedSync($request, $integration, $oldValues, $syncService);
        }

        if ($integration->module === 'siakad'
            && $integration->source_type === 'pddikti'
            && $integration->key === 'siakad_mata_kuliah') {
            return $this->startPddiktiQueuedSync($request, $integration, $oldValues, $mataKuliahSyncService);
        }

        if ($integration->module === 'siakad'
            && $integration->source_type === 'pddikti'
            && $integration->key === 'siakad_skala_nilai') {
            return $this->startPddiktiQueuedSync($request, $integration, $oldValues, $skalaNilaiSyncService);
        }

        try {
            $result = match (true) {
                $integration->module === 'siakad'
                    && $integration->source_type === 'pddikti'
                    && in_array('pddikti', $integration->supported_sources ?? [], true)
                    && ($integration->source_schemas[$integration->source_type]['target_entity'] ?? null) === 'tahun_akademik' => $tahunAkademikSyncService->sync($integration),
                $integration->module === 'siakad'
                    && $integration->source_type === 'pddikti'
                    && in_array('pddikti', $integration->supported_sources ?? [], true)
                    && ($integration->source_schemas[$integration->source_type]['target_entity'] ?? null) === 'dosen' => $dosenSyncService->sync($integration, $request->user()?->id),
                $integration->module === 'siakad'
                    && $integration->source_type === 'pddikti'
                    && in_array('pddikti', $integration->supported_sources ?? [], true)
                    && ($integration->source_schemas[$integration->source_type]['target_entity'] ?? null) === 'mahasiswa' => $siakadMahasiswaSyncService->syncPddikti($integration, $request->boolean('preview')),
                $integration->module === 'siakad' && $integration->key === 'siakad_program_studi' => $syncService->sync($integration),
                $integration->module === 'siakad' && $integration->key === 'siakad_mahasiswa' => $siakadMahasiswaSyncService->sync($integration),
                $integration->module === 'siakad'
                    && $integration->source_type === 'pddikti'
                    && in_array('pddikti', $integration->supported_sources ?? [], true)
                    && ($integration->source_schemas[$integration->source_type]['target_entity'] ?? null) === 'kurikulum' => $kurikulumSyncService->sync($integration),
                $integration->module === 'siakad' && in_array('pddikti', $integration->supported_sources ?? [], true) => $mataKuliahSyncService->sync($integration),
                $integration->module === 'sikeu' => $sikeuSyncService->sync($integration),
                default => throw new ApiIntegrationSyncException("Aksi sync belum didukung untuk integrasi '{$integration->key}' (modul '{$integration->module}')."),
            };
            $integration->update([
                'last_synced_at' => now(),
                'last_status' => 'success',
            ]);

            try {
                AuditLogService::record(
                    module: 'IAM',
                    action: 'sync',
                    tableName: 'iam_api_integrations',
                    recordId: $integration->id,
                    oldValues: $oldValues,
                    newValues: [
                        'last_synced_at' => $integration->last_synced_at?->toISOString(),
                        'last_status' => $integration->last_status,
                        'result' => $result,
                    ],
                    request: $request
                );
            } catch (Throwable $auditError) {
                \Illuminate\Support\Facades\Log::warning('Gagal mencatat audit log sync API integration: ' . $auditError->getMessage());
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Sinkronisasi berhasil dijalankan.',
                'data' => $result,
            ]);
        } catch (ApiIntegrationSyncException $exception) {
            $integration->update([
                'last_synced_at' => now(),
                'last_status' => 'failed',
            ]);

            try {
                AuditLogService::record(
                    module: 'IAM',
                    action: 'sync',
                    tableName: 'iam_api_integrations',
                    recordId: $integration->id,
                    oldValues: $oldValues,
                    newValues: [
                        'last_synced_at' => $integration->last_synced_at?->toISOString(),
                        'last_status' => $integration->last_status,
                        'error' => $exception->getMessage(),
                    ],
                    request: $request
                );
            } catch (Throwable $auditError) {
                \Illuminate\Support\Facades\Log::warning('Gagal mencatat audit log sync API integration: ' . $auditError->getMessage());
            }

            return response()->json([
                'status' => 'error',
                'message' => $exception->getMessage(),
            ], $exception->statusCode);
        }
    }

    public function studentSyncProgress(Request $request, string $progressId): JsonResponse
    {
        Gate::authorize('iam.api_integration.manage');

        if (!Str::isUuid($progressId)) {
            return response()->json([
                'status' => 'error',
                'message' => 'ID progres tidak valid.',
            ], 404);
        }
        $progress = null;
        $progressKey = null;
        foreach (['siakad-mahasiswa-pddikti-sync:', 'siakad-dosen-pddikti-sync:', 'siakad-kelas-pddikti-sync:', 'siakad-prodi-pddikti-sync:', 'siakad-matkul-pddikti-sync:', 'siakad-skala-pddikti-sync:'] as $prefix) {
            if ($progress = Cache::get($prefix . $progressId)) {
                $progressKey = $prefix . $progressId;
                break;
            }
        }
        if (!is_array($progress)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Proses sinkronisasi tidak ditemukan atau sudah kedaluwarsa.',
            ], 404);
        }
        $isOwner = (int) ($progress['requested_by'] ?? 0) === (int) $request->user()?->id;
        if (!$isOwner && !$request->user()?->isSuperAdmin()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki akses ke progres sinkronisasi ini.',
            ], 403);
        }

        return response()->json([
            'status' => 'success',
            'message' => match (true) {
                str_contains((string) $progressKey, 'dosen') => 'Progres sinkronisasi dosen berhasil diambil.',
                str_contains((string) $progressKey, 'kelas') => 'Progres sinkronisasi kelas berhasil diambil.',
                str_contains((string) $progressKey, 'prodi') => 'Progres sinkronisasi program studi berhasil diambil.',
                str_contains((string) $progressKey, 'matkul') => 'Progres sinkronisasi mata kuliah berhasil diambil.',
                str_contains((string) $progressKey, 'skala') => 'Progres sinkronisasi skala nilai berhasil diambil.',
                default => 'Progres sinkronisasi mahasiswa berhasil diambil.',
            },
            'data' => $progress,
        ]);
    }

    private function startPddiktiDosenSync(
        Request $request,
        IamApiIntegration $integration,
        array $oldValues,
        DosenIntegrationSyncService $syncService
    ): JsonResponse {
        if ($request->boolean('preview')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Pratinjau sinkronisasi dosen belum tersedia; jalankan Uji Koneksi untuk pemeriksaan read-only.',
            ], 422);
        }

        try {
            $result = $syncService->sync($integration, $request->user()?->id);
        } catch (ApiIntegrationSyncException $exception) {
            return response()->json(['status' => 'error', 'message' => $exception->getMessage()], $exception->statusCode);
        }

        try {
            AuditLogService::record(
                module: 'IAM',
                action: 'sync_queued',
                tableName: 'iam_api_integrations',
                recordId: $integration->id,
                oldValues: $oldValues,
                newValues: ['progress_id' => $result['progress_id'], 'status' => 'queued'],
                request: $request,
            );
        } catch (Throwable $auditError) {
            \Illuminate\Support\Facades\Log::warning('Gagal mencatat audit enqueue sync dosen PDDIKTI.');
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Sinkronisasi dosen dijadwalkan. Progres diperbarui selama proses berjalan.',
            'data' => ['progress_id' => $result['progress_id']],
        ], 202);
    }

    private function startPddiktiQueuedSync(
        Request $request,
        IamApiIntegration $integration,
        array $oldValues,
        ProgramStudiIntegrationSyncService|MataKuliahIntegrationSyncService|SkalaNilaiIntegrationSyncService $syncService
    ): JsonResponse {
        $label = $syncService->syncLabel();
        if ($request->boolean('preview')) {
            return response()->json([
                'status' => 'error',
                'message' => "Pratinjau sinkronisasi {$label} belum tersedia; jalankan Uji Koneksi untuk pemeriksaan read-only.",
            ], 422);
        }

        try {
            $result = $syncService->queuePddikti($integration, $request->user()?->id);
        } catch (ApiIntegrationSyncException $exception) {
            return response()->json(['status' => 'error', 'message' => $exception->getMessage()], $exception->statusCode);
        }

        try {
            AuditLogService::record(
                module: 'IAM',
                action: 'sync_queued',
                tableName: 'iam_api_integrations',
                recordId: $integration->id,
                oldValues: $oldValues,
                newValues: ['progress_id' => $result['progress_id'], 'status' => 'queued'],
                request: $request,
            );
        } catch (Throwable) {
            \Illuminate\Support\Facades\Log::warning("Gagal mencatat audit enqueue sync {$label} PDDIKTI.");
        }

        return response()->json([
            'status' => 'success',
            'message' => "Sinkronisasi {$label} PDDIKTI dijadwalkan. Progres diperbarui selama proses berjalan.",
            'data' => ['progress_id' => $result['progress_id']],
        ], 202);
    }

    private function startPddiktiKelasSync(
        Request $request,
        IamApiIntegration $integration,
        array $oldValues,
        KelasPddiktiIntegrationSyncService $syncService
    ): JsonResponse {
        if ($request->boolean('preview')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Pratinjau sinkronisasi kelas belum tersedia; jalankan Uji Koneksi untuk pemeriksaan read-only.',
            ], 422);
        }

        try {
            $result = $syncService->sync($integration, $request->user()?->id);
        } catch (ApiIntegrationSyncException $exception) {
            return response()->json(['status' => 'error', 'message' => $exception->getMessage()], $exception->statusCode);
        }

        try {
            AuditLogService::record(
                module: 'IAM',
                action: 'sync_queued',
                tableName: 'iam_api_integrations',
                recordId: $integration->id,
                oldValues: $oldValues,
                newValues: ['progress_id' => $result['progress_id'], 'status' => 'queued'],
                request: $request,
            );
        } catch (Throwable) {
            \Illuminate\Support\Facades\Log::warning('Gagal mencatat audit enqueue sync kelas PDDIKTI.');
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Sinkronisasi kelas PDDIKTI dijadwalkan. Progres diperbarui selama proses berjalan.',
            'data' => ['progress_id' => $result['progress_id']],
        ], 202);
    }

    private function startPddiktiMahasiswaSync(Request $request, IamApiIntegration $integration, array $oldValues)
    {
        if (!$integration->is_active) {
            throw new ApiIntegrationSyncException('Integrasi sedang dinonaktifkan.');
        }
        if (config('queue.default') === 'sync') {
            throw new ApiIntegrationSyncException('Progress real-time memerlukan queue worker. Atur QUEUE_CONNECTION ke database dan jalankan worker queue default.', 503);
        }

        $lock = Cache::lock('siakad-mahasiswa-pddikti-sync:lock', 3600);
        if (!$lock->get()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Sinkronisasi mahasiswa PDDIKTI sedang berjalan. Tunggu hingga selesai.',
            ], 409);
        }

        $progressId = (string) Str::uuid();
        $integration->update([
            'last_synced_at' => now(),
            'last_status' => 'processing',
        ]);

        Cache::put('siakad-mahasiswa-pddikti-sync:' . $progressId, [
            'progress_id' => $progressId,
            'integration_id' => $integration->id,
            'requested_by' => $request->user()?->id,
            'status' => 'queued',
            'phase' => 'queued',
            'progress' => 0,
            'processed' => 0,
            'total' => null,
            'message' => 'Sinkronisasi masuk antrean, menunggu worker.',
            'started_at' => now()->toISOString(),
            'result' => [
                'read_count' => 0,
                'source_total' => 0,
                'created' => 0,
                'updated' => 0,
                'synced' => 0,
                'skipped' => 0,
                'failed' => 0,
                'skipped_items' => [],
            ],
        ], now()->addHours(2));

        try {
            SyncPddiktiMahasiswaJob::dispatch(
                $integration->id,
                $progressId,
                $lock->owner(),
                $request->user()?->id,
            )->afterCommit();
        } catch (Throwable $exception) {
            Cache::forget('siakad-mahasiswa-pddikti-sync:' . $progressId);
            $lock->release();
            $integration->update([
                'last_synced_at' => now(),
                'last_status' => 'failed',
            ]);
            throw new ApiIntegrationSyncException('Tidak dapat menjadwalkan sinkronisasi. Periksa queue worker dan coba lagi.', 503);
        }

        try {
            AuditLogService::record(
                module: 'IAM',
                action: 'sync_queued',
                tableName: 'iam_api_integrations',
                recordId: $integration->id,
                oldValues: $oldValues,
                newValues: ['progress_id' => $progressId, 'status' => 'queued'],
                request: $request,
            );
        } catch (Throwable $auditError) {
            \Illuminate\Support\Facades\Log::warning('Gagal mencatat audit enqueue sync mahasiswa PDDIKTI.');
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Sinkronisasi dijadwalkan. Progres diperbarui selama proses berjalan.',
            'data' => ['progress_id' => $progressId],
        ], 202);
    }
}
