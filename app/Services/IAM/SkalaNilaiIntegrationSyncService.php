<?php

namespace App\Services\IAM;

use App\Jobs\SyncPddiktiSkalaNilaiJob;
use App\Models\IamApiIntegration;
use App\Models\Siakad\ProgramStudi;
use App\Models\Siakad\SkalaNilai;
use App\Services\IAM\Concerns\QueuesPddiktiSync;
use App\Services\IAM\Contracts\QueuedPddiktiSyncService;
use App\Services\Siakad\NeoFeederService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Sinkronisasi skala nilai per prodi dari Neo Feeder (GetListSkalaNilaiProdi) ke siakad_skala_nilai.
 *
 * Identitas baris adalah id_bobot_nilai (siakad_skala_nilai.id_feeder, unik). Skala standar lokal
 * (program_studi_id NULL) tidak pernah diubah atau dihapus, dan baris yang absen di sumber tidak dihapus.
 * Data satu prodi diproses utuh (rentang antar-huruf saling bergantung), jadi grup prodi yang terpotong
 * batas batch ditahan dan diproses bersama batch berikutnya.
 */
class SkalaNilaiIntegrationSyncService implements QueuedPddiktiSyncService
{
    use QueuesPddiktiSync;

    private const MAX_SOURCE_PAGES = 100;
    private const PDDIKTI_BATCH_SIZE = 500;
    private const PROGRESS_PREFIX = 'siakad-skala-pddikti-sync:';
    private const MAX_DETAIL_ITEMS = 100;

    public function __construct(private NeoFeederService $neoFeeder) {}

    public function progressPrefix(): string
    {
        return self::PROGRESS_PREFIX;
    }

    public function syncLabel(): string
    {
        return 'skala nilai';
    }

    protected function jobClass(): string
    {
        return SyncPddiktiSkalaNilaiJob::class;
    }

    /** Uji Koneksi: read-only, satu sampel, tidak menulis target. */
    public function testPddiktiConnection(string $action): array
    {
        if ($action !== 'GetListSkalaNilaiProdi') {
            throw new ApiIntegrationSyncException('Action tes koneksi skala nilai PDDIKTI tidak valid.');
        }

        try {
            $response = $this->neoFeeder->request($action, ['filter' => '', 'order' => 'id_prodi', 'limit' => 1, 'offset' => 0]);
        } catch (\Throwable) {
            throw new ApiIntegrationSyncException('Koneksi Neo Feeder gagal. Periksa konfigurasi dan hak akses GetListSkalaNilaiProdi.', 502);
        }
        if ((int) ($response['error_code'] ?? -1) !== 0) {
            throw new ApiIntegrationSyncException('Neo Feeder menolak permintaan GetListSkalaNilaiProdi. Periksa hak akses akun Feeder.', 502);
        }
        $rows = $response['data'] ?? null;
        if (!is_array($rows)) {
            throw new ApiIntegrationSyncException('Respons GetListSkalaNilaiProdi dari Neo Feeder tidak valid.', 502);
        }

        return [
            'action' => $action,
            'data_count' => count($rows),
            'sample_fields' => isset($rows[0]) && is_array($rows[0]) ? array_keys($rows[0]) : [],
            'read_only' => true,
        ];
    }

