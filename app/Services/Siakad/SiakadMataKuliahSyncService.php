<?php

namespace App\Services\Siakad;

use App\Models\Siakad\Kurikulum;
use App\Models\Siakad\MataKuliah;
use App\Models\Siakad\ProgramStudi;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SiakadMataKuliahSyncException extends \RuntimeException {}

class SiakadMataKuliahSyncService
{
    /**
     * Sinkronisasi master mata kuliah dari API SIAKAD legacy (/api/mata-kuliah-sync).
     *
     * Sumber tidak mengirim identitas kurikulum, sehingga mata kuliah ditempatkan pada
     * kurikulum prodi yang sudah ada (yang aktif lebih diutamakan) atau kurikulum default
     * prodi yang dibuat otomatis dengan kode KUR-<kode_prodi>.
     *
     * Catatan: kolom siakad_mata_kuliah.kode_mk bersifat unik global di sistem tujuan,
     * sedangkan sumber memakai kode yang sama untuk beberapa mata kuliah/prodi. Baris yang
     * bentrok dilewati dan dilaporkan pada skip_reasons agar tidak menimpa data lain.
     */
    public function sync(array $options = []): array
    {
        $baseUrl = rtrim((string) ($options['url'] ?? ''), '/');
        $token = (string) ($options['token'] ?? '');
        $authScheme = strtolower((string) ($options['auth_scheme'] ?? 'bearer'));

        if ($baseUrl === '') {
            throw new SiakadMataKuliahSyncException('URL API mata kuliah siakad belum dikonfigurasi pada pengaturan integrasi.');
        }
        if ($token === '') {
            throw new SiakadMataKuliahSyncException('Token API mata kuliah siakad belum dikonfigurasi pada pengaturan integrasi.');
        }

        $perPage = max(1, min(500, (int) ($options['per_page'] ?? $options['limit'] ?? 100)));
        $maxPages = (int) ($options['max_pages'] ?? 0);
        $startPage = max(1, (int) ($options['page'] ?? 1));

        $summary = [
            'fetched' => 0,
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'failed' => 0,
            'pages' => 0,
            'kurikulum_created' => 0,
            'skip_reasons' => [],
            'errors' => [],
            'source_url' => $baseUrl,
        ];

        // Cache kurikulum per program studi selama satu proses sync.
        $kurikulumCache = [];
        // Kode mata kuliah yang sudah dipakai pada proses sync ini (kode => nama ternormalisasi).
        $claimedKode = [];

        $page = $startPage;
        $lastPage = $startPage;

        do {
            $query = array_filter([
                'page' => $page,
                'per_page' => $perPage,
            ], fn ($v) => $v !== null && $v !== '');

            try {
                $request = Http::timeout(30)->acceptJson();
                if (in_array($authScheme, ['header_key', 'x-api-key'], true)) {
                    $request = $request->withHeaders(['X-API-KEY' => $token]);
                } else {
                    $request = $request->withToken($token);
                }

                $response = $request->get($baseUrl, $query);
            } catch (\Throwable $e) {
                throw new SiakadMataKuliahSyncException('Gagal menghubungi API mata kuliah siakad: ' . $e->getMessage(), 502, $e);
            }

            if (!$response->successful()) {
                $body = $response->json();
                $msg = is_array($body) ? ($body['message'] ?? $body['error']['message'] ?? null) : null;
                throw new SiakadMataKuliahSyncException(
                    'API mata kuliah siakad mengembalikan HTTP ' . $response->status() . ($msg ? (': ' . $msg) : ''),
                    502
                );
            }

            $payload = $response->json();
            if (!is_array($payload)) {
                throw new SiakadMataKuliahSyncException('Respons API mata kuliah siakad bukan JSON yang valid.');
            }

            $items = $payload['data'] ?? null;
            if ($items !== null && !is_array($items)) {
                $items = [$items];
            }
            if ($items === null) {
                $items = array_is_list($payload) ? $payload : [];
            }
            if (!is_array($items)) {
                $items = [];
            }
            if ($items !== [] && !array_is_list($items) && isset($items['identitas'])) {
                $items = [$items];
            }

            $summary['pages']++;
            $summary['fetched'] += count($items);

            foreach ($items as $item) {
                DB::transaction(function () use ($item, &$summary, &$kurikulumCache, &$claimedKode) {
                    $this->processItem($item, $summary, $kurikulumCache, $claimedKode);
                });
            }

            $pagination = $payload['pagination'] ?? $payload['meta'] ?? [];
            if (is_array($pagination) && $pagination !== []) {
                $totalPages = $pagination['total_pages'] ?? $pagination['last_page'] ?? null;
                $currentPage = $pagination['current_page'] ?? $pagination['page'] ?? $page;
                $hasNext = $pagination['has_next'] ?? null;
                if ($totalPages !== null) {
                    $lastPage = (int) $totalPages;
                } elseif ($hasNext === true) {
                    $lastPage = $page + 1;
                } elseif ($hasNext === false) {
                    $lastPage = $page;
                } else {
                    $lastPage = count($items) < $perPage ? $page : $page + 1;
                }
                $page = ((int) $currentPage) + 1;
            } else {
                $lastPage = count($items) < $perPage ? $page : $page + 1;
                $page++;
            }

            if ($maxPages > 0 && $summary['pages'] >= $maxPages) {
                break;
            }

            // Halaman kosong menandakan data sumber sudah habis walau total_pages besar.
            if ($items === []) {
                break;
            }
        } while ($page <= $lastPage);

        return $summary;
    }

