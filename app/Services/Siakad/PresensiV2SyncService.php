<?php

namespace App\Services\Siakad;

use App\Models\Lms\LmsPresensiSyncLog;
use App\Models\Siakad\AbsensiMahasiswa;
use App\Models\Siakad\DosenPengampu;
use App\Models\Siakad\Kelas;
use App\Models\Siakad\KrsDetail;
use App\Models\Siakad\Mahasiswa;
use App\Models\Siakad\Pertemuan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Integrasi LMS -> PresensiV2 (LMS-03).
 *
 * RAG menyimpan presensinya sendiri pada `siakad_pertemuan` (sesi) dan
 * `siakad_absensi_mahasiswa` (rekap). Service ini menyinkronkan cermin data
 * tersebut ke aplikasi PresensiV2:
 *
 *  - `buka_jurnal`  : sesi/pertemuan -> jurnal perkuliahan PresensiV2.
 *  - `hadir`        : mahasiswa hadir  -> baris rekap kehadiran PresensiV2.
 *  - `hapus_hadir`  : status izin/sakit/alfa -> baris hadir dihapus di PresensiV2.
 *  - `tarik`        : jurnal + rekap dari PresensiV2 ditarik ke LMS.
 *
 * Dua jalur komunikasi (dipilih via `config/lms_integration.php`):
 *  - `api` : POST {PRESENSI_API_URL}/api/sync.php
 *  - `db`  : tulis langsung ke MySQL PresensiV2 (koneksi `presensiv2`)
 *  - `auto`: coba `api` dulu bila URL terisi, fallback ke `db`.
 *
 * Bila integrasi belum dikonfigurasi, seluruh metode mengembalikan status
 * "skipped" tanpa mengubah data lokal — fitur LMS tetap berjalan normal.
 *
 * Skema PresensiV2 (native PHP):
 *  - presensi_jurnal_perkuliahan(xid_kls, tanggal, kode_mk, nama_makul, materi,
 *    id_ptk, id_smt, id_prodi, nm_kls, pertemuan_ke, kegiatan, ruang, tanggal_input)
 *  - presensi_rekap(nim, id_jurnal, id_ptk[, waktu]) — "hadir" = row ada,
 *    tidak ada kolom `status`.
 */
class PresensiV2SyncService
{
    protected string $apiUrl;

    protected string $apiKey;

    protected string $mode;

    protected int $timeout;

    protected string $dbConnection;

    protected string $jurnalTable;

    protected string $rekapTable;

    protected string $idPtkSource;

    protected string $ruangDefault;

    protected ?string $lastFetchError = null;

    public function __construct()
    {
        $this->apiUrl = (string) config('lms_integration.presensi.api_url', '');
        $this->apiKey = (string) config('lms_integration.presensi.api_key', '');
        $this->mode = (string) config('lms_integration.presensi.mode', 'auto');
        $this->timeout = (int) config('lms_integration.presensi.timeout', 10);
        $this->dbConnection = (string) config('lms_integration.presensi.db_connection', 'presensiv2');
        $this->jurnalTable = (string) config('lms_integration.presensi.jurnal_table', 'presensi_jurnal_perkuliahan');
        $this->rekapTable = (string) config('lms_integration.presensi.rekap_table', 'presensi_rekap');
        $this->idPtkSource = (string) config('lms_integration.presensi.id_ptk_source', 'nidn');
        $this->ruangDefault = (string) config('lms_integration.presensi.ruang_default', 'LMS Online');
    }

    // ------------------------------------------------------------------
    // Konfigurasi & status
    // ------------------------------------------------------------------

    /**
     * Apakah integrasi PresensiV2 sudah siap dipakai.
     */
    public function isConfigured(): bool
    {
        if ($this->shouldUseApi()) {
            return $this->apiUrl !== '';
        }

        return $this->dbConfigured();
    }

    /**
     * Ringkasan status integrasi untuk panel admin.
     *
     * @return array<string, mixed>
     */
    public function status(): array
    {
        $configured = $this->isConfigured();

        $via = match (true) {
            ! $configured                    => LmsPresensiSyncLog::VIA_NONE,
            $this->shouldUseApi()            => LmsPresensiSyncLog::VIA_API,
            default                           => LmsPresensiSyncLog::VIA_DB,
        };

        $result = [
            'configured'     => $configured,
            'mode'           => $this->mode,
            'via'            => $via,
            'api_url'        => $this->apiUrl,
            'timeout'        => $this->timeout,
            'db_connection'  => $this->dbConnection,
            'api_reachable'  => false,
            'db_reachable'   => false,
            'message'        => $configured
                ? 'Integrasi PresensiV2 aktif.'
                : 'Integrasi PresensiV2 belum dikonfigurasi (PRESENSI_API_URL / koneksi database PresensiV2 kosong).',
        ];

        if ($configured && ! $this->shouldUseApi()) {
            $result['db_reachable'] = $this->dbReachable();
            $result['message'] = $result['db_reachable']
                ? 'Integrasi PresensiV2 aktif (direct-DB).'
                : 'Integrasi PresensiV2 aktif, tetapi koneksi database PresensiV2 belum dapat dijangkau.';
        }

        if ($configured && $this->shouldUseApi()) {
            $result['api_reachable'] = $this->apiReachable();
            $result['message'] = $result['api_reachable']
                ? 'Integrasi PresensiV2 aktif (REST API).'
                : 'Integrasi PresensiV2 aktif, tetapi API PresensiV2 belum dapat dijangkau.';
        }

        return $result;
    }

    // ------------------------------------------------------------------
    // Public API
    // ------------------------------------------------------------------