    public function processPddikti(IamApiIntegration $integration, ?string $progressId = null): array
    {
        if (!in_array('pddikti', $integration->supported_sources ?? [], true)) {
            throw new ApiIntegrationSyncException('Integrasi skala nilai tidak mendukung sumber PDDIKTI.');
        }

        $total = $this->fetchPddiktiCount('GetCountSkalaNilaiProdi');
        $result = [
            'source_total' => 0,
            'processed' => 0,
            'created' => 0,
            'updated' => 0,
            'synced' => 0,
            'skipped' => 0,
            'failed' => 0,
            'inactive' => 0,
            'warnings' => 0,
            'pages' => 0,
            'expected_total' => $total,
            'skipped_items' => [],
            'warning_items' => [],
        ];
        $this->updatePddiktiProgress($progressId, [
            'status' => 'running', 'phase' => 'reading', 'progress' => $total === null ? null : 0,
            'processed' => 0, 'total' => $total, 'result' => $result,
            'message' => $total === null ? 'Membaca skala nilai dari PDDIKTI; total belum diketahui.' : "Membaca {$total} baris skala nilai dari PDDIKTI.",
        ]);

        $carry = [];
        $offset = 0;
        for ($page = 0; $page < self::MAX_SOURCE_PAGES; $page++) {
            $batch = $this->fetchPddiktiBatch($offset);
            $count = count($batch);
            $isLast = $count < self::PDDIKTI_BATCH_SIZE;

            $groups = $carry;
            $lastKey = array_key_last($carry);
            foreach ($batch as $index => $row) {
                $prodiUuid = is_array($row) ? trim((string) ($row['id_prodi'] ?? '')) : '';
                if ($prodiUuid === '') {
                    $this->skip($result, $offset + $index, $row, 'Field id_prodi wajib tersedia dari GetListSkalaNilaiProdi.');
                    continue;
                }
                $groups[$prodiUuid][] = ['index' => $offset + $index, 'row' => $row];
                $lastKey = $prodiUuid;
            }

            $carry = [];
            if (!$isLast && $lastKey !== null && isset($groups[$lastKey])) {
                $carry[$lastKey] = $groups[$lastKey];
                unset($groups[$lastKey]);
            }

            DB::transaction(function () use ($groups, &$result): void {
                foreach ($groups as $prodiUuid => $entries) {
                    $this->importProgramStudiScale((string) $prodiUuid, $entries, $result);
                }
            });

            $result['processed'] += $count;
            $result['source_total'] = $result['processed'];
            $result['synced'] = $result['created'] + $result['updated'];
            if ($count > 0) {
                $result['pages']++;
            }
            $this->updatePddiktiProgress($progressId, [
                'status' => 'running', 'phase' => 'saving',
                'progress' => $this->pddiktiProgressPercent($result['processed'], $total),
                'processed' => $result['processed'], 'total' => $total, 'result' => $result,
                'message' => "Sudah memproses {$result['processed']} baris skala nilai dari PDDIKTI.",
            ]);
            unset($batch, $groups);
            if ($isLast) {
                return $result;
            }
            $offset += self::PDDIKTI_BATCH_SIZE;
        }

        throw new ApiIntegrationSyncException('Data skala nilai PDDIKTI mencapai batas ' . self::MAX_SOURCE_PAGES . ' batch tanpa penanda akhir; sinkronisasi tidak dinyatakan selesai.', 422);
    }

    /**
     * @param list<array{index: int, row: array}> $entries seluruh baris skala satu prodi
     */
    private function importProgramStudiScale(string $prodiUuid, array $entries, array &$result): void
    {
        $program = ProgramStudi::query()->where('id_feeder', $prodiUuid)->first();
        if (!$program) {
            foreach ($entries as $entry) {
                $this->skip($result, $entry['index'], $entry['row'], 'Program studi PDDIKTI belum dipetakan ke ID Feeder di SIAKAD.');
            }

            return;
        }

        $valid = [];
        foreach ($entries as $entry) {
            $normalized = $this->normalizeRow($entry['row']);
            if (is_string($normalized)) {
                $this->skip($result, $entry['index'], $entry['row'], $normalized);
                continue;
            }
            $normalized['index'] = $entry['index'];
            $valid[] = $normalized;
        }
        if ($valid === []) {
            return;
        }

        // Beberapa versi huruf yang sama: hanya versi berlaku dengan tanggal mulai terbaru yang aktif.
        $today = now()->startOfDay();
        foreach ($valid as $i => $row) {
            $valid[$i]['active'] = $this->isEffective($row, $today);
        }
        $latestByLetter = [];
        foreach ($valid as $i => $row) {
            if (!$row['active']) {
                continue;
            }
            $key = $row['nilai_huruf'];
            if (!isset($latestByLetter[$key]) || $this->startOf($row) > $this->startOf($valid[$latestByLetter[$key]])) {
                $latestByLetter[$key] = $i;
            }
        }
        foreach ($valid as $i => $row) {
            if ($row['active'] && $latestByLetter[$row['nilai_huruf']] !== $i) {
                $valid[$i]['active'] = false;
            }
        }

        // Nilai huruf non-numerik (rentang 0-0 yang bertabrakan dengan huruf lain berindeks sama) tidak boleh dipakai konversi angka.
        foreach ($valid as $i => $row) {
            if (!$row['active'] || $row['min'] !== $row['max']) {
                continue;
            }
            foreach ($valid as $j => $other) {
                if ($j !== $i && $other['active'] && $other['nilai_indeks'] === $row['nilai_indeks'] && $other['min'] < $other['max']) {
                    $valid[$i]['active'] = false;
                    break;
                }
            }
        }

        $this->normalizeUpperBounds($valid, $program, $result);

        foreach ($valid as $row) {
            $this->persistRow($program, $row, $result);
        }
    }