    private function processItem(
        $item,
        array &$summary,
        array &$kurikulumCache,
        array &$claimedKode
    ): void {
        if (!is_array($item)) {
            $this->skip($summary, 'invalid_item', 'Item bukan object data.');
            return;
        }

        $identitas = is_array($item['identitas'] ?? null) ? $item['identitas'] : [];
        $bobot = is_array($item['bobot_sks'] ?? null) ? $item['bobot_sks'] : [];
        $kurikulum = is_array($item['kurikulum'] ?? null) ? $item['kurikulum'] : [];
        $prodiData = is_array($item['program_studi'] ?? null) ? $item['program_studi'] : [];
        $raw = is_array($item['raw_data'] ?? null) ? $item['raw_data'] : [];

        $sourceId = $this->nullableTrim(
            $identitas['source_id'] ?? $identitas['id_mata_kuliah'] ?? $raw['xid_mk'] ?? null
        );
        if ($sourceId === null) {
            $this->skip($summary, 'missing_source_id', 'ID mata kuliah sumber (xid_mk) kosong.');
            return;
        }

        $kodeMk = $this->normalizeKode(
            $identitas['kode_mk'] ?? $raw['kode_mk'] ?? null
        );
        if ($kodeMk === null) {
            $this->skip($summary, 'missing_kode_mk', 'Kode mata kuliah kosong.', $sourceId);
            return;
        }

        $nama = $this->nullableTrim($identitas['nama_mk'] ?? null);
        if ($nama === null) {
            $this->skip($summary, 'missing_nama_mk', 'Nama mata kuliah kosong.', $kodeMk);
            return;
        }

        $namaKey = $this->normalizeNama($nama);

        // Kode mata kuliah unik global di sistem tujuan: hanya satu baris sumber boleh memakainya.
        if (isset($claimedKode[$kodeMk])) {
            $reason = $claimedKode[$kodeMk] === $namaKey ? 'duplicate_kode_mk' : 'kode_mk_conflict';
            $this->skip(
                $summary,
                $reason,
                "Kode mata kuliah {$kodeMk} sudah dipakai baris sumber lain ({$claimedKode[$kodeMk]}).",
                $kodeMk
            );
            return;
        }

        $prodi = $this->resolveProgramStudi($prodiData, $raw);
        if (!$prodi) {
            $this->skip(
                $summary,
                'program_studi_not_found',
                'Program studi mata kuliah tidak ditemukan pada master program studi lokal.',
                $kodeMk
            );
            return;
        }

        $kurikulumModel = $this->resolveKurikulum($prodi, $summary, $kurikulumCache);
        if (!$kurikulumModel) {
            $this->skip(
                $summary,
                'kurikulum_not_found',
                'Kurikulum prodi tidak dapat disiapkan untuk mata kuliah ini.',
                $kodeMk
            );
            return;
        }

        try {
            $mataKuliah = MataKuliah::withTrashed()->where('source_id', $sourceId)->first();

            if (!$mataKuliah) {
                $mataKuliah = MataKuliah::withTrashed()->where('kode_mk', $kodeMk)->first();

                if ($mataKuliah) {
                    // Baris lokal dengan kode sama namun milik sumber lain tidak boleh ditimpa.
                    if (filled($mataKuliah->source_id) && $mataKuliah->source_id !== $sourceId) {
                        $this->skip(
                            $summary,
                            'kode_mk_conflict',
                            "Kode mata kuliah {$kodeMk} sudah terhubung ke ID sumber lain ({$mataKuliah->source_id}).",
                            $kodeMk
                        );
                        return;
                    }

                    if ($this->normalizeNama($mataKuliah->nama) !== $namaKey) {
                        $this->skip(
                            $summary,
                            'kode_mk_conflict',
                            "Kode mata kuliah {$kodeMk} sudah dipakai mata kuliah lokal '{$mataKuliah->nama}'.",
                            $kodeMk
                        );
                        return;
                    }
                }
            }

            // Baris yang cocok lewat source_id bisa saja berubah kode; pastikan kode baru belum dipakai lokal.
            if ($mataKuliah && $mataKuliah->kode_mk !== $kodeMk) {
                $kodeTerpakai = MataKuliah::withTrashed()
                    ->where('kode_mk', $kodeMk)
                    ->whereKeyNot($mataKuliah->getKey())
                    ->exists();

                if ($kodeTerpakai) {
                    $this->skip(
                        $summary,
                        'kode_mk_conflict',
                        "Kode mata kuliah {$kodeMk} sudah dipakai mata kuliah lokal lain.",
                        $kodeMk
                    );
                    return;
                }
            }

            if ($mataKuliah && $mataKuliah->trashed()) {
                $mataKuliah->restore();
            }

            $sksTeori = max(0, (int) ($bobot['sks_tatap_muka'] ?? 0));
            $sksPraktik = max(0, (int) ($bobot['sks_praktikum'] ?? 0))
                + max(0, (int) ($bobot['sks_praktik_lapangan'] ?? 0))
                + max(0, (int) ($bobot['sks_simulasi'] ?? 0));
            $totalSks = max(0, (int) ($bobot['sks_total'] ?? 0));
            if ($totalSks === 0 && ($sksTeori + $sksPraktik) > 0) {
                $totalSks = $sksTeori + $sksPraktik;
            }

            $semester = $kurikulum['semester'] ?? null;
            if ($semester === null || $semester === '') {
                $semester = $raw['semester_anjuran'] ?? null;
            }
            $semesterAnjuran = max(1, (int) ($semester ?? 1));

            $tipe = ($kurikulum['is_wajib'] ?? null) === false ? 'pilihan' : 'wajib';

            $attributes = [
                'kode_mk' => $kodeMk,
                'nama' => $nama,
                'sks_teori' => $sksTeori,
                'sks_praktik' => $sksPraktik,
                'total_sks' => $totalSks,
                'semester_anjuran' => $semesterAnjuran,
                'tipe' => $tipe,
                'source_id' => $sourceId,
            ];

            if ($mataKuliah) {
                // Kurikulum dan status aktif lokal tidak ditimpa agar penempatan/koreksi admin tetap.
                $mataKuliah->fill($attributes);
                $mataKuliah->save();
                $summary['updated']++;
            } else {
                $attributes['kurikulum_id'] = $kurikulumModel->id;
                $attributes['is_active'] = true;
                MataKuliah::create($attributes);
                $summary['created']++;
            }

            $claimedKode[$kodeMk] = $namaKey;
        } catch (\Throwable $e) {
            $summary['failed']++;
            if (count($summary['errors']) < 50) {
                $summary['errors'][] = [
                    'kode_mk' => $kodeMk,
                    'source_id' => $sourceId,
                    'message' => $e->getMessage(),
                ];
            }
            Log::warning('SiakadMataKuliahSync gagal memproses mata kuliah ' . $sourceId . ': ' . $e->getMessage());
        }
    }

