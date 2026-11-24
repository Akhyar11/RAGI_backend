<?php

namespace App\Services\IAM;

use App\Jobs\SyncPddiktiKelasJob;
use App\Models\IamApiIntegration;
use App\Models\Siakad\DistribusiMengajar;
use App\Models\Siakad\Dosen;
use App\Models\Siakad\MasterKelas;
use App\Models\Siakad\MataKuliah;
use App\Models\Siakad\ProgramStudi;
use App\Models\Siakad\TahunAkademik;
use App\Services\Siakad\NeoFeederService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class KelasPddiktiIntegrationSyncService
{
    private const PAGE_SIZE = 500;
    private const MAX_PAGES = 100;
    private const PROGRESS_PREFIX = 'siakad-kelas-pddikti-sync:';
    private const LOCK_KEY = 'siakad-kelas-pddikti-sync:lock';

    private const CLASS_MAP_TTL_HOURS = 2;

    /** Pemetaan sementara id_kelas_kuliah Feeder -> relasi lokal; tidak pernah ditulis ke siakad_jadwal_kelas. */
    private array $pendingClassMap = [];

    public function __construct(private NeoFeederService $neoFeeder) {}

    public function testPddiktiConnection(string $action): array
    {
        if ($action !== 'GetListKelasKuliah') {
            throw new ApiIntegrationSyncException('Action tes koneksi kelas PDDIKTI tidak valid.');
        }

        try {
            $response = $this->neoFeeder->request($action, [
                'filter' => '',
                'order' => 'id_kelas_kuliah',
                'limit' => 1,
                'offset' => 0,
            ]);
        } catch (\Throwable) {
            throw new ApiIntegrationSyncException('Koneksi Neo Feeder gagal. Periksa konfigurasi dan hak akses GetListKelasKuliah.', 502);
        }

        if ((int) ($response['error_code'] ?? -1) !== 0) {
            throw new ApiIntegrationSyncException('Neo Feeder menolak permintaan GetListKelasKuliah. Periksa hak akses akun Feeder.', 502);
        }

        $rows = $this->normalizeRows($response['data'] ?? []);
        if ($rows === null) {
            throw new ApiIntegrationSyncException('Respons GetListKelasKuliah dari Neo Feeder tidak valid.', 502);
        }

        return [
            'action' => $action,
            'data_count' => count($rows),
            'sample_fields' => isset($rows[0]) && is_array($rows[0]) ? array_keys($rows[0]) : [],
            'read_only' => true,
        ];
    }

    public function sync(IamApiIntegration $integration, ?int $requestedBy = null): array
    {
        $this->assertSupportedIntegration($integration);
        if (config('queue.default') === 'sync') {
            throw new ApiIntegrationSyncException('Sinkronisasi kelas memerlukan queue worker. Atur QUEUE_CONNECTION ke database dan jalankan worker queue default.', 503);
        }

        $lock = Cache::lock(self::LOCK_KEY, 3600);
        if (!$lock->get()) {
            throw new ApiIntegrationSyncException('Sinkronisasi kelas PDDIKTI sedang berjalan. Tunggu hingga selesai.', 409);
        }

        $progressId = (string) Str::uuid();
        $progressKey = self::PROGRESS_PREFIX . $progressId;
        Cache::put($progressKey, [
            'progress_id' => $progressId,
            'integration_id' => $integration->id,
            'requested_by' => $requestedBy,
            'status' => 'queued',
            'phase' => 'queued',
            'progress' => null,
            'processed' => 0,
            'total' => null,
            'message' => 'Sinkronisasi kelas masuk antrean, menunggu worker.',
            'started_at' => now()->toISOString(),
            'result' => $this->emptyResult(),
        ], now()->addHours(2));

        try {
            $integration->update(['last_synced_at' => now(), 'last_status' => 'processing']);
            SyncPddiktiKelasJob::dispatch($integration->id, $progressId, $lock->owner(), $requestedBy)->afterCommit();
        } catch (\Throwable) {
            Cache::forget($progressKey);
            $lock->release();
            $integration->update(['last_synced_at' => now(), 'last_status' => 'failed']);
            throw new ApiIntegrationSyncException('Tidak dapat menjadwalkan sinkronisasi kelas. Periksa queue worker dan coba lagi.', 503);
        }

        return ['progress_id' => $progressId];
    }

    public function process(IamApiIntegration $integration, string $progressId): array
    {
        $this->assertSupportedIntegration($integration);
        $result = $this->emptyResult();
        $this->updateProgress($progressId, [
            'status' => 'running',
            'phase' => 'reading',
            'progress' => null,
            'processed' => 0,
            'total' => null,
            'phase_processed' => 0,
            'phase_total' => null,
            'work_processed' => 0,
            'work_total' => null,
            'message' => 'Membaca kelas kuliah dari PDDIKTI; total belum diketahui.',
            'result' => $result,
        ]);

        $offset = 0;
        for ($page = 0; $page < self::MAX_PAGES; $page++) {
            $batch = $this->fetchBatch($offset);
            if (count($batch) > self::PAGE_SIZE) {
                throw new ApiIntegrationSyncException('GetListKelasKuliah mengembalikan lebih dari ' . self::PAGE_SIZE . ' record dalam satu batch.', 502);
            }

            DB::transaction(function () use ($batch, $offset, &$result): void {
                foreach ($batch as $index => $row) {
                    try {
                        $outcome = DB::transaction(fn (): string => $this->importRow($row));
                    } catch (\Throwable) {
                        $outcome = 'Data kelas gagal disimpan karena konflik database atau referensi.';
                    }
                    if ($outcome === 'created' || $outcome === 'updated') {
                        $result[$outcome]++;
                        $result['synced']++;
                    } else {
                        $result['skipped']++;
                        if (count($result['skipped_items']) < 100) {
                            $result['skipped_items'][] = [
                                'source_id' => is_array($row) ? ($row['id_kelas_kuliah'] ?? null) : null,
                                'item_ref' => is_array($row) ? ($row['nama_kelas_kuliah'] ?? null) : null,
                                'row' => $offset + $index + 1,
                                'reason' => $outcome,
                            ];
                        }
                    }
                }
            });

            $this->flushClassMap($progressId);
            $batchCount = count($batch);
            $result['processed'] += $batchCount;
            if ($batchCount > 0) {
                $result['pages']++;
            }
            $this->updateProgress($progressId, [
                'status' => 'running',
                'phase' => 'reading',
                'progress' => null,
                'processed' => $result['processed'],
                'total' => null,
                'phase_processed' => $result['processed'],
                'phase_total' => null,
                'work_processed' => $result['processed'],
                'work_total' => null,
                'message' => "Sudah membaca {$result['processed']} kelas kuliah dari PDDIKTI.",
                'result' => $result,
            ]);
            unset($batch);
            if ($batchCount < self::PAGE_SIZE) {
                $result['source_total'] = $result['processed'];
                break;
            }
            $offset += self::PAGE_SIZE;
        }

        if ($result['processed'] >= self::PAGE_SIZE * self::MAX_PAGES
            && $result['processed'] % self::PAGE_SIZE === 0) {
            throw new ApiIntegrationSyncException('Data kelas PDDIKTI mencapai batas ' . self::MAX_PAGES . ' batch (' . number_format(self::PAGE_SIZE * self::MAX_PAGES, 0, ',', '.') . ' record) tanpa penanda akhir; sinkronisasi tidak dinyatakan selesai.', 422);
        }

        $this->updateProgress($progressId, [
            'phase' => 'assignments',
            'progress' => null,
            'phase_processed' => 0,
            'phase_total' => null,
            'work_processed' => $result['processed'],
            'work_total' => null,
            'message' => 'Membaca dosen pengajar kelas dari PDDIKTI.',
            'result' => $result,
        ]);
        $assignmentResult = $this->syncTeachingAssignments($progressId);
        $result['assignment_source_total'] = $assignmentResult['source_total'];
        $result['distributions_created'] = $assignmentResult['created'];
        $result['distributions_updated'] = $assignmentResult['updated'];
        $result['assignments_synced'] = $assignmentResult['synced'];
        $result['assignment_skipped'] = $assignmentResult['skipped'];
        $result['assignment_skipped_items'] = $assignmentResult['skipped_items'];
        $this->updateProgress($progressId, ['result' => $result]);

        return $result;
    }

    public function progressPrefix(): string
    {
        return self::PROGRESS_PREFIX;
    }

    public function emptyResult(): array
    {
        return [
            'source_total' => 0,
            'processed' => 0,
            'created' => 0,
            'updated' => 0,
            'synced' => 0,
            'skipped' => 0,
            'failed' => 0,
            'pages' => 0,
            'skipped_items' => [],
            'assignment_source_total' => 0,
            'distributions_created' => 0,
            'distributions_updated' => 0,
            'assignments_synced' => 0,
            'assignment_skipped' => 0,
            'assignment_skipped_items' => [],
        ];
    }

    private function syncTeachingAssignments(string $progressId): array
    {
        $result = [
            'source_total' => 0,
            'created' => 0,
            'updated' => 0,
            'synced' => 0,
            'skipped' => 0,
            'pages' => 0,
            'skipped_items' => [],
        ];
        $offset = 0;

        for ($page = 0; $page < self::MAX_PAGES; $page++) {
            $batch = $this->fetchTeachingAssignmentBatch($offset);
            if (count($batch) > self::PAGE_SIZE) {
                throw new ApiIntegrationSyncException('GetDosenPengajarKelasKuliah mengembalikan lebih dari ' . self::PAGE_SIZE . ' record dalam satu batch.', 502);
            }

            DB::transaction(function () use ($batch, $offset, &$result, $progressId): void {
                $groups = [];
                foreach ($batch as $index => $row) {
                    $resolved = $this->resolveTeachingAssignment($row, $progressId);
                    if (is_string($resolved)) {
                        $result['skipped']++;
                        if (count($result['skipped_items']) < 100) {
                            $result['skipped_items'][] = [
                                'source_id' => is_array($row) ? ($row['id_aktivitas_mengajar'] ?? $row['id_aktivitas_mahasiswa'] ?? null) : null,
                                'item_ref' => is_array($row) ? ($row['id_kelas_kuliah'] ?? null) : null,
                                'row' => $offset + $index + 1,
                                'reason' => $resolved,
                            ];
                        }
                        continue;
                    }

                    $key = implode(':', [
                        $resolved['program']->id,
                        $resolved['semester']->id,
                        $resolved['course']->id,
                    ]);
                    if (!isset($groups[$key])) {
                        $groups[$key] = [
                            'program' => $resolved['program'],
                            'semester' => $resolved['semester'],
                            'course' => $resolved['course'],
                            'classes' => [],
                            'teachers' => [],
                        ];
                    }
                    $groups[$key]['classes'][$resolved['master_class']->id] = $resolved['master_class'];
                    $teacherId = (int) $resolved['lecturer']->id;
                    $teacher = $groups[$key]['teachers'][$teacherId] ?? [
                        'dosen_id' => $resolved['lecturer']->id,
                        'dosen_nama' => $resolved['lecturer']->nama_lengkap,
                        'dosen_nidn' => $resolved['lecturer']->nidn,
                        'sks_by_class' => [],
                        'kelas_ids' => [],
                        'registrasi_ids' => [],
                    ];
                    $masterClassId = (int) $resolved['master_class']->id;
                    $teacher['sks_by_class'][$masterClassId] = max(
                        (float) ($teacher['sks_by_class'][$masterClassId] ?? 0),
                        (float) $resolved['sks']
                    );
                    $teacher['sks'] = array_sum($teacher['sks_by_class']);
                    $teacher['kelas_ids'][] = $masterClassId;
                    $teacher['kelas_ids'] = array_values(array_unique($teacher['kelas_ids']));
                    if ($resolved['registration_id'] !== '') {
                        $teacher['registrasi_ids'] = array_values(array_unique(array_merge($teacher['registrasi_ids'], [$resolved['registration_id']])));
                    }
                    $groups[$key]['teachers'][$teacherId] = $teacher;
                }

                foreach ($groups as $group) {
                    $outcome = $this->persistTeachingDistribution($group);
                    if ($outcome === 'skipped') {
                        $result['skipped']++;
                        if (count($result['skipped_items']) < 100) {
                            $result['skipped_items'][] = [
                                'source_id' => null,
                                'item_ref' => $group['course']->kode_mk,
                                'row' => $offset + 1,
                                'reason' => 'Distribusi lokal untuk mata kuliah, prodi, dan periode ini terduplikasi.',
                            ];
                        }
                        continue;
                    }
                    $result[$outcome]++;
                    $result['synced'] += count($group['teachers']);
                }
            });

            $batchCount = count($batch);
            $result['source_total'] += $batchCount;
            if ($batchCount > 0) {
                $result['pages']++;
            }
            $currentProgress = Cache::get(self::PROGRESS_PREFIX . $progressId, []);
            $currentResult = $currentProgress['result'] ?? [];
            $this->updateProgress($progressId, [
                'status' => 'running',
                'phase' => 'assignments',
                'progress' => null,
                'work_processed' => $result['source_total'],
                'work_total' => null,
                'message' => "Sudah membaca {$result['source_total']} dosen pengajar kelas dari PDDIKTI.",
                'result' => array_merge($currentResult, ['assignments' => $result]),
            ]);
            unset($batch);
            if ($batchCount < self::PAGE_SIZE) {
                return $result;
            }
            $offset += self::PAGE_SIZE;
        }

        throw new ApiIntegrationSyncException('Data dosen pengajar kelas PDDIKTI mencapai batas ' . self::MAX_PAGES . ' batch (' . number_format(self::PAGE_SIZE * self::MAX_PAGES, 0, ',', '.') . ' record) tanpa penanda akhir.', 422);
    }

    /**
     * Request list ke Feeder dengan retry untuk kegagalan jaringan/timeout saja (halaman ~300 KB bisa lambat).
     * Respons error_code != 0 tidak diulang; ditangani pemanggil. Batch tetap 500 dan offset tidak berubah.
     */
    private function requestWithRetry(string $action, array $params): array
    {
        $delaysSeconds = app()->environment('testing') ? [0, 0] : [3, 10];
        $attempt = 0;
        while (true) {
            try {
                return $this->neoFeeder->request($action, $params);
            } catch (\Throwable $e) {
                if ($attempt >= count($delaysSeconds)) {
                    throw $e;
                }
                if ($delaysSeconds[$attempt] > 0) {
                    sleep($delaysSeconds[$attempt]);
                }
                $attempt++;
            }
        }
    }

    private function fetchTeachingAssignmentBatch(int $offset): array
    {
        try {
            $response = $this->requestWithRetry('GetDosenPengajarKelasKuliah', [
                'filter' => '',
                'order' => 'id_kelas_kuliah',
                'limit' => self::PAGE_SIZE,
                'offset' => $offset,
            ]);
        } catch (\Throwable) {
            throw new ApiIntegrationSyncException('Gagal mengambil data dosen pengajar dari Neo Feeder (GetDosenPengajarKelasKuliah).', 502);
        }
        if ((int) ($response['error_code'] ?? -1) !== 0) {
            throw new ApiIntegrationSyncException('Neo Feeder menolak permintaan GetDosenPengajarKelasKuliah.', 502);
        }
        $rows = $this->normalizeRows($response['data'] ?? null);
        if ($rows === null) {
            throw new ApiIntegrationSyncException('Respons GetDosenPengajarKelasKuliah dari Neo Feeder tidak valid.', 502);
        }
        return $rows;
    }

    private function resolveTeachingAssignment(mixed $row, string $progressId): array|string
    {
        if (!is_array($row)) {
            return 'Item dosen pengajar Neo Feeder bukan object data.';
        }
        foreach (['id_kelas_kuliah', 'id_dosen', 'sks_substansi_total'] as $field) {
            if (!isset($row[$field]) || trim((string) $row[$field]) === '') {
                return "Field {$field} wajib tersedia dari GetDosenPengajarKelasKuliah.";
            }
        }

        $classRef = Cache::get($this->classMapKey($progressId, trim((string) $row['id_kelas_kuliah'])));
        if (!is_array($classRef)) {
            return 'Kelas Feeder tidak ditemukan pada hasil pembacaan GetListKelasKuliah atau belum lolos pemetaan.';
        }
        $program = ProgramStudi::query()->find($classRef['program_studi_id']);
        $semesterModel = TahunAkademik::query()->find($classRef['tahun_akademik_id']);
        $course = MataKuliah::query()->find($classRef['mata_kuliah_id']);
        $masterClass = MasterKelas::query()->find($classRef['master_kelas_id']);
        if (!$program || !$semesterModel || !$course) {
            return 'Relasi lokal kelas ke mata kuliah, prodi, atau tahun akademik tidak tersedia.';
        }
        if (!$masterClass) {
            return 'Master Kelas untuk rombel kelas kuliah belum tersedia.';
        }

        $lecturerMatches = Dosen::query()->where('id_feeder', trim((string) $row['id_dosen']))->limit(2)->get();
        if ($lecturerMatches->count() !== 1) {
            return $lecturerMatches->isEmpty()
                ? 'Dosen Feeder belum dipetakan ke siakad_dosen.'
                : 'ID dosen Feeder cocok ke lebih dari satu siakad_dosen.';
        }
        $lecturer = $lecturerMatches->first();

        // id_registrasi_dosen tidak lagi dicocokkan ke siakad_dosen_penugasan (tabel itu tidak diisi sync PDDIKTI);
        // dosen cukup dikenali lewat id_dosen -> siakad_dosen.id_feeder. Registrasi hanya disimpan sebagai data tambahan.
        $registrationId = isset($row['id_registrasi_dosen']) ? trim((string) $row['id_registrasi_dosen']) : '';
        if (mb_strlen($registrationId) > 100) {
            $registrationId = '';
        }

        $sks = $this->normalizeSks($row['sks_substansi_total']);
        if ($sks === null) {
            return 'Field sks_substansi_total dari dosen pengajar tidak valid.';
        }

        return [
            'course' => $course,
            'program' => $program,
            'semester' => $semesterModel,
            'lecturer' => $lecturer,
            'master_class' => $masterClass,
            'sks' => $sks,
            'registration_id' => $registrationId,
        ];
    }

    private function persistTeachingDistribution(array $group): string
    {
        $matches = DistribusiMengajar::query()
            ->where('program_studi_id', $group['program']->id)
            ->where('tahun_akademik_id', $group['semester']->id)
            ->where('mata_kuliah_id', $group['course']->id)
            ->where('semester', $group['course']->semester_anjuran)
            ->limit(2)
            ->get();
        if ($matches->count() > 1) {
            return 'skipped';
        }

        $distribution = $matches->first();
        $isNew = !$distribution;
        if (!$distribution) {
            $distribution = DistribusiMengajar::firstOrCreate(
                [
                    'program_studi_id' => $group['program']->id,
                    'tahun_akademik_id' => $group['semester']->id,
                    'mata_kuliah_id' => $group['course']->id,
                    'semester' => $group['course']->semester_anjuran,
                ],
                [
                    'kurikulum_id' => $group['course']->kurikulum_id,
                    'dosen_koordinator_id' => null,
                    'dosen_anggota_ids' => [],
                    'kelas_ids' => [],
                    'dosen_anggota_details' => [],
                    'is_active' => true,
                ]
            );
            $isNew = $distribution->wasRecentlyCreated;
        }

        $details = collect($distribution->dosen_anggota_details ?? [])->keyBy('dosen_id');
        foreach ($group['teachers'] as $teacher) {
            $existing = $details->get($teacher['dosen_id'], []);
            $existingClasses = $existing['kelas_ids'] ?? [];
            $details->put($teacher['dosen_id'], array_merge($existing, [
                'dosen_id' => $teacher['dosen_id'],
                'dosen_nama' => $existing['dosen_nama'] ?? $teacher['dosen_nama'],
                'dosen_nidn' => $existing['dosen_nidn'] ?? $teacher['dosen_nidn'],
                'sks' => ($existing['source'] ?? null) === 'pddikti'
                    ? (float) ($teacher['sks'] ?? 0)
                    : ($existing['sks'] ?? $teacher['sks']),
                'kelas_ids' => array_values(array_unique(array_merge($existingClasses, $teacher['kelas_ids']))),
                'registrasi_ids' => array_values(array_unique(array_merge($existing['registrasi_ids'] ?? [], $teacher['registrasi_ids'] ?? []))),
                'source' => ($existing['source'] ?? null) === 'manual' ? 'manual' : 'pddikti',
            ]));
        }

        $memberIds = collect($distribution->dosen_anggota_ids ?? [])
            ->merge($details->keys())
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
        $classIds = collect($distribution->kelas_ids ?? [])
            ->merge(collect($group['classes'])->keys())
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
        $distribution->update([
            'dosen_anggota_ids' => $memberIds,
            'kelas_ids' => $classIds,
            'dosen_anggota_details' => $details->values()->all(),
        ]);

        return $isNew ? 'created' : 'updated';
    }

    private function normalizeSks(mixed $value): ?float
    {
        if (!is_numeric($value) || (float) $value < 0 || (float) $value > 16) {
            return null;
        }
        return (float) $value;
    }

    private function deriveCohortYear(string $classLabel): ?int
    {
        if (preg_match('/^(\d{2})[A-Z]/D', mb_strtoupper(trim($classLabel)), $matches) !== 1) {
            return null;
        }
        return 2000 + (int) $matches[1];
    }

    private function assertSupportedIntegration(IamApiIntegration $integration): void
    {
        if (!$integration->is_active
            || $integration->key !== 'siakad_kelas'
            || ($integration->source_type ?? 'custom_api') !== 'pddikti'
            || !in_array('pddikti', $integration->supported_sources ?? [], true)
            || ($integration->source_schemas['pddikti']['target_entity'] ?? null) !== 'kelas') {
            throw new ApiIntegrationSyncException('Pilih sumber PDDIKTI untuk sinkronisasi kelas SIAKAD.');
        }
    }

    private function fetchBatch(int $offset): array
    {
        try {
            $response = $this->requestWithRetry('GetListKelasKuliah', [
                'filter' => '',
                'order' => 'id_kelas_kuliah',
                'limit' => self::PAGE_SIZE,
                'offset' => $offset,
            ]);
        } catch (\Throwable) {
            throw new ApiIntegrationSyncException('Gagal mengambil batch kelas dari Neo Feeder (GetListKelasKuliah).', 502);
        }
        if ((int) ($response['error_code'] ?? -1) !== 0) {
            throw new ApiIntegrationSyncException('Neo Feeder menolak permintaan GetListKelasKuliah.', 502);
        }
        $rows = $this->normalizeRows($response['data'] ?? null);
        if ($rows === null) {
            throw new ApiIntegrationSyncException('Respons GetListKelasKuliah dari Neo Feeder tidak valid.', 502);
        }
        return $rows;
    }

    private function normalizeRows(mixed $value): ?array
    {
        if (!is_array($value)) {
            return null;
        }
        if ($value === []) {
            return [];
        }
        return array_is_list($value) ? $value : [$value];
    }

    private function importRow(mixed $row): string
    {
        if (!is_array($row)) {
            return 'Item kelas kuliah Neo Feeder bukan object data.';
        }
        foreach (['id_kelas_kuliah', 'id_prodi', 'id_semester', 'id_matkul', 'nama_kelas_kuliah'] as $field) {
            if (!isset($row[$field]) || !is_string($row[$field]) || trim($row[$field]) === '') {
                return "Field {$field} wajib tersedia dari GetListKelasKuliah.";
            }
        }

        $sourceId = trim($row['id_kelas_kuliah']);
        $classLabel = mb_strtoupper(trim($row['nama_kelas_kuliah']));
        if (mb_strlen($sourceId) > 100 || mb_strlen($classLabel) > 20) {
            return 'ID atau nama kelas kuliah melebihi panjang kolom tujuan.';
        }
        $classLabel = preg_replace('/\s+/u', ' ', $classLabel) ?? $classLabel;

        $program = $this->resolveMappedModel(ProgramStudi::class, trim($row['id_prodi']), 'Program Studi PDDIKTI belum dipetakan ke SIAKAD.');
        if (is_string($program)) {
            return $program;
        }
        $semester = $this->resolveMappedModel(TahunAkademik::class, trim($row['id_semester']), 'Semester PDDIKTI belum dipetakan ke Tahun Akademik SIAKAD.');
        if (is_string($semester)) {
            return $semester;
        }
        $mataKuliah = $this->resolveMappedModel(MataKuliah::class, trim($row['id_matkul']), 'Mata kuliah PDDIKTI belum dipetakan ke SIAKAD.');
        if (is_string($mataKuliah)) {
            return $mataKuliah;
        }
        // Angkatan hanya diturunkan bila label berformat tahun dua digit + kode (mis. 26A); selain itu null.
        // Keunikan master kelas dijaga per prodi + nama + angkatan (null dicocokkan dengan whereNull),
        // karena unique index DB tidak menganggap NULL sama sehingga duplikat tidak tertangkap di sana.
        $angkatan = $this->deriveCohortYear($classLabel);
        $masterMatches = MasterKelas::withTrashed()
            ->where('program_studi_id', $program->id)
            ->where('nama_kelas', $classLabel)
            ->when(
                $angkatan === null,
                fn ($query) => $query->whereNull('tahun_angkatan'),
                fn ($query) => $query->where('tahun_angkatan', $angkatan)
            )
            ->limit(2)
            ->get();
        if ($masterMatches->count() > 1) {
            return 'Master Kelas dengan prodi, angkatan, dan nama yang sama terduplikasi.';
        }
        if ($masterMatches->first()?->trashed()) {
            return 'Master Kelas yang cocok sudah dihapus; pulihkan atau petakan secara manual terlebih dahulu.';
        }

        $master = $masterMatches->first();
        $isNewMaster = !$master;
        if (!$master) {
            $master = MasterKelas::firstOrCreate(
                [
                    'program_studi_id' => $program->id,
                    'nama_kelas' => $classLabel,
                    'tahun_angkatan' => $angkatan,
                ],
                [
                    'dosen_pa_id' => null,
                    'is_active' => true,
                ]
            );
            $isNewMaster = $master->wasRecentlyCreated;
        }

        // siakad_jadwal_kelas adalah tabel penjadwalan dan tidak diisi oleh sync Feeder.
        $this->pendingClassMap[$sourceId] = [
            'program_studi_id' => $program->id,
            'tahun_akademik_id' => $semester->id,
            'mata_kuliah_id' => $mataKuliah->id,
            'master_kelas_id' => $master->id,
        ];

        return $isNewMaster ? 'created' : 'updated';
    }

    private function classMapKey(string $progressId, string $sourceId): string
    {
        return self::PROGRESS_PREFIX . $progressId . ':class:' . $sourceId;
    }

    private function flushClassMap(string $progressId): void
    {
        $entries = [];
        foreach ($this->pendingClassMap as $sourceId => $ref) {
            $entries[$this->classMapKey($progressId, (string) $sourceId)] = $ref;
        }
        $this->pendingClassMap = [];
        if ($entries !== []) {
            Cache::putMany($entries, now()->addHours(self::CLASS_MAP_TTL_HOURS));
        }
    }

    private function resolveMappedModel(string $modelClass, string $feederId, string $missingMessage): mixed
    {
        $matches = $modelClass::withTrashed()->where('id_feeder', $feederId)->limit(2)->get();
        if ($matches->count() !== 1) {
            return $matches->isEmpty() ? $missingMessage : 'ID Feeder referensi terhubung ke lebih dari satu data lokal.';
        }
        if ($matches->first()->trashed()) {
            return 'Referensi lokal yang cocok dengan ID Feeder sudah dihapus.';
        }
        if ($modelClass === MataKuliah::class) {
            $matches->first()->load('kurikulum');
        }
        return $matches->first();
    }

    private function updateProgress(string $progressId, array $updates): void
    {
        $key = self::PROGRESS_PREFIX . $progressId;
        Cache::put($key, array_merge(Cache::get($key, []), $updates), now()->addHours(2));
    }
}