    /**
     * Buat / perbarui jurnal perkuliahan di PresensiV2 untuk satu pertemuan.
     *
     * Idempotent: cek dulu jurnal yang sama (xid_kls + pertemuan_ke + id_ptk).
     * Jurnal WAJIB ada sebelum kehadiran dikirim (aturan PresensiV2).
     *
     * @return array{success: bool, skipped: bool, message: string, via: string, id_jurnal?: int|null}
     */
    public function syncJurnal(Pertemuan $pertemuan): array
    {
        if (! $this->isConfigured()) {
            return $this->skipped('Integrasi PresensiV2 belum dikonfigurasi.');
        }

        $pertemuan->loadMissing('kelas.mataKuliah', 'kelas.tahunAkademik', 'kelas.programStudi', 'kelas.ruangan');
        $meta = $this->resolveJurnalMeta($pertemuan);

        if ($missing = $this->validateMeta($meta)) {
            $result = [
                'success' => false,
                'skipped' => false,
                'message' => 'Jurnal tidak dikirim ke PresensiV2: '.$missing,
                'via' => LmsPresensiSyncLog::VIA_NONE,
            ];
            $this->markJurnalResult($pertemuan, $result);

            return $result;
        }

        if ($this->shouldUseApi()) {
            $result = $this->syncJurnalViaApi($meta);
            if ($result['success'] || $this->mode === 'api') {
                $this->markJurnalResult($pertemuan, $result);

                return $result;
            }
            Log::warning('Presensi API gagal, fallback ke direct-DB: '.($result['error'] ?? 'unknown'));
        }

        $result = $this->syncJurnalViaDb($meta);
        $this->markJurnalResult($pertemuan, $result);

        return $result;
    }

    /**
     * Sinkronkan seluruh rekap kehadiran satu pertemuan ke PresensiV2.
     *
     * Status `hadir` → pastikan row hadir di PresensiV2.
     * Status selain hadir → pastikan row hadir dihapus (PresensiV2 tidak punya
     * kolom status; kehadiran di sana berarti "hadir").
     *
     * @return array{success: bool, skipped: bool, message: string, via: string, synced: int, failed: int, deleted: int}
     */
    public function syncKehadiran(Pertemuan $pertemuan): array
    {
        if (! $this->isConfigured()) {
            return $this->skipped('Integrasi PresensiV2 belum dikonfigurasi.');
        }

        $pertemuan->loadMissing('absensi.mahasiswa', 'kelas.mataKuliah', 'kelas.tahunAkademik', 'kelas.programStudi');

        $jurnal = $this->syncJurnal($pertemuan);
        if (! ($jurnal['success'] ?? false)) {
            return [
                'success' => false,
                'skipped' => false,
                'message' => $jurnal['message'] ?? 'Gagal menyinkronkan jurnal.',
                'via' => $jurnal['via'] ?? LmsPresensiSyncLog::VIA_NONE,
                'synced' => 0,
                'failed' => 0,
                'deleted' => 0,
            ];
        }

        $pertemuan->refresh();
        $meta = $this->resolveJurnalMeta($pertemuan);
        $idJurnal = (int) ($pertemuan->id_jurnal_presensiv2 ?? 0);

        $synced = 0;
        $failed = 0;
        $deleted = 0;

        foreach ($pertemuan->absensi as $absensi) {
            $nim = (string) ($absensi->mahasiswa?->nim ?? '');

            if ($nim === '') {
                $this->markAbsensiResult($absensi, [
                    'success' => false,
                    'message' => 'NIM mahasiswa tidak ditemukan.',
                    'via' => LmsPresensiSyncLog::VIA_NONE,
                ]);
                $failed++;
                continue;
            }

            if ($absensi->status !== AbsensiMahasiswa::STATUS_HADIR) {
                if ($idJurnal === 0) {
                    $this->markAbsensiResult($absensi, [
                        'success' => true,
                        'message' => 'Status tersimpan di LMS (belum ada jurnal PresensiV2).',
                        'via' => LmsPresensiSyncLog::VIA_LOCAL,
                    ]);
                    continue;
                }

                $result = $this->shouldUseApi()
                    ? $this->hapusHadirViaApi($idJurnal, $nim, $meta)
                    : $this->hapusHadirViaDb($idJurnal, $nim, $meta['id_ptk']);

                $this->markAbsensiResult($absensi, $result);
                if ($result['success'] ?? false) {
                    $deleted++;
                } else {
                    $failed++;
                }
                continue;
            }

            $result = $this->shouldUseApi()
                ? $this->syncHadirViaApi($meta, $nim)
                : $this->syncHadirViaDb($nim, $idJurnal, $meta['id_ptk']);

            $this->markAbsensiResult($absensi, $result);
            if ($result['success'] ?? false) {
                $synced++;
            } else {
                $failed++;
            }
        }

        $status = $failed === 0
            ? LmsPresensiSyncLog::STATUS_SUCCESS
            : ($synced > 0 ? LmsPresensiSyncLog::STATUS_PARTIAL : LmsPresensiSyncLog::STATUS_FAILED);

        $this->log(
            LmsPresensiSyncLog::ENTITAS_PERTEMUAN,
            $pertemuan->id,
            LmsPresensiSyncLog::AKSI_PUSH_KEHADIRAN,
            $status,
            $this->mode === 'db' ? LmsPresensiSyncLog::VIA_DB : LmsPresensiSyncLog::VIA_API,
            "Kehadiran tersinkron: {$synced} hadir, {$deleted} dihapus, {$failed} gagal.",
            [
                'kelas_id' => $pertemuan->kelas_id,
                'pertemuan_ke' => $pertemuan->pertemuan_ke,
                'synced' => $synced,
                'deleted' => $deleted,
                'failed' => $failed,
            ]
        );

        return [
            'success' => $failed === 0,
            'skipped' => false,
            'message' => "Kehadiran tersinkron: {$synced} hadir, {$deleted} dihapus, {$failed} gagal.",
            'via' => $this->mode === 'db' ? LmsPresensiSyncLog::VIA_DB : LmsPresensiSyncLog::VIA_API,
            'synced' => $synced,
            'failed' => $failed,
            'deleted' => $deleted,
        ];
    }