    /**
     * Menyiapkan kurikulum tujuan. Sumber tidak mengirim identitas kurikulum, sehingga
     * dipakai kurikulum prodi yang sudah ada (aktif diutamakan), atau dibuat kurikulum
     * default prodi dengan kode KUR-<kode_prodi>.
     */
    private function resolveKurikulum(ProgramStudi $prodi, array &$summary, array &$cache): ?Kurikulum
    {
        if (array_key_exists($prodi->id, $cache)) {
            return $cache[$prodi->id];
        }

        $kurikulum = Kurikulum::where('program_studi_id', $prodi->id)
            ->where('is_active', true)
            ->orderByDesc('tahun_berlaku')
            ->first();

        if (!$kurikulum) {
            $kurikulum = Kurikulum::where('program_studi_id', $prodi->id)
                ->orderByDesc('tahun_berlaku')
                ->first();
        }

        if (!$kurikulum) {
            $kode = 'KUR-' . ($prodi->kode_prodi ?: $prodi->id);
            $existing = Kurikulum::withTrashed()->where('kode', $kode)->first();

            // Kode kurikulum unik global; pakai sufiks prodi bila kode dipakai prodi lain.
            if ($existing && (int) $existing->program_studi_id !== (int) $prodi->id) {
                $kode = $kode . '-' . $prodi->id;
                $existing = Kurikulum::withTrashed()->where('kode', $kode)->first();
            }

            if ($existing) {
                if ($existing->trashed()) {
                    $existing->restore();
                }
                $kurikulum = $existing;
            } else {
                $kurikulum = Kurikulum::create([
                    'program_studi_id' => $prodi->id,
                    'kode' => $kode,
                    'nama' => 'Kurikulum ' . $prodi->nama,
                    'tahun_berlaku' => (int) date('Y'),
                    'is_active' => true,
                ]);
                $summary['kurikulum_created']++;
            }
        }

        $cache[$prodi->id] = $kurikulum;

        return $kurikulum;
    }

