<?php

namespace App\Services\IAM;

use App\Jobs\SyncPddiktiKrsJob;
use App\Models\IamApiIntegration;
use App\Models\Siakad\Krs;
use App\Models\Siakad\KrsDetail;
use App\Models\Siakad\Mahasiswa;
use App\Models\Siakad\MasterKelas;
use App\Models\Siakad\MataKuliah;
use App\Models\Siakad\ProgramStudi;
use App\Models\Siakad\TahunAkademik;
use App\Services\IAM\Concerns\QueuesPddiktiSync;
use App\Services\IAM\Contracts\QueuedPddiktiSyncService;
use App\Services\Siakad\NeoFeederService;
use Illuminate\Support\Facades\DB;

/**
 * Sinkronisasi KRS historis dari Neo Feeder (GetKRSMahasiswa) ke siakad_krs + siakad_krs_detail.
 *
 * KRS Feeder berisi mahasiswa x periode x mata kuliah (+ label rombel), tanpa kelas konkret. Karena itu baris detail
 * disimpan dengan kelas_id NULL, mata_kuliah_id terisi, dan master_kelas_id (rombel) bila ditemukan; siakad_jadwal_kelas
 * tidak disentuh. Data dibaca per periode (filter id_periode) dengan urutan majemuk unik agar paging offset deterministik.
 * Tidak ada record yang dihapus; KRS dan detail yang sudah ada (mis. dari KRS online) tidak ditimpa.
 */
class KrsHistorisIntegrationSyncService implements QueuedPddiktiSyncService
{
    use QueuesPddiktiSync;

    private const PDDIKTI_BATCH_SIZE = 500;
    private const MAX_PAGES_PER_PERIOD = 200;
    private const PROGRESS_PREFIX = 'siakad-krs-pddikti-sync:';
    private const MAX_DETAIL_ITEMS = 100;
    private const ORDER = 'id_registrasi_mahasiswa,id_periode,id_matkul,id_kelas';
    private const REASON_STUDENT_UNMAPPED = 'Registrasi mahasiswa Feeder belum dipetakan ke siakad_mahasiswa.id_feeder_riwayat.';
    private const REASON_OTHER_REGISTRATION = 'Registrasi lama: mahasiswa lokal ber-NIM sama memakai registrasi Feeder lain (alih bentuk/pindah prodi); tidak ditempel otomatis.';

    /** @var array<string, int|null> cache pencarian rombel (Master Kelas) per proses */
    private array $rombelCache = [];

    public function __construct(private NeoFeederService $neoFeeder) {}

    public function progressPrefix(): string
    {
        return self::PROGRESS_PREFIX;
    }

    public function syncLabel(): string
    {
        return 'KRS historis';
    }

    protected function jobClass(): string
    {
        return SyncPddiktiKrsJob::class;
    }

    /** Uji Koneksi: read-only, satu sampel, tidak menulis target. */
    public function testPddiktiConnection(string $action): array
    {
        if ($action !== 'GetKRSMahasiswa') {
            throw new ApiIntegrationSyncException('Action tes koneksi KRS PDDIKTI tidak valid.');
        }

        try {
            $response = $this->neoFeeder->request($action, ['filter' => '', 'order' => self::ORDER, 'limit' => 1, 'offset' => 0]);
        } catch (\Throwable) {
            throw new ApiIntegrationSyncException('Koneksi Neo Feeder gagal. Periksa konfigurasi dan hak akses GetKRSMahasiswa.', 502);
        }
        if ((int) ($response['error_code'] ?? -1) !== 0) {
            throw new ApiIntegrationSyncException('Neo Feeder menolak permintaan GetKRSMahasiswa. Periksa hak akses akun Feeder.', 502);
        }
        $rows = $response['data'] ?? null;
        if (!is_array($rows)) {
            throw new ApiIntegrationSyncException('Respons GetKRSMahasiswa dari Neo Feeder tidak valid.', 502);
        }

        return [
            'action' => $action,
            'data_count' => count($rows),
            'sample_fields' => isset($rows[0]) && is_array($rows[0]) ? array_keys($rows[0]) : [],
            'read_only' => true,
        ];
    }