    /**
     * Sinkronkan satu baris kehadiran mahasiswa.
     */
    public function syncAbsensi(AbsensiMahasiswa $absensi): array
    {
        if (! $this->isConfigured()) {
            return $this->skipped('Integrasi PresensiV2 belum dikonfigurasi.');
        }

        $absensi->loadMissing('mahasiswa', 'pertemuan.kelas.mataKuliah', 'pertemuan.kelas.tahunAkademik', 'pertemuan.kelas.programStudi');
        $pertemuan = $absensi->pertemuan;

        if (! $pertemuan) {
            return [
                'success' => false,
                'skipped' => false,
                'message' => 'Pertemuan tidak ditemukan.',
                'via' => LmsPresensiSyncLog::VIA_NONE,
            ];
        }

        if ($absensi->status !== AbsensiMahasiswa::STATUS_HADIR) {
            return $this->hapusAbsensi($absensi);
        }

        $nim = (string) ($absensi->mahasiswa?->nim ?? '');
        if ($nim === '') {
            $result = [
                'success' => false,
                'skipped' => false,
                'message' => 'NIM mahasiswa tidak ditemukan.',
                'via' => LmsPresensiSyncLog::VIA_NONE,
            ];
            $this->markAbsensiResult($absensi, $result);

            return $result;
        }

        // Jurnal harus ada lebih dulu di PresensiV2.
        if (! $pertemuan->id_jurnal_presensiv2) {
            $jurnal = $this->syncJurnal($pertemuan);
            if (! ($jurnal['success'] ?? false)) {
                return $jurnal;
            }
            $pertemuan->refresh();
        }

        $meta = $this->resolveJurnalMeta($pertemuan);
        $idJurnal = (int) ($pertemuan->id_jurnal_presensiv2 ?? 0);

        $result = $this->shouldUseApi()
            ? $this->syncHadirViaApi($meta, $nim)
            : $this->syncHadirViaDb($nim, $idJurnal, $meta['id_ptk']);

        $this->markAbsensiResult($absensi, $result);

        return $result;
    }

    /**
     * Hapus satu baris "hadir" dari PresensiV2 (untuk status izin/sakit/alfa).
     */
    public function hapusAbsensi(AbsensiMahasiswa $absensi): array
    {
        if (! $this->isConfigured()) {
            return $this->skipped('Integrasi PresensiV2 belum dikonfigurasi.');
        }

        $absensi->loadMissing('mahasiswa', 'pertemuan.kelas.mataKuliah', 'pertemuan.kelas.tahunAkademik', 'pertemuan.kelas.programStudi');
        $pertemuan = $absensi->pertemuan;
        $idJurnal = (int) ($pertemuan?->id_jurnal_presensiv2 ?? 0);
        $nim = (string) ($absensi->mahasiswa?->nim ?? '');

        if (! $pertemuan || $idJurnal === 0 || $nim === '') {
            $result = [
                'success' => true,
                'skipped' => false,
                'message' => 'Status tersimpan di LMS (belum ada jurnal PresensiV2).',
                'aksi' => LmsPresensiSyncLog::AKSI_HAPUS_HADIR,
                'via' => LmsPresensiSyncLog::VIA_LOCAL,
            ];
            $this->markAbsensiResult($absensi, $result);

            return $result;
        }

        $meta = $this->resolveJurnalMeta($pertemuan);
        $result = $this->shouldUseApi()
            ? $this->hapusHadirViaApi($idJurnal, $nim, $meta)
            : $this->hapusHadirViaDb($idJurnal, $nim, $meta['id_ptk']);

        $this->markAbsensiResult($absensi, $result);

        return $result;
    }

