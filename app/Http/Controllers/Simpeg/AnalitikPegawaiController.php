<?php

namespace App\Http\Controllers\Simpeg;

use App\Http\Controllers\Controller;
use App\Services\AuditLogService;
use App\Services\Simpeg\AnalitikPegawaiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AnalitikPegawaiController extends Controller
{
    public function __construct(
        protected AnalitikPegawaiService $service
    ) {}

    /**
     * Menampilkan ringkasan metrik statistik agregat pegawai (JAD, TMT, Unit Kerja, Serdos).
     */
    public function ringkasan(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasPermission('simpeg.pegawai.read') && !$user->isAdmin()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses untuk melihat analitik pegawai.',
            ], 403);
        }

        $filters = $request->only([
            'search',
            'tmt_start',
            'tmt_end',
            'jabatan_fungsional_id',
            'unit_kerja_id',
            'status_kepegawaian',
            'jenis_pegawai',
            'is_serdos',
        ]);

        $ringkasan = $this->service->getRingkasan($filters);

        return response()->json([
            'status' => 'success',
            'message' => 'Ringkasan analitik pegawai berhasil dimuat.',
            'data' => $ringkasan,
        ]);
    }

    /**
     * Menampilkan daftar pegawai hasil filter analitik dengan pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasPermission('simpeg.pegawai.read') && !$user->isAdmin()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses untuk melihat data analitik pegawai.',
            ], 403);
        }

        $filters = $request->only([
            'search',
            'tmt_start',
            'tmt_end',
            'jabatan_fungsional_id',
            'unit_kerja_id',
            'status_kepegawaian',
            'jenis_pegawai',
            'is_serdos',
            'sort_by',
        ]);

        $sortOrder = strtolower($request->string('sort_order', 'desc')->value());
        $filters['sort_order'] = in_array($sortOrder, ['asc', 'desc'], true) ? $sortOrder : 'desc';

        $perPage = max(1, min(100, $request->integer('per_page', 15)));
        $paginated = $this->service->getPaginated($filters, $perPage);

        return response()->json([
            'status' => 'success',
            'message' => 'Data analitik pegawai berhasil diambil.',
            'data' => $paginated->items(),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
                'last_page' => $paginated->lastPage(),
                'from' => $paginated->firstItem(),
                'to' => $paginated->lastItem(),
                'filters' => $filters,
            ],
        ]);
    }

    /**
     * Mengunduh rekapitulasi data pegawai ke file Excel (.xlsx).
     */
    public function exportExcel(Request $request): StreamedResponse|JsonResponse
    {
        $user = $request->user();
        if (!$user->hasPermission('simpeg.pegawai.read') && !$user->isAdmin()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses untuk mengekspor data analitik pegawai.',
            ], 403);
        }

        $filters = $request->only([
            'search',
            'tmt_start',
            'tmt_end',
            'jabatan_fungsional_id',
            'unit_kerja_id',
            'status_kepegawaian',
            'jenis_pegawai',
            'is_serdos',
            'sort_by',
        ]);

        $sortOrder = strtolower($request->string('sort_order', 'desc')->value());
        $filters['sort_order'] = in_array($sortOrder, ['asc', 'desc'], true) ? $sortOrder : 'desc';

        try {
            AuditLogService::record(
                module: 'simpeg',
                action: 'export',
                tableName: 'simpeg_pegawai',
                recordId: null,
                oldValues: null,
                newValues: $filters,
                request: $request
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal mencatat audit log ekspor analitik pegawai: ' . $e->getMessage());
        }

        return $this->service->exportExcel($filters);
    }
}
