<?php

namespace App\Services\IAM;

use App\Models\IamApiIntegration;
use App\Models\Siakad\Mahasiswa;
use App\Models\Siakad\MahasiswaStatus;
use App\Models\Siakad\ProgramStudi;
use App\Services\AuditLogService;
use App\Services\Siakad\NeoFeederService;
use App\Services\Siakad\SiakadMahasiswaSyncException;
use App\Services\Siakad\SiakadMahasiswaSyncService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SiakadMahasiswaIntegrationSyncService
{
    private const PDDIKTI_BATCH_SIZE = 500;

    public function __construct(
        private SiakadMahasiswaSyncService $syncService,
        private NeoFeederService $neoFeeder
    ) {}

    public function sync(IamApiIntegration $integration, array $options = []): array
    {
        if (!$integration->is_active) {
            throw new ApiIntegrationSyncException('Integrasi sedang dinonaktifkan.');
        }
        if ($integration->key !== 'siakad_mahasiswa') {
            throw new ApiIntegrationSyncException("Aksi sync belum didukung untuk key '{$integration->key}'.");
        }
        if (($integration->source_type ?? 'custom_api') === 'pddikti') {
            return $this->syncPddikti($integration);
        }
        if (empty($integration->url_api)) {
            throw new ApiIntegrationSyncException('URL API belum dikonfigurasi di pengaturan integrasi.');
        }

        $apiToken = method_exists($integration, 'getDecryptedToken')
            ? $integration->getDecryptedToken()
            : (filled($integration->token) ? (string) $integration->token : '');
        if ($apiToken === '') {
            throw new ApiIntegrationSyncException('Token API belum dikonfigurasi di pengaturan integrasi.');
        }

        try {
            $res = $this->syncService->sync(array_merge($options, [
                'url' => $integration->url_api,
                'token' => $apiToken,
                'auth_scheme' => $integration->auth_type ?? 'bearer',
            ]));

            $skippedItems = [];
            foreach ($res['skip_reasons'] ?? [] as $reason => $count) {
                if ($count > 0) {
                    $skippedItems[] = ['reason' => $reason, 'count' => $count];
                }
            }

            return [
                'source_total' => $res['fetched'] ?? 0,
                'created' => $res['created'] ?? 0,
                'updated' => $res['updated'] ?? 0,
                'skipped' => $res['skipped'] ?? 0,
                'failed' => $res['failed'] ?? 0,
                'pages' => $res['pages'] ?? 0,
                'skip_reasons' => $res['skip_reasons'] ?? [],
                'skipped_items' => $skippedItems,
                'errors' => $res['errors'] ?? [],
            ];
        } catch (SiakadMahasiswaSyncException $exception) {
            throw new ApiIntegrationSyncException($exception->getMessage(), 502);
        } catch (\Throwable $exception) {
            Log::error('Sinkronisasi mahasiswa siakad via IAM gagal: ' . $exception->getMessage(), [
                'integration_id' => $integration->id,
                'key' => $integration->key,
            ]);
            throw new ApiIntegrationSyncException(
                'Sinkronisasi data mahasiswa dari SIAKAD gagal. Periksa koneksi/API sumber dan coba lagi.',
                502
            );
        }
    }

    public function testPddiktiConnection(string $action): array
    {
        if (!in_array($action, ['GetListMahasiswa', 'GetBiodataMahasiswa'], true)) {
            throw new ApiIntegrationSyncException('Action tes koneksi mahasiswa PDDIKTI tidak valid.');
        }

        try {
            $response = $this->neoFeeder->request($action, [
                'filter' => '',
                'order' => '',
                'limit' => 1,
                'offset' => 0,
            ]);
        } catch (\Throwable) {
            throw new ApiIntegrationSyncException("Koneksi Neo Feeder gagal. Periksa konfigurasi dan hak akses {$action}.", 502);
        }
        if ((int) ($response['error_code'] ?? -1) !== 0) {
            throw new ApiIntegrationSyncException("Neo Feeder menolak permintaan {$action}. Periksa hak akses akun Feeder.", 502);
        }

        $data = $response['data'] ?? [];
        return ['action' => $action, 'data_count' => is_array($data) ? count($data) : 0];
    }

    public function syncPddikti(IamApiIntegration $integration, bool $preview = false, ?string $progressId = null, ?int $actorId = null): array
    {
        if (!$integration->is_active) {
            throw new ApiIntegrationSyncException('Integrasi sedang dinonaktifkan.');
        }
        if ($integration->key !== 'siakad_mahasiswa') {
            throw new ApiIntegrationSyncException('Integrasi ini bukan integrasi mahasiswa SIAKAD.');
        }
        if (!in_array('pddikti', $integration->supported_sources ?? [], true)
            || ($integration->source_type ?? 'custom_api') !== 'pddikti') {
            throw new ApiIntegrationSyncException('Pilih sumber PDDIKTI untuk menjalankan sinkronisasi mahasiswa ini.');
        }

        $sourceTotal = $this->fetchPddiktiCount('GetCountMahasiswa');
        if ($preview) {
            $previewResult = $this->previewPddikti($this->fetchPddiktiRows('GetListMahasiswa', $sourceTotal));
            $previewResult['source_total'] = $sourceTotal;
            $previewResult['read_count'] = $sourceTotal;
            return $previewResult;
        }

        $result = [
            'source_total' => $sourceTotal,
            'created' => 0,
            'updated' => 0,
            'synced' => 0,
            'skipped' => 0,
            'failed' => 0,
            'pages' => (int) ceil($sourceTotal / self::PDDIKTI_BATCH_SIZE),
            'biodata_total' => 0,
            'biodata_updated' => 0,
            'biodata_skipped' => 0,
            'biodata_failed' => 0,
            'read_count' => 0,
            'preview' => false,
            'read_only' => false,
            'skipped_items' => [],
        ];
        $seenIds = [];
        $readProcessed = 0;

        if ($progressId !== null) {
            $this->updateProgress($progressId, [
                'status' => 'running',
                'phase' => 'reading',
                'processed' => 0,
                'total' => $sourceTotal,
                'phase_processed' => 0,
                'phase_total' => $sourceTotal,
                'work_processed' => 0,
                'work_total' => null,
                'progress' => 0,
                'message' => "Menyiapkan pembacaan {$sourceTotal} mahasiswa dari PDDIKTI.",
                'result' => $result,
            ]);
        }

        for ($offset = 0; $offset < $sourceTotal; $offset += self::PDDIKTI_BATCH_SIZE) {
            $batch = $this->fetchPddiktiBatch('GetListMahasiswa', $offset);
            $this->savePddiktiStudentBatch($batch, $offset, $result, $seenIds, $actorId);
            $readProcessed += count($batch);
            $result['read_count'] = $readProcessed;
            if ($progressId !== null) {
                $this->updateProgress($progressId, [
                    'status' => 'running',
                    'phase' => 'reading',
                    'processed' => $readProcessed,
                    'total' => $sourceTotal,
                    'phase_processed' => $readProcessed,
                    'phase_total' => $sourceTotal,
                    'work_processed' => $readProcessed,
                    'work_total' => null,
                    'progress' => $sourceTotal > 0 ? min(49, (int) floor(($readProcessed / $sourceTotal) * 50)) : 49,
                    'message' => "Mengambil dan menyimpan daftar mahasiswa: {$readProcessed} dari {$sourceTotal}.",
                    'result' => $result,
                ]);
            }
            unset($batch);
            gc_collect_cycles();
        }

        $result['read_count'] = $readProcessed;
        $biodataTotal = $this->fetchPddiktiCount('GetCountBiodataMahasiswa');
        $workTotal = $sourceTotal + $biodataTotal;
        $result['biodata_total'] = $biodataTotal;
        if ($progressId !== null) {
            $this->updateProgress($progressId, [
                'phase' => 'biodata',
                'processed' => 0,
                'total' => $biodataTotal,
                'phase_processed' => 0,
                'phase_total' => $biodataTotal,
                'work_processed' => $readProcessed,
                'work_total' => $workTotal,
                'progress' => 50,
                'message' => "Daftar mahasiswa selesai. Mengambil {$biodataTotal} data biodata.",
                'result' => $result,
            ]);
        }

        $biodataProcessed = 0;
        for ($offset = 0; $offset < $biodataTotal; $offset += self::PDDIKTI_BATCH_SIZE) {
            $batch = $this->fetchPddiktiBatch('GetBiodataMahasiswa', $offset);
            $this->applyPddiktiBiodataBatch($batch, $offset, $result, $actorId);
            $biodataProcessed += count($batch);
            if ($progressId !== null) {
                $this->updateProgress($progressId, [
                    'phase' => 'biodata',
                    'processed' => $biodataProcessed,
                    'total' => $biodataTotal,
                    'phase_processed' => $biodataProcessed,
                    'phase_total' => $biodataTotal,
                    'work_processed' => $readProcessed + $biodataProcessed,
                    'work_total' => $workTotal,
                    'progress' => $workTotal > 0 ? min(99, 50 + (int) floor(($biodataProcessed / $biodataTotal) * 49)) : 99,
                    'message' => "Mengambil dan menyimpan biodata: {$biodataProcessed} dari {$biodataTotal}.",
                    'result' => $result,
                ]);
            }
            unset($batch);
            gc_collect_cycles();
        }

        return $result;
    }

    private function savePddiktiStudentBatch(array $batch, int $offset, array &$result, array &$seenIds, ?int $actorId): void
    {
        foreach ($batch as $batchIndex => $row) {
            $index = $offset + $batchIndex;
            $reason = $this->validatePddiktiStudent($row);
            if ($reason !== null) {
                $this->skipPddikti($result, $index, $row, $reason);
                continue;
            }

            $sourceId = trim((string) $row['id_mahasiswa']);
            $nim = $this->studentNim($row);
            $status = $this->mapPddiktiStatus($row['id_status_mahasiswa'] ?? null, $row['nama_status_mahasiswa'] ?? null);
            if ($status === null) {
                $this->skipPddikti($result, $index, $row, 'Status PDDIKTI belum memiliki pemetaan yang disetujui ke status mahasiswa lokal.');
                continue;
            }
            if (preg_match('/^(20\d{2})\d$/D', trim((string) ($row['id_periode'] ?? '')), $period) !== 1) {
                $this->skipPddikti($result, $index, $row, 'Periode masuk PDDIKTI tidak dapat dipetakan ke tahun angkatan.');
                continue;
            }
            $program = $this->resolveProgram(trim((string) $row['id_prodi']));
            if ($program === null) {
                $this->skipPddikti($result, $index, $row, 'Program studi PDDIKTI belum dipetakan secara unik ke program studi lokal.');
                continue;
            }
            if (isset($seenIds[$sourceId])) {
                $this->skipPddikti($result, $index, $row, 'UUID id_mahasiswa duplikat dalam data PDDIKTI.');
                continue;
            }
            $seenIds[$sourceId] = true;

            try {
                DB::transaction(function () use ($row, $sourceId, $nim, $status, $period, $program, $index, $actorId, &$result): void {
                    $student = $this->resolveExistingStudent($sourceId, $nim);
                    if ($student === false) {
                        $this->skipPddikti($result, $index, $row, 'UUID id_mahasiswa terhubung ke lebih dari satu record lokal.');
                        return;
                    }
                    if ($student?->trashed()) {
                        $this->skipPddikti($result, $index, $row, 'Mahasiswa dengan UUID id_mahasiswa ini sudah dihapus lokal; perlu rekonsiliasi sebelum dipulihkan.');
                        return;
                    }
                    if (Mahasiswa::withTrashed()->where('nim', $nim)
                        ->when($student, static fn ($query) => $query->where('id', '!=', $student->id))
                        ->exists()) {
                        $this->skipPddikti($result, $index, $row, 'NIM sudah digunakan record lokal lain; UUID id_mahasiswa tidak cocok dengan record tersebut.');
                        return;
                    }

                    $registrationId = trim((string) ($row['id_registrasi_mahasiswa'] ?? ''));
                    $attributes = [
                        'id_feeder' => $sourceId,
                        'id_feeder_biodata' => $sourceId,
                        'program_studi_id' => $program->id,
                        'nim' => $nim,
                        'nama_lengkap' => trim((string) $row['nama_mahasiswa']),
                        'angkatan' => (int) $period[1],
                        'status' => $status,
                    ];
                    if ($registrationId !== '') {
                        $attributes['id_feeder_riwayat'] = $registrationId;
                    }
                    $gender = $this->mapPddiktiGender($row['jenis_kelamin'] ?? null);
                    if ($gender !== null) {
                        $attributes['jenis_kelamin'] = $gender;
                    }
                    $birthDate = $this->parsePddiktiDate($row['tanggal_lahir'] ?? null);
                    if ($birthDate !== null) {
                        $attributes['tanggal_lahir'] = $birthDate;
                    }

                    if ($student) {
                        $originalValues = $student->getRawOriginal();
                        $student->update($attributes);
                        $newValues = $student->getChanges();
                        $oldValues = array_intersect_key($originalValues, $newValues);
                        $result['updated']++;
                    } else {
                        $student = Mahasiswa::create($attributes);
                        $oldValues = null;
                        $newValues = $student->getAttributes();
                        $result['created']++;
                    }
                    $result['synced']++;
                    $this->recordStudentAudit($student, $oldValues, $newValues, $actorId);
                });
            } catch (\Throwable $exception) {
                $result['failed']++;
                $this->recordStudentFailure($result, $row, $index, $exception);
            }
        }
    }

    private function applyPddiktiBiodataBatch(array $batch, int $offset, array &$result, ?int $actorId): void
    {
        foreach ($batch as $batchIndex => $row) {
            if (!is_array($row) || !is_string($row['id_mahasiswa'] ?? null)
                || !Str::isUuid(trim($row['id_mahasiswa']))) {
                $result['biodata_skipped']++;
                continue;
            }

            $sourceId = trim($row['id_mahasiswa']);
            $matches = Mahasiswa::query()
                ->where(fn ($query) => $query->where('id_feeder_biodata', $sourceId)->orWhere('id_feeder', $sourceId))
                ->limit(2)
                ->get();
            if ($matches->count() !== 1) {
                $result['biodata_skipped']++;
                continue;
            }
            $attributes = array_filter(
                $this->mapPddiktiBiodata($row),
                static fn (mixed $value): bool => $value !== null && $value !== ''
            );
            $gender = $this->mapPddiktiGender($row['jenis_kelamin'] ?? null);
            if ($gender !== null) {
                $attributes['jenis_kelamin'] = $gender;
            }
            if ($attributes === []) {
                $result['biodata_skipped']++;
                continue;
            }

            try {
                DB::transaction(function () use ($matches, $attributes, $actorId): void {
                    $student = $matches->first();
                    $originalValues = $student->getRawOriginal();
                    $student->update($attributes);
                    $newValues = $student->getChanges();
                    $oldValues = array_intersect_key($originalValues, $newValues);
                    $this->recordStudentAudit($student, $oldValues, $newValues, $actorId);
                });
                $result['biodata_updated']++;
            } catch (\Throwable $exception) {
                $result['biodata_failed']++;
                $result['failed']++;
                Log::warning('Sinkronisasi biodata mahasiswa PDDIKTI gagal menyimpan record.', [
                    'source_id' => $sourceId,
                    'row' => $offset + $batchIndex + 1,
                    'exception' => $exception::class,
                ]);
            }
        }
    }

    private function recordStudentAudit(Mahasiswa $student, ?array $oldValues, array $newValues, ?int $actorId): void
    {
        try {
            AuditLogService::record(
                module: 'SIAKAD',
                action: $oldValues === null ? 'create' : 'update',
                tableName: 'siakad_mahasiswa',
                recordId: $student->id,
                oldValues: $oldValues,
                newValues: $newValues,
                request: app()->runningInConsole() ? null : request(),
                actorId: $actorId,
            );
        } catch (\Throwable) {
            Log::warning('Gagal mencatat audit log sinkronisasi mahasiswa PDDIKTI.', [
                'mahasiswa_id' => $student->id,
            ]);
        }
    }

    private function recordStudentFailure(array &$result, mixed $row, int $index, \Throwable $exception): void
    {
        if (count($result['skipped_items']) < 100) {
            $result['skipped_items'][] = [
                'source_id' => is_array($row) ? ($row['id_mahasiswa'] ?? null) : null,
                'item_ref' => is_array($row) ? ($row['nim'] ?? $row['nipd'] ?? null) : null,
                'row' => $index + 1,
                'reason' => 'Gagal menyimpan data mahasiswa; periksa log aplikasi.',
            ];
        }
        Log::warning('Sinkronisasi mahasiswa PDDIKTI gagal menyimpan record.', [
            'source_id' => is_array($row) ? ($row['id_mahasiswa'] ?? null) : null,
            'exception' => $exception::class,
        ]);
    }

    private function previewPddikti(array $students): array
    {
        $result = [
            'preview' => true,
            'read_only' => true,
            'read_count' => count($students),
            'source_total' => count($students),
            'created' => 0,
            'updated' => 0,
            'synced' => 0,
            'skipped' => 0,
            'failed' => 0,
            'pages' => (int) ceil(count($students) / self::PDDIKTI_BATCH_SIZE),
            'skipped_items' => [],
        ];
        $seenIds = [];
        foreach ($students as $index => $row) {
            $reason = $this->validatePddiktiStudent($row);
            if ($reason === null && $this->mapPddiktiStatus($row['id_status_mahasiswa'] ?? null, $row['nama_status_mahasiswa'] ?? null) === null) {
                $reason = 'Status PDDIKTI belum memiliki pemetaan yang disetujui ke status mahasiswa lokal.';
            }
            if ($reason === null && preg_match('/^20\d{2}\d$/D', trim((string) ($row['id_periode'] ?? ''))) !== 1) {
                $reason = 'Periode masuk PDDIKTI tidak dapat dipetakan ke tahun angkatan.';
            }
            if ($reason === null && $this->resolveProgram(trim((string) ($row['id_prodi'] ?? ''))) === null) {
                $reason = 'Program studi PDDIKTI belum dipetakan secara unik ke program studi lokal.';
            }
            if ($reason !== null) {
                $this->skipPddikti($result, $index, $row, $reason);
                continue;
            }

            $sourceId = trim((string) $row['id_mahasiswa']);
            $nim = $this->studentNim($row);
            if (isset($seenIds[$sourceId])) {
                $this->skipPddikti($result, $index, $row, 'UUID id_mahasiswa duplikat dalam data PDDIKTI.');
                continue;
            }
            $seenIds[$sourceId] = true;
            $existing = $this->resolveExistingStudent($sourceId, $nim);
            if ($existing === false || $existing?->trashed()) {
                $this->skipPddikti($result, $index, $row, 'Record lokal dengan UUID id_mahasiswa ini ambigu atau sudah dihapus; perlu rekonsiliasi.');
                continue;
            }
            if (Mahasiswa::withTrashed()->where('nim', $nim)
                ->when($existing, static fn ($query) => $query->where('id', '!=', $existing->id))
                ->exists()) {
                $this->skipPddikti($result, $index, $row, 'NIM sudah digunakan record lokal lain; UUID id_mahasiswa tidak cocok dengan record tersebut.');
                continue;
            }
            $result[$existing ? 'updated' : 'created']++;
            $result['synced']++;
        }
        return $result;
    }

    private function fetchPddiktiRows(string $action, int $total): array
    {
        $rows = [];
        for ($offset = 0; $offset < $total; $offset += self::PDDIKTI_BATCH_SIZE) {
            $batch = $this->fetchPddiktiBatch($action, $offset);
            array_push($rows, ...$batch);
            unset($batch);
        }
        return $rows;
    }

    private function fetchPddiktiCount(string $action): int
    {
        try {
            $response = $this->neoFeeder->request($action, ['filter' => '']);
        } catch (\Throwable) {
            throw new ApiIntegrationSyncException("Gagal mengambil jumlah data dari Neo Feeder ({$action}).", 502);
        }
        if ((int) ($response['error_code'] ?? -1) !== 0) {
            throw new ApiIntegrationSyncException("Neo Feeder menolak permintaan {$action}.", 502);
        }
        $count = $this->extractPddiktiCount($response['data'] ?? $response);
        if ($count === null) {
            throw new ApiIntegrationSyncException("Respons jumlah data dari Neo Feeder ({$action}) tidak valid.", 502);
        }
        return $count;
    }

    private function extractPddiktiCount(mixed $value): ?int
    {
        if (is_numeric($value) && (int) $value >= 0) {
            return (int) $value;
        }
        if (!is_array($value)) {
            return null;
        }
        foreach (['count', 'total', 'jumlah', 'total_data', 'total_records', 'total_count'] as $key) {
            if (isset($value[$key]) && is_numeric($value[$key]) && (int) $value[$key] >= 0) {
                return (int) $value[$key];
            }
        }
        foreach ($value as $nested) {
            $count = $this->extractPddiktiCount($nested);
            if ($count !== null) {
                return $count;
            }
        }
        return null;
    }

    private function fetchPddiktiBatch(string $action, int $offset): array
    {
        try {
            $response = $this->neoFeeder->request($action, [
                'filter' => '',
                'order' => '',
                'limit' => self::PDDIKTI_BATCH_SIZE,
                'offset' => $offset,
            ]);
        } catch (\Throwable) {
            throw new ApiIntegrationSyncException("Gagal mengambil batch data dari Neo Feeder ({$action}).", 502);
        }
        if ((int) ($response['error_code'] ?? -1) !== 0) {
            throw new ApiIntegrationSyncException("Neo Feeder menolak permintaan {$action}.", 502);
        }
        $data = $response['data'] ?? [];
        if (!is_array($data)) {
            throw new ApiIntegrationSyncException("Respons {$action} dari Neo Feeder tidak valid.", 502);
        }
        return $data !== [] && !array_is_list($data) ? [$data] : $data;
    }

    private function studentNim(array $row): string
    {
        foreach (['nim', 'nipd'] as $field) {
            if (is_string($row[$field] ?? null) && trim($row[$field]) !== '') {
                return trim($row[$field]);
            }
        }
        return '';
    }

    private function validatePddiktiStudent(mixed $row): ?string
    {
        if (!is_array($row)) {
            return 'Item GetListMahasiswa bukan object data.';
        }
        foreach (['id_mahasiswa', 'id_prodi', 'nama_mahasiswa', 'id_periode'] as $field) {
            if (!is_string($row[$field] ?? null) || trim($row[$field]) === '') {
                return "Field {$field} wajib tersedia dari GetListMahasiswa.";
            }
        }
        if ($this->studentNim($row) === '') {
            return 'Field nim atau nipd wajib tersedia dari GetListMahasiswa.';
        }
        if (!Str::isUuid(trim($row['id_mahasiswa'])) || !Str::isUuid(trim($row['id_prodi']))) {
            return 'UUID mahasiswa atau program studi dari PDDIKTI tidak valid.';
        }
        if ($this->mapPddiktiStatus($row['id_status_mahasiswa'] ?? null, $row['nama_status_mahasiswa'] ?? null) === null) {
            return 'Kode atau nama id_status_mahasiswa tidak ditemukan di kamus GetStatusMahasiswa PDDIKTI.';
        }
        if (isset($row['id_registrasi_mahasiswa']) && $row['id_registrasi_mahasiswa'] !== ''
            && (!is_string($row['id_registrasi_mahasiswa']) || !Str::isUuid($row['id_registrasi_mahasiswa']))) {
            return 'UUID registrasi mahasiswa dari PDDIKTI tidak valid.';
        }
        if (mb_strlen($this->studentNim($row)) > 30 || mb_strlen(trim($row['nama_mahasiswa'])) > 255) {
            return 'NIM atau nama mahasiswa melebihi batas kolom tujuan.';
        }
        return null;
    }

    private function resolveProgram(string $feederId): ?ProgramStudi
    {
        $matches = ProgramStudi::query()->where('id_feeder', $feederId)->orWhere('source_id', $feederId)->get();
        return $matches->count() === 1 ? $matches->first() : null;
    }

    private function resolveExistingStudent(string $sourceId, string $nim): Mahasiswa|false|null
    {
        $matches = Mahasiswa::withTrashed()
            ->where(fn ($query) => $query->where('id_feeder_biodata', $sourceId)->orWhere('id_feeder', $sourceId))
            ->limit(2)
            ->get();
        if ($matches->count() > 1) {
            return false;
        }
        if ($matches->count() === 1) {
            return $matches->first();
        }

        $legacyMatches = Mahasiswa::withTrashed()
            ->where('nim', $nim)
            ->whereNull('id_feeder')
            ->whereNull('id_feeder_biodata')
            ->limit(2)
            ->get();

        return $legacyMatches->count() > 1 ? false : $legacyMatches->first();
    }

    private function mapPddiktiStatus(mixed $value, mixed $label = null): ?string
    {
        $status = strtoupper(trim((string) $value));
        if (in_array($status, MahasiswaStatus::codes(), true)) {
            return $status;
        }

        $name = mb_strtoupper(trim((string) $label), 'UTF-8');
        $name = preg_replace('/\s+/', ' ', $name);

        return match ($name) {
            'AKTIF' => MahasiswaStatus::AKTIF,
            'CUTI' => MahasiswaStatus::CUTI,
            'SEDANG DOUBLE DEGREE' => MahasiswaStatus::DOUBLE_DEGREE,
            'KAMPUS MERDEKA' => MahasiswaStatus::KAMPUS_MERDEKA,
            'NON-AKTIF', 'NON AKTIF' => MahasiswaStatus::NON_AKTIF,
            'MENUNGGU UJIAN' => MahasiswaStatus::MENUNGGU_UJIAN,
            'LULUS', 'MUTASI', 'MENGAJUKAN PENGUNDURAN DIRI', 'DIKELUARKAN',
            'PUTUS STUDI', 'MENINGGAL DUNIA' => MahasiswaStatus::NON_AKTIF,
            default => null,
        };
    }

    private function mapPddiktiGender(mixed $value): ?string
    {
        return match (mb_strtoupper(trim((string) $value))) {
            'L' => 'L',
            'P' => 'P',
            default => null,
        };
    }

    private function mapPddiktiBiodata(array $row): array
    {
        $attributes = [
            'nik' => $row['nik'] ?? null,
            'nisn' => $row['nisn'] ?? null,
            'tempat_lahir' => $row['tempat_lahir'] ?? null,
            'tanggal_lahir' => $this->parsePddiktiDate($row['tanggal_lahir'] ?? null),
            'agama' => $row['nama_agama'] ?? null,
            'alamat' => $row['jalan'] ?? null,
            'rt' => $row['rt'] ?? null,
            'rw' => $row['rw'] ?? null,
            'dusun' => $row['dusun'] ?? null,
            'kelurahan' => $row['kelurahan'] ?? null,
            'kode_pos' => isset($row['kode_pos']) ? (string) $row['kode_pos'] : null,
            'jenis_tinggal' => $row['nama_jenis_tinggal'] ?? null,
            'alat_transportasi' => $row['nama_alat_transportasi'] ?? null,
            'telepon' => $row['handphone'] ?? $row['telepon'] ?? null,
            'email' => $row['email'] ?? null,
            'nama_ibu_kandung' => $row['nama_ibu_kandung'] ?? null,
            'nik_ibu' => $row['nik_ibu'] ?? null,
            'nama_ayah' => $row['nama_ayah'] ?? null,
            'nik_ayah' => $row['nik_ayah'] ?? null,
            'nama_wali' => $row['nama_wali'] ?? null,
        ];
        foreach ($attributes as $field => $value) {
            if (is_string($value)) {
                $attributes[$field] = trim($value) !== '' ? trim($value) : null;
            }
        }
        if (is_array($row['nama_wilayah'] ?? null)) {
            $attributes['kecamatan'] = $row['nama_wilayah']['kecamatan'] ?? null;
            $attributes['kota'] = $row['nama_wilayah']['kabupaten'] ?? $row['nama_wilayah']['kota'] ?? null;
            $attributes['provinsi'] = $row['nama_wilayah']['provinsi'] ?? null;
        }
        return $attributes;
    }

    private function parsePddiktiDate(mixed $value): ?string
    {
        if (!is_string($value) || trim($value) === '' || str_starts_with($value, '0000-00-00')) {
            return null;
        }
        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function skipPddikti(array &$result, int $index, mixed $row, string $reason): void
    {
        $result['skipped']++;
        if (count($result['skipped_items']) < 100) {
            $result['skipped_items'][] = [
                'source_id' => is_array($row) ? ($row['id_mahasiswa'] ?? null) : null,
                'item_ref' => is_array($row) ? ($row['nim'] ?? $row['nipd'] ?? null) : null,
                'row' => $index + 1,
                'reason' => $reason,
            ];
        }
    }

    public function markProgressFailed(string $progressId, string $message): void
    {
        $this->updateProgress($progressId, [
            'status' => 'failed',
            'phase' => 'failed',
            'message' => $message,
            'finished_at' => now()->toISOString(),
        ]);
    }

    public function markProgressRunning(string $progressId, string $message): void
    {
        $this->updateProgress($progressId, ['status' => 'running', 'phase' => 'starting', 'message' => $message]);
    }

    public function completeProgress(string $progressId, array $result): void
    {
        $current = cache()->get('siakad-mahasiswa-pddikti-sync:' . $progressId, []);
        $total = (int) ($result['read_count'] ?? $result['source_total'] ?? 0);
        $workTotal = max($total, (int) ($current['work_total'] ?? $total));
        $this->updateProgress($progressId, [
            'status' => 'completed',
            'phase' => 'completed',
            'processed' => (int) ($result['biodata_total'] ?? $total),
            'total' => (int) ($result['biodata_total'] ?? $total),
            'phase_processed' => (int) ($result['biodata_total'] ?? $total),
            'phase_total' => (int) ($result['biodata_total'] ?? $total),
            'work_processed' => $workTotal,
            'work_total' => $workTotal,
            'progress' => 100,
            'message' => 'Sinkronisasi mahasiswa selesai.',
            'result' => $result,
            'finished_at' => now()->toISOString(),
        ]);
    }

    private function updateProgress(string $progressId, array $updates): void
    {
        $key = 'siakad-mahasiswa-pddikti-sync:' . $progressId;
        cache()->put($key, array_replace_recursive(cache()->get($key, []), $updates), now()->addHours(2));
    }

    public static function buildProgressSnapshot(int $sourceTotal, int $processed, int $created, int $updated, int $skipped, int $failed, string $phase = 'saving'): array
    {
        return [
            'status' => 'running',
            'phase' => $phase,
            'progress' => $sourceTotal > 0 ? min(99, (int) floor(($processed / $sourceTotal) * 100)) : 0,
            'processed' => min($processed, $sourceTotal),
            'total' => $sourceTotal,
            'message' => "Memproses {$processed} dari {$sourceTotal} record mahasiswa.",
            'result' => [
                'read_count' => $sourceTotal,
                'source_total' => $sourceTotal,
                'created' => $created,
                'updated' => $updated,
                'synced' => $created + $updated,
                'skipped' => $skipped,
                'failed' => $failed,
                'skipped_items' => [],
            ],
        ];
    }
}