    /**
     * Reset rekap kehadiran satu pertemuan (lokal + PresensiV2).
     */
    public function resetRekap(Pertemuan $pertemuan): array
    {
        $pertemuan->loadMissing('kelas.mataKuliah', 'kelas.tahunAkademik', 'kelas.programStudi', 'kelas.ruangan');
        $idJurnal = (int) ($pertemuan->id_jurnal_presensiv2 ?? 0);

        AbsensiMahasiswa::where('pertemuan_id', $pertemuan->id)->delete();

        if (! $this->isConfigured() || $idJurnal === 0) {
            $result = [
                'success' => true,
                'skipped' => false,
                'message' => 'Rekap LMS dihapus (belum ada jurnal PresensiV2).',
                'via' => LmsPresensiSyncLog::VIA_LOCAL,
            ];
            $this->log(
                LmsPresensiSyncLog::ENTITAS_PERTEMUAN,
                $pertemuan->id,
                LmsPresensiSyncLog::AKSI_RESET,
                LmsPresensiSyncLog::STATUS_SUCCESS,
                LmsPresensiSyncLog::VIA_LOCAL,
                $result['message'],
                ['kelas_id' => $pertemuan->kelas_id, 'pertemuan_ke' => $pertemuan->pertemuan_ke]
            );

            return $result;
        }

        $meta = $this->resolveJurnalMeta($pertemuan);

        try {
            if ($this->shouldUseApi()) {
                $response = $this->postSync([
                    'action' => 'reset',
                    'xid_kls' => $meta['xid_kls'],
                    'id_ptk' => $meta['id_ptk'],
                    'pertemuan_ke' => $meta['pertemuan_ke'],
                    'tanggal' => $meta['tanggal'],
                    'materi' => $meta['materi'],
                ]);

                $body = $response->json() ?? [];
                if ($response->successful() && ($body['status'] ?? null) === 'success') {
                    $result = [
                        'success' => true,
                        'skipped' => false,
                        'message' => 'Rekap PresensiV2 dihapus.',
                        'via' => LmsPresensiSyncLog::VIA_API,
                    ];
                    $this->log(
                        LmsPresensiSyncLog::ENTITAS_PERTEMUAN,
                        $pertemuan->id,
                        LmsPresensiSyncLog::AKSI_RESET,
                        LmsPresensiSyncLog::STATUS_SUCCESS,
                        LmsPresensiSyncLog::VIA_API,
                        $result['message'],
                        ['kelas_id' => $pertemuan->kelas_id, 'pertemuan_ke' => $pertemuan->pertemuan_ke]
                    );

                    return $result;
                }

                if ($this->mode === 'api') {
                    $result = [
                        'success' => false,
                        'skipped' => false,
                        'message' => 'API Presensi menolak reset.',
                        'via' => LmsPresensiSyncLog::VIA_API,
                        'error' => $body,
                    ];
                    $this->log(
                        LmsPresensiSyncLog::ENTITAS_PERTEMUAN,
                        $pertemuan->id,
                        LmsPresensiSyncLog::AKSI_RESET,
                        LmsPresensiSyncLog::STATUS_FAILED,
                        LmsPresensiSyncLog::VIA_API,
                        $result['message'],
                        ['error' => $body]
                    );

                    return $result;
                }
            }

            DB::connection($this->dbConnection)->table($this->rekapTable)
                ->where('id_jurnal', $idJurnal)
                ->delete();

            $result = [
                'success' => true,
                'skipped' => false,
                'message' => 'Rekap PresensiV2 dihapus.',
                'via' => LmsPresensiSyncLog::VIA_DB,
            ];
            $this->log(
                LmsPresensiSyncLog::ENTITAS_PERTEMUAN,
                $pertemuan->id,
                LmsPresensiSyncLog::AKSI_RESET,
                LmsPresensiSyncLog::STATUS_SUCCESS,
                LmsPresensiSyncLog::VIA_DB,
                $result['message'],
                ['kelas_id' => $pertemuan->kelas_id, 'pertemuan_ke' => $pertemuan->pertemuan_ke]
            );

            return $result;
        } catch (\Throwable $e) {
            Log::error('Presensi reset failed: '.$e->getMessage());

            $result = [
                'success' => false,
                'skipped' => false,
                'message' => 'Gagal menghapus rekap PresensiV2.',
                'via' => LmsPresensiSyncLog::VIA_DB,
                'error' => $e->getMessage(),
            ];
            $this->log(
                LmsPresensiSyncLog::ENTITAS_PERTEMUAN,
                $pertemuan->id,
                LmsPresensiSyncLog::AKSI_RESET,
                LmsPresensiSyncLog::STATUS_FAILED,
                LmsPresensiSyncLog::VIA_DB,
                $result['message'],
                ['error' => $e->getMessage()]
            );

            return $result;
        }
    }

    /**
     * Tarik riwayat jurnal + rekap dari PresensiV2 ke LMS.
     *
     * Idempotent: pertemuan yang sudah ada diperbarui, absensi yang sudah ada
     * tidak diduplikasi. Pertemuan hasil tarik tidak diberi token absensi
     * sehingga tidak dapat diklaim ulang oleh mahasiswa.
     *
     * @return array{success: bool, skipped: bool, message: string, via: string, pertemuan: int, absensi: int, skipped_mahasiswa: int}
     */
    public function tarikRiwayat(Kelas $kelas): array
    {
        if (! $this->isConfigured()) {
            // Tarik riwayat tidak pernah mengubah data lokal bila integrasi
            // belum siap — hasilnya dianggap gagal (bukan sekadar dilewati).
            return [
                'success' => false,
                'skipped' => true,
                'message' => 'Integrasi PresensiV2 belum dikonfigurasi.',
                'via'    => LmsPresensiSyncLog::VIA_NONE,
            ];
        }

        $kelas->loadMissing('mataKuliah', 'tahunAkademik', 'programStudi');
        $xidKls = (string) ($kelas->kode_kelas ?? '');
        $idSmt = (string) ($kelas->tahunAkademik?->kode ?? '');

        if ($xidKls === '') {
            return [
                'success' => false,
                'skipped' => false,
                'message' => 'Kelas belum memiliki kode kelas yang dapat dipetakan ke PresensiV2.',
                'via' => LmsPresensiSyncLog::VIA_NONE,
            ];
        }

        $jurnals = $this->fetchJurnalList($xidKls, $idSmt);

        if ($jurnals === null) {
            $why = $this->lastFetchError ? " ({$this->lastFetchError})" : '';

            return [
                'success' => false,
                'skipped' => false,
                'message' => "Gagal mengambil riwayat dari PresensiV2{$why}.",
                'via' => $this->mode === 'db' ? LmsPresensiSyncLog::VIA_DB : LmsPresensiSyncLog::VIA_API,
            ];
        }

        if (empty($jurnals)) {
            return [
                'success' => true,
                'skipped' => false,
                'message' => "Tidak ada jurnal di PresensiV2 untuk kelas {$xidKls}.",
                'via' => $this->mode === 'db' ? LmsPresensiSyncLog::VIA_DB : LmsPresensiSyncLog::VIA_API,
                'pertemuan' => 0,
                'absensi' => 0,
                'skipped_mahasiswa' => 0,
            ];
        }

        // NIM terdaftar pada kelas (KRS detail) agar hanya mahasiswa sah yang
        // absensinya ditarik masuk.
        $mahasiswaIds = KrsDetail::query()
            ->where('kelas_id', $kelas->id)
            ->join('siakad_krs', 'siakad_krs.id', '=', 'siakad_krs_detail.krs_id')
            ->pluck('siakad_krs.mahasiswa_id')
            ->map(fn ($v) => (int) $v)
            ->all();

        $mhsTerdaftar = [];
        if (! empty($mahasiswaIds)) {
            foreach (Mahasiswa::query()->whereIn('id', $mahasiswaIds)->pluck('nim', 'id') as $id => $nim) {
                $mhsTerdaftar[(string) $nim] = (int) $id;
            }
        }

        $pertemuanBaru = 0;
        $absensiBaru = 0;
        $mhsDilewati = 0;

        foreach ($jurnals as $j) {
            $pertemuanKe = (int) ($j['pertemuan_ke'] ?? 0);
            if ($pertemuanKe < 1) {
                continue;
            }

            $pertemuan = Pertemuan::firstOrNew([
                'kelas_id' => $kelas->id,
                'pertemuan_ke' => $pertemuanKe,
            ]);

            $isBaru = ! $pertemuan->exists;
            $pertemuan->tanggal = $this->normalizeDate($j['tanggal'] ?? null) ?? $pertemuan->tanggal;
            $pertemuan->materi = $pertemuan->materi ?: (string) ($j['materi'] ?? 'Perkuliahan LMS');
            $pertemuan->id_jurnal_presensiv2 = (int) ($j['id_jurnal'] ?? 0) ?: $pertemuan->id_jurnal_presensiv2;
            $pertemuan->sync_status = Pertemuan::SYNC_SYNCED;
            $pertemuan->sync_error = null;
            $pertemuan->synced_at = now();
            $pertemuan->save();

            if ($isBaru) {
                $pertemuanBaru++;
            }

            foreach ((array) ($j['nim'] ?? []) as $nim) {
                $nim = (string) $nim;

                if ($nim === '') {
                    continue;
                }

                $mahasiswaId = $mhsTerdaftar[$nim] ?? null;
                if ($mahasiswaId === null) {
                    $mhsDilewati++;
                    continue;
                }

                $absensi = AbsensiMahasiswa::firstOrCreate([
                    'pertemuan_id' => $pertemuan->id,
                    'mahasiswa_id' => $mahasiswaId,
                ], [
                    'status' => AbsensiMahasiswa::STATUS_HADIR,
                ]);

                if ($absensi->wasRecentlyCreated) {
                    $absensiBaru++;
                }

                $absensi->sync_status = AbsensiMahasiswa::SYNC_SYNCED;
                $absensi->sync_error = null;
                $absensi->synced_at = now();
                $absensi->save();
            }
        }

        $message = "Riwayat ditarik: {$pertemuanBaru} pertemuan baru, {$absensiBaru} absensi baru, {$mhsDilewati} mahasiswa di luar kelas dilewati.";

        $this->log(
            LmsPresensiSyncLog::ENTITAS_PERTEMUAN,
            $kelas->id,
            LmsPresensiSyncLog::AKSI_TARIK,
            LmsPresensiSyncLog::STATUS_SUCCESS,
            $this->mode === 'db' ? LmsPresensiSyncLog::VIA_DB : LmsPresensiSyncLog::VIA_API,
            $message,
            [
                'kelas_id' => $kelas->id,
                'pertemuan' => $pertemuanBaru,
                'absensi' => $absensiBaru,
                'skipped_mahasiswa' => $mhsDilewati,
            ]
        );

        return [
            'success' => true,
            'skipped' => false,
            'message' => $message,
            'via' => $this->mode === 'db' ? LmsPresensiSyncLog::VIA_DB : LmsPresensiSyncLog::VIA_API,
            'pertemuan' => $pertemuanBaru,
            'absensi' => $absensiBaru,
            'skipped_mahasiswa' => $mhsDilewati,
        ];
    }

