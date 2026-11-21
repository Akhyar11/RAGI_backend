<?php

namespace App\Services\Siakad;

use App\Models\Siakad\Dosen;
use App\Models\Siakad\DosenPenugasan;
use App\Models\Siakad\ProgramStudi;
use App\Models\Siakad\TahunAkademik;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SiakadDosenSyncException extends \RuntimeException {}

class SiakadDosenSyncService
{
    /**
     * Sinkronisasi master dosen + penugasan homebase dari API SIAKAD legacy (/api/dosen-sync).
     *
     * Satu baris sumber mewakili satu penugasan dosen pada satu program studi. Master dosen
     * direkonsiliasi memakai source_id (xid_ptk) yang stabil, dengan fallback NIDN.
     */
    public function sync(array $options = []): array
    {
        $baseUrl = rtrim((string) ($options['url'] ?? ''), '/');
        $token = (string) ($options['token'] ?? '');
        $authScheme = strtolower((string) ($options['auth_scheme'] ?? 'bearer'));

        if ($baseUrl === '') {
            throw new SiakadDosenSyncException('URL API dosen siakad belum dikonfigurasi pada pengaturan integrasi.');
        }
        if ($token === '') {
            throw new SiakadDosenSyncException('Token API dosen siakad belum dikonfigurasi pada pengaturan integrasi.');
        }

        $perPage = max(1, min(500, (int) ($options['per_page'] ?? $options['limit'] ?? 100)));
        $maxPages = (int) ($options['max_pages'] ?? 0);
        $startPage = max(1, (int) ($options['page'] ?? 1));

        $tahunAkademik = $this->resolveTahunAkademik($options['tahun_akademik_id'] ?? null);

        $summary = [
            'fetched' => 0,
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'failed' => 0,
            'pages' => 0,
            'penugasan_created' => 0,
            'penugasan_homebase' => 0,
            'skip_reasons' => [],
            'errors' => [],
            'source_url' => $baseUrl,
            'tahun_akademik_id' => $tahunAkademik?->id,
        ];

        // Penugasan unik per (dosen, prodi, tahun akademik) sudah diproses pada sync ini.
        $seenPenugasan = [];
        // Dosen yang sudah dihitung pada ringkasan created/updated (satu dosen bisa punya banyak baris).
        $processedDosen = [];
        // Kandidat homebase: penugasan dengan id_reg_ptk terbaru per dosen.
        $homebaseCandidates = [];

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
                throw new SiakadDosenSyncException('Gagal menghubungi API dosen siakad: ' . $e->getMessage(), 502, $e);
            }

            if (!$response->successful()) {
                $body = $response->json();
                $msg = is_array($body) ? ($body['message'] ?? $body['error']['message'] ?? null) : null;
                throw new SiakadDosenSyncException(
                    'API dosen siakad mengembalikan HTTP ' . $response->status() . ($msg ? (': ' . $msg) : ''),
                    502
                );
            }

