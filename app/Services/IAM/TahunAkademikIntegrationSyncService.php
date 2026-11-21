<?php

namespace App\Services\IAM;

use App\Models\IamApiIntegration;
use App\Models\Siakad\TahunAkademik;
use App\Services\Siakad\NeoFeederService;
use Illuminate\Support\Facades\DB;

class TahunAkademikIntegrationSyncService
{
    private const PAGE_SIZE = 500;
    private const MAX_PAGES = 100;

    public function __construct(private NeoFeederService $neoFeeder) {}

    public function testPddiktiConnection(string $action): array
    {
        if ($action !== 'GetSemester') {
            throw new ApiIntegrationSyncException('Action tes koneksi Tahun Akademik PDDIKTI tidak valid.');
        }

        try {
            $response = $this->neoFeeder->request($action, [
                'filter' => '',
                'order' => 'id_semester',
                'limit' => 1,
                'offset' => 0,
            ]);
        } catch (\Throwable) {
            throw new ApiIntegrationSyncException('Koneksi Neo Feeder gagal. Periksa konfigurasi dan hak akses GetSemester.', 502);
        }

        if ((int) ($response['error_code'] ?? -1) !== 0) {
            throw new ApiIntegrationSyncException('Neo Feeder menolak permintaan GetSemester. Periksa hak akses akun Feeder.', 502);
        }

        $data = $response['data'] ?? [];
        if (!is_array($data)) {
            throw new ApiIntegrationSyncException('Respons GetSemester dari Neo Feeder tidak valid.', 502);
        }

        return [
            'action' => $action,
            'data_count' => count($data),
            'sample_fields' => isset($data[0]) && is_array($data[0]) ? array_keys($data[0]) : array_keys($data),
        ];
    }