    // ------------------------------------------------------------------
    // Transport — REST API
    // ------------------------------------------------------------------

    protected function shouldUseApi(): bool
    {
        if ($this->mode === 'db') {
            return false;
        }

        if ($this->mode === 'api') {
            return true;
        }

        return $this->apiUrl !== ''; // auto
    }

    protected function apiBase(): string
    {
        return rtrim($this->apiUrl, '/');
    }

    protected function postSync(array $payload)
    {
        return Http::withHeaders(['X-Api-Key' => $this->apiKey])
            ->timeout($this->timeout)
            ->post($this->apiBase().'/api/sync.php', $payload);
    }

    protected function syncJurnalViaApi(array $meta): array
    {
        try {
            $response = $this->postSync([
                'action' => 'buka_jurnal',
                'xid_kls' => $meta['xid_kls'],
                'id_ptk' => $meta['id_ptk'],
                'pertemuan_ke' => $meta['pertemuan_ke'],
                'tanggal' => $meta['tanggal'],
                'materi' => $meta['materi'],
                'kode_mk' => $meta['kode_mk'],
                'nama_makul' => $meta['nama_makul'],
                'nm_kls' => $meta['nm_kls'],
                'id_smt' => $meta['id_smt'],
                'id_prodi' => $meta['id_prodi'],
                'kegiatan' => $meta['kegiatan'],
                'ruang' => $meta['ruang'],
            ]);

            $body = $response->json() ?? [];
            if ($response->successful() && ($body['status'] ?? null) === 'success' && isset($body['id_jurnal'])) {
                return [
                    'success' => true,
                    'skipped' => false,
                    'message' => 'Jurnal tersimpan di PresensiV2.',
                    'id_jurnal' => (int) $body['id_jurnal'],
                    'via' => LmsPresensiSyncLog::VIA_API,
                ];
            }

            return [
                'success' => false,
                'skipped' => false,
                'message' => 'API Presensi menolak jurnal.',
                'error' => $body ?: $response->body(),
                'via' => LmsPresensiSyncLog::VIA_API,
            ];
        } catch (\Throwable $e) {
            Log::error('Presensi API (buka_jurnal) exception: '.$e->getMessage());

            return [
                'success' => false,
                'skipped' => false,
                'message' => 'Koneksi ke API Presensi terputus.',
                'error' => $e->getMessage(),
                'via' => LmsPresensiSyncLog::VIA_API,
            ];
        }
    }