    /**
     * Dijalankan worker queue. GetCountKRSMahasiswa timeout pada Neo Feeder kampus (diverifikasi live, juga dengan filter periode),
     * sehingga total baris tidak diketahui: progres indeterminate pada jumlah baris, dengan posisi periode pada pesan.
     */
    public function processPddikti(IamApiIntegration $integration, ?string $progressId = null): array
    {
        if (!in_array('pddikti', $integration->supported_sources ?? [], true)) {
            throw new ApiIntegrationSyncException('Integrasi KRS tidak mendukung sumber PDDIKTI.');
        }

        $periods = TahunAkademik::query()->whereNotNull('id_feeder')->orderBy('id_feeder')->get(['id', 'id_feeder', 'is_active'])
            ->filter(fn (TahunAkademik $period): bool => preg_match('/^[A-Za-z0-9_-]{1,20}$/', (string) $period->id_feeder) === 1)
            ->values();
        if ($periods->isEmpty()) {
            throw new ApiIntegrationSyncException('Belum ada tahun akademik yang dipetakan ke ID Feeder; jalankan sinkronisasi tahun akademik terlebih dahulu.', 422);
        }

        $this->rombelCache = [];
        $result = [
            'source_total' => 0,
            'processed' => 0,
            'periods_total' => $periods->count(),
            'periods_done' => 0,
            'krs_created' => 0,
            'created' => 0,
            'updated' => 0,
            'unchanged' => 0,
            'collapsed' => 0,
            'synced' => 0,
            'skipped' => 0,
            'failed' => 0,
            'without_rombel' => 0,
            'pages' => 0,
            'expected_total' => null,
            'skipped_by_reason' => [],
            'skipped_items' => [],
        ];
        $this->updatePddiktiProgress($progressId, [
            'status' => 'running', 'phase' => 'reading', 'progress' => null, 'processed' => 0, 'total' => null,
            'result' => $result, 'message' => "Membaca KRS dari PDDIKTI per periode ({$periods->count()} periode); total baris belum diketahui.",
        ]);

        foreach ($periods as $position => $period) {
            $this->importPeriod($period, $position + 1, $periods->count(), $result, $progressId);
        }
        $result['synced'] = $result['created'] + $result['updated'] + $result['unchanged'];

        return $result;
    }

    private function importPeriod(TahunAkademik $period, int $position, int $periodTotal, array &$result, ?string $progressId): void
    {
        $offset = 0;
        $ended = false;
        for ($page = 0; $page < self::MAX_PAGES_PER_PERIOD; $page++) {
            $batch = $this->fetchBatch((string) $period->id_feeder, $offset);
            DB::transaction(function () use ($batch, $offset, $period, &$result): void {
                $this->persistBatch($batch, $offset, $period, $result);
            });

            $count = count($batch);
            $result['processed'] += $count;
            $result['source_total'] = $result['processed'];
            $result['synced'] = $result['created'] + $result['updated'] + $result['unchanged'];
            if ($count > 0) {
                $result['pages']++;
            }
            $this->updatePddiktiProgress($progressId, [
                'status' => 'running', 'phase' => 'saving', 'progress' => null, 'processed' => $result['processed'], 'total' => null,
                'result' => $result,
                'message' => "Periode {$period->id_feeder} ({$position} dari {$periodTotal}): {$result['processed']} baris KRS diproses.",
            ]);
            unset($batch);
            if ($count < self::PDDIKTI_BATCH_SIZE) {
                $ended = true;
                break;
            }
            $offset += self::PDDIKTI_BATCH_SIZE;
        }
        if (!$ended) {
            throw new ApiIntegrationSyncException("Data KRS periode {$period->id_feeder} mencapai batas " . self::MAX_PAGES_PER_PERIOD . ' batch tanpa penanda akhir; sinkronisasi tidak dinyatakan selesai.', 422);
        }
        $result['periods_done']++;
        $this->updatePddiktiProgress($progressId, [
            'status' => 'running', 'phase' => 'saving', 'progress' => null, 'processed' => $result['processed'], 'total' => null,
            'result' => $result,
            'message' => "Periode {$period->id_feeder} ({$position} dari {$periodTotal}) selesai: {$result['processed']} baris KRS diproses.",
        ]);
    }

