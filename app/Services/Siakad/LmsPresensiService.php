<?php

namespace App\Services\Siakad;

use App\Models\Lms\LmsPresensiSyncLog;
use App\Models\Lms\LmsSetting;
use App\Models\Siakad\AbsensiMahasiswa;
use App\Models\Siakad\Kelas;
use App\Models\Siakad\Pertemuan;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Service.layer untuk panel administrasi presensi LMS (LMS-03).
 *
 * Menyediakan data rekap presensi lintas kelas + orkestrasi sinkronisasi
 * ke aplikasi PresensiV2. Logika transport ke PresensiV2 sendiri berada di
 * {@see PresensiV2SyncService}.
 */
class LmsPresensiService
{
    public function __construct(protected PresensiV2SyncService $sync) {}

    /* ---------------------------------------------------------------------
     | Status integrasi
     | --------------------------------------------------------------------- */

    /**
     * Ringkasan status integrasi PresensiV2 + feature flag.
     *
     * @return array<string, mixed>
     */
    public function status(): array
    {
        $status = $this->sync->status();

        $status['fitur_presensi_aktif'] = LmsSetting::fiturPresensiAktif();
        $status['total_pertemuan'] = Pertemuan::query()->count();
        $status['total_gagal_sync'] = Pertemuan::query()
            ->where('sync_status', Pertemuan::SYNC_FAILED)
            ->count();
        $status['total_tersinkron'] = Pertemuan::query()
            ->where('sync_status', Pertemuan::SYNC_SYNCED)
            ->count();
        $status['sinkron_terakhir'] = LmsPresensiSyncLog::query()
            ->latest('id')
            ->first(['aksi', 'status', 'via', 'message', 'created_at']);

        return $status;
    }

    /* ---------------------------------------------------------------------
     | Daftar kelas
     | --------------------------------------------------------------------- */

    /**
     * Daftar kelas yang memiliki sesi presensi beserta ringkasan absensinya.
     *
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, object>
     */
    public function daftarKelas(array $filters, int $perPage, string $sortBy, string $sortOrder): LengthAwarePaginator
    {
        $query = Kelas::query()
            ->with(['mataKuliah', 'programStudi'])
            ->whereHas('pertemuans')
            ->withCount([
                'pertemuans as jumlah_sesi',
                'pertemuans as jumlah_gagal_sync' => fn ($q) => $q->where('sync_status', Pertemuan::SYNC_FAILED),
                'pertemuans as jumlah_tersinkron' => fn ($q) => $q->where('sync_status', Pertemuan::SYNC_SYNCED),
            ])
            ->addSelect([
                'jumlah_rekap' => AbsensiMahasiswa::query()
                    ->selectRaw('COUNT(*)')
                    ->whereIn('siakad_absensi_mahasiswa.pertemuan_id', function ($sub) {
                        $sub->select('id')
                            ->from('siakad_pertemuan')
                            ->whereColumn('siakad_pertemuan.kelas_id', 'siakad_kelas.id');
                    }),
                'jumlah_hadir' => AbsensiMahasiswa::query()
                    ->selectRaw('COUNT(*)')
                    ->where('siakad_absensi_mahasiswa.status', AbsensiMahasiswa::STATUS_HADIR)
                    ->whereIn('siakad_absensi_mahasiswa.pertemuan_id', function ($sub) {
                        $sub->select('id')
                            ->from('siakad_pertemuan')
                            ->whereColumn('siakad_pertemuan.kelas_id', 'siakad_kelas.id');
                    }),
            ]);

        if (! empty($filters['search'])) {
            $search = (string) $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('kode_kelas', 'like', "%{$search}%")
                    ->orWhere('nama_kelas', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['tahun_akademik_id'])) {
            $query->where('tahun_akademik_id', (int) $filters['tahun_akademik_id']);
        }

        $allowedSorts = ['kode_kelas', 'nama_kelas', 'jumlah_sesi', 'created_at'];
        $sortBy = in_array($sortBy, $allowedSorts, true) ? $sortBy : 'kode_kelas';
        $sortOrder = $sortOrder === 'asc' ? 'asc' : 'desc';

        return $query
            ->orderBy($sortBy, $sortOrder)
            ->orderBy('id', 'asc')
            ->paginate($perPage)
            ->withQueryString();
    }

    /* ---------------------------------------------------------------------
     | Rekap kehadiran
     | --------------------------------------------------------------------- */

