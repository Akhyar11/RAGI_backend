<?php

namespace App\Http\Controllers\API\Siakad;

use App\Http\Controllers\Controller;
use App\Http\Requests\Siakad\Lms\ExportLmsRequest;
use App\Http\Requests\Siakad\Lms\SyncPresensiRequest;
use App\Http\Requests\Siakad\Lms\TarikPresensiRequest;
use App\Services\AuditLogService;
use App\Services\Siakad\LmsExportService;
use App\Services\Siakad\LmsPresensiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Panel administrasi integrasi presensi LMS (LMS-03).
 *
 * Menyediakan monitoring & sinkronisasi dua arah antara LMS dan aplikasi
 * PresensiV2: rekap kehadiran lintas kelas, jejak sinkronisasi, percobaan
 * sinkron ulang, dan tarik riwayat dari PresensiV2.
 *
 * Otorisasi memakai permission granular modul LMS:
 *  - `lms.presensi.read`   → membaca status, rekap, dan log sinkronisasi.
 *  - `lms.presensi.manage` → sinkron ulang, tarik riwayat, dan export.
 */
class LmsPresensiController extends Controller
{
    public function __construct(
        protected LmsPresensiService $lmsPresensiService,
        protected LmsExportService $lmsExportService
    ) {}

    /**
     * Status integrasi PresensiV2 + feature flag + ringkasan sinkronisasi.
     */
    public function status(Request $request): JsonResponse
    {
        Gate::authorize('lms.presensi.read');

        return response()->json([
            'status'  => 'success',
            'message' => 'Status integrasi presensi berhasil diambil.',
            'data'    => $this->lmsPresensiService->status(),
        ]);
    }

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
        $syncStatus = $request->filled('sync_status') ? (string) $request->input('sync_status') : null;

        $paginator = $this->lmsPresensiService->rekap(
            [
                'search'       => $search,
                'kelas_id'     => $kelasId,
                'pertemuan_ke' => $pertemuanKe,
                'status'       => $status,
                'sync_status'  => $syncStatus,
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
                'search'        => $search,
                'kelas_id'      => $kelasId,
                'pertemuan_ke' => $pertemuanKe,
                'status'        => $status,
                'sync_status'   => $syncStatus,
            ],
        ]);
    }

    /**
     * Jejak percobaan sinkronisasi ke PresensiV2.
     */
    public function log(Request $request): JsonResponse
    {
        Gate::authorize('lms.presensi.read');

        $perPage = min(100, max(1, $request->integer('per_page', 15)));
        $status = $request->filled('status') ? (string) $request->input('status') : null;
        $kelasId = $request->filled('kelas_id') ? (int) $request->input('kelas_id') : null;

        $paginator = $this->lmsPresensiService->logSinkronisasi($perPage, $status, $kelasId);

        return response()->json([
            'status'  => 'success',
            'message' => 'Log sinkronisasi presensi berhasil diambil.',
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
                'status'   => $status,
                'kelas_id' => $kelasId,
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
            'search'        => $request->filled('search') ? trim((string) $request->input('search')) : null,
            'kelas_id'      => $request->filled('kelas_id') ? (int) $request->input('kelas_id') : null,
            'pertemuan_ke' => $request->filled('pertemuan_ke') ? (int) $request->input('pertemuan_ke') : null,
            'status'        => $request->filled('status') ? (string) $request->input('status') : null,
            'sync_status'   => $request->filled('sync_status') ? (string) $request->input('sync_status') : null,
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
     * Sinkronkan ulang jurnal & kehadiran ke PresensiV2.
     */
    public function sync(SyncPresensiRequest $request): JsonResponse
    {
        Gate::authorize('lms.presensi.manage');

        $result = $this->lmsPresensiService->syncUlang($request->pertemuanId(), $request->kelasId());

        try {
            AuditLogService::record(
                module: 'LMS',
                action: 'sync_presensi',
                tableName: 'siakad_pertemuan',
                recordId: $request->pertemuanId() ?? $request->kelasId(),
                newValues: [
                    'kelas_id'     => $request->kelasId(),
                    'pertemuan_id' => $request->pertemuanId(),
                    'sukses'       => $result['success'] ?? false,
                ],
                request: $request
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Gagal mencatat audit log sync presensi: ' . $e->getMessage());
        }

        if (!($result['success'] ?? false)) {
            $payload = [
                'status'  => 'error',
                'message' => $result['message'] ?? 'Sinkronisasi gagal diproses.',
            ];

            if (isset($result['errors']) && is_array($result['errors'])) {
                $payload['errors'] = $result['errors'];
            }

            $statusCode = is_int($result['code'] ?? null) && $result['code'] >= 400 && $result['code'] < 600
                ? $result['code']
                : 422;

            return response()->json($payload, $statusCode);
        }

        return response()->json([
            'status'  => 'success',
            'message' => $result['message'] ?? 'Sinkronisasi selesai.',
            'data'    => $result,
        ]);
    }

    /**
     * Tarik riwayat jurnal & rekap dari PresensiV2 ke LMS.
     */
    public function tarik(TarikPresensiRequest $request): JsonResponse
    {
        Gate::authorize('lms.presensi.manage');

        $result = $this->lmsPresensiService->tarikRiwayat($request->kelasId());

        try {
            AuditLogService::record(
                module: 'LMS',
                action: 'tarik_presensi',
                tableName: 'siakad_kelas',
                recordId: $request->kelasId(),
                newValues: [
                    'sukses' => $result['success'] ?? false,
                    'via'    => $result['via'] ?? null,
                ],
                request: $request
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Gagal mencatat audit log tarik presensi: ' . $e->getMessage());
        }

        if (!($result['success'] ?? false)) {
            $payload = [
                'status'  => 'error',
                'message' => $result['message'] ?? 'Penarikan riwayat gagal diproses.',
            ];

            if (isset($result['errors']) && is_array($result['errors'])) {
                $payload['errors'] = $result['errors'];
            }

            $statusCode = is_int($result['code'] ?? null) && $result['code'] >= 400 && $result['code'] < 600
                ? $result['code']
                : 422;

            return response()->json($payload, $statusCode);
        }

        return response()->json([
            'status'  => 'success',
            'message' => $result['message'] ?? 'Penarikan riwayat selesai.',
            'data'    => $result,
        ]);
    }
}