    private function resolveProgramStudi(array $prodiData, array $raw): ?ProgramStudi
    {
        $sourceIds = [
            $prodiData['prodi_id'] ?? null,
            $prodiData['prodi_source_id'] ?? null,
            $raw['id_sms'] ?? null,
            $raw['xid_sms'] ?? null,
        ];

        foreach ($sourceIds as $sourceId) {
            $sourceId = $this->nullableTrim($sourceId);
            if ($sourceId === null) {
                continue;
            }

            $prodi = ProgramStudi::where('source_id', $sourceId)->first();
            if ($prodi) {
                return $prodi;
            }
        }

        $kode = $this->nullableTrim($prodiData['kode_prodi'] ?? null);
        if ($kode !== null) {
            $prodi = ProgramStudi::where('kode_prodi', $kode)
                ->orWhere('source_kode_prodi', $kode)
                ->first();
            if ($prodi) {
                return $prodi;
            }
        }

        $nama = $this->nullableTrim($prodiData['nama_prodi'] ?? null);
        if ($nama !== null) {
            $query = ProgramStudi::where('nama', $nama);
            $jenjang = $this->nullableTrim($prodiData['jenjang'] ?? null);
            if ($jenjang !== null) {
                $query->where('jenjang', $jenjang);
            }
            $prodi = $query->first();
            if ($prodi) {
                return $prodi;
            }
        }

        return null;
    }

    private function normalizeKode($value): ?string
    {
        $v = $this->nullableTrim($value);

        return $v === null ? null : strtoupper($v);
    }

    private function normalizeNama(?string $value): string
    {
        $v = strtoupper(trim((string) $value));

        return preg_replace('/\s+/', ' ', $v) ?? $v;
    }

    private function nullableTrim($value): ?string
    {
        if ($value === null) {
            return null;
        }
        $v = trim((string) $value);

        return $v === '' ? null : $v;
    }

    private function skip(array &$summary, string $reason, string $message, ?string $identity = null): void
    {
        $summary['skipped']++;
        $summary['skip_reasons'][$reason] = ($summary['skip_reasons'][$reason] ?? 0) + 1;
        if (count($summary['errors']) < 50) {
            $entry = ['reason' => $reason, 'message' => $message];
            if ($identity !== null) {
                $entry['kode_mk'] = $identity;
            }
            $summary['errors'][] = $entry;
        }
    }
}
