<?php

namespace App\Http\Controllers\IAM;

use App\Http\Controllers\Controller;
use App\Http\Requests\IAM\UpdateApiIntegrationRequest;
use App\Http\Requests\IAM\TestApiIntegrationRequest;
use App\Models\IamApiIntegration;
use App\Services\AuditLogService;
use App\Services\IAM\ApiIntegrationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
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

        $integration->fill([
            'url_api' => $request->url_api,
            'auth_type' => $request->auth_type,
            'is_active' => $request->boolean('is_active'),
        ]);

        if ($request->has('token')) {
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
}