    /**
     * Rekap kehadiranflatten (satu baris per mahasiswa per pertemuan).
     *
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, object>
     */
    public function rekap(array $filters, int $perPage): LengthAwarePaginator
    {
        $query = $this->baseRekapQuery();

        $this->applyRekapFilters($query, $filters);

        return $query
            ->orderByDesc('siakad_pertemuan.tanggal')
            ->orderByDesc('siakad_pertemuan.pertemuan_ke')
            ->orderBy('siakad_mahasiswa.nim')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Baris rekap untuk keperluan export (tanpa pagination).
     *
     * @param  array<string, mixed>  $filters
     * @return array{headers: array<int, string>, rows: array<int, array<int, mixed>>, filename: string}
     */
    public function barisRekap(array $filters): array
    {
        $query = $this->baseRekapQuery();
        $this->applyRekapFilters($query, $filters);

        $headers = [
            'Kode Kelas',
            'Nama Kelas',
            'Mata Kuliah',
            'Pertemuan Ke',
            'Tanggal',
            'Topik Pertemuan',
            'NIM',
            'Nama Mahasiswa',
            'Status Kehadiran',
            'Catatan',
            'Status Sinkron',
            'Sinkron Terakhir',
        ];

        $rows = [];
        $query->orderBy('siakad_kelas.kode_kelas')
            ->orderBy('siakad_pertemuan.pertemuan_ke')
            ->orderBy('siakad_mahasiswa.nim')
            ->chunk(500, function ($chunk) use (&$rows, $headers) {
                foreach ($chunk as $row) {
                    $rows[] = [
                        $row->kode_kelas ?? '-',
                        $row->nama_kelas ?? '-',
                        $row->nama_mk ?? '-',
                        $row->pertemuan_ke ?? '-',
                        $row->tanggal ? Carbon::parse($row->tanggal)->toDateString() : '-',
                        $row->materi ?? '-',
                        $row->nim ?? '-',
                        $row->nama_lengkap ?? '-',
                        $this->labelStatus((string) $row->status),
                        $row->catatan ?? '-',
                        $this->labelSyncStatus((string) $row->sync_status),
                        $row->synced_at ? Carbon::parse($row->synced_at)->format('Y-m-d H:i') : '-',
                    ];
                }
            });

        return [
            'headers'  => $headers,
            'rows'     => $rows,
            'filename' => 'Rekap_Presensi_LMS_' . now()->format('Ymd_His'),
        ];
    }

    /**
     * Query dasar rekap absensi (join pertemuan, kelas, mahasiswa).
     */
    protected function baseRekapQuery()
    {
        return AbsensiMahasiswa::query()
            ->from('siakad_absensi_mahasiswa')
            ->join('siakad_pertemuan', 'siakad_pertemuan.id', '=', 'siakad_absensi_mahasiswa.pertemuan_id')
            ->join('siakad_kelas', 'siakad_kelas.id', '=', 'siakad_pertemuan.kelas_id')
            ->leftJoin('siakad_mata_kuliah', 'siakad_mata_kuliah.id', '=', 'siakad_kelas.mata_kuliah_id')
            ->join('siakad_mahasiswa', 'siakad_mahasiswa.id', '=', 'siakad_absensi_mahasiswa.mahasiswa_id')
            ->whereNull('siakad_mahasiswa.deleted_at')
            ->select([
                'siakad_absensi_mahasiswa.id',
                'siakad_absensi_mahasiswa.status',
                'siakad_absensi_mahasiswa.catatan',
                'siakad_absensi_mahasiswa.sync_status',
                'siakad_absensi_mahasiswa.synced_at',
                'siakad_pertemuan.id as pertemuan_id',
                'siakad_pertemuan.pertemuan_ke',
                'siakad_pertemuan.tanggal',
                'siakad_pertemuan.materi',
                'siakad_pertemuan.kelas_id',
                'siakad_kelas.kode_kelas',
                'siakad_kelas.nama_kelas',
                'siakad_mata_kuliah.nama as nama_mk',
                'siakad_mahasiswa.nim',
                'siakad_mahasiswa.nama_lengkap',
            ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    protected function applyRekapFilters($query, array $filters): void
    {
        if (! empty($filters['kelas_id'])) {
            $query->where('siakad_pertemuan.kelas_id', (int) $filters['kelas_id']);
        }

        if (! empty($filters['pertemuan_ke'])) {
            $query->where('siakad_pertemuan.pertemuan_ke', (int) $filters['pertemuan_ke']);
        }

        if (! empty($filters['status'])) {
            $query->where('siakad_absensi_mahasiswa.status', (string) $filters['status']);
        }

        if (! empty($filters['sync_status'])) {
            $query->where('siakad_absensi_mahasiswa.sync_status', (string) $filters['sync_status']);
        }

        if (! empty($filters['search'])) {
            $search = (string) $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('siakad_mahasiswa.nim', 'like', "%{$search}%")
                    ->orWhere('siakad_mahasiswa.nama_lengkap', 'like', "%{$search}%")
                    ->orWhere('siakad_pertemuan.materi', 'like', "%{$search}%");
            });
        }
    }

    /* ---------------------------------------------------------------------
     | Log sinkronisasi
     | --------------------------------------------------------------------- */

    /**
     * @return LengthAwarePaginator<int, LmsPresensiSyncLog>
     */
    public function logSinkronisasi(int $perPage, ?string $status, ?int $kelasId): LengthAwarePaginator
    {
        $query = LmsPresensiSyncLog::query()->with('user:id,name,username');

        if ($status) {
            $query->where('status', $status);
        }

        if ($kelasId) {
            $query->where('kelas_id', $kelasId);
        }

        return $query->orderByDesc('id')->paginate($perPage)->withQueryString();
    }

    /* ---------------------------------------------------------------------
     | Sinkronisasi
     | --------------------------------------------------------------------- */

    /**
     * Sinkronkan ulang satu pertemuan atau seluruh pertemuan sebuah kelas.
     *
     * @return array<string, mixed>
     */
    public function syncUlang(?int $pertemuanId, ?int $kelasId): array
    {
        if (! $this->sync->isConfigured()) {
            return [
                'success' => false,
                'message' => 'Integrasi PresensiV2 belum dikonfigurasi.',
                'pertemuan' => [],
            ];
        }

        if ($pertemuanId) {
            $pertemuan = Pertemuan::with('kelas')->findOrFail($pertemuanId);
            $result = $this->sync->syncKehadiran($pertemuan);

            return [
                'success'     => (bool) ($result['success'] ?? false),
                'message'     => $result['message'] ?? 'Sinkronisasi selesai.',
                'pertemuan'  => [$pertemuan->id => $result],
            ];
        }

        $pertemuans = Pertemuan::with('kelas')
            ->where('kelas_id', $kelasId)
            ->orderBy('pertemuan_ke')
            ->get();

        $hasil = [];
        $sukses = 0;
        $gagal = 0;

        foreach ($pertemuans as $pertemuan) {
            $result = $this->sync->syncKehadiran($pertemuan);
            $hasil[$pertemuan->id] = $result;

            if ($result['success'] ?? false) {
                $sukses++;
            } else {
                $gagal++;
            }
        }

        return [
            'success'    => $gagal === 0,
            'message'    => "Sinkronisasi selesai: {$sukses} berhasil, {$gagal} gagal.",
            'pertemuan' => $hasil,
        ];
    }

    /**
     * Tarik riwayat jurnal + rekap dari PresensiV2 ke LMS untuk satu kelas.
     *
     * @return array<string, mixed>
     */
    public function tarikRiwayat(int $kelasId): array
    {
        $kelas = Kelas::with('mataKuliah', 'tahunAkademik', 'programStudi')->findOrFail($kelasId);

        return $this->sync->tarikRiwayat($kelas);
    }

    /* ---------------------------------------------------------------------
     | Label
     | --------------------------------------------------------------------- */

    protected function labelStatus(string $status): string
    {
        return match ($status) {
            AbsensiMahasiswa::STATUS_HADIR => 'Hadir',
            AbsensiMahasiswa::STATUS_SAKIT => 'Sakit',
            AbsensiMahasiswa::STATUS_IZIN  => 'Izin',
            AbsensiMahasiswa::STATUS_ALFA  => 'Alfa',
            default                        => $status === '' ? '-' : $status,
        };
    }

    protected function labelSyncStatus(string $status): string
    {
        return match ($status) {
            AbsensiMahasiswa::SYNC_LOCAL   => 'Hanya di LMS',
            AbsensiMahasiswa::SYNC_PENDING => 'Menunggu Sinkron',
            AbsensiMahasiswa::SYNC_SYNCED  => 'Tersinkron',
            AbsensiMahasiswa::SYNC_FAILED  => 'Gagal Sinkron',
            default                        => $status === '' ? '-' : $status,
        };
    }
}