    protected function syncHadirViaApi(array $meta, string $nim): array
    {
        try {
            $response = $this->postSync([
                'action' => 'hadir',
                'xid_kls' => $meta['xid_kls'],
                'id_ptk' => $meta['id_ptk'],
                'pertemuan_ke' => $meta['pertemuan_ke'],
                'tanggal' => $meta['tanggal'],
                'materi' => $meta['materi'],
                'nim' => $nim,
            ]);

            $body = $response->json() ?? [];
            if ($response->successful() && ($body['status'] ?? null) === 'success') {
                return [
                    'success' => true,
                    'skipped' => false,
                    'message' => 'Hadir tercatat di PresensiV2.',
                    'id_jurnal' => isset($body['id_jurnal']) ? (int) $body['id_jurnal'] : null,
                    'via' => LmsPresensiSyncLog::VIA_API,
                ];
            }

            return [
                'success' => false,
                'skipped' => false,
                'message' => 'API Presensi menolak kehadiran.',
                'error' => $body ?: $response->body(),
                'via' => LmsPresensiSyncLog::VIA_API,
            ];
        } catch (\Throwable $e) {
            Log::error('Presensi API (hadir) exception: '.$e->getMessage());

            return [
                'success' => false,
                'skipped' => false,
                'message' => 'Koneksi ke API Presensi terputus.',
                'error' => $e->getMessage(),
                'via' => LmsPresensiSyncLog::VIA_API,
            ];
        }
    }

    protected function hapusHadirViaApi(int $idJurnal, string $nim, array $meta): array
    {
        try {
            $response = $this->postSync([
                'action' => 'hapus',
                'xid_kls' => $meta['xid_kls'],
                'id_ptk' => $meta['id_ptk'],
                'pertemuan_ke' => $meta['pertemuan_ke'],
                'tanggal' => $meta['tanggal'],
                'materi' => $meta['materi'],
                'nim' => $nim,
            ]);

            $body = $response->json() ?? [];
            if ($response->successful() && ($body['status'] ?? null) === 'success') {
                return [
                    'success' => true,
                    'skipped' => false,
                    'message' => 'Baris hadir dihapus dari PresensiV2.',
                    'aksi' => LmsPresensiSyncLog::AKSI_HAPUS_HADIR,
                    'via' => LmsPresensiSyncLog::VIA_API,
                ];
            }

            return [
                'success' => false,
                'skipped' => false,
                'message' => 'API Presensi menolak hapus.',
                'aksi' => LmsPresensiSyncLog::AKSI_HAPUS_HADIR,
                'error' => $body ?: $response->body(),
                'via' => LmsPresensiSyncLog::VIA_API,
            ];
        } catch (\Throwable $e) {
            Log::error('Presensi API (hapus) exception: '.$e->getMessage());

            return [
                'success' => false,
                'skipped' => false,
                'message' => 'Koneksi API Presensi terputus.',
                'error' => $e->getMessage(),
                'via' => LmsPresensiSyncLog::VIA_API,
            ];
        }
    }

    /**
     * Ambil daftar jurnal (beserta NIM hadir) dari PresensiV2.
     *
     * @return array<int, array<string, mixed>>|null null bila gagal
     */
    protected function fetchJurnalList(string $xidKls, string $idSmt): ?array
    {
        $this->lastFetchError = null;

        if ($this->shouldUseApi()) {
            try {
                $response = $this->postSync([
                    'action' => 'tarik',
                    'xid_kls' => $xidKls,
                    'id_smt' => $idSmt,
                    'id_ptk' => '',
                    'pertemuan_ke' => 0,
                ]);

                $body = $response->json() ?? [];
                if ($response->successful() && ($body['status'] ?? null) === 'success') {
                    return is_array($body['data'] ?? null) ? $body['data'] : [];
                }

                $this->lastFetchError = 'API HTTP '.$response->status();
                if ($this->mode === 'api') {
                    return null;
                }
                Log::warning('Presensi API tarik gagal, fallback DB');
            } catch (\Throwable $e) {
                Log::error('Presensi API (tarik) exception: '.$e->getMessage());
                $this->lastFetchError = 'API tak terjangkau';
                if ($this->mode === 'api') {
                    return null;
                }
            }
        }

        try {
            $db = DB::connection($this->dbConnection);
            $query = $db->table($this->jurnalTable)->where('xid_kls', $xidKls);

            if ($idSmt !== '') {
                $query->where('id_smt', $idSmt);
            }

            $jurnals = $query->orderBy('pertemuan_ke')->get();

            if ($jurnals->isEmpty()) {
                return [];
            }

            $ids = $jurnals->pluck('id_jurnal')->map(fn ($v) => (int) $v)->all();
            $rekap = $db->table($this->rekapTable)
                ->whereIn('id_jurnal', $ids)
                ->select(['id_jurnal', 'nim'])
                ->get()
                ->groupBy('id_jurnal');

            return $jurnals->map(fn ($j) => [
                'id_jurnal' => (int) $j->id_jurnal,
                'pertemuan_ke' => $j->pertemuan_ke,
                'tanggal' => $j->tanggal,
                'materi' => $j->materi,
                'id_ptk' => $j->id_ptk,
                'nim' => array_values(array_unique(
                    $rekap->get($j->id_jurnal, collect())->pluck('nim')->all()
                )),
            ])->all();
        } catch (\Throwable $e) {
            Log::error('Presensi DB (tarik) failed: '.$e->getMessage());
            $this->lastFetchError = 'database tak terjangkau';

            return null;
        }
    }

    // ------------------------------------------------------------------
    // Transport — direct DB
    // ------------------------------------------------------------------