    /**
     * Rentang Feeder berupa bilangan bulat (mis. 75-84 lalu 85-100), sedangkan nilai akhir berupa desimal.
     * Bila batas atas tepat 1 di bawah batas bawah huruf berikutnya, batas atas dibuat kontinu (84.99).
     * Celah atau tumpang tindih sungguhan tidak ditebak: dibiarkan dan dilaporkan sebagai peringatan.
     */
    private function normalizeUpperBounds(array &$rows, ProgramStudi $program, array &$result): void
    {
        $activeIndexes = array_keys(array_filter($rows, fn (array $row): bool => $row['active']));
        usort($activeIndexes, fn (int $a, int $b): int => [$rows[$a]['min'], $rows[$a]['nilai_indeks']] <=> [$rows[$b]['min'], $rows[$b]['nilai_indeks']]);

        foreach ($rows as $i => $row) {
            $rows[$i]['batas_atas'] = $row['max'];
        }
        for ($k = 0; $k < count($activeIndexes) - 1; $k++) {
            $current = $activeIndexes[$k];
            $next = $activeIndexes[$k + 1];
            $gap = round($rows[$next]['min'] - $rows[$current]['max'], 2);
            $label = "{$rows[$current]['nilai_huruf']} ({$rows[$current]['min']}-{$rows[$current]['max']}) ke {$rows[$next]['nilai_huruf']} ({$rows[$next]['min']}-{$rows[$next]['max']})";
            if ($gap === 1.0) {
                $rows[$current]['batas_atas'] = round($rows[$next]['min'] - 0.01, 2);
            } elseif ($gap > 1.0) {
                $this->warn($result, $program, "Celah rentang nilai {$label}; nilai di antaranya tidak memiliki huruf pada skala prodi ini.");
            } elseif ($gap <= 0.0) {
                $this->warn($result, $program, "Rentang nilai tumpang tindih {$label}; huruf berbobot tertinggi dipakai pada nilai yang beririsan.");
            }
        }
    }

    private function persistRow(ProgramStudi $program, array $row, array &$result): void
    {
        $existing = SkalaNilai::withTrashed()->where('id_feeder', $row['id_feeder'])->first()
            ?? SkalaNilai::withTrashed()
                ->where('program_studi_id', $program->id)
                ->where('nilai_huruf', $row['nilai_huruf'])
                ->whereNull('id_feeder')
                ->first();
        if ($existing?->trashed()) {
            $this->skip($result, $row['index'], ['id_bobot_nilai' => $row['id_feeder'], 'nilai_huruf' => $row['nilai_huruf']], 'Skala nilai tujuan sudah dihapus di SIAKAD.');

            return;
        }

        $attributes = [
            'program_studi_id' => $program->id,
            'id_feeder' => $row['id_feeder'],
            'nilai_huruf' => $row['nilai_huruf'],
            'bobot_indeks' => $row['nilai_indeks'],
            'batas_bawah' => $row['min'],
            'batas_atas' => $row['batas_atas'],
            'tanggal_mulai_efektif' => $row['mulai']?->toDateString(),
            'tanggal_akhir_efektif' => $row['akhir']?->toDateString(),
            'is_active' => $row['active'],
        ];
        if (!$row['active']) {
            $result['inactive']++;
        }

        if ($existing) {
            $existing->update($attributes);
            $result['updated']++;

            return;
        }

        // Feeder tidak memuat status kelulusan: ikuti skala standar lokal untuk huruf yang sama (huruf lain: tidak lulus).
        $attributes['is_lulus'] = (bool) SkalaNilai::query()->whereNull('program_studi_id')
            ->where('nilai_huruf', $row['nilai_huruf'])->value('is_lulus');
        SkalaNilai::create($attributes);
        $result['created']++;
    }

