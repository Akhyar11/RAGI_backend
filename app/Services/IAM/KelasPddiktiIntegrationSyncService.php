<?php

namespace App\Services\IAM;

use App\Jobs\SyncPddiktiKelasJob;
use App\Models\IamApiIntegration;
use App\Models\Siakad\Kelas;
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
                throw new ApiIntegrationSyncException('GetListKelasKuliah mengembalikan lebih dari 500 record dalam satu batch.', 502);
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
                return $result;
            }
            $offset += self::PAGE_SIZE;
        }

        throw new ApiIntegrationSyncException('Data kelas PDDIKTI mencapai batas 100 batch (50.000 record) tanpa penanda akhir; sinkronisasi tidak dinyatakan selesai.', 422);
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
        ];
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
            $response = $this->neoFeeder->request('GetListKelasKuliah', [
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
        if (preg_match('/^(\d{2})([A-Z].*)$/D', $classLabel, $matches) !== 1) {
            return 'Nama kelas kuliah harus mengikuti format rombel tahun dua digit dan kode kelas, misalnya 26A.';
        }

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
        $angkatan = 2000 + (int) $matches[1];
        $masterMatches = MasterKelas::withTrashed()
            ->where('program_studi_id', $program->id)
            ->where('nama_kelas', $classLabel)
            ->where('tahun_angkatan', $angkatan)
            ->limit(2)
            ->get();
        if ($masterMatches->count() > 1) {
            return 'Master Kelas dengan prodi, angkatan, dan nama yang sama terduplikasi.';
        }
        if ($masterMatches->first()?->trashed()) {
            return 'Master Kelas yang cocok sudah dihapus; pulihkan atau petakan secara manual terlebih dahulu.';
        }

        $classMatches = Kelas::withTrashed()->where('id_feeder', $sourceId)->limit(2)->get();
        if ($classMatches->count() > 1) {
            return 'ID kelas PDDIKTI sudah terhubung ke beberapa kelas lokal.';
        }
        $kelas = $classMatches->first();
        if ($kelas?->trashed()) {
            return 'Kelas lokal yang cocok dengan ID PDDIKTI sudah dihapus.';
        }

        if (!$kelas) {
            $localMatches = Kelas::withTrashed()
                ->where('mata_kuliah_id', $mataKuliah->id)
                ->where('tahun_akademik_id', $semester->id)
                ->where('kode_kelas', $classLabel)
                ->limit(2)
                ->get();
            if ($localMatches->count() > 1) {
                return 'Kelas lokal dengan mata kuliah, periode, dan kode yang sama terduplikasi.';
            }
            $kelas = $localMatches->first();
            if ($kelas?->trashed()) {
                return 'Kelas lokal yang cocok sudah dihapus; pulihkan atau petakan secara manual terlebih dahulu.';
            }
            if ($kelas && filled($kelas->id_feeder) && $kelas->id_feeder !== $sourceId) {
                return 'Kode kelas lokal sudah dipakai oleh ID PDDIKTI yang berbeda.';
            }
        }

        if ($kelas && ((int) $kelas->mata_kuliah_id !== (int) $mataKuliah->id
            || (int) $kelas->tahun_akademik_id !== (int) $semester->id
            || (int) $kelas->program_studi_id !== (int) $program->id
            || mb_strtoupper(trim((string) $kelas->kode_kelas)) !== $classLabel)) {
            return 'ID kelas PDDIKTI bertentangan dengan mata kuliah, semester, prodi, atau kode kelas lokal; pemetaan tidak diubah.';
        }

        MasterKelas::firstOrCreate(
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

        if ($kelas) {
            if (empty($kelas->id_feeder)) {
                $kelas->update(['id_feeder' => $sourceId]);
            }
            return 'updated';
        }

        Kelas::firstOrCreate(
            [
                'mata_kuliah_id' => $mataKuliah->id,
                'tahun_akademik_id' => $semester->id,
                'kode_kelas' => $classLabel,
            ],
            [
                'program_studi_id' => $program->id,
                'nama_kelas' => trim($mataKuliah->nama) . " (Kelas {$classLabel})",
                'id_feeder' => $sourceId,
                'kapasitas' => 40,
                'kuota_krs' => 40,
                'status' => 'draft',
            ]
        );
        return 'created';
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