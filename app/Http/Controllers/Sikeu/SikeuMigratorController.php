<?php

namespace App\Http\Controllers\Sikeu;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sikeu\StartSikeuMigrationRequest;
use App\Http\Requests\Sikeu\UpdateSikeuAkunMappingRequest;
use App\Jobs\MigrateSikeuJob;
use App\Models\Sikeu\SikeuLegacyAkunMapping;
use App\Services\AuditLogService;
use App\Services\Sikeu\SikeuMigratorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Throwable;

class SikeuMigratorController extends Controller
{
    public function __construct(protected SikeuMigratorService $migratorService) {}

    /**
     * Menampilkan ringkasan status migrasi data SIKEU di RAG.
     */
    public function status(): JsonResponse
    {
        Gate::authorize('sikeu.akuntansi.read');

        $status = $this->migratorService->getStatus();

        return response()->json([
            'status'  => 'success',
            'message' => 'Status migrasi data SIKEU berhasil diambil',
            'data'    => $status,
        ]);
    }

    /**
     * Memulai proses migrasi data di background queue.
     */
    public function start(StartSikeuMigrationRequest $request): JsonResponse
    {
        Gate::authorize('sikeu.akuntansi.manage');

        $options = $request->validated();
        $options['user_id'] = $request->user()?->id;
        $dispatchedAt = now()->toISOString();

        // Dispatch background queue job agar tidak terkena timeout web browser
        MigrateSikeuJob::dispatch($options);

        try {
            AuditLogService::record(
                module: 'SIKEU',
                action: 'start_migration',
                tableName: 'sikeu_jurnal_umum',
                recordId: null,
                oldValues: [],
                newValues: array_merge($options, ['dispatched_at' => $dispatchedAt]),
                request: $request
            );
        } catch (Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Gagal mencatat audit log start migration SIKEU: ' . $e->getMessage());
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Migrasi data SIKEU telah dijadwalkan di background queue.',
            'data'    => [
                'options'       => $options,
                'dispatched_at' => $dispatchedAt,
            ],
        ], 200);
    }

    /**
     * Menampilkan daftar pemetaan akun (sikeu_mig_akun_mapping).
     */
    public function mappings(Request $request): JsonResponse
    {
        Gate::authorize('sikeu.akuntansi.read');

        $result = $this->migratorService->getMappings($request);

        return response()->json([
            'status'  => 'success',
            'message' => 'Daftar mapping akun legacy berhasil diambil',
            'data'    => $result['data'],
            'meta'    => $result['meta'],
        ]);
    }

    /**
     * Memperbarui target COA untuk sebuah kode perkiraan legacy.
     */
    public function updateMapping(UpdateSikeuAkunMappingRequest $request, int $id): JsonResponse
    {
        Gate::authorize('sikeu.akuntansi.manage');

        $result = $this->migratorService->updateMapping($id, $request->validated());

        try {
            AuditLogService::record(
                module: 'SIKEU',
                action: 'update_mapping',
                tableName: $result['model']->getTable(),
                recordId: $result['model']->id,
                oldValues: $result['old_values'],
                newValues: $result['new_values'],
                request: $request
            );
        } catch (Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Gagal mencatat audit log update mapping akun SIKEU: ' . $e->getMessage());
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Mapping akun legacy berhasil diperbarui',
            'data'    => $result['model'],
        ]);
    }

    /**
     * Reset data jurnal / pembayaran hasil migrasi (hanya yang memiliki legacy_source).
     */
    public function reset(Request $request): JsonResponse
    {
        Gate::authorize('sikeu.akuntansi.manage');

        $request->validate([
            'confirm' => 'required|accepted',
            'type'    => 'nullable|string|in:all,jurnal,pembayaran',
            'source'  => ['nullable', 'string', \Illuminate\Validation\Rule::in(config('sikeu.migrator.sources', ['all', 'indonusa', 'sikeudb']))],
        ]);

        $type = $request->input('type', 'jurnal');
        $source = $request->input('source', 'all');

        $resetOutput = $this->migratorService->resetData($type, $source);
        $result = $resetOutput['result'];
        $snapshot = $resetOutput['snapshot'];

        try {
            AuditLogService::record(
                module: 'SIKEU',
                action: 'reset_migrated_data',
                tableName: ($type === 'pembayaran' ? 'sikeu_pembayaran' : 'sikeu_jurnal_umum'),
                recordId: null,
                oldValues: [
                    'type'     => $type,
                    'source'   => $source,
                    'snapshot' => $snapshot,
                ],
                newValues: $result,
                request: $request
            );
        } catch (Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Gagal mencatat audit log reset migration SIKEU: ' . $e->getMessage());
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Data hasil migrasi berhasil dibersihkan',
            'data'    => $result,
        ]);
    }
}