    /** @return array<string, mixed>|string baris ternormalisasi atau alasan skip */
    private function normalizeRow(mixed $row): array|string
    {
        if (!is_array($row)) {
            return 'Item skala nilai Neo Feeder bukan object data.';
        }
        foreach (['id_bobot_nilai', 'nilai_huruf'] as $field) {
            if (!isset($row[$field]) || !is_string($row[$field]) || trim($row[$field]) === '') {
                return "Field {$field} wajib tersedia dari GetListSkalaNilaiProdi.";
            }
        }
        $id = trim($row['id_bobot_nilai']);
        $huruf = trim($row['nilai_huruf']);
        if (mb_strlen($id) > 100 || mb_strlen($huruf) > 5) {
            return 'ID atau nilai huruf melebihi panjang kolom tujuan.';
        }
        foreach (['nilai_indeks', 'bobot_nilai_min', 'bobot_nilai_maks'] as $field) {
            if (!isset($row[$field]) || !is_numeric($row[$field])) {
                return "Field {$field} wajib berupa angka dari GetListSkalaNilaiProdi.";
            }
        }
        $indeks = round((float) $row['nilai_indeks'], 2);
        $min = round((float) $row['bobot_nilai_min'], 2);
        $max = round((float) $row['bobot_nilai_maks'], 2);
        if ($indeks < 0 || $indeks > 99.99 || $min < 0 || $max > 100 || $min > $max) {
            return 'Rentang nilai atau bobot indeks di luar batas yang valid (0-100, minimum tidak boleh melebihi maksimum).';
        }
        $mulai = $this->parseDate($row['tanggal_mulai_efektif'] ?? null);
        $akhir = $this->parseDate($row['tanggal_akhir_efektif'] ?? null);
        if ($mulai === false || $akhir === false) {
            return 'Format tanggal efektif tidak valid.';
        }

        return [
            'id_feeder' => $id, 'nilai_huruf' => $huruf, 'nilai_indeks' => $indeks,
            'min' => $min, 'max' => $max, 'mulai' => $mulai, 'akhir' => $akhir,
        ];
    }

    private function parseDate(mixed $value): Carbon|false|null
    {
        if ($value === null || (is_string($value) && trim($value) === '')) {
            return null;
        }
        foreach (['d-m-Y', 'Y-m-d'] as $format) {
            try {
                $date = Carbon::createFromFormat('!' . $format, trim((string) $value));
                if ($date !== false && $date->format($format) === trim((string) $value)) {
                    return $date;
                }
            } catch (\Throwable) {
                // coba format berikutnya
            }
        }

        return false;
    }

    private function isEffective(array $row, Carbon $today): bool
    {
        return ($row['mulai'] === null || $row['mulai']->lessThanOrEqualTo($today))
            && ($row['akhir'] === null || $row['akhir']->greaterThanOrEqualTo($today));
    }

    private function startOf(array $row): int
    {
        return $row['mulai']?->getTimestamp() ?? PHP_INT_MIN;
    }

    private function fetchPddiktiBatch(int $offset): array
    {
        try {
            $response = $this->neoFeeder->request('GetListSkalaNilaiProdi', [
                'filter' => '',
                'order' => 'id_prodi',
                'limit' => self::PDDIKTI_BATCH_SIZE,
                'offset' => $offset,
            ]);
        } catch (\Throwable) {
            throw new ApiIntegrationSyncException('Gagal mengambil skala nilai dari Neo Feeder PDDIKTI.', 502);
        }
        if ((int) ($response['error_code'] ?? -1) !== 0) {
            throw new ApiIntegrationSyncException('Neo Feeder PDDIKTI menolak permintaan GetListSkalaNilaiProdi.', 502);
        }
        $batch = $response['data'] ?? [];
        if (!is_array($batch)) {
            throw new ApiIntegrationSyncException('Respons GetListSkalaNilaiProdi dari Neo Feeder tidak valid.', 502);
        }
        if ($batch !== [] && !array_is_list($batch)) {
            $batch = [$batch];
        }
        if (count($batch) > self::PDDIKTI_BATCH_SIZE) {
            throw new ApiIntegrationSyncException('GetListSkalaNilaiProdi mengembalikan lebih dari ' . self::PDDIKTI_BATCH_SIZE . ' record dalam satu batch.', 502);
        }

        return $batch;
    }

    private function skip(array &$result, int $index, mixed $row, string $reason): void
    {
        $result['skipped']++;
        if (count($result['skipped_items']) < self::MAX_DETAIL_ITEMS) {
            $result['skipped_items'][] = [
                'source_id' => is_array($row) ? ($row['id_bobot_nilai'] ?? null) : null,
                'item_ref' => is_array($row) ? ($row['nilai_huruf'] ?? null) : null,
                'row' => $index + 1,
                'reason' => $reason,
            ];
        }
    }

    private function warn(array &$result, ProgramStudi $program, string $message): void
    {
        $result['warnings']++;
        if (count($result['warning_items']) < self::MAX_DETAIL_ITEMS) {
            $result['warning_items'][] = ['program_studi' => $program->nama, 'message' => $message];
        }
    }
}
