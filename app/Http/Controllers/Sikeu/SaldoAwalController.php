<?php

namespace App\Http\Controllers\Sikeu;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sikeu\StoreSaldoAwalRequest;
use App\Http\Requests\Sikeu\UpdateSaldoAwalRequest;
use App\Models\Sikeu\SaldoAwal;
use App\Services\Sikeu\SaldoAwalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SaldoAwalController extends Controller
{
    public function __construct(private readonly SaldoAwalService $service)
    {
    }

    /**
     * GET /api/v1/sikeu/akuntansi/saldo-awal
     * Data saldo awal aktif + COA neraca + akun penyeimbang + riwayat.
     */
    public function index()
    {
        $this->authorize('sikeu.akuntansi.read');

        return response()->json([
            'status' => 'success',
            'message' => 'Data saldo awal berhasil dimuat.',
            'data' => $this->service->getSaldoAwal(),
        ]);
    }

    /**
     * PUT /api/v1/sikeu/akuntansi/saldo-awal
     * Simpan atau perbarui draft saldo awal.
     */
    public function saveDraft(UpdateSaldoAwalRequest $request)
    {
        $draftLama = SaldoAwal::where('status', 'draft')->latest('id')->first();
        $oldValues = $draftLama?->getOriginal();

        try {
            $header = $this->service->saveDraft($request->validated(), $request->user()?->id);

            try {
                \App\Services\AuditLogService::record(
                    module: 'SIKEU',
                    action: $draftLama ? 'update' : 'create',
                    tableName: 'sikeu_saldo_awal',
                    recordId: $header->id,
                    oldValues: $oldValues,
                    newValues: $header->getChanges() ?: $header->toArray(),
                    request: $request,
                );
            } catch (\Throwable $e) {
                Log::warning('Gagal mencatat audit log saldo awal (simpan draft): ' . $e->getMessage());
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Draft saldo awal berhasil disimpan.',
                'data' => $header,
            ], $draftLama ? 200 : 201);
        } catch (\DomainException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 400);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal menyimpan saldo awal: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /api/v1/sikeu/akuntansi/saldo-awal
     * Posting draft menjadi jurnal umum (terkunci).
     */
    public function post(StoreSaldoAwalRequest $request)
    {
        $draftSebelum = SaldoAwal::where('status', 'draft')->latest('id')->first();

        try {
            $header = $this->service->post($request->user()?->id);

            try {
                \App\Services\AuditLogService::record(
                    module: 'SIKEU',
                    action: 'update',
                    tableName: 'sikeu_saldo_awal',
                    recordId: $header->id,
                    oldValues: $draftSebelum?->getOriginal(),
                    newValues: $header->getChanges() ?: $header->toArray(),
                    request: $request,
                );
            } catch (\Throwable $e) {
                Log::warning('Gagal mencatat audit log saldo awal (posting): ' . $e->getMessage());
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Saldo awal berhasil diposting ke jurnal umum.',
                'data' => $header,
            ], 201);
        } catch (\DomainException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 400);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal memposting saldo awal: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * DELETE /api/v1/sikeu/akuntansi/saldo-awal
     * Hapus draft atau batalkan posting (kembali ke draft).
     */
    public function destroy(Request $request)
    {
        $this->authorize('sikeu.akuntansi.manage');

        $postedSebelum = SaldoAwal::where('status', 'posted')->latest('id')->first();
        $draftSebelum = SaldoAwal::where('status', 'draft')->latest('id')->first();

        try {
            $header = DB::transaction(fn () => $this->service->cancel());
            $adalahPembatalan = $header !== null;
            $recordLama = $adalahPembatalan ? $postedSebelum : $draftSebelum;

            try {
                \App\Services\AuditLogService::record(
                    module: 'SIKEU',
                    action: $adalahPembatalan ? 'update' : 'delete',
                    tableName: 'sikeu_saldo_awal',
                    recordId: $recordLama?->id,
                    oldValues: $recordLama?->toArray(),
                    newValues: $adalahPembatalan
                        ? ($header->getChanges() ?: $header->toArray())
                        : null,
                    request: $request,
                );
            } catch (\Throwable $e) {
                Log::warning('Gagal mencatat audit log saldo awal (pembatalan): ' . $e->getMessage());
            }

            return response()->json([
                'status' => 'success',
                'message' => $header
                    ? 'Posting saldo awal berhasil dibatalkan dan dikembalikan menjadi draft.'
                    : 'Draft saldo awal berhasil dihapus.',
                'data' => null,
            ]);
        } catch (\DomainException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 400);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal membatalkan saldo awal: ' . $e->getMessage(),
            ], 500);
        }
    }
}
