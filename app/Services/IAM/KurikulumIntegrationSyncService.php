<?php

namespace App\Services\IAM;

use App\Models\IamApiIntegration;
use App\Models\Siakad\Kurikulum;
use App\Models\Siakad\ProgramStudi;
use App\Services\AuditLogService;
use App\Services\Siakad\NeoFeederService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class KurikulumIntegrationSyncService
{
    private const MAX_SOURCE_PAGES = 100;
    private const PDDIKTI_BATCH_SIZE = 500;

    public function __construct(private NeoFeederService $neoFeeder) {}

    public function sync(IamApiIntegration $integration): array
    {
        if (!$integration->is_active) {
            throw new ApiIntegrationSyncException('Integrasi sedang dinonaktifkan.');
        }
        if (!in_array('pddikti', $integration->supported_sources ?? [], true)) {
            throw new ApiIntegrationSyncException('Integrasi ini tidak mendukung sumber PDDIKTI.');
        }
        if (($integration->source_type ?? 'custom_api') !== 'pddikti') {
            throw new ApiIntegrationSyncException('Pilih sumber PDDIKTI untuk menjalankan migrasi kurikulum ini.');
        }

        $rows = $this->fetchRows();

        return DB::transaction(function () use ($rows): array {
            $result = [
                'source_total' => count($rows),
                'created' => 0,
                'updated' => 0,
                'skipped' => 0,
                'skipped_items' => [],
            ];
            $seenIds = [];

            foreach ($rows as $index => $row) {
                $reason = $this->validateRow($row);
                if ($reason !== null) {
                    $this->skip($result, $index, $row, $reason);
                    continue;
                }

                $sourceId = trim((string) $row['id_kurikulum']);
                if (isset($seenIds[$sourceId])) {
                    $this->skip($result, $index, $row, 'ID kurikulum duplikat dalam data PDDIKTI.');
                    continue;
                }
                $seenIds[$sourceId] = true;

                $program = ProgramStudi::query()->where('id_feeder', trim((string) $row['id_prodi']))->first();
                if (!$program) {
                    $this->skip($result, $index, $row, 'Program studi PDDIKTI belum dipetakan ke ID Feeder di SIAKAD.');
                    continue;
                }

                $existing = Kurikulum::withTrashed()->where('id_feeder', $sourceId)->first();
                $localCode = 'PDDIKTI-' . $sourceId;
                if (!$existing) {
                    $existing = Kurikulum::withTrashed()->where('kode', $localCode)->first();
                }
                if ($existing?->trashed()) {
                    $this->skip($result, $index, $row, 'Kurikulum PDDIKTI sudah dihapus di SIAKAD.');
                    continue;
                }
                if ($existing && (int) $existing->program_studi_id !== (int) $program->id) {
                    $this->skip($result, $index, $row, 'Kurikulum dengan ID Feeder ini sudah terhubung ke program studi lain.');
                    continue;
                }

                $year = $this->resolveYear($row);
                if ($year === null) {
                    $this->skip($result, $index, $row, 'Tahun berlaku kurikulum tidak dapat ditentukan dari data semester PDDIKTI.');
                    continue;
                }

                $attributes = [
                    'program_studi_id' => $program->id,
                    'id_feeder' => $sourceId,
                    'kode' => $localCode,
                    'nama' => trim($row['nama_kurikulum']),
                    'tahun_berlaku' => $year,
                    'total_sks_lulus' => (int) $row['jumlah_sks_lulus'],
                    'is_active' => true,
                ];

                if ($existing) {
                    $oldValues = $existing->getOriginal();
                    $existing->update($attributes);
                    $newValues = $existing->getChanges();
                    $result['updated']++;
                } else {
                    $existing = Kurikulum::create($attributes);
                    $oldValues = null;
                    $newValues = $existing->getAttributes();
                    $result['created']++;
                }

                try {
                    AuditLogService::record(
                        module: 'SIAKAD',
                        action: $oldValues === null ? 'create' : 'update',
                        tableName: 'siakad_kurikulum',
                        recordId: $existing->id,
                        oldValues: $oldValues,
                        newValues: $newValues,
                        request: request(),
                    );
                } catch (\Throwable $exception) {
                    Log::warning('Gagal mencatat audit log sinkronisasi kurikulum PDDIKTI: ' . $exception->getMessage(), [
                        'kurikulum_id' => $existing->id,
                    ]);
                }
            }

            return $result;
        });
    }

    public function testPddiktiConnection(string $action): array
    {
        if ($action !== 'GetListKurikulum') {
            throw new ApiIntegrationSyncException('Action tes koneksi kurikulum PDDIKTI tidak valid.');
        }

        try {
            $response = $this->neoFeeder->request($action, [
                'limit' => 1,
                'offset' => 0,
            ]);
        } catch (\Throwable $exception) {
            throw new ApiIntegrationSyncException('Koneksi Neo Feeder gagal. Periksa konfigurasi Koneksi Neo Feeder PDDikti dan hak akses GetListKurikulum.', 502);
        }

        if (($response['error_code'] ?? null) !== 0) {
            throw new ApiIntegrationSyncException('Neo Feeder menolak permintaan GetListKurikulum. Periksa hak akses akun Feeder.', 502);
        }

        $data = $response['data'] ?? [];
        return [
            'action' => $action,
            'data_count' => is_array($data) ? count($data) : 0,
        ];
    }

    private function fetchRows(): array
    {
        $rows = [];
        $offset = 0;

        for ($page = 0; $page < self::MAX_SOURCE_PAGES; $page++) {
            try {
                $response = $this->neoFeeder->request('GetListKurikulum', [
                    'order' => 'nama_kurikulum',
                    'limit' => self::PDDIKTI_BATCH_SIZE,
                    'offset' => $offset,
                ]);
            } catch (\Throwable $exception) {
                throw new ApiIntegrationSyncException('Gagal mengambil kurikulum dari Neo Feeder PDDIKTI.', 502);
            }

            if (($response['error_code'] ?? null) !== 0) {
                throw new ApiIntegrationSyncException('Neo Feeder PDDIKTI menolak permintaan GetListKurikulum.', 502);
            }

            $batch = $response['data'] ?? [];
            if (!is_array($batch)) {
                throw new ApiIntegrationSyncException('Respons GetListKurikulum dari Neo Feeder tidak valid.', 502);
            }
            if ($batch !== [] && !array_is_list($batch)) {
                $batch = [$batch];
            }
            $rows = array_merge($rows, $batch);
            if (count($batch) < self::PDDIKTI_BATCH_SIZE) {
                return $rows;
            }
            $offset += self::PDDIKTI_BATCH_SIZE;
        }

        throw new ApiIntegrationSyncException('Data kurikulum PDDIKTI melebihi batas 100 halaman per proses sync.');
    }

    private function validateRow(mixed $row): ?string
    {
        if (!is_array($row)) {
            return 'Item kurikulum Neo Feeder bukan object data.';
        }
        foreach (['id_kurikulum', 'id_prodi', 'nama_kurikulum'] as $field) {
            if (!isset($row[$field]) || !is_string($row[$field]) || trim($row[$field]) === '') {
                return "Field {$field} wajib tersedia dari GetListKurikulum.";
            }
        }
        if (mb_strlen(trim($row['nama_kurikulum'])) > 255) {
            return 'Nama kurikulum PDDIKTI melebihi batas 255 karakter.';
        }
        if (!$this->isNonNegativeInteger($row['jumlah_sks_lulus'] ?? null)) {
            return 'Field jumlah_sks_lulus wajib berupa bilangan bulat nol atau lebih.';
        }
        if (!$this->isNonNegativeInteger($row['jumlah_sks_wajib'] ?? null)) {
            return 'Field jumlah_sks_wajib wajib berupa bilangan bulat nol atau lebih.';
        }

        return null;
    }

    private function resolveYear(array $row): ?int
    {
        $semesterStart = trim((string) ($row['semester_mulai_berlaku'] ?? ''));
        if (preg_match('/(?:^|[^0-9])(20\d{2})/', $semesterStart, $matches) === 1) {
            return (int) $matches[1];
        }
        if (preg_match('/^(20\d{2})/', (string) ($row['id_semester'] ?? ''), $matches) === 1) {
            return (int) $matches[1];
        }
        return null;
    }

    private function isNonNegativeInteger(mixed $value): bool
    {
        return (is_int($value) && $value >= 0)
            || (is_string($value) && preg_match('/^\d+$/D', $value) === 1);
    }

    private function skip(array &$result, int $index, mixed $row, string $reason): void
    {
        $result['skipped']++;
        if (count($result['skipped_items']) < 100) {
            $result['skipped_items'][] = [
                'source_id' => is_array($row) ? ($row['id_kurikulum'] ?? null) : null,
                'item_ref' => is_array($row) ? ($row['nama_kurikulum'] ?? null) : null,
                'row' => $index + 1,
                'reason' => $reason,
            ];
        }
    }
}
