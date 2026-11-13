<?php

namespace App\Services\IAM;

use App\Models\IamApiIntegration;
use App\Models\Siakad\Fakultas;
use App\Models\Siakad\ProgramStudi;
use App\Services\Siakad\ProgramStudiReferenceService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\DB;

class ProgramStudiIntegrationSyncService
{
    private const MAX_SOURCE_PAGES = 100;

    public function __construct(private ProgramStudiReferenceService $references) {}

    public function sync(IamApiIntegration $integration): array
    {
        if (!$integration->is_active) {
            throw new ApiIntegrationSyncException('Integrasi sedang dinonaktifkan.');
        }

        if (empty($integration->url_api)) {
            throw new ApiIntegrationSyncException('URL API belum dikonfigurasi.');
        }

        if ($integration->key !== 'siakad_program_studi') {
            throw new ApiIntegrationSyncException('Aksi sync belum didukung untuk integrasi ini.');
        }

        if (strtoupper($integration->http_method ?? 'GET') !== 'GET') {
            throw new ApiIntegrationSyncException('Sinkronisasi data master hanya mendukung method GET.');
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

                $code = trim($row['kode_prodi']);
                $sourceId = (string) $row['source_id'];
                if (isset($seenCodes[$code])) {
                    $this->skip($result, $index, $row, 'Kode program studi duplikat dalam data sumber.');
                    continue;
                }
                if (isset($seenSourceIds[$sourceId])) {
                    $this->skip($result, $index, $row, 'source_id duplikat dalam data sumber.');
                    continue;
                }
                $seenCodes[$code] = true;
                $seenSourceIds[$sourceId] = true;

                if (!$this->references->hasActiveJenjang(trim($row['jenjang']))) {
                    $this->skip($result, $index, $row, 'Kode jenjang tidak tersedia pada master referensi SIAKAD.');
                    continue;
                }

                $accreditation = isset($row['akreditasi']) ? trim($row['akreditasi']) : null;
                if ($accreditation !== null && $accreditation !== '' && !$this->references->hasActiveAkreditasi($accreditation)) {
                    $this->skip($result, $index, $row, 'Kode akreditasi tidak tersedia pada master referensi SIAKAD.');
                    continue;
                }

                $facultyId = null;
                if (!empty($row['fakultas'])) {
                    $faculty = Fakultas::query()
                        ->where(function ($query) use ($row): void {
                            $query->where('kode', trim($row['fakultas']))
                                ->orWhere('nama', trim($row['fakultas']));
                        })
                        ->where('is_active', true)
                        ->first();

                    if (!$faculty) {
                        $this->skip($result, $index, $row, 'Fakultas sumber tidak cocok dengan fakultas aktif di SIAKAD.');
                        continue;
                    }

                    $facultyId = $faculty->id;
                }

                $existingIncludingDeleted = ProgramStudi::withTrashed()
                    ->where('source_id', $sourceId)
                    ->first();

                if (!$existingIncludingDeleted) {
                    $existingByCode = ProgramStudi::withTrashed()->where('kode_prodi', $code)->first();
                    if ($existingByCode && $existingByCode->source_id !== null && $existingByCode->source_id !== $sourceId) {
                        $this->skip($result, $index, $row, 'Kode program studi sudah dipetakan ke source_id lain.');
                        continue;
                    }
                    $existingIncludingDeleted = $existingByCode;
                }
                if ($existingIncludingDeleted?->trashed()) {
                    $this->skip($result, $index, $row, 'Program studi dengan kode ini sudah dihapus di SIAKAD.');
                    continue;
                }

                $attributes = [
                    'source_id' => $sourceId,
                    'source_kode_prodi' => $code,
                    'nama' => trim($row['nama']),
                    'jenjang' => trim($row['jenjang']),
                ];

                $previousSourceCode = $existingIncludingDeleted?->source_kode_prodi;
                $targetCodeStillMatchesSource = !$existingIncludingDeleted;
                if ($existingIncludingDeleted) {
                    $targetCodeStillMatchesSource = $previousSourceCode === null
                        ? $existingIncludingDeleted->kode_prodi === $code
                        : $existingIncludingDeleted->kode_prodi === $previousSourceCode;
                }

                if ($targetCodeStillMatchesSource) {
                    $attributes['kode_prodi'] = $code;
                }
                if (!$existingIncludingDeleted?->is_status_manual) {
                    $attributes['is_active'] = $this->normalizeBoolean($row['is_active']);
                }

                if (array_key_exists('kode_prodi_dikti', $row)) {
                    $attributes['kode_prodi_dikti'] = $this->nullableTrim($row['kode_prodi_dikti']);
                }
                if (array_key_exists('akreditasi', $row)) {
                    $attributes['akreditasi'] = $accreditation ?: null;
                }
                if ($facultyId !== null) {
                    $attributes['fakultas_id'] = $facultyId;
                } elseif (!$existingIncludingDeleted) {
                    $attributes['fakultas_id'] = null;
                }

                if ($existingIncludingDeleted) {
                    $existingIncludingDeleted->update($attributes);
                    $result['updated']++;
                } else {
                    ProgramStudi::create($attributes);
                    $result['created']++;
                }
            }

            return $result;
        });
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
            } catch (ConnectionException $exception) {
                throw new ApiIntegrationSyncException('Tidak dapat terhubung ke API sumber.', 502);
            }

            if (!$response->successful()) {
                throw new ApiIntegrationSyncException(
                    "API sumber mengembalikan HTTP {$response->status()}.",
                    502
                );
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

        if (!isset($row['source_id']) || (!is_string($row['source_id']) && !is_int($row['source_id'])) || trim((string) $row['source_id']) === '') {
            return 'Field source_id wajib diisi.';
        }

        foreach (['kode_prodi', 'nama', 'jenjang'] as $field) {
            if (!isset($row[$field]) || !is_string($row[$field]) || trim($row[$field]) === '') {
                return "Field {$field} wajib berupa teks.";
            }
        }

        foreach (['kode_prodi' => 20, 'nama' => 255, 'jenjang' => 20] as $field => $maxLength) {
            if (mb_strlen(trim((string) $row[$field])) > $maxLength) {
                return "Field {$field} melebihi {$maxLength} karakter.";
            }
        }

        if ($this->normalizeBoolean($row['is_active'] ?? null) === null) {
            return 'Field is_active wajib berupa boolean.';
        }

        foreach (['kode_prodi_dikti' => 50, 'akreditasi' => 50] as $field => $maxLength) {
            if (array_key_exists($field, $row) && $row[$field] !== null && (!is_string($row[$field]) || mb_strlen(trim($row[$field])) > $maxLength)) {
                return "Field {$field} harus berupa teks maksimal {$maxLength} karakter atau null.";
            }
        }

        if (array_key_exists('fakultas', $row) && $row['fakultas'] !== null && !is_string($row['fakultas'])) {
            return 'Field fakultas harus berupa kode/nama fakultas atau null.';
        }

        return null;
    }

    private function skip(array &$result, int $index, mixed $row, string $reason): void
    {
        $result['skipped']++;
        if (count($result['skipped_items']) < 100) {
            $result['skipped_items'][] = [
                'source_id' => is_array($row) ? ($row['source_id'] ?? null) : null,
                'kode_prodi' => is_array($row) ? ($row['kode_prodi'] ?? null) : null,
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

    private function nullableTrim(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);
        return $trimmed === '' ? null : $trimmed;
    }
}