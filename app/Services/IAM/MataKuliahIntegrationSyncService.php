<?php

namespace App\Services\IAM;

use App\Models\IamApiIntegration;
use App\Models\Siakad\Kurikulum;
use App\Models\Siakad\MataKuliah;
use App\Models\Siakad\ProgramStudi;
use App\Services\AuditLogService;
use App\Services\Siakad\NeoFeederService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MataKuliahIntegrationSyncService
{
    private const MAX_SOURCE_PAGES = 100;
    private const PDDIKTI_BATCH_SIZE = 500;

    public function __construct(private NeoFeederService $neoFeeder) {}

    public function sync(IamApiIntegration $integration): array
    {
        if (!$integration->is_active) {
            throw new ApiIntegrationSyncException('Integrasi sedang dinonaktifkan.');
        }

        if ($integration->key !== 'siakad_mata_kuliah') {
            throw new ApiIntegrationSyncException('Aksi sync belum didukung untuk integrasi ini.');
        }

        if (($integration->source_type ?? 'custom_api') === 'pddikti') {
            return $this->syncPddikti();
        }

        if (empty($integration->url_api)) {
            throw new ApiIntegrationSyncException('URL API belum dikonfigurasi.');
        }

        if (strtoupper($integration->http_method ?? 'GET') !== 'GET') {
            throw new ApiIntegrationSyncException('Sinkronisasi mata kuliah hanya mendukung method GET.');
        }

        $sourceRows = $this->fetchSourceRows($integration);

        return DB::transaction(function () use ($sourceRows): array {
            $result = [
                'source_total' => count($sourceRows),
                'created' => 0,
                'updated' => 0,
                'skipped' => 0,
                'skipped_items' => [],
            ];
            $seenCodes = [];
            $seenSourceIds = [];

            foreach ($sourceRows as $index => $row) {
                $reason = $this->validateRow($row);
                if ($reason !== null) {
                    $this->skip($result, $index, $row, $reason);
                    continue;
                }

                $code = trim($row['kode_mk']);
                $sourceId = (string) $row['source_id'];
                if (isset($seenCodes[$code])) {
                    $this->skip($result, $index, $row, 'Kode mata kuliah duplikat dalam data sumber.');
                    continue;
                }
                if (isset($seenSourceIds[$sourceId])) {
                    $this->skip($result, $index, $row, 'source_id duplikat dalam data sumber.');
                    continue;
                }
                $seenCodes[$code] = true;
                $seenSourceIds[$sourceId] = true;

                $curriculum = Kurikulum::withTrashed()->where('kode', trim($row['kurikulum_kode']))->first();
                if (!$curriculum) {
                    $this->skip($result, $index, $row, 'Kode kurikulum tidak ditemukan di SIAKAD tujuan.');
                    continue;
                }
                if ($curriculum->trashed()) {
                    $this->skip($result, $index, $row, 'Kurikulum tujuan sudah dihapus di SIAKAD.');
                    continue;
                }

                $existing = MataKuliah::withTrashed()->where('source_id', $sourceId)->first();
                if (!$existing) {
                    $existingByCode = MataKuliah::withTrashed()->where('kode_mk', $code)->first();
                    if ($existingByCode && $existingByCode->source_id !== null && $existingByCode->source_id !== $sourceId) {
                        $this->skip($result, $index, $row, 'Kode mata kuliah sudah terpetakan ke source_id lain.');
                        continue;
                    }
                    $existing = $existingByCode;
                }

                if ($existing?->trashed()) {
                    $this->skip($result, $index, $row, 'Mata kuliah dengan kode ini sudah dihapus di SIAKAD.');
                    continue;
                }
                if ($existing && (int) $existing->kurikulum_id !== (int) $curriculum->id) {
                    $this->skip($result, $index, $row, 'Kurikulum mata kuliah berubah; perlu rekonsiliasi manual.');
                    continue;
                }

                $attributes = [
                    'kurikulum_id' => $curriculum->id,
                    'source_id' => $sourceId,
                    'kode_mk' => $code,
                    'nama' => trim($row['nama']),
                    'sks_teori' => (int) $row['sks_teori'],
                    'sks_praktik' => (int) $row['sks_praktik'],
                    'total_sks' => (int) $row['total_sks'],
                    'semester_anjuran' => (int) $row['semester_anjuran'],
                    'tipe' => $row['tipe'],
                    'is_active' => $this->normalizeBoolean($row['is_active']),
                ];
                foreach (['kategori', 'jumlah_pertemuan'] as $optionalField) {
                    if (array_key_exists($optionalField, $row)) {
                        $attributes[$optionalField] = $row[$optionalField];
                    }
                }

                if ($existing) {
                    $existing->update($attributes);
                    $result['updated']++;
                } else {
                    MataKuliah::create($attributes);
                    $result['created']++;
                }
            }

            return $result;
        });
    }

    public function testPddiktiConnection(string $action = 'GetMatkulKurikulum'): array
    {
        try {
            $response = $this->neoFeeder->request($action, [
                'limit' => 1,
                'offset' => 0,
            ]);
        } catch (\Throwable $exception) {
            throw new ApiIntegrationSyncException(
                "Koneksi Neo Feeder gagal. Periksa konfigurasi Koneksi Neo Feeder PDDikti dan hak akses {$action}.",
                502
            );
        }

        if (($response['error_code'] ?? null) !== 0) {
            throw new ApiIntegrationSyncException(
                "Neo Feeder menolak permintaan {$action}. Periksa hak akses akun Feeder.",
                502
            );
        }

        $data = $response['data'] ?? [];

        return [
            'action' => $action,
            'data_count' => is_array($data) ? count($data) : 0,
        ];
    }

    private function syncPddikti(): array
    {
        $rows = $this->fetchPddiktiRows();

        return DB::transaction(function () use ($rows): array {
            $result = [
                'source_total' => count($rows),
                'created' => 0,
                'updated' => 0,
                'skipped' => 0,
                'skipped_items' => [],
            ];
            $seenSourceIds = [];
            $seenCodes = [];

            foreach ($rows as $index => $row) {
                if (!is_array($row)) {
                    $this->skip($result, $index, $row, 'Item Neo Feeder bukan object data.');
                    continue;
                }

                $sourceId = trim((string) ($row['id_matkul'] ?? ''));
                $courseCode = trim((string) ($row['kode_mata_kuliah'] ?? ''));
                if ($sourceId === '' || $courseCode === '') {
                    $this->skip($result, $index, $row, 'ID atau kode mata kuliah Neo Feeder kosong.');
                    continue;
                }
                if (isset($seenSourceIds[$sourceId]) || isset($seenCodes[$courseCode])) {
                    $this->skip($result, $index, $row, 'ID atau kode mata kuliah duplikat dalam data PDDIKTI.');
                    continue;
                }
                $seenSourceIds[$sourceId] = true;
                $seenCodes[$courseCode] = true;

                $program = ProgramStudi::query()->where('id_feeder', (string) ($row['id_prodi'] ?? ''))->first();
                if (!$program) {
                    $this->skip($result, $index, $row, 'Program studi PDDIKTI belum dipetakan ke ID Feeder di SIAKAD.');
                    continue;
                }

                $feederCurriculumId = trim((string) ($row['id_kurikulum'] ?? ''));
                $curriculum = $feederCurriculumId !== ''
                    ? Kurikulum::withTrashed()->where('id_feeder', $feederCurriculumId)->first()
                    : null;
                if ($curriculum && (int) $curriculum->program_studi_id !== (int) $program->id) {
                    $this->skip($result, $index, $row, 'ID kurikulum PDDIKTI sudah dipetakan ke program studi lain.');
                    continue;
                }

                if (!$curriculum) {
                    $this->skip($result, $index, $row, 'Kurikulum PDDIKTI belum dimigrasikan. Jalankan sinkronisasi kurikulum terlebih dahulu.');
                    continue;
                }
                if ($curriculum->trashed()) {
                    $this->skip($result, $index, $row, 'Kurikulum tujuan sudah dihapus di SIAKAD.');
                    continue;
                }
                if ($feederCurriculumId !== '' && empty($curriculum->id_feeder)) {
                    $oldCurriculumValues = $curriculum->getOriginal();
                    $curriculum->update(['id_feeder' => $feederCurriculumId]);
                    $newCurriculumValues = $curriculum->getChanges();
                    try {
                        AuditLogService::record(
                            module: 'SIAKAD',
                            action: 'update',
                            tableName: 'siakad_kurikulum',
                            recordId: $curriculum->id,
                            oldValues: $oldCurriculumValues,
                            newValues: $newCurriculumValues,
                            request: request(),
                        );
                    } catch (\Throwable $exception) {
                        Log::warning('Gagal mencatat audit log mapping kurikulum Neo Feeder: ' . $exception->getMessage(), [
                            'kurikulum_id' => $curriculum->id,
                        ]);
                    }
                }

                $normalized = $this->normalizePddiktiCourse($row, $curriculum);
                $validationError = $this->validateRow($normalized);
                if ($validationError !== null) {
                    $this->skip($result, $index, $row, $validationError);
                    continue;
                }

                $existing = MataKuliah::withTrashed()->where('source_id', $sourceId)->first();
                if (!$existing) {
                    $existingByCode = MataKuliah::withTrashed()->where('kode_mk', $courseCode)->first();
                    if ($existingByCode && $existingByCode->source_id !== null && $existingByCode->source_id !== $sourceId) {
                        $this->skip($result, $index, $row, 'Kode mata kuliah sudah dipetakan ke source ID lain.');
                        continue;
                    }
                    $existing = $existingByCode;
                }
                if ($existing?->trashed()) {
                    $this->skip($result, $index, $row, 'Mata kuliah PDDIKTI sudah dihapus di SIAKAD.');
                    continue;
                }
                if ($existing && (int) $existing->kurikulum_id !== (int) $curriculum->id) {
                    $this->skip($result, $index, $row, 'Mata kuliah sudah terhubung ke kurikulum lain; perlu rekonsiliasi manual.');
                    continue;
                }

                $attributes = [
                    'kurikulum_id' => $curriculum->id,
                    'source_id' => $sourceId,
                    'id_feeder' => $sourceId,
                    'kode_mk' => $courseCode,
                    'nama' => trim((string) $row['nama_mata_kuliah']),
                    'sks_teori' => $normalized['sks_teori'],
                    'sks_praktik' => $normalized['sks_praktik'],
                    'total_sks' => $normalized['total_sks'],
                    'semester_anjuran' => $normalized['semester_anjuran'],
                    'tipe' => $normalized['tipe'],
                    'is_active' => $normalized['is_active'],
                ];

                if ($existing) {
                    $existing->update($attributes);
                    $result['updated']++;
                } else {
                    MataKuliah::create($attributes);
                    $result['created']++;
                }
            }

            return $result;
        });
    }

    private function fetchPddiktiRows(): array
    {
        $rows = [];
        $offset = 0;
        $limit = self::PDDIKTI_BATCH_SIZE;

        for ($page = 0; $page < self::MAX_SOURCE_PAGES; $page++) {
            try {
                $response = $this->neoFeeder->request('GetMatkulKurikulum', [
                    'order' => 'id_kurikulum',
                    'limit' => $limit,
                    'offset' => $offset,
                ]);
            } catch (\Throwable $exception) {
                throw new ApiIntegrationSyncException('Gagal mengambil data mata kuliah dari Neo Feeder PDDIKTI: ' . $exception->getMessage(), 502);
            }

            if (($response['error_code'] ?? null) !== 0) {
                throw new ApiIntegrationSyncException('Neo Feeder PDDIKTI menolak permintaan daftar mata kuliah.', 502);
            }

            $batch = $response['data'] ?? [];
            if (!is_array($batch)) {
                throw new ApiIntegrationSyncException('Respons GetMatkulKurikulum dari Neo Feeder tidak valid.', 502);
            }
            if ($batch !== [] && !array_is_list($batch)) {
                $batch = [$batch];
            }
            $rows = array_merge($rows, $batch);
            if (count($batch) < $limit) {
                return $rows;
            }
            $offset += $limit;
        }

        throw new ApiIntegrationSyncException('Data mata kuliah PDDIKTI melebihi batas 100 halaman per proses sync.', 422);
    }

    private function normalizePddiktiCourse(array $row, Kurikulum $curriculum): array
    {
        $totalSks = $this->normalizePddiktiNumber($row['sks_mata_kuliah'] ?? null);
        $sksTeori = $this->normalizePddiktiNumber($row['sks_tatap_muka'] ?? null);
        $sksPraktik = $this->normalizePddiktiNumber($row['sks_praktek'] ?? null);

        return [
            'source_id' => (string) ($row['id_matkul'] ?? ''),
            'kurikulum_kode' => $curriculum->kode,
            'kode_mk' => (string) ($row['kode_mata_kuliah'] ?? ''),
            'nama' => (string) ($row['nama_mata_kuliah'] ?? ''),
            'sks_teori' => $sksTeori,
            'sks_praktik' => $sksPraktik,
            'total_sks' => $totalSks,
            'semester_anjuran' => $this->normalizePddiktiNumber($row['semester'] ?? null),
            'tipe' => (string) ($row['apakah_wajib'] ?? '') === '1' || ($row['apakah_wajib'] ?? null) === 1 ? 'wajib' : 'pilihan',
            'is_active' => $this->isPddiktiCourseActive($row),
        ];
    }

    private function normalizePddiktiNumber(mixed $value): ?int
    {
        if (!is_numeric($value) || (float) $value < 0 || floor((float) $value) !== (float) $value) {
            return null;
        }

        return (int) $value;
    }

    private function isPddiktiCourseActive(array $row): bool
    {
        $today = now()->toDateString();
        $startsAt = $row['tanggal_mulai_efektif'] ?? null;
        $endsAt = $row['tanggal_selesai_efektif'] ?? null;

        return (!$startsAt || $startsAt <= $today) && (!$endsAt || $endsAt >= $today);
    }

    private function fetchSourceRows(IamApiIntegration $integration): array
    {
        $rows = [];
        $page = 1;
        $lastPage = 1;
        $hasPaginationMetadata = false;

        do {
            try {
                $response = ApiIntegrationService::client($integration, timeoutSeconds: 30)
                    ->get($integration->url_api, ['page' => $page, 'per_page' => 100]);
            } catch (ConnectionException) {
                throw new ApiIntegrationSyncException('Tidak dapat terhubung ke API sumber.', 502);
            }

            if (!$response->successful()) {
                throw new ApiIntegrationSyncException("API sumber mengembalikan HTTP {$response->status()}.", 502);
            }

            [$pageRows, $lastPage, $hasPaginationMetadata] = $this->extractPage($response, $page);
            $rows = array_merge($rows, $pageRows);
            $page++;

            if ($lastPage > self::MAX_SOURCE_PAGES) {
                throw new ApiIntegrationSyncException('Data sumber melebihi batas 100 halaman per proses sync.');
            }
        } while ($hasPaginationMetadata && $page <= $lastPage);

        return $rows;
    }

    private function extractPage(Response $response, int $requestedPage): array
    {
        $payload = $response->json();
        if (!is_array($payload)) {
            throw new ApiIntegrationSyncException('Respons API sumber bukan JSON array/object yang valid.');
        }

        if (array_is_list($payload)) {
            return [$payload, 1, false];
        }

        $meta = is_array($payload['meta'] ?? null) ? $payload['meta'] : [];
        $data = $payload['data'] ?? null;
        if (is_array($data) && array_is_list($data)) {
            $hasPaginationMetadata = isset($meta['last_page']) || isset($payload['last_page']);
            return [$data, max($requestedPage, (int) ($meta['last_page'] ?? $payload['last_page'] ?? 1)), $hasPaginationMetadata];
        }

        if (is_array($data) && is_array($data['data'] ?? null) && array_is_list($data['data'])) {
            $nestedMeta = is_array($data['meta'] ?? null) ? $data['meta'] : $data;
            $lastPage = (int) ($nestedMeta['last_page'] ?? $meta['last_page'] ?? 1);
            $hasPaginationMetadata = isset($nestedMeta['last_page']) || isset($meta['last_page']);
            return [$data['data'], max($requestedPage, $lastPage), $hasPaginationMetadata];
        }

        throw new ApiIntegrationSyncException('Format respons API sumber tidak sesuai: data harus berupa array.');
    }

    private function validateRow(mixed $row): ?string
    {
        if (!is_array($row)) {
            return 'Item sumber bukan object data.';
        }

        if (!isset($row['source_id']) || (!is_string($row['source_id']) && !is_int($row['source_id'])) || trim((string) $row['source_id']) === '' || mb_strlen((string) $row['source_id']) > 100) {
            return 'Field source_id wajib berupa string/integer maksimal 100 karakter.';
        }

        foreach (['kurikulum_kode' => 50, 'kode_mk' => 50, 'nama' => 255] as $field => $maxLength) {
            if (!isset($row[$field]) || !is_string($row[$field]) || trim($row[$field]) === '' || mb_strlen(trim($row[$field])) > $maxLength) {
                return "Field {$field} wajib berupa teks maksimal {$maxLength} karakter.";
            }
        }

        foreach (['sks_teori', 'sks_praktik', 'total_sks'] as $field) {
            if (!$this->isNonNegativeInteger($row[$field] ?? null)) {
                return "Field {$field} wajib berupa bilangan bulat nol atau lebih.";
            }
        }
        if (!$this->isPositiveInteger($row['semester_anjuran'] ?? null)) {
            return 'Field semester_anjuran wajib berupa bilangan bulat positif.';
        }
        if ($this->normalizeBoolean($row['is_active'] ?? null) === null) {
            return 'Field is_active wajib berupa boolean.';
        }
        if (!in_array($row['tipe'] ?? null, ['wajib', 'pilihan', 'wajib_prodi'], true)) {
            return 'Field tipe tidak sesuai dengan nilai tipe mata kuliah SIAKAD.';
        }
        if (array_key_exists('kategori', $row) && $row['kategori'] !== null && (!is_string($row['kategori']) || mb_strlen(trim($row['kategori'])) > 50)) {
            return 'Field kategori harus berupa teks maksimal 50 karakter atau null.';
        }
        if (array_key_exists('jumlah_pertemuan', $row) && !$this->isPositiveInteger($row['jumlah_pertemuan'])) {
            return 'Field jumlah_pertemuan wajib berupa bilangan bulat positif.';
        }

        return null;
    }

    private function skip(array &$result, int $index, mixed $row, string $reason): void
    {
        $result['skipped']++;
        if (count($result['skipped_items']) < 100) {
            $result['skipped_items'][] = [
                'source_id' => is_array($row) ? ($row['source_id'] ?? null) : null,
                'item_ref' => is_array($row) ? ($row['kode_mk'] ?? null) : null,
                'kode_mk' => is_array($row) ? ($row['kode_mk'] ?? null) : null,
                'row' => $index + 1,
                'reason' => $reason,
            ];
        }
    }

    private function normalizeBoolean(mixed $value): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (in_array($value, [0, 1, '0', '1'], true)) {
            return (bool) $value;
        }
        if (is_string($value) && in_array(strtolower($value), ['true', 'false'], true)) {
            return strtolower($value) === 'true';
        }

        return null;
    }

    private function isNonNegativeInteger(mixed $value): bool
    {
        return (is_int($value) && $value >= 0)
            || (is_string($value) && preg_match('/^\d+$/D', $value) === 1);
    }

    private function isPositiveInteger(mixed $value): bool
    {
        return (is_int($value) && $value > 0)
            || (is_string($value) && preg_match('/^[1-9]\d*$/D', $value) === 1);
    }
}