    protected function syncJurnalViaDb(array $meta): array
    {
        try {
            $db = DB::connection($this->dbConnection);

            // Idempotent: jurnal yang sama jangan diduplikasi.
            $existing = $db->table($this->jurnalTable)
                ->where('xid_kls', $meta['xid_kls'])
                ->where('pertemuan_ke', $meta['pertemuan_ke'])
                ->where('id_ptk', $meta['id_ptk'])
                ->value('id_jurnal');

            if ($existing) {
                $db->table($this->jurnalTable)->where('id_jurnal', $existing)->update([
                    'tanggal' => $meta['tanggal'],
                    'kode_mk' => $meta['kode_mk'],
                    'nama_makul' => $meta['nama_makul'],
                    'materi' => $meta['materi'],
                    'id_smt' => $meta['id_smt'],
                    'id_prodi' => $meta['id_prodi'],
                    'nm_kls' => $meta['nm_kls'],
                    'kegiatan' => $meta['kegiatan'],
                    'ruang' => $meta['ruang'],
                    'tanggal_input' => now(),
                ]);
                $idJurnal = (int) $existing;
            } else {
                $idJurnal = (int) $db->table($this->jurnalTable)->insertGetId([
                    'xid_kls' => $meta['xid_kls'],
                    'tanggal' => $meta['tanggal'],
                    'kode_mk' => $meta['kode_mk'],
                    'nama_makul' => $meta['nama_makul'],
                    'materi' => $meta['materi'],
                    'id_ptk' => $meta['id_ptk'],
                    'id_smt' => $meta['id_smt'],
                    'id_prodi' => $meta['id_prodi'],
                    'nm_kls' => $meta['nm_kls'],
                    'pertemuan_ke' => $meta['pertemuan_ke'],
                    'kegiatan' => $meta['kegiatan'],
                    'ruang' => $meta['ruang'],
                    'tanggal_input' => now(),
                ], 'id_jurnal');
            }

            return [
                'success' => true,
                'skipped' => false,
                'message' => 'Jurnal tersimpan di PresensiV2.',
                'id_jurnal' => $idJurnal,
                'via' => LmsPresensiSyncLog::VIA_DB,
            ];
        } catch (\Throwable $e) {
            Log::error('Presensi DB (jurnal) failed: '.$e->getMessage());

            return [
                'success' => false,
                'skipped' => false,
                'message' => 'Gagal menulis jurnal ke database PresensiV2.',
                'error' => $e->getMessage(),
                'via' => LmsPresensiSyncLog::VIA_DB,
            ];
        }
    }

    protected function syncHadirViaDb(string $nim, int $idJurnal, string $idPtk): array
    {
        try {
            $db = DB::connection($this->dbConnection);

            $exists = $db->table($this->rekapTable)
                ->where('id_jurnal', $idJurnal)
                ->where('nim', $nim)
                ->where('id_ptk', $idPtk)
                ->exists();

            if (! $exists) {
                $db->table($this->rekapTable)->insert([
                    'nim' => $nim,
                    'id_jurnal' => $idJurnal,
                    'id_ptk' => $idPtk,
                ]);
            }

            return [
                'success' => true,
                'skipped' => false,
                'message' => 'Hadir tercatat di PresensiV2.',
                'id_jurnal' => $idJurnal,
                'via' => LmsPresensiSyncLog::VIA_DB,
            ];
        } catch (\Throwable $e) {
            Log::error('Presensi DB (hadir) failed: '.$e->getMessage());

            return [
                'success' => false,
                'skipped' => false,
                'message' => 'Gagal menulis kehadiran ke database PresensiV2.',
                'error' => $e->getMessage(),
                'via' => LmsPresensiSyncLog::VIA_DB,
            ];
        }
    }

