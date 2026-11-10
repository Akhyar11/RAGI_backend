<?php

namespace App\Services\Simpeg;

use App\Models\Siakad\Dosen;
use App\Models\Siakad\DosenPengampu;
use App\Models\Siakad\KrsDetail;
use App\Models\Siakad\Pertemuan;
use App\Models\Simpeg\PresensiPengajaranCutoff;
use App\Models\Simpeg\PresensiPengajaranException;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PresensiPengajaranService
{
    /**
     * @param array{periode:string,search?:string,sort_by?:string,sort_order?:string,per_page?:int} $filters
     */
    public function report(array $filters): LengthAwarePaginator
    {
        $period = Carbon::createFromFormat('Y-m', $filters['periode'])->startOfMonth();
        $start = $period->toDateString();
        $end = $period->copy()->endOfMonth()->toDateString();
        $search = trim((string) ($filters['search'] ?? ''));

        $query = Dosen::query()
            ->whereHas('kelasPengampu.kelas.pertemuans', fn ($meeting) => $meeting->whereBetween('tanggal', [$start, $end]))
            ->with([
                'user:id,name',
                'kelasPengampu' => fn ($assignments) => $assignments
                    ->whereHas('kelas.pertemuans', fn ($meeting) => $meeting->whereBetween('tanggal', [$start, $end]))
                    ->with(['kelas' => fn ($kelas) => $kelas
                        ->select('id', 'kode_kelas', 'nama_kelas', 'mata_kuliah_id')
                        ->addSelect([
                            'total_mahasiswa' => KrsDetail::query()
                                ->join('siakad_krs', 'siakad_krs.id', '=', 'siakad_krs_detail.krs_id')
                                ->selectRaw('COUNT(DISTINCT siakad_krs.mahasiswa_id)')
                                ->whereColumn('siakad_krs_detail.kelas_id', 'siakad_kelas.id')
                                ->whereIn('siakad_krs.status', ['disetujui', 'diajukan'])
                                ->whereNull('siakad_krs.deleted_at'),
                        ])
                        ->with([
                            'mataKuliah:id,nama,kode_mk',
                            'pertemuans' => fn ($meetings) => $meetings
                            ->whereBetween('tanggal', [$start, $end])
                            ->withCount([
                                'absensi as absensi_count' => fn ($absensi) => $absensi->where('is_submitted', true),
                            ])
                            ->orderBy('tanggal')
                            ->orderBy('pertemuan_ke'),
                        ]),
                    ]),
            ]);

        if ($search !== '') {
            $query->where(function ($dosen) use ($search) {
                $dosen->where('nama_lengkap', 'like', "%{$search}%")
                    ->orWhere('nidn', 'like', "%{$search}%")
                    ->orWhereHas('kelasPengampu.kelas', fn ($kelas) => $kelas
                        ->where('kode_kelas', 'like', "%{$search}%")
                        ->orWhere('nama_kelas', 'like', "%{$search}%")
                        ->orWhereHas('mataKuliah', fn ($mk) => $mk
                            ->where('nama', 'like', "%{$search}%")
                            ->orWhere('kode_mk', 'like', "%{$search}%")));
            });
        }

        $sortBy = in_array($filters['sort_by'] ?? '', ['nama_lengkap', 'nidn'], true)
            ? $filters['sort_by']
            : 'nama_lengkap';
        $sortOrder = ($filters['sort_order'] ?? 'asc') === 'desc' ? 'desc' : 'asc';
        $paginator = $query->orderBy($sortBy, $sortOrder)
            ->paginate(min(100, max(1, (int) ($filters['per_page'] ?? 15))));

        $paginator->getCollection()->transform(function (Dosen $dosen) {
            $kelas = $dosen->kelasPengampu
                ->map(function (DosenPengampu $assignment) {
                    $kelas = $assignment->kelas;
                    if (!$kelas) {
                        return null;
                    }

                    $totalMahasiswa = (int) $kelas->total_mahasiswa;

                    return [
                        'kelas_id' => $kelas->id,
                        'kode_kelas' => $kelas->kode_kelas,
                        'nama_kelas' => $kelas->nama_kelas,
                        'mata_kuliah' => $kelas->mataKuliah?->nama,
                        'total_mahasiswa' => $totalMahasiswa,
                        'pertemuans' => $kelas->pertemuans->map(fn (Pertemuan $meeting) => [
                            'id' => $meeting->id,
                            'pertemuan_ke' => $meeting->pertemuan_ke,
                            'tanggal' => $meeting->tanggal,
                            'status_pertemuan' => $meeting->status_pertemuan,
                            'total_absensi' => (int) $meeting->absensi_count,
                            'total_mahasiswa' => $totalMahasiswa,
                            'status_presensi' => $meeting->status_pertemuan !== Pertemuan::STATUS_SELESAI
                                ? 'belum_terlaksana'
                                : ($totalMahasiswa === 0
                                    ? 'tanpa_peserta'
                                    : ($meeting->absensi_count >= $totalMahasiswa ? 'terisi' : 'belum_diisi')),
                        ])->values(),
                    ];
                })
                ->filter()
                ->values();

            $meetings = $kelas->flatMap(fn (array $class) => $class['pertemuans']);
            $completedMeetings = $meetings->where('status_pertemuan', Pertemuan::STATUS_SELESAI);
            $missingAttendance = $completedMeetings->where('status_presensi', 'belum_diisi')->count();

            return [
                'dosen_id' => $dosen->id,
                'user_id' => $dosen->user_id,
                'nama_dosen' => $dosen->nama_gelar,
                'nidn' => $dosen->nidn,
                'kelas' => $kelas,
                'total_pertemuan' => $meetings->count(),
                'pertemuan_terlaksana' => $completedMeetings->count(),
                'presensi_terisi' => $completedMeetings->where('status_presensi', 'terisi')->count(),
                'presensi_belum_diisi' => $missingAttendance,
                'status_pemenuhan' => $missingAttendance === 0 && $completedMeetings->isNotEmpty()
                    ? 'lengkap'
                    : 'perlu_dilengkapi',
            ];
        });

        return $paginator;
    }

    public function cutoff(string $period): ?PresensiPengajaranCutoff
    {
        return PresensiPengajaranCutoff::query()->where('periode', $period)->first();
    }

    public function isPeriodClosed(string $period): bool
    {
        $cutoff = $this->cutoff($period);

        return $cutoff !== null && now()->gte($cutoff->cutoff_at);
    }

    public function saveCutoff(string $period, string $cutoffAt, int $userId): PresensiPengajaranCutoff
    {
        return PresensiPengajaranCutoff::query()->updateOrCreate(
            ['periode' => $period],
            ['cutoff_at' => $cutoffAt, 'set_by' => $userId]
        );
    }

    public function exceptions(string $period, array $filters = []): LengthAwarePaginator
    {
        $query = PresensiPengajaranException::query()
            ->where('periode', $period)
            ->with(['dosen:id,nama_lengkap,nidn,user_id', 'grantor:id,name', 'revoker:id,name']);

        if (!empty($filters['search'])) {
            $search = trim($filters['search']);
            $query->whereHas('dosen', fn ($dosen) => $dosen
                ->where('nama_lengkap', 'like', "%{$search}%")
                ->orWhere('nidn', 'like', "%{$search}%"));
        }

        $query->orderByDesc('created_at');
        $paginator = $query->paginate(min(100, max(1, (int) ($filters['per_page'] ?? 15))));
        $paginator->getCollection()->transform(function (PresensiPengajaranException $exception) {
            $exception->setAttribute(
                'is_active',
                $exception->revoked_at === null && $exception->valid_until->isFuture()
            );
            return $exception;
        });

        return $paginator;
    }

    public function grantException(array $data, int $adminId): PresensiPengajaranException
    {
        return DB::transaction(function () use ($data, $adminId) {
            $dosen = Dosen::query()
                ->whereKey($data['dosen_id'])
                ->whereNotNull('user_id')
                ->lockForUpdate()
                ->firstOrFail();

            $existing = PresensiPengajaranException::query()
                ->where('dosen_id', $dosen->id)
                ->where('periode', $data['periode'])
                ->whereNull('revoked_at')
                ->where('valid_until', '>', now())
                ->exists();
            if ($existing) {
                throw ValidationException::withMessages([
                    'dosen_id' => ['Dosen sudah memiliki izin aktif untuk periode tersebut.'],
                ]);
            }

            return PresensiPengajaranException::query()->create([
                'dosen_id' => $dosen->id,
                'periode' => $data['periode'],
                'valid_until' => $data['valid_until'],
                'reason' => $data['reason'],
                'granted_by' => $adminId,
            ]);
        });
    }

    public function revokeException(PresensiPengajaranException $exception, string $reason, int $adminId): PresensiPengajaranException
    {
        if ($exception->revoked_at !== null) {
            throw ValidationException::withMessages([
                'exception' => ['Izin khusus ini sudah dicabut.'],
            ]);
        }

        $exception->forceFill([
            'revoked_at' => now(),
            'revoked_by' => $adminId,
            'revocation_reason' => $reason,
        ])->save();

        return $exception->fresh(['dosen:id,nama_lengkap,nidn,user_id', 'grantor:id,name', 'revoker:id,name']);
    }

    public function assertWriteAllowed(Pertemuan $meeting, int $userId): void
    {
        $period = Carbon::parse($meeting->tanggal)->format('Y-m');
        $cutoff = $this->cutoff($period);
        if (!$cutoff || now()->lt($cutoff->cutoff_at)) {
            return;
        }

        $assignedDosenIds = Dosen::query()
            ->where('user_id', $userId)
            ->whereHas('kelasPengampu', fn ($assignment) => $assignment->where('kelas_id', $meeting->kelas_id))
            ->pluck('id');

        $hasActiveException = $assignedDosenIds->isNotEmpty()
            && PresensiPengajaranException::query()
                ->whereIn('dosen_id', $assignedDosenIds)
                ->where('periode', $period)
                ->whereNull('revoked_at')
                ->where('valid_until', '>', now())
                ->exists();

        if (!$hasActiveException) {
            throw ValidationException::withMessages([
                'presensi' => ['Batas waktu pengisian presensi periode ini telah berakhir. Hubungi Admin SIMPEG jika memerlukan izin khusus.'],
            ]);
        }
    }
}