    public function sync(IamApiIntegration $integration): array
    {
        if (!$integration->is_active) {
            throw new ApiIntegrationSyncException('Integrasi sedang dinonaktifkan.');
        }
        if ($integration->key !== 'siakad_tahun_akademik'
            || ($integration->source_type ?? 'custom_api') !== 'pddikti'
            || !in_array('pddikti', $integration->supported_sources ?? [], true)) {
            throw new ApiIntegrationSyncException('Pilih sumber PDDIKTI untuk sinkronisasi Tahun Akademik.');
        }

        $rows = $this->fetchRows();
        $result = [
            'source_total' => count($rows),
            'created' => 0,
            'updated' => 0,
            'synced' => 0,
            'skipped' => 0,
            'failed' => 0,
            'pages' => (int) ceil(count($rows) / self::PAGE_SIZE),
            'skipped_items' => [],
        ];
        $seenIds = [];

        foreach ($rows as $index => $row) {
            $sourceId = is_array($row) ? trim((string) ($row['id_semester'] ?? '')) : '';
            $sourceYear = is_array($row) ? trim((string) ($row['id_tahun_ajaran'] ?? '')) : '';
            $sourceName = is_array($row) ? trim((string) ($row['nama_semester'] ?? '')) : '';
            $reason = null;

            if ($sourceId === '' || mb_strlen($sourceId) !== 5) {
                $reason = 'Field id_semester wajib berisi kode 5 karakter dari GetSemester.';
            } elseif (isset($seenIds[$sourceId])) {
                $reason = 'id_semester duplikat dalam respons Neo Feeder.';
            } elseif (preg_match('/^\d{4}$/D', $sourceYear) !== 1 || (int) $sourceYear < 1900 || (int) $sourceYear > 2200) {
                $reason = 'Field id_tahun_ajaran dari GetSemester tidak valid.';
            } elseif ($sourceName === '' || mb_strlen($sourceName) > 255) {
                $reason = 'Field nama_semester wajib tersedia dan maksimal 255 karakter.';
            } else {
                $seenIds[$sourceId] = true;
            }

            $localMatches = $reason === null
                ? TahunAkademik::withTrashed()->where('kode', $sourceId)->limit(2)->get()
                : collect();
            $feederMatches = $reason === null
                ? TahunAkademik::withTrashed()->where('id_feeder', $sourceId)->limit(2)->get()
                : collect();

            if ($reason === null && $localMatches->count() > 1) {
                $reason = 'Kode semester cocok dengan lebih dari satu Tahun Akademik lokal.';
            } elseif ($reason === null && $localMatches->isEmpty()) {
                if ($feederMatches->isNotEmpty()) {
                    $reason = 'ID semester Feeder sudah terhubung ke kode Tahun Akademik lokal yang berbeda.';
                }
            } elseif ($reason === null && $localMatches->first()->trashed()) {
                $reason = 'Tahun Akademik lokal dengan kode ini sudah dihapus.';
            } elseif ($reason === null && $feederMatches->count() > 1) {
                $reason = 'ID semester Feeder sudah terhubung ke beberapa Tahun Akademik lokal.';
            } elseif ($reason === null && $feederMatches->isNotEmpty()
                && (int) $feederMatches->first()->id !== (int) $localMatches->first()->id) {
                $reason = 'ID semester Feeder sudah terhubung ke kode Tahun Akademik lokal yang berbeda.';
            } elseif ($reason === null && $localMatches->first()->id_feeder !== null
                && $localMatches->first()->id_feeder !== $sourceId) {
                $reason = 'Tahun Akademik lokal sudah memiliki ID Feeder yang berbeda.';
            }

            if ($reason !== null) {
                $result['skipped']++;
                if (count($result['skipped_items']) < 100) {
                    $result['skipped_items'][] = [
                        'source_id' => $sourceId ?: null,
                        'item_ref' => is_array($row) ? ($row['nama_semester'] ?? null) : null,
                        'row' => $index + 1,
                        'reason' => $reason,
                    ];
                }
                continue;
            }

            $tahunAkademik = $localMatches->first();
            if (!$tahunAkademik) {
                try {
                    $tahunAkademik = DB::transaction(fn () => TahunAkademik::create([
                        'kode' => $sourceId,
                        'id_feeder' => $sourceId,
                        'nama' => $sourceName,
                        'tahun_mulai' => (int) $sourceYear,
                        'tahun_selesai' => (int) $sourceYear + 1,
                        'is_active' => false,
                    ]));
                    $result['created']++;
                } catch (\Throwable) {
                    $result['skipped']++;
                    if (count($result['skipped_items']) < 100) {
                        $result['skipped_items'][] = [
                            'source_id' => $sourceId,
                            'item_ref' => $sourceName,
                            'row' => $index + 1,
                            'reason' => 'Tahun Akademik baru gagal disimpan karena konflik kode atau ID Feeder.',
                        ];
                    }
                    continue;
                }
            }
            if ($tahunAkademik->id_feeder === null) {
                try {
                    DB::transaction(fn () => $tahunAkademik->update(['id_feeder' => $sourceId]));
                    $result['updated']++;
                } catch (\Throwable) {
                    $result['skipped']++;
                    if (count($result['skipped_items']) < 100) {
                        $result['skipped_items'][] = [
                            'source_id' => $sourceId,
                            'item_ref' => is_array($row) ? ($row['nama_semester'] ?? null) : null,
                            'row' => $index + 1,
                            'reason' => 'ID semester Feeder bertabrakan dengan mapping lain dan tidak dapat disimpan.',
                        ];
                    }
                    continue;
                }
            }

            $result['synced']++;
        }

        return $result;
    }

    private function fetchRows(): array
    {
        $rows = [];
        $offset = 0;

        for ($page = 0; $page < self::MAX_PAGES; $page++) {
            try {
                $response = $this->neoFeeder->request('GetSemester', [
                    'filter' => '',
                    'order' => 'id_semester',
                    'limit' => self::PAGE_SIZE,
                    'offset' => $offset,
                ]);
            } catch (\Throwable) {
                throw new ApiIntegrationSyncException('Gagal mengambil daftar semester dari Neo Feeder PDDIKTI.', 502);
            }

            if ((int) ($response['error_code'] ?? -1) !== 0) {
                throw new ApiIntegrationSyncException('Neo Feeder PDDIKTI menolak permintaan GetSemester.', 502);
            }

            $batch = $response['data'] ?? [];
            if (!is_array($batch)) {
                throw new ApiIntegrationSyncException('Respons GetSemester dari Neo Feeder tidak valid.', 502);
            }
            if ($batch !== [] && !array_is_list($batch)) {
                $batch = [$batch];
            }

            array_push($rows, ...$batch);
            if (count($batch) < self::PAGE_SIZE) {
                return $rows;
            }
            $offset += self::PAGE_SIZE;
        }

        throw new ApiIntegrationSyncException('Data semester PDDIKTI melebihi batas 100 halaman per proses sync.');
    }
}