    protected function hapusHadirViaDb(int $idJurnal, string $nim, string $idPtk): array
    {
        try {
            DB::connection($this->dbConnection)->table($this->rekapTable)
                ->where('id_jurnal', $idJurnal)
                ->where('nim', $nim)
                ->where('id_ptk', $idPtk)
                ->delete();

            return [
                'success' => true,
                'skipped' => false,
                'message' => 'Baris hadir dihapus dari PresensiV2.',
                'aksi' => LmsPresensiSyncLog::AKSI_HAPUS_HADIR,
                'via' => LmsPresensiSyncLog::VIA_DB,
            ];
        } catch (\Throwable $e) {
            Log::error('Presensi DB (hapus) failed: '.$e->getMessage());

            return [
                'success' => false,
                'skipped' => false,
                'message' => 'Gagal menghapus di database PresensiV2.',
                'aksi' => LmsPresensiSyncLog::AKSI_HAPUS_HADIR,
                'error' => $e->getMessage(),
                'via' => LmsPresensiSyncLog::VIA_DB,
            ];
        }
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /**
     * Petakan identitas meeting ke data meta jurnal PresensiV2.
     *
     * @return array{xid_kls:string,id_ptk:string,pertemuan_ke:int,tanggal:string,materi:string,kode_mk:string,nama_makul:string,nm_kls:string,id_smt:string,id_prodi:string,kegiatan:string,ruang:string}
     */
    protected function resolveJurnalMeta(Pertemuan $pertemuan): array
    {
        $kelas = $pertemuan->kelas;
        $mataKuliah = $kelas?->mataKuliah;
        $tahunAkademik = $kelas?->tahunAkademik;
        $programStudi = $kelas?->programStudi;

        return [
            'xid_kls' => (string) ($kelas?->kode_kelas ?? ''),
            'id_ptk' => $this->resolveIdPtk($pertemuan),
            'pertemuan_ke' => (int) $pertemuan->pertemuan_ke,
            'tanggal' => $pertemuan->tanggal instanceof \DateTimeInterface
                ? $pertemuan->tanggal->format('Y-m-d')
                : (string) $pertemuan->tanggal,
            'materi' => (string) ($pertemuan->materi ?: 'Perkuliahan LMS'),
            'kode_mk' => (string) ($mataKuliah?->kode_mk ?? ''),
            'nama_makul' => (string) ($mataKuliah?->nama ?? ''),
            'nm_kls' => (string) ($kelas?->nama_kelas ?? ''),
            'id_smt' => (string) ($tahunAkademik?->kode ?? ''),
            'id_prodi' => (string) ($programStudi?->kode_prodi ?? ''),
            'kegiatan' => (string) ($pertemuan->catatan_pertemuan ?: 'Perkuliahan LMS'),
            'ruang' => (string) ($kelas?->ruangan?->nama ?? $this->ruangDefault),
        ];
    }

    /**
     * Ambil identitas dosen pengampu yang dikirim sebagai `id_ptk`.
     *
     * RAG tidak menyimpan UUID registrasi seperti donor, sehingga sumber
     * identitas diatur lewat config `presensi.id_ptk_source` (nidn | nip).
     */
    protected function resolveIdPtk(Pertemuan $pertemuan): string
    {
        $pengampu = DosenPengampu::query()
            ->where('kelas_id', $pertemuan->kelas_id)
            ->orderByRaw("CASE WHEN peran = ? THEN 0 ELSE 1 END", ['pengampu_utama'])
            ->first();

        $dosen = $pengampu?->dosen;

        if (! $dosen) {
            return '';
        }

        $column = $this->idPtkSource === 'nip' ? 'nip' : 'nidn';

        return (string) ($dosen->{$column} ?? '');
    }

    /**
     * Validasi cepat sebelum push.
     */
    protected function validateMeta(array $meta): ?string
    {
        if (($meta['xid_kls'] ?? '') === '') {
            return 'kelas belum memiliki kode kelas yang dapat dipetakan ke PresensiV2.';
        }

        if (($meta['id_ptk'] ?? '') === '') {
            return 'identitas dosen pengampu (id_ptk) tidak ditemukan.';
        }

        if (($meta['pertemuan_ke'] ?? 0) < 1) {
            return 'nomor pertemuan tidak valid.';
        }

        if (($meta['tanggal'] ?? '') === '') {
            return 'tanggal pertemuan kosong.';
        }

        return null;
    }

    protected function normalizeDate(mixed $value): ?string
    {
        if (empty($value)) {
            return null;
        }

        try {
            return \Carbon\Carbon::parse($value)->toDateString();
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function dbConfigured(): bool
    {
        try {
            return (bool) config('lms_integration.presensi.db_configured', false);
        } catch (\Throwable $e) {
            return false;
        }
    }

    protected function dbReachable(): bool
    {
        try {
            DB::connection($this->dbConnection)->select('SELECT 1');

            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    protected function apiReachable(): bool
    {
        try {
            $response = Http::withHeaders(['X-Api-Key' => $this->apiKey])
                ->timeout(min($this->timeout, 5))
                ->get($this->apiBase().'/api/sync.php');

            return $response->successful() || $response->status() < 500;
        } catch (\Throwable $e) {
            return false;
        }
    }

    protected function markJurnalResult(Pertemuan $pertemuan, array $result): void
    {
        $success = (bool) ($result['success'] ?? false);

        $pertemuan->forceFill([
            'id_jurnal_presensiv2' => $result['id_jurnal'] ?? $pertemuan->id_jurnal_presensiv2,
            'sync_status' => $success ? Pertemuan::SYNC_SYNCED : Pertemuan::SYNC_FAILED,
            'sync_error' => $success ? null : ($result['error'] ?? $result['message'] ?? null),
            'synced_at' => $success ? now() : $pertemuan->synced_at,
        ])->save();

        $this->log(
            LmsPresensiSyncLog::ENTITAS_PERTEMUAN,
            $pertemuan->id,
            LmsPresensiSyncLog::AKSI_BUKA_JURNAL,
            $success ? LmsPresensiSyncLog::STATUS_SUCCESS : LmsPresensiSyncLog::STATUS_FAILED,
            $result['via'] ?? LmsPresensiSyncLog::VIA_NONE,
            $result['message'] ?? null,
            [
                'kelas_id' => $pertemuan->kelas_id,
                'pertemuan_ke' => $pertemuan->pertemuan_ke,
                'id_jurnal_presensiv2' => $result['id_jurnal'] ?? null,
                'error' => $result['error'] ?? null,
            ]
        );
    }

    protected function markAbsensiResult(AbsensiMahasiswa $absensi, array $result): void
    {
        $success = (bool) ($result['success'] ?? false);

        $absensi->forceFill([
            'sync_status' => $success ? AbsensiMahasiswa::SYNC_SYNCED : AbsensiMahasiswa::SYNC_FAILED,
            'sync_error' => $success ? null : ($result['error'] ?? $result['message'] ?? null),
            'synced_at' => $success ? now() : $absensi->synced_at,
        ])->save();

        $this->log(
            LmsPresensiSyncLog::ENTITAS_ABSENSI,
            $absensi->id,
            $result['aksi'] ?? LmsPresensiSyncLog::AKSI_HADIR,
            $success ? LmsPresensiSyncLog::STATUS_SUCCESS : LmsPresensiSyncLog::STATUS_FAILED,
            $result['via'] ?? LmsPresensiSyncLog::VIA_NONE,
            $result['message'] ?? null,
            [
                'pertemuan_id' => $absensi->pertemuan_id,
                'mahasiswa_id' => $absensi->mahasiswa_id,
                'error' => $result['error'] ?? null,
            ]
        );
    }

    /**
     * Tulis jejak sinkronisasi ke `lms_presensi_sync_log`.
     *
     * @param  array<string, mixed>|null  $payload
     */
    protected function log(
        string $entitas,
        int $entitasId,
        string $aksi,
        string $status,
        string $via,
        ?string $message = null,
        ?array $payload = null
    ): void {
        try {
            LmsPresensiSyncLog::create([
                'entitas' => $entitas,
                'entitas_id' => $entitasId,
                'kelas_id' => $payload['kelas_id'] ?? null,
                'pertemuan_ke' => $payload['pertemuan_ke'] ?? null,
                'aksi' => $aksi,
                'status' => $status,
                'via' => $via,
                'message' => $message,
                'payload' => $payload,
                'user_id' => auth()->id(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Gagal tulis lms_presensi_sync_log: '.$e->getMessage());
        }
    }

    /**
     * Hasil "skipped" — integrasi tidak dikonfigurasi, data lokal tidak diubah.
     *
     * @return array<string, mixed>
     */
    protected function skipped(string $message): array
    {
        return [
            'success' => true,
            'skipped' => true,
            'message' => $message,
            'via' => LmsPresensiSyncLog::VIA_NONE,
            'synced' => 0,
            'failed' => 0,
            'deleted' => 0,
        ];
    }
}
