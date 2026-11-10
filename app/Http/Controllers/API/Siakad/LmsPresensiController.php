<?php

namespace App\Http\Controllers\API\Siakad;

use App\Http\Controllers\Controller;
use App\Http\Requests\Siakad\Lms\ExportLmsRequest;
use App\Services\AuditLogService;
use App\Services\Siakad\LmsExportService;
use App\Services\Siakad\LmsPresensiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Monitoring dan ekspor presensi yang tersimpan di RAG/SIAKAD.
 */
class LmsPresensiController extends Controller
{
    public function __construct(
        protected LmsPresensiService $lmsPresensiService,
        protected LmsExportService $lmsExportService
    ) {}

    /**
     * Daftar kelas yang memiliki sesi presensi beserta ringkasan absensinya.
     */
    public function kelas(Request $request): JsonResponse
    {
        Gate::authorize('lms.presensi.read');

        $perPage = min(100, max(1, $request->integer('per_page', 15)));
        $search = $request->filled('search') ? trim((string) $request->input('search')) : null;
        $allowedSorts = ['kode_kelas', 'nama_kelas', 'jumlah_sesi', 'created_at'];
        $sortBy = in_array($request->input('sort_by'), $allowedSorts, true)
            ? $request->input('sort_by')
            : 'kode_kelas';
        $sortOrder = $request->input('sort_order') === 'asc' ? 'asc' : 'desc';
        $tahunAkademikId = $request->filled('tahun_akademik_id')
            ? (int) $request->input('tahun_akademik_id')
            : null;

        $paginator = $this->lmsPresensiService->daftarKelas(
            [
                'search'            => $search,
                'tahun_akademik_id' => $tahunAkademikId,
            ],
            $perPage,
            $sortBy,
            $sortOrder
        );

        return response()->json([
            'status'  => 'success',
            'message' => 'Daftar kelas presensi berhasil diambil.',
            'data'    => $paginator->items(),
            'meta'    => [
                'current_page' => $paginator->currentPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
                'last_page'    => $paginator->lastPage(),
                'from'         => $paginator->firstItem(),
                'to'           => $paginator->lastItem(),
            ],
            'filters' => [
                'search'            => $search,
                'sort_by'           => $sortBy,
                'sort_order'        => $sortOrder,
                'tahun_akademik_id' => $tahunAkademikId,
            ],
        ]);
    }

    /**
     * Rekap kehadiran seluruh kelas (satu baris per mahasiswa per pertemuan).
     */
    public function rekap(Request $request): JsonResponse
    {
        Gate::authorize('lms.presensi.read');

        $perPage = min(100, max(1, $request->integer('per_page', 15)));
        $search = $request->filled('search') ? trim((string) $request->input('search')) : null;
        $kelasId = $request->filled('kelas_id') ? (int) $request->input('kelas_id') : null;
        $pertemuanKe = $request->filled('pertemuan_ke') ? (int) $request->input('pertemuan_ke') : null;
        $status = $request->filled('status') ? (string) $request->input('status') : null;
        $tahunAkademikId = $request->filled('tahun_akademik_id') ? (int) $request->input('tahun_akademik_id') : null;

        $paginator = $this->lmsPresensiService->rekap(
            [
                'search'            => $search,
                'kelas_id'          => $kelasId,
                'pertemuan_ke'      => $pertemuanKe,
                'status'            => $status,
                'tahun_akademik_id' => $tahunAkademikId,
            ],
            $perPage
        );

        return response()->json([
            'status'  => 'success',
            'message' => 'Rekap kehadiran berhasil diambil.',
            'data'    => $paginator->items(),
            'meta'    => [
                'current_page' => $paginator->currentPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
                'last_page'    => $paginator->lastPage(),
                'from'         => $paginator->firstItem(),
                'to'           => $paginator->lastItem(),
            ],
            'filters' => [
                'search'            => $search,
                'kelas_id'          => $kelasId,
                'pertemuan_ke'      => $pertemuanKe,
                'status'            => $status,
                'tahun_akademik_id' => $tahunAkademikId,
            ],
        ]);
    }

    /**
     * Export rekap kehadiran presensi dalam format CSV/XLSX.
     */
    public function export(ExportLmsRequest $request): StreamedResponse
    {
        Gate::authorize('lms.presensi.manage');

        $filters = [
            'search'            => $request->filled('search') ? trim((string) $request->input('search')) : null,
            'kelas_id'          => $request->filled('kelas_id') ? (int) $request->input('kelas_id') : null,
            'pertemuan_ke'      => $request->filled('pertemuan_ke') ? (int) $request->input('pertemuan_ke') : null,
            'status'            => $request->filled('status') ? (string) $request->input('status') : null,
            'tahun_akademik_id' => $request->filled('tahun_akademik_id') ? (int) $request->input('tahun_akademik_id') : null,
        ];

        $data = $this->lmsPresensiService->barisRekap($filters);
        $format = $request->exportFormat();

        AuditLogService::record(
            module: 'LMS',
            action: 'export',
            tableName: 'siakad_absensi_mahasiswa',
            recordId: $filters['kelas_id'],
            newValues: [
                'jenis_laporan' => 'rekap_presensi_admin',
                'format'        => $format,
                'jumlah_baris'  => count($data['rows']),
                'filter'        => array_filter($filters, fn ($v) => $v !== null),
            ],
            request: $request
        );

        return $this->lmsExportService->download($data['filename'], $data['headers'], $data['rows'], $format);
    }

    /**
     * Monitoring keaktifan dosen mengajar & pengisian presensi kelas perkuliahan.
     */
    public function keaktifanDosen(Request $request): JsonResponse
    {
        Gate::authorize('lms.presensi.read');

        $perPage = min(100, max(1, $request->integer('per_page', 15)));
        $search = $request->filled('search') ? trim((string) $request->query('search')) : null;
        $programStudiId = $request->filled('program_studi_id')
            ? (int) $request->query('program_studi_id')
            : null;

        $paginator = $this->lmsPresensiService->monitoringKeaktifanDosen([
            'search' => $search,
            'program_studi_id' => $programStudiId,
        ], $perPage);

        return response()->json([
            'status'  => 'success',
            'message' => 'Data monitoring keaktifan dosen berhasil diambil.',
            'data'    => $paginator->items(),
            'meta'    => [
                'current_page' => $paginator->currentPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
                'last_page'    => $paginator->lastPage(),
                'from'         => $paginator->firstItem(),
                'to'           => $paginator->lastItem(),
            ],
            'filters' => [
                'search' => $search,
                'program_studi_id' => $programStudiId,
            ],
        ]);
    }

    /**
     * Export laporan keaktifan mengajar dosen (CSV/XLSX).
     */
    public function exportKeaktifanDosen(ExportLmsRequest $request): StreamedResponse
    {
        Gate::authorize('lms.presensi.manage');

        $format = $request->exportFormat();
        $payload = $this->lmsPresensiService->barisKeaktifanDosen([
            'search' => $request->query('search'),
            'program_studi_id' => $request->query('program_studi_id'),
        ]);

        try {
            AuditLogService::record(
                module: 'LMS',
                action: 'export_keaktifan_dosen',
                tableName: 'siakad_kelas',
                recordId: null,
                newValues: ['format' => $format, 'total_baris' => count($payload['rows'])],
                request: $request
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Gagal mencatat audit log export keaktifan dosen: ' . $e->getMessage());
        }

        return $this->lmsExportService->download(
            filenameBase: $payload['filename'],
            headers: $payload['headers'],
            rows: $payload['rows'],
            format: $format
        );
    }
}
