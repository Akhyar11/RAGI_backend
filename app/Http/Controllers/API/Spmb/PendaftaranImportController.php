<?php

namespace App\Http\Controllers\API\Spmb;

use App\Http\Controllers\Controller;
use App\Http\Requests\Spmb\ImportPendaftaranRequest;
use App\Services\AuditLogService;
use App\Services\Spmb\SpmbPendaftaranImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PendaftaranImportController extends Controller
{
    public function __construct(private readonly SpmbPendaftaranImportService $service) {}

    public function template(Request $request)
    {
        abort_unless($request->user()?->hasPermission('spmb.manage'), 403, 'Akses ditolak.');

        $fileName = 'template-import-pendaftar.xlsx';

        try {
            AuditLogService::record(
                module: 'SPMB',
                action: 'pendaftaran_import_download_template',
                tableName: 'spmb_pendaftaran_calon_mhs',
                newValues: ['file_name' => $fileName],
                request: $request,
            );
        } catch (\Throwable $exception) {
            Log::warning('Gagal mencatat audit log download template import: '.$exception->getMessage());
        }

        return response()
            ->download($this->service->buildTemplate(), $fileName)
            ->deleteFileAfterSend(true);
    }

    public function preview(ImportPendaftaranRequest $request): JsonResponse
    {
        $result = $this->service->preview($request->file('file')->getRealPath());

        return response()->json([
            'status' => 'success',
            'message' => 'Pratinjau import berhasil dibuat. Belum ada data yang disimpan.',
            'data' => $result,
        ]);
    }

    public function import(ImportPendaftaranRequest $request): JsonResponse
    {
        $result = $this->service->import(
            $request->file('file')->getRealPath(),
            (int) $request->user()->id,
            $request,
        );

        return response()->json([
            'status' => 'success',
            'message' => "{$result['summary']['imported']} dari {$result['summary']['total']} pendaftar berhasil diimpor.",
            'data' => $result,
        ], 201);
    }
}