    /**
     * Satu batch dalam satu transaksi: mapping mahasiswa/mata kuliah/rombel, KRS per mahasiswa, detail per mata kuliah.
     */
    private function persistBatch(array $batch, int $offset, TahunAkademik $period, array &$result): void
    {
        $valid = [];
        foreach ($batch as $index => $row) {
            $reason = $this->validateRow($row, (string) $period->id_feeder);
            if ($reason !== null) {
                $this->skip($result, $offset + $index, $row, $reason);
                continue;
            }
            $valid[] = ['index' => $offset + $index, 'row' => $row];
        }
        if ($valid === []) {
            return;
        }

        $students = Mahasiswa::query()->whereIn('id_feeder_riwayat', array_unique(array_map(fn ($e) => trim((string) $e['row']['id_registrasi_mahasiswa']), $valid)))
            ->pluck('id', 'id_feeder_riwayat')->all();
        $courses = MataKuliah::query()->whereIn('id_feeder', array_unique(array_map(fn ($e) => trim((string) $e['row']['id_matkul']), $valid)))
            ->pluck('id', 'id_feeder')->all();
        $programs = ProgramStudi::query()->whereIn('id_feeder', array_unique(array_filter(array_map(fn ($e) => trim((string) ($e['row']['id_prodi'] ?? '')), $valid))))
            ->pluck('id', 'id_feeder')->all();

        // NIM yang ada di lokal pada baris dengan registrasi lain: orang yang sama dengan beberapa registrasi (alih bentuk/pindah prodi).
        $unmappedNims = [];
        foreach ($valid as $entry) {
            if (!isset($students[trim((string) $entry['row']['id_registrasi_mahasiswa'])]) && trim((string) ($entry['row']['nim'] ?? '')) !== '') {
                $unmappedNims[] = trim((string) $entry['row']['nim']);
            }
        }
        $nimsWithOtherRegistration = $unmappedNims === []
            ? []
            : Mahasiswa::query()->whereIn('nim', array_unique($unmappedNims))->pluck('id', 'nim')->all();

        $items = [];
        foreach ($valid as $entry) {
            $row = $entry['row'];
            $studentId = $students[trim((string) $row['id_registrasi_mahasiswa'])] ?? null;
            if ($studentId === null) {
                // Sync mahasiswa menyimpan satu baris per orang (registrasi terbaru). KRS milik registrasi lain tidak ditempel
                // otomatis karena prodi/angkatannya dapat berbeda dan riwayat akademik akan tercampur.
                $this->skip($result, $entry['index'], $row, isset($nimsWithOtherRegistration[trim((string) ($row['nim'] ?? ''))])
                    ? self::REASON_OTHER_REGISTRATION
                    : self::REASON_STUDENT_UNMAPPED);
                continue;
            }
            $courseId = $courses[trim((string) $row['id_matkul'])] ?? null;
            if ($courseId === null) {
                $this->skip($result, $entry['index'], $row, 'Mata kuliah Feeder belum dipetakan ke siakad_mata_kuliah.id_feeder.');
                continue;
            }
            $rombelId = $this->resolveRombel($programs[trim((string) ($row['id_prodi'] ?? ''))] ?? null, (string) ($row['nama_kelas_kuliah'] ?? ''));
            $items[] = ['index' => $entry['index'], 'student' => (int) $studentId, 'course' => (int) $courseId, 'rombel' => $rombelId];
        }
        if ($items === []) {
            return;
        }

        // KRS per mahasiswa pada periode ini: yang sudah ada (mis. dari KRS online) dipakai apa adanya.
        $studentIds = array_values(array_unique(array_column($items, 'student')));
        $krsByStudent = Krs::query()->where('tahun_akademik_id', $period->id)->whereIn('mahasiswa_id', $studentIds)
            ->orderBy('id')->get()->unique('mahasiswa_id')->keyBy('mahasiswa_id');
        foreach ($studentIds as $studentId) {
            if (!$krsByStudent->has($studentId)) {
                $krsByStudent->put($studentId, Krs::create([
                    'mahasiswa_id' => $studentId,
                    'tahun_akademik_id' => $period->id,
                    'total_sks_diambil' => 0,
                    // Periode berjalan dianggap sudah disetujui; periode lampau dikunci. Tidak ada persetujuan dosen yang ditebak.
                    'status' => $period->is_active ? 'disetujui' : 'dikunci',
                    'locked_by_keuangan' => false,
                ]));
                $result['krs_created']++;
            }
        }

        $existing = KrsDetail::query()->whereIn('krs_id', $krsByStudent->pluck('id')->all())
            ->get(['id', 'krs_id', 'kelas_id', 'mata_kuliah_id', 'master_kelas_id'])
            ->filter(fn (KrsDetail $detail): bool => $detail->mata_kuliah_id !== null)
            ->keyBy(fn (KrsDetail $detail): string => $detail->krs_id . '|' . $detail->mata_kuliah_id);

        $insert = [];
        $touchedKrs = [];
        $now = now();
        foreach ($items as $item) {
            $krsId = (int) $krsByStudent->get($item['student'])->id;
            $key = $krsId . '|' . $item['course'];
            if ($item['rombel'] === null) {
                $result['without_rombel']++;
            }

            if (isset($insert[$key])) {
                $result['collapsed']++; // mata kuliah sama pada KRS yang sama (mis. beberapa id_kelas Feeder dengan rombel serupa)
                continue;
            }
            $found = $existing->get($key);
            if ($found) {
                if ($found->kelas_id === null && $found->master_kelas_id === null && $item['rombel'] !== null) {
                    $found->update(['master_kelas_id' => $item['rombel']]);
                    $result['updated']++;
                } else {
                    $result['unchanged']++;
                }
                continue;
            }
            $insert[$key] = [
                'krs_id' => $krsId,
                'kelas_id' => null,
                'mata_kuliah_id' => $item['course'],
                'master_kelas_id' => $item['rombel'],
                'status' => 'aktif',
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $touchedKrs[$krsId] = true;
        }

        if ($insert !== []) {
            foreach (array_chunk(array_values($insert), 200) as $chunk) {
                KrsDetail::query()->insert($chunk);
            }
            $result['created'] += count($insert);
            $this->recomputeTotalSks(array_keys($touchedKrs));
        }
    }

    /** total_sks_diambil mengikuti jumlah SKS mata kuliah pada detail aktif, hanya untuk KRS yang detailnya bertambah. */
    private function recomputeTotalSks(array $krsIds): void
    {
        $sums = DB::table('siakad_krs_detail as d')
            ->join('siakad_mata_kuliah as m', 'm.id', '=', 'd.mata_kuliah_id')
            ->whereIn('d.krs_id', $krsIds)->where('d.status', 'aktif')
            ->groupBy('d.krs_id')->selectRaw('d.krs_id, sum(m.total_sks) as total')->pluck('total', 'krs_id');
        foreach ($krsIds as $krsId) {
            DB::table('siakad_krs')->where('id', $krsId)->update(['total_sks_diambil' => (int) ($sums[$krsId] ?? 0)]);
        }
    }

    /** Rombel hanya dicari (tidak dibuat): prodi + label (huruf besar) + angkatan dari awalan tahun dua digit bila ada. */
    private function resolveRombel(?int $programId, string $label): ?int
    {
        $label = mb_strtoupper(preg_replace('/\s+/u', ' ', trim($label)) ?? '');
        if ($programId === null || $label === '' || mb_strlen($label) > 20) {
            return null;
        }
        $cohort = preg_match('/^(\d{2})[A-Z]/D', $label, $matches) === 1 ? 2000 + (int) $matches[1] : null;
        $cacheKey = "{$programId}|{$label}|" . ($cohort ?? 'null');
        if (!array_key_exists($cacheKey, $this->rombelCache)) {
            $matches = MasterKelas::query()->where('program_studi_id', $programId)->where('nama_kelas', $label)
                ->when($cohort === null, fn ($query) => $query->whereNull('tahun_angkatan'), fn ($query) => $query->where('tahun_angkatan', $cohort))
                ->limit(2)->pluck('id');
            // Ambigu (lebih dari satu kecocokan) tidak ditebak.
            $this->rombelCache[$cacheKey] = $matches->count() === 1 ? (int) $matches->first() : null;
        }

        return $this->rombelCache[$cacheKey];
    }

    private function validateRow(mixed $row, string $periodId): ?string
    {
        if (!is_array($row)) {
            return 'Item KRS Neo Feeder bukan object data.';
        }
        foreach (['id_registrasi_mahasiswa', 'id_periode', 'id_matkul'] as $field) {
            if (!isset($row[$field]) || !is_string($row[$field]) || trim($row[$field]) === '') {
                return "Field {$field} wajib tersedia dari GetKRSMahasiswa.";
            }
        }
        if (trim($row['id_periode']) !== $periodId) {
            return 'Periode baris KRS tidak sesuai dengan periode yang diminta.';
        }

        return null;
    }

    private function fetchBatch(string $periodId, int $offset): array
    {
        try {
            $response = $this->requestPddiktiWithRetry('GetKRSMahasiswa', [
                'filter' => "id_periode='{$periodId}'",
                'order' => self::ORDER,
                'limit' => self::PDDIKTI_BATCH_SIZE,
                'offset' => $offset,
            ]);
        } catch (\Throwable $exception) {
            throw new ApiIntegrationSyncException('Gagal mengambil data KRS dari Neo Feeder PDDIKTI: ' . $exception->getMessage(), 502);
        }
        if ((int) ($response['error_code'] ?? -1) !== 0) {
            throw new ApiIntegrationSyncException('Neo Feeder PDDIKTI menolak permintaan GetKRSMahasiswa.', 502);
        }
        $batch = $response['data'] ?? [];
        if (!is_array($batch)) {
            throw new ApiIntegrationSyncException('Respons GetKRSMahasiswa dari Neo Feeder tidak valid.', 502);
        }
        if ($batch !== [] && !array_is_list($batch)) {
            $batch = [$batch];
        }
        if (count($batch) > self::PDDIKTI_BATCH_SIZE) {
            throw new ApiIntegrationSyncException('GetKRSMahasiswa mengembalikan lebih dari ' . self::PDDIKTI_BATCH_SIZE . ' record dalam satu batch.', 502);
        }

        return $batch;
    }

    private function skip(array &$result, int $index, mixed $row, string $reason): void
    {
        $result['skipped']++;
        $result['skipped_by_reason'][$reason] = ($result['skipped_by_reason'][$reason] ?? 0) + 1;
        if (count($result['skipped_items']) < self::MAX_DETAIL_ITEMS) {
            $result['skipped_items'][] = [
                'source_id' => is_array($row) ? ($row['id_registrasi_mahasiswa'] ?? null) : null,
                'item_ref' => is_array($row) ? ($row['kode_mata_kuliah'] ?? null) : null,
                'row' => $index + 1,
                'reason' => $reason,
            ];
        }
    }
}
