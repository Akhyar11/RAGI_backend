<?php

namespace App\Services\Siakad;

use App\Models\Siakad\AbsensiMahasiswa;
use App\Models\Siakad\Kelas;
use App\Models\Siakad\Pertemuan;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Menyediakan monitoring dan rekap presensi yang tersimpan di RAG/SIAKAD.
 */
class LmsPresensiService
{
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
            ])
            ->addSelect([
                'jumlah_rekap' => AbsensiMahasiswa::query()
                    ->selectRaw('COUNT(*)')
                    ->where('siakad_absensi_mahasiswa.is_submitted', true)
                    ->whereIn('siakad_absensi_mahasiswa.pertemuan_id', function ($sub) {
                        $sub->select('id')
                            ->from('siakad_pertemuan')
                            ->whereColumn('siakad_pertemuan.kelas_id', 'siakad_kelas.id');
                    }),
                'jumlah_hadir' => AbsensiMahasiswa::query()
                    ->selectRaw('COUNT(*)')
                    ->where('siakad_absensi_mahasiswa.is_submitted', true)
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
     * Rekap kehadiran (satu baris per mahasiswa per pertemuan).
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
            ->where('siakad_absensi_mahasiswa.is_submitted', true)
            ->select([
                'siakad_absensi_mahasiswa.id',
                'siakad_absensi_mahasiswa.status',
                'siakad_absensi_mahasiswa.catatan',
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
     | Monitoring & Rekap Keaktifan Mengajar Dosen
     | --------------------------------------------------------------------- */

    /**
     * Rekap keaktifan mengajar dosen per kelas, termasuk realisasi pertemuan dan status pengisian presensi.
     *
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, object>
     */
    public function monitoringKeaktifanDosen(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = Kelas::query()
            ->with([
                'mataKuliah:id,kode_mk,nama',
                'programStudi:id,kode_prodi,nama',
                'tahunAkademik:id,kode,nama',
                'dosenPengampu.dosen' => fn ($q) => $q->select('id', 'nama_lengkap', 'nidn', 'nip'),
                'pertemuans' => fn ($q) => $q->select('id', 'kelas_id', 'pertemuan_ke', 'tanggal', 'status_pertemuan', 'materi')
                    ->withCount([
                        'absensi as absensi_count' => fn ($absensi) => $absensi
                            ->where('is_submitted', true)
                            ->whereHas('mahasiswa.krs.krsDetails', fn ($details) => $details
                                ->whereColumn('siakad_krs_detail.kelas_id', 'siakad_pertemuan.kelas_id')
                                ->whereHas('krs', fn ($krs) => $krs->whereIn('status', ['disetujui', 'diajukan']))),
                    ]),
            ])
            ->withCount([
                'pertemuans as total_pertemuan',
                'pertemuans as pertemuan_terisi' => fn ($q) => $q->whereHas(
                    'absensi',
                    fn ($absensi) => $absensi->where('is_submitted', true)
                ),
                'pertemuans as pertemuan_selesai' => fn ($q) => $q->where('status_pertemuan', Pertemuan::STATUS_SELESAI),
            ]);

        if (! empty($filters['tahun_akademik_id'])) {
            $query->where('tahun_akademik_id', (int) $filters['tahun_akademik_id']);
        }

        if (! empty($filters['program_studi_id'])) {
            $query->where('program_studi_id', (int) $filters['program_studi_id']);
        }

        if (! empty($filters['search'])) {
            $search = (string) $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('kode_kelas', 'like', "%{$search}%")
                    ->orWhere('nama_kelas', 'like', "%{$search}%")
                    ->orWhereHas('mataKuliah', fn ($mq) => $mq->where('nama', 'like', "%{$search}%")->orWhere('kode_mk', 'like', "%{$search}%"))
                    ->orWhereHas('dosenPengampu.dosen', fn ($dq) => $dq->where('nama_lengkap', 'like', "%{$search}%")->orWhere('nidn', 'like', "%{$search}%"));
            });
        }

        return $query
            ->orderBy('kode_kelas')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Format data export keaktifan dosen mengajar.
     *
     * @param  array<string, mixed>  $filters
     * @return array{headers: array<int, string>, rows: array<int, array<int, mixed>>, filename: string}
     */
    public function barisKeaktifanDosen(array $filters): array
    {
        $paginator = $this->monitoringKeaktifanDosen($filters, 1000);
        $headers = [
            'Kode Kelas',
            'Nama Kelas',
            'Program Studi',
            'Mata Kuliah',
            'Dosen Pengampu',
            'NIDN Dosen',
            'Rencana Pertemuan',
            'Realisasi Pertemuan',
            'Pertemuan Ada Presensi',
            'Pertemuan Selesai',
            '% Keaktifan Presensi',
            'Status Keaktifan',
        ];

        $rows = [];
        foreach ($paginator->items() as $k) {
            $dosenNames = [];
            $dosenNidns = [];
            foreach ($k->dosenPengampu as $dp) {
                if ($dp->dosen) {
                    $dosenNames[] = $dp->dosen->nama_lengkap;
                    if ($dp->dosen->nidn) {
                        $dosenNidns[] = $dp->dosen->nidn;
                    }
                }
            }

            $total = (int) ($k->total_pertemuan ?? 0);
            $terisi = (int) ($k->pertemuan_terisi ?? 0);
            $selesai = (int) ($k->pertemuan_selesai ?? 0);
            $persen = $total > 0 ? round(($terisi / $total) * 100, 1) : 0;
            $status = $persen >= 80 ? 'Sangat Aktif' : ($persen >= 50 ? 'Cukup Aktif' : ($terisi > 0 ? 'Perlu Perhatian' : 'Belum Ada Presensi'));

            $rows[] = [
                $k->kode_kelas ?? '-',
                $k->nama_kelas ?? '-',
                $k->programStudi?->nama ?? '-',
                $k->mataKuliah?->nama ?? '-',
                implode(', ', $dosenNames) ?: 'Belum diplot',
                implode(', ', $dosenNidns) ?: '-',
                16, // Standar semester
                $total,
                $terisi,
                $selesai,
                "{$persen}%",
                $status,
            ];
        }

        return [
            'headers'  => $headers,
            'rows'     => $rows,
            'filename' => 'Laporan_Keaktifan_Mengajar_Dosen_' . now()->format('Ymd_His'),
        ];
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

}
