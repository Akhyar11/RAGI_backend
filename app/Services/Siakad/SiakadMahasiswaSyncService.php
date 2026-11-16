<?php

namespace App\Services\Siakad;

use App\Models\Siakad\Mahasiswa;
use App\Models\Siakad\ProgramStudi;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SiakadMahasiswaSyncException extends \RuntimeException {}

class SiakadMahasiswaSyncService
{
    public function sync(array $options = []): array
    {
        $baseUrl = rtrim((string) ($options['url'] ?? ''), '/');
        $token = (string) ($options['token'] ?? '');
        $authScheme = strtolower((string) ($options['auth_scheme'] ?? 'bearer'));

        if ($baseUrl === '') {
            throw new SiakadMahasiswaSyncException('URL API mahasiswa siakad belum dikonfigurasi (SIAKAD_MAHASISWA_SYNC_URL).');
        }
        if ($token === '') {
            throw new SiakadMahasiswaSyncException('Token API mahasiswa siakad belum dikonfigurasi (SIAKAD_MAHASISWA_SYNC_TOKEN).');
        }

        $statusFilter = $options['status'] ?? 'all';
        $angkatan = $options['angkatan'] ?? null;
        $prodi = $options['prodi'] ?? null;
        $perPage = max(1, min(500, (int) ($options['per_page'] ?? $options['limit'] ?? 100)));
        $maxPages = (int) ($options['max_pages'] ?? 0);
        $startPage = max(1, (int) ($options['page'] ?? 1));
        $nim = $options['nim'] ?? null;

        $summary = [
            'fetched' => 0,
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'failed' => 0,
            'pages' => 0,
            'skip_reasons' => [],
            'errors' => [],
            'source_url' => $baseUrl,
        ];

        $page = $startPage;
        $lastPage = $startPage;

        do {
            $query = array_filter([
                'page' => $page,
                'per_page' => $perPage,
                'status' => $statusFilter,
                'angkatan' => $angkatan,
                'prodi' => $prodi,
                'nim' => $nim,
            ], fn ($v) => $v !== null && $v !== '');

            try {
                $request = Http::timeout(30)->acceptJson();
                if ($authScheme === 'header_key' || $authScheme === 'x-api-key') {
                    $request = $request->withHeaders(['X-API-KEY' => $token]);
                } else {
                    $request = $request->withToken($token);
                }

                $response = $request->get($baseUrl, $query);
            } catch (\Throwable $e) {
                throw new SiakadMahasiswaSyncException('Gagal menghubungi API mahasiswa siakad: ' . $e->getMessage(), 502, $e);
            }

            if (!$response->successful()) {
                $body = $response->json();
                $msg = is_array($body) ? ($body['message'] ?? $body['error']['message'] ?? null) : null;
                throw new SiakadMahasiswaSyncException(
                    'API mahasiswa siakad mengembalikan HTTP ' . $response->status() . ($msg ? (': ' . $msg) : ''),
                    502
                );
            }

            $payload = $response->json();
            if (!is_array($payload)) {
                throw new SiakadMahasiswaSyncException('Respons API mahasiswa siakad bukan JSON yang valid.');
            }

            $items = $payload['data'] ?? null;
            if ($items !== null && !is_array($items)) {
                // single object case
                $items = [$items];
            }
            if ($items === null) {
                // fallback: maybe list response
                $items = array_is_list($payload) ? $payload : [];
            }
            if (!is_array($items)) {
                $items = [];
            }
            // if associative single record slipped through
            if ($items !== [] && !array_is_list($items) && isset($items['identitas'])) {
                $items = [$items];
            }

            $summary['pages']++;
            $summary['fetched'] += count($items);

            foreach ($items as $item) {
                DB::transaction(function () use ($item, &$summary) {
                    $this->processItem($item, $summary);
                });
            }

            $pagination = $payload['pagination'] ?? $payload['meta'] ?? [];
            if (is_array($pagination)) {
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
                    // infer from count
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
        } while ($page <= $lastPage);

        return $summary;
    }

    private function processItem($item, array &$summary): void
    {
        if (!is_array($item)) {
            $this->skip($summary, 'invalid_item', 'Item bukan object data.');
            return;
        }

        $identitas = is_array($item['identitas'] ?? null) ? $item['identitas'] : [];
        $akademik = is_array($item['akademik'] ?? null) ? $item['akademik'] : [];
        $kontak = is_array($item['kontak'] ?? null) ? $item['kontak'] : [];
        $alamat = is_array($item['alamat'] ?? null) ? $item['alamat'] : [];
        $ortu = is_array($item['orang_tua_wali'] ?? null) ? $item['orang_tua_wali'] : [];
        $raw = is_array($item['raw_data'] ?? null) ? $item['raw_data'] : [];

        $nim = trim((string) ($identitas['nim'] ?? $raw['nipd'] ?? $raw['nim'] ?? ''));
        if ($nim === '') {
            $this->skip($summary, 'missing_nim', 'NIM kosong.');
            return;
        }

        $nama = trim((string) ($identitas['nama'] ?? $raw['nm_pd'] ?? ''));
        if ($nama === '') {
            $nama = $nim;
        }

        $prodi = $this->resolveProgramStudi($akademik, $raw);
        if (!$prodi) {
            $this->skip($summary, 'program_studi_not_found', "Prodi tidak ditemukan untuk NIM {$nim}.", $nim);
            return;
        }

        $status = $this->mapStatus($akademik, $raw);
        $jenisKelamin = $this->mapJenisKelamin($identitas['jenis_kelamin'] ?? $raw['jk'] ?? null);
        $angkatan = $this->resolveAngkatan($akademik, $raw);
        $tanggalLahir = $this->parseDate($identitas['tanggal_lahir'] ?? $raw['tgl_lahir'] ?? null);
        $tanggalMasuk = $this->parseDate($akademik['tanggal_masuk'] ?? $raw['tgl_masuk_sp'] ?? null);

        $email = trim((string) ($kontak['email'] ?? $raw['email'] ?? ''));
        $telepon = trim((string) ($kontak['nomor_hp'] ?? $kontak['telepon_seluler'] ?? $raw['telepon_seluler'] ?? $raw['hp'] ?? ''));

        $alamatText = $this->buildAlamat($alamat, $raw);

        $ibu = is_array($ortu['ibu'] ?? null) ? $ortu['ibu'] : [];
        $ayah = is_array($ortu['ayah'] ?? null) ? $ortu['ayah'] : [];
        $wali = is_array($ortu['wali'] ?? null) ? $ortu['wali'] : [];

        $attributes = [
            'program_studi_id' => $prodi->id,
            'nama_lengkap' => $nama,
            'nik' => $this->nullableTrim($identitas['nik'] ?? $raw['nik'] ?? null),
            'nisn' => $this->nullableTrim($identitas['nisn'] ?? $raw['nisn'] ?? null),
            'tempat_lahir' => $this->nullableTrim($identitas['tempat_lahir'] ?? $raw['tmpt_lahir'] ?? null),
            'tanggal_lahir' => $tanggalLahir,
            'jenis_kelamin' => $jenisKelamin,
            'agama' => $this->nullableTrim($identitas['agama'] ?? $raw['nm_agama'] ?? null),
            'alamat' => $alamatText,
            'rt' => $this->nullableTrim($alamat['rt'] ?? $raw['rt'] ?? null),
            'rw' => $this->nullableTrim($alamat['rw'] ?? $raw['rw'] ?? null),
            'dusun' => $this->nullableTrim($alamat['dusun'] ?? $raw['nm_dsn'] ?? null),
            'kelurahan' => $this->nullableTrim($alamat['kelurahan_desa'] ?? $raw['ds_kel'] ?? null),
            'kode_pos' => $this->nullableTrim($alamat['kode_pos'] ?? $raw['kode_pos'] ?? null),
            'jenis_tinggal' => $this->nullableTrim($alamat['jenis_tinggal'] ?? $raw['id_jns_tinggal'] ?? null),
            'alat_transportasi' => $this->nullableTrim($alamat['alat_transportasi'] ?? $raw['id_alat_transport'] ?? null),
            'telepon' => $telepon !== '' ? $telepon : null,
            'email' => $email !== '' ? $email : null,
            'nama_ibu_kandung' => $this->nullableTrim($ibu['nama'] ?? $raw['nm_ibu_kandung'] ?? null),
            'nama_ayah' => $this->nullableTrim($ayah['nama'] ?? $raw['nm_ayah'] ?? null),
            'nama_wali' => $this->nullableTrim($wali['nama'] ?? $raw['nm_wali'] ?? null),
            'angkatan' => $angkatan,
            'tanggal_masuk' => $tanggalMasuk,
            'jenis_pendaftaran' => $this->nullableTrim($akademik['jenis_pendaftaran'] ?? $akademik['id_jenis_daftar'] ?? $raw['id_jns_daftar'] ?? null),
            'status' => $status,
            'kelas' => $this->nullableTrim($akademik['kelas'] ?? $raw['kelas'] ?? null),
        ];

        try {
            $existing = Mahasiswa::withTrashed()->where('nim', $nim)->first();
            if ($existing) {
                if (method_exists($existing, 'trashed') && $existing->trashed()) {
                    $existing->restore();
                }
                $existing->fill($attributes);
                $existing->save();
                $summary['updated']++;
            } else {
                $attributes['nim'] = $nim;
                Mahasiswa::create($attributes);
                $summary['created']++;
            }
        } catch (\Throwable $e) {
            $summary['failed']++;
            if (count($summary['errors']) < 50) {
                $summary['errors'][] = ['nim' => $nim, 'message' => $e->getMessage()];
            }
            Log::warning('SiakadMahasiswaSync gagal memproses NIM ' . $nim . ': ' . $e->getMessage());
        }
    }

    private function resolveProgramStudi(array $akademik, array $raw): ?ProgramStudi
    {
        $prodiIdSource = $akademik['prodi_id'] ?? $raw['id_sms'] ?? $raw['xid_sms'] ?? null;
        if ($prodiIdSource !== null && $prodiIdSource !== '') {
            $prodi = ProgramStudi::where('source_id', (string) $prodiIdSource)->first();
            if ($prodi) return $prodi;
        }

        $kode = $raw['kode_prodi'] ?? null;
        if ($kode) {
            $prodi = ProgramStudi::where('kode_prodi', (string) $kode)
                ->orWhere('source_kode_prodi', (string) $kode)
                ->first();
            if ($prodi) return $prodi;
        }

        $nama = $akademik['program_studi'] ?? $raw['nama_prodi'] ?? $raw['nm_lemb'] ?? null;
        $jenjang = $akademik['jenjang'] ?? $raw['jenjang_prodi'] ?? $raw['nm_jenj_didik'] ?? null;
        if ($nama) {
            $q = ProgramStudi::where('nama', $nama);
            if ($jenjang) $q->where('jenjang', $jenjang);
            $prodi = $q->first();
            if ($prodi) return $prodi;
        }

        return null;
    }

    private function mapStatus(array $akademik, array $raw): string
    {
        $status = strtolower(trim((string) ($akademik['status_mahasiswa'] ?? $raw['status_mahasiswa'] ?? '')));
        $idKeluar = trim((string) ($raw['id_jns_keluar'] ?? ($akademik['kelulusan']['id_jenis_keluar'] ?? '')));

        if ($status === 'lulus' || $idKeluar === '1' || (!empty($akademik['is_alumni']) && $akademik['is_alumni'] === true)) {
            return 'lulus';
        }
        if ($status === 'aktif' || (!empty($akademik['is_mahasiswa_aktif']) && $akademik['is_mahasiswa_aktif'] === true)) {
            return 'aktif';
        }
        if ($status === 'cuti' || $idKeluar === '2') {
            return 'cuti';
        }
        if (str_contains($status, 'drop') || $idKeluar === '3' || $idKeluar === '4') {
            return 'dropout';
        }
        if ($status === 'mangkir' || $idKeluar === '5') {
            return 'mangkir';
        }
        if ($status === 'keluar' || $status === 'mutasi' || ($idKeluar !== '' && $idKeluar !== '0')) {
            return 'dropout';
        }

        return 'aktif';
    }

    private function mapJenisKelamin($value): ?string
    {
        $v = strtoupper(trim((string) $value));
        if ($v === 'L' || $v === 'LAKI-LAKI' || $v === 'LAKI LAKI' || $v === 'M') return 'L';
        if ($v === 'P' || $v === 'PEREMPUAN' || $v === 'F') return 'P';
        return null;
    }

    private function resolveAngkatan(array $akademik, array $raw): int
    {
        $angkatan = $akademik['angkatan'] ?? null;
        if ($angkatan !== null && $angkatan !== '' && is_numeric($angkatan)) {
            return (int) $angkatan;
        }
        $mulai = (string) ($akademik['semester_masuk'] ?? $raw['mulai_smt'] ?? '');
        if (strlen($mulai) >= 4 && is_numeric(substr($mulai, 0, 4))) {
            return (int) substr($mulai, 0, 4);
        }
        $nim = (string) ($raw['nipd'] ?? '');
        if (preg_match('/(20\d{2})/', $nim, $m)) {
            return (int) $m[1];
        }
        return (int) date('Y');
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

    private function buildAlamat(array $alamat, array $raw): ?string
    {
        $parts = array_filter([
            $this->nullableTrim($alamat['jalan'] ?? $raw['jln'] ?? null),
            $this->nullableTrim($alamat['dusun'] ?? $raw['nm_dsn'] ?? null),
            $this->nullableTrim($alamat['kelurahan_desa'] ?? $raw['ds_kel'] ?? null),
            $this->nullableTrim($alamat['wilayah'] ?? $raw['nama_wilayah'] ?? null),
        ], fn ($v) => $v !== null && $v !== '');
        if (empty($parts)) return null;
        return implode(', ', $parts);
    }

    private function nullableTrim($value): ?string
    {
        if ($value === null) return null;
        $v = trim((string) $value);
        return $v === '' ? null : $v;
    }

    private function skip(array &$summary, string $reason, string $message, ?string $nim = null): void
    {
        $summary['skipped']++;
        $summary['skip_reasons'][$reason] = ($summary['skip_reasons'][$reason] ?? 0) + 1;
        if (count($summary['errors']) < 50) {
            $entry = ['reason' => $reason, 'message' => $message];
            if ($nim !== null) $entry['nim'] = $nim;
            $summary['errors'][] = $entry;
        }
    }
}
