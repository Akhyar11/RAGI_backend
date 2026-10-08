<?php

namespace App\Http\Controllers\Sinapra;

use App\Http\Controllers\Controller;
use App\Models\Sinapra\Gedung;
use App\Models\Sinapra\Ruangan;
use App\Models\Sinapra\Aset;
use App\Models\Sinapra\KategoriAset;
use App\Services\AuditLogService;
use App\Services\Sinapra\SinapraImportService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SinapraImportController extends Controller
{
    protected array $allowedEntities = [
        'gedung',
        'tipe-ruangan',
        'ruangan',
        'kategori-aset',
        'kategori-bhp',
        'satuan',
        'vendor',
        'aset',
    ];

    public function __construct(private SinapraImportService $importService) {}

    /**
     * Endpoint untuk import file Excel / CSV ke entitas SINAPRA
     */
    public function import(Request $request, string $entity): JsonResponse
    {
        if (!in_array($entity, $this->allowedEntities)) {
            return response()->json([
                'status' => 'error',
                'message' => "Entitas '{$entity}' tidak valid atau belum didukung untuk import.",
                'errors' => [
                    'entity' => ["Entitas '{$entity}' tidak ditemukan."],
                ],
            ], 422);
        }

        $this->authorizeEntity($request, $entity, 'create');

        $request->validate([
            'file' => [
                'required',
                'file',
                'mimes:xlsx,xls,csv,txt',
                'max:10240', // Maks 10MB
            ],
        ], [
            'file.required' => 'File Excel wajib diunggah.',
            'file.file' => 'Berkas yang diunggah harus berupa file yang valid.',
            'file.mimes' => 'Format file harus berupa .xlsx, .xls, atau .csv.',
            'file.max' => 'Ukuran file tidak boleh melebihi 10MB.',
        ]);

        $result = $this->importService->import($entity, $request->file('file'), $request->user());

        if ($result['status'] === 'error') {
            return response()->json([
                'status' => 'error',
                'message' => $result['message'],
            ], 400);
        }

        $statusCode = ($result['created'] > 0) ? 201 : 200;

        $data = [
            'created' => $result['created'],
            'updated' => $result['updated'],
            'failed' => $result['failed'],
            'total_processed' => $result['total_processed'],
            'errors' => $result['errors'],
        ];

        return response()->json([
            'status' => 'success',
            'message' => $result['message'],
            'data' => $data,
        ], $statusCode);
    }

    /**
     * Endpoint untuk mengunduh template Excel (.xlsx) per entitas
     */
    public function downloadTemplate(Request $request, string $entity): StreamedResponse|JsonResponse
    {
        if (!in_array($entity, $this->allowedEntities)) {
            return response()->json([
                'status' => 'error',
                'message' => "Entitas '{$entity}' tidak valid.",
                'errors' => [
                    'entity' => ["Entitas '{$entity}' tidak ditemukan."],
                ],
            ], 422);
        }

        $this->authorizeEntity($request, $entity, 'read');

        try {
            AuditLogService::record(
                'SINAPRA',
                'download_template',
                "sinapra_{$entity}",
                null,
                null,
                [
                    'entity' => $entity,
                    'user_id' => $request->user()?->id,
                ]
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal mencatat audit log download template SINAPRA: ' . $e->getMessage());
        }

        $spreadsheet = $this->importService->generateTemplate($entity);
        $writer = new Xlsx($spreadsheet);
        $filename = "Template_Import_Sinapra_{$entity}.xlsx";

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'max-age=0',
        ]);
    }

    /**
     * Validasi otorisasi pengguna berdasarkan entitas
     */
    protected function authorizeEntity(Request $request, string $entity, string $ability = 'create'): void
    {
        $user = $request->user();

        $permissionMap = [
            'gedung' => [
                'read' => 'sinapra.gedung.read',
                'create' => 'sinapra.gedung.create',
            ],
            'tipe-ruangan' => [
                'read' => 'sinapra.tipe_ruangan.read',
                'create' => 'sinapra.tipe_ruangan.create',
            ],
            'ruangan' => [
                'read' => 'sinapra.ruangan.read',
                'create' => 'sinapra.ruangan.create',
            ],
            'kategori-aset' => [
                'read' => 'sinapra.kategori_aset.read',
                'create' => 'sinapra.kategori_aset.create',
            ],
            'kategori-bhp' => [
                'read' => 'sinapra.kategori_bhp.read',
                'create' => 'sinapra.kategori_bhp.create',
            ],
            'satuan' => [
                'read' => 'sinapra.satuan.read',
                'create' => 'sinapra.satuan.create',
            ],
            'vendor' => [
                'read' => 'sinapra.vendor.read',
                'create' => 'sinapra.vendor.create',
            ],
            'aset' => [
                'read' => 'sinapra.aset.read',
                'create' => 'sinapra.aset.create',
            ],
        ];

        $requiredPermission = $permissionMap[$entity][$ability] ?? null;

        $hasAccess = $user && method_exists($user, 'hasPermission') && (
            ($requiredPermission && $user->hasPermission($requiredPermission)) ||
            $user->hasPermission('sinapra.master.manage')
        );

        if (!$hasAccess) {
            abort(403, "Anda tidak memiliki hak akses untuk melakukan operasi ini pada entitas {$entity}.");
        }
    }
}