            $payload = $response->json();
            if (!is_array($payload)) {
                throw new SiakadDosenSyncException('Respons API dosen siakad bukan JSON yang valid.');
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
                DB::transaction(function () use (
                    $item,
                    $tahunAkademik,
                    &$summary,
                    &$seenPenugasan,
                    &$processedDosen,
                    &$homebaseCandidates
                ) {
                    $this->processItem(
                        $item,
                        $tahunAkademik,
                        $summary,
                        $seenPenugasan,
                        $processedDosen,
                        $homebaseCandidates
                    );
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

            // Halaman kosong menandakan data sumber sudah habis walau total_pages lebih besar.
            if ($items === []) {
                break;
            }
        } while ($page <= $lastPage);

        $this->finalizeHomebase($homebaseCandidates, $tahunAkademik, $summary);

        return $summary;
    }

    private function processItem(
        $item,
        ?TahunAkademik $tahunAkademik,
        array &$summary,
        array &$seenPenugasan,
        array &$processedDosen,
        array &$homebaseCandidates
    ): void {
        if (!is_array($item)) {
            $this->skip($summary, 'invalid_item', 'Item bukan object data.');
            return;
        }

        $identitas = is_array($item['identitas'] ?? null) ? $item['identitas'] : [];
        $kontak = is_array($item['kontak'] ?? null) ? $item['kontak'] : [];
        $kepegawaian = is_array($item['kepegawaian'] ?? null) ? $item['kepegawaian'] : [];
        $homebase = is_array($item['homebase'] ?? null) ? $item['homebase'] : [];
        $raw = is_array($item['raw_data'] ?? null) ? $item['raw_data'] : [];

        $sourceId = $this->nullableTrim(
            $identitas['source_id'] ?? $identitas['id_ptk'] ?? $raw['xid_ptk'] ?? $raw['id_ptk'] ?? null
        );
        if ($sourceId === null) {
            $this->skip($summary, 'missing_source_id', 'ID dosen sumber (xid_ptk) kosong.');
            return;
        }

        $nidn = $this->nullableTrim($identitas['nidn'] ?? $raw['nidn'] ?? null);
        $nama = $this->nullableTrim($identitas['nama'] ?? $identitas['nama_lengkap'] ?? null) ?? $nidn ?? $sourceId;

        $prodi = $this->resolveProgramStudi($homebase, $raw);
        $isAktif = $this->resolveIsActive($kepegawaian);
        $statusAktif = $this->nullableTrim($kepegawaian['status_aktif'] ?? null)
            ?? ($isAktif ? 'Aktif' : 'Non-Aktif');

        try {
            $dosen = Dosen::withTrashed()->where('source_id', $sourceId)->first();

            if (!$dosen && $nidn !== null) {
                $dosen = Dosen::withTrashed()->where('nidn', $nidn)->first();

                if ($dosen && filled($dosen->source_id) && $dosen->source_id !== $sourceId) {
                    $this->skip(
                        $summary,
                        'nidn_conflict',
                        "NIDN {$nidn} sudah terhubung ke ID dosen sumber lain ({$dosen->source_id}).",
                        $nidn
                    );
                    return;
                }
            }

            if ($dosen && $dosen->trashed()) {
                $dosen->restore();
            }

            $attributes = [
                'source_id' => $sourceId,
                'nidn' => $nidn ?? $dosen?->nidn,
                'nip' => $this->nullableTrim($identitas['nip'] ?? $raw['nip'] ?? null) ?? $dosen?->nip,
                'nik' => $this->nullableTrim($identitas['nik'] ?? $raw['nik'] ?? null) ?? $dosen?->nik,
                'nuptk' => $this->nullableTrim($identitas['nuptk'] ?? $raw['nuptk'] ?? null) ?? $dosen?->nuptk,
                'nama_lengkap' => $nama,
                'gelar_depan' => $this->nullableTrim($identitas['gelar_depan'] ?? null) ?? $dosen?->gelar_depan,
                'gelar_belakang' => $this->nullableTrim($identitas['gelar_belakang'] ?? null) ?? $dosen?->gelar_belakang,
                'jenis_kelamin' => $this->mapJenisKelamin($identitas['jenis_kelamin'] ?? null) ?? $dosen?->jenis_kelamin,
                'tempat_lahir' => $this->nullableTrim($identitas['tempat_lahir'] ?? null) ?? $dosen?->tempat_lahir,
                'tanggal_lahir' => $this->parseDate($identitas['tanggal_lahir'] ?? null) ?? $dosen?->tanggal_lahir,
                'agama' => $this->nullableTrim($identitas['agama'] ?? null) ?? $dosen?->agama,
                // Data kontak lokal tidak ditimpa jika sudah terisi (menghormati koreksi admin).
                'email' => $dosen?->email ?: $this->nullableTrim($kontak['email'] ?? null),
                'handphone' => $dosen?->handphone ?: $this->nullableTrim($kontak['no_hp'] ?? null),
                'telepon' => $dosen?->telepon ?: $this->nullableTrim($kontak['telepon_rumah'] ?? null),
                'status_aktif' => $statusAktif,
                'is_active' => $isAktif,
            ];

            if ($dosen) {
                $dosen->fill($attributes);
                $dosen->save();
                if (!isset($processedDosen[$dosen->id])) {
                    $processedDosen[$dosen->id] = true;
                    $summary['updated']++;
                }
            } else {
                $dosen = Dosen::create($attributes);
                $processedDosen[$dosen->id] = true;
                $summary['created']++;
            }

            if (!$prodi) {
                // Dosen tanpa penugasan pada sumber (mis. data legacy tanpa homebase prodi).
                return;
            }

            if (!$tahunAkademik) {
                $this->skip(
                    $summary,
                    'tahun_akademik_not_found',
                    'Tahun akademik belum tersedia sehingga penugasan dosen tidak dapat dicatat.',
                    $nidn
                );
                return;
            }

            $idRegistrasi = $this->nullableTrim($identitas['id_reg_ptk'] ?? $raw['id_reg_ptk'] ?? null);

            $this->syncPenugasan(
                $dosen,
                $prodi,
                $tahunAkademik,
                $idRegistrasi,
                $summary,
                $seenPenugasan,
                $homebaseCandidates
            );
        } catch (\Throwable $e) {
            $summary['failed']++;
            if (count($summary['errors']) < 50) {
                $summary['errors'][] = [
                    'nidn' => $nidn,
                    'source_id' => $sourceId,
                    'message' => $e->getMessage(),
                ];
            }
            Log::warning('SiakadDosenSync gagal memproses dosen ' . $sourceId . ': ' . $e->getMessage());
        }
    }

    private function syncPenugasan(
        Dosen $dosen,
        ProgramStudi $prodi,
        TahunAkademik $tahunAkademik,
        ?string $idRegistrasi,
        array &$summary,
        array &$seenPenugasan,
        array &$homebaseCandidates
    ): void {
        // Sumber dapat memuat beberapa id_reg_ptk untuk dosen + prodi yang sama.
        // Penugasan lokal unik per (dosen, prodi, tahun akademik), jadi cukup diproses sekali.
        $dedupeKey = $dosen->id . ':' . $prodi->id . ':' . $tahunAkademik->id;

        if (!isset($seenPenugasan[$dedupeKey])) {
            $seenPenugasan[$dedupeKey] = true;

            $penugasan = DosenPenugasan::withTrashed()
                ->where('dosen_id', $dosen->id)
                ->where('program_studi_id', $prodi->id)
                ->where('tahun_akademik_id', $tahunAkademik->id)
                ->first();

            if ($penugasan) {
                if ($penugasan->trashed()) {
                    $penugasan->restore();
                }
            } else {
                DosenPenugasan::create([
                    'dosen_id' => $dosen->id,
                    'program_studi_id' => $prodi->id,
                    'tahun_akademik_id' => $tahunAkademik->id,
                    'is_homebase' => false,
                    'sync_status' => 'pending',
                ]);
                $summary['penugasan_created']++;
            }
        }

        // Homebase ditentukan setelah seluruh halaman diproses: penugasan dengan id_reg_ptk terbaru.
        $idRegistrasiNumber = is_numeric($idRegistrasi) ? (int) $idRegistrasi : 0;
        $current = $homebaseCandidates[$dosen->id] ?? null;
        if ($current === null || $idRegistrasiNumber > $current['id_reg']) {
            $homebaseCandidates[$dosen->id] = [
                'prodi_id' => $prodi->id,
                'id_reg' => $idRegistrasiNumber,
            ];
        }
    }

    /**
     * Menetapkan homebase dosen tanpa menimpa homebase yang sudah ada.
     */
    private function finalizeHomebase(array $homebaseCandidates, ?TahunAkademik $tahunAkademik, array &$summary): void
    {
        if ($homebaseCandidates === []) {
            return;
        }

        foreach ($homebaseCandidates as $dosenId => $candidate) {
            $dosen = Dosen::find($dosenId);
            if (!$dosen) {
                continue;
            }

            if (!$dosen->program_studi_id && $candidate['prodi_id']) {
                $dosen->program_studi_id = $candidate['prodi_id'];
                $dosen->save();
            }

            if (!$tahunAkademik) {
                continue;
            }

            $penugasan = DosenPenugasan::where('dosen_id', $dosenId)
                ->where('tahun_akademik_id', $tahunAkademik->id)
                ->where('program_studi_id', $candidate['prodi_id'])
                ->first();

            if (!$penugasan || $penugasan->is_homebase) {
                continue;
            }

            $hasHomebase = DosenPenugasan::where('dosen_id', $dosenId)
                ->where('tahun_akademik_id', $tahunAkademik->id)
                ->where('is_homebase', true)
                ->exists();

            if ($hasHomebase) {
                continue;
            }

            $penugasan->update(['is_homebase' => true]);
            $summary['penugasan_homebase']++;
        }
    }

    private function resolveProgramStudi(array $homebase, array $raw): ?ProgramStudi
    {
        $prodiIdSource = $homebase['prodi_id'] ?? $homebase['prodi_source_id'] ?? $raw['id_sms'] ?? $raw['xid_sms'] ?? null;
        if ($prodiIdSource !== null && $prodiIdSource !== '') {
            $prodi = ProgramStudi::where('source_id', (string) $prodiIdSource)->first();
            if ($prodi) {
                return $prodi;
            }
        }

        $kode = $this->nullableTrim($homebase['kode_prodi'] ?? null);
        if ($kode !== null) {
            $prodi = ProgramStudi::where('kode_prodi', $kode)
                ->orWhere('source_kode_prodi', $kode)
                ->first();
            if ($prodi) {
                return $prodi;
            }
        }

        $nama = $this->nullableTrim($homebase['nama_prodi'] ?? null);
        if ($nama !== null) {
            $query = ProgramStudi::where('nama', $nama);
            $jenjang = $this->nullableTrim($homebase['jenjang'] ?? null);
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

    private function resolveTahunAkademik($tahunAkademikId): ?TahunAkademik
    {
        if ($tahunAkademikId !== null && $tahunAkademikId !== '') {
            return TahunAkademik::find((int) $tahunAkademikId);
        }

        return TahunAkademik::where('is_active', true)->orderByDesc('kode')->first()
            ?? TahunAkademik::orderByDesc('tahun_mulai')->orderByDesc('kode')->first();
    }

    private function resolveIsActive(array $kepegawaian): bool
    {
        $isAktif = $kepegawaian['is_aktif'] ?? null;
        if (is_bool($isAktif)) {
            return $isAktif;
        }
        if ($isAktif !== null && $isAktif !== '') {
            return filter_var($isAktif, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true;
        }

        $status = strtolower(trim((string) ($kepegawaian['status_aktif'] ?? '')));

        return $status === '' || $status === 'aktif';
    }

    private function mapJenisKelamin($value): ?string
    {
        $v = strtoupper(trim((string) $value));
        if ($v === 'L' || $v === 'LAKI-LAKI' || $v === 'LAKI LAKI' || $v === 'M') return 'L';
        if ($v === 'P' || $v === 'PEREMPUAN' || $v === 'F') return 'P';

        return null;
    }

    private function parseDate($value): ?string
    {
        if ($value === null) return null;
        $v = trim((string) $value);
        if ($v === '' || $v === '0000-00-00' || $v === '0000-00-00 00:00:00') return null;
        try {
            return Carbon::parse($v)->format('Y-m-d');
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function nullableTrim($value): ?string
    {
        if ($value === null) return null;
        $v = trim((string) $value);
        return $v === '' ? null : $v;
    }

    private function skip(array &$summary, string $reason, string $message, ?string $identity = null): void
    {
        $summary['skipped']++;
        $summary['skip_reasons'][$reason] = ($summary['skip_reasons'][$reason] ?? 0) + 1;
        if (count($summary['errors']) < 50) {
            $entry = ['reason' => $reason, 'message' => $message];
            if ($identity !== null) $entry['nidn'] = $identity;
            $summary['errors'][] = $entry;
        }
    }
}
