<?php

namespace App\Services\Siakad;

use App\Models\Siakad\Dosen;
use App\Models\Siakad\DosenPengampu;
use App\Models\Siakad\KetercapaianCpmkMahasiswa;
use App\Models\Siakad\Khs;
use App\Models\Siakad\KoreksiNilaiMahasiswa;
use App\Models\Siakad\KrsDetail;
use App\Models\Siakad\NilaiKomponenMahasiswa;
use App\Models\Siakad\NilaiMahasiswa;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class NilaiKoreksiService
{
    public function __construct(
        protected NilaiKomponenSyncService $nilaiKomponenSync,
        protected SiakadAkademikService $akademik
    ) {}

    public function paginate(
        User $user,
        int $perPage,
        ?string $status,
        ?string $search,
        string $sortBy,
        string $sortOrder
    ): LengthAwarePaginator
    {
        $query = KoreksiNilaiMahasiswa::query()
            ->with([
                'krsDetail.krs.mahasiswa:id,nim,nama_lengkap',
                'krsDetail.kelas.mataKuliah:id,kode_mk,nama,kurikulum_id',
                'nilaiKomponenMahasiswa.komponenPenilaian:id,nama_komponen',
                'ketercapaianCpmkMahasiswa.cpmk:id,kode_cpmk,deskripsi',
                'diajukanOleh:id,name,username',
                'diputuskanOleh:id,name,username',
            ]);

        if ($this->canReview($user)) {
            $this->scopeReviewerQuery($query, $user);
        } else {
            $query->where('diajukan_oleh', $user->id);
        }

        if ($status && in_array($status, [
            KoreksiNilaiMahasiswa::STATUS_MENUNGGU,
            KoreksiNilaiMahasiswa::STATUS_DISETUJUI,
            KoreksiNilaiMahasiswa::STATUS_DITOLAK,
        ], true)) {
            $query->where('status', $status);
        }

        if ($search !== null && $search !== '') {
            $query->where(function ($searchQuery) use ($search) {
                $searchQuery->where('alasan_koreksi', 'like', "%{$search}%")
                    ->orWhere('catatan_reviewer', 'like', "%{$search}%")
                    ->orWhereHas('krsDetail.krs.mahasiswa', function ($mahasiswaQuery) use ($search) {
                        $mahasiswaQuery->where('nim', 'like', "%{$search}%")
                            ->orWhere('nama_lengkap', 'like', "%{$search}%");
                    })
                    ->orWhereHas('krsDetail.kelas.mataKuliah', function ($mataKuliahQuery) use ($search) {
                        $mataKuliahQuery->where('kode_mk', 'like', "%{$search}%")
                            ->orWhere('nama', 'like', "%{$search}%");
                    });
            });
        }

        return $query->orderBy($sortBy, $sortOrder)->orderBy('id', $sortOrder)->paginate($perPage);
    }

    public function canReview(User $user): bool
    {
        return $user->hasPermission('siakad.nilai.koreksi.approve')
            || $user->isAdmin()
            || $user->getSiakadProdiIds()->isNotEmpty();
    }

    public function submit(User $user, array $data): KoreksiNilaiMahasiswa
    {
        return DB::transaction(function () use ($user, $data) {
            [$detail, $oldValues, $targetColumn, $targetId] = $this->resolveTarget($data);
            $this->assertAssignedLecturer($user, (int) $detail->kelas_id);
            $this->assertFinalGrade($detail);

            $newValues = $data['nilai_baru'];
            $this->assertChangedValues($oldValues, $newValues);

            $pending = KoreksiNilaiMahasiswa::query()
                ->where($targetColumn, $targetId)
                ->where('status', KoreksiNilaiMahasiswa::STATUS_MENUNGGU)
                ->lockForUpdate()
                ->exists();
            if ($pending) {
                throw ValidationException::withMessages([
                    'nilai_baru' => 'Nilai ini sudah memiliki pengajuan koreksi yang menunggu keputusan.',
                ]);
            }

            return KoreksiNilaiMahasiswa::create([
                'krs_detail_id' => $detail->id,
                'jenis_nilai' => $data['jenis_nilai'],
                $targetColumn => $targetId,
                'nilai_lama' => $oldValues,
                'nilai_baru' => $newValues,
                'alasan_koreksi' => trim($data['alasan_koreksi']),
                'status' => KoreksiNilaiMahasiswa::STATUS_MENUNGGU,
                'diajukan_oleh' => $user->id,
            ]);
        });
    }

    public function decide(User $user, int $id, bool $approve, string $reviewNote): KoreksiNilaiMahasiswa
    {
        return DB::transaction(function () use ($user, $id, $approve, $reviewNote) {
            $correction = KoreksiNilaiMahasiswa::query()
                ->with('krsDetail.krs')
                ->lockForUpdate()
                ->findOrFail($id);

            $this->assertReviewer($user, $correction->krsDetail);
            if ($correction->diajukan_oleh === $user->id) {
                abort(403, 'Pengaju tidak dapat menyetujui atau menolak koreksinya sendiri.');
            }
            if ($correction->status !== KoreksiNilaiMahasiswa::STATUS_MENUNGGU) {
                throw ValidationException::withMessages([
                    'status' => 'Pengajuan koreksi ini sudah diputuskan.',
                ]);
            }

            if ($approve) {
                $this->assertKhsUnlocked($correction->krsDetail);
                $this->applyCorrection($correction, (int) $user->id);
                $this->recalculateKhs($correction->krsDetail);
            }

            $correction->update([
                'status' => $approve
                    ? KoreksiNilaiMahasiswa::STATUS_DISETUJUI
                    : KoreksiNilaiMahasiswa::STATUS_DITOLAK,
                'diputuskan_oleh' => $user->id,
                'diputuskan_at' => now(),
                'catatan_reviewer' => trim($reviewNote),
            ]);

            return $correction->fresh([
                'diajukanOleh:id,name,username',
                'diputuskanOleh:id,name,username',
            ]);
        });
    }

    private function resolveTarget(array $data): array
    {
        return match ($data['jenis_nilai']) {
            KoreksiNilaiMahasiswa::JENIS_KONVENSIONAL => $this->resolveConventionalTarget((int) $data['nilai_mahasiswa_id']),
            KoreksiNilaiMahasiswa::JENIS_KOMPONEN_OBE => $this->resolveComponentTarget((int) $data['nilai_komponen_mahasiswa_id']),
            KoreksiNilaiMahasiswa::JENIS_CPMK_OBE => $this->resolveCpmkTarget((int) $data['ketercapaian_cpmk_mahasiswa_id']),
        };
    }

    private function resolveConventionalTarget(int $id): array
    {
        $grade = NilaiMahasiswa::query()
            ->with('krsDetail.kelas.tahunAkademik')
            ->lockForUpdate()
            ->findOrFail($id);
        if (($grade->krsDetail?->kelas?->tahunAkademik?->mode_penilaian ?? 'konvensional') !== 'konvensional') {
            throw ValidationException::withMessages(['nilai_mahasiswa_id' => 'Nilai ini tidak menggunakan mode konvensional.']);
        }

        $fields = ['nilai_harian', 'nilai_uts', 'nilai_uas', 'nilai_praktik'];
        return [
            $grade->krsDetail,
            array_intersect_key($grade->only($fields), array_flip($fields)),
            'nilai_mahasiswa_id',
            $grade->id,
        ];
    }

    private function resolveComponentTarget(int $id): array
    {
        $score = NilaiKomponenMahasiswa::query()
            ->with('krsDetail.kelas.tahunAkademik')
            ->lockForUpdate()
            ->findOrFail($id);
        if (($score->krsDetail?->kelas?->tahunAkademik?->mode_penilaian ?? 'semi_obe') !== 'semi_obe') {
            throw ValidationException::withMessages(['nilai_komponen_mahasiswa_id' => 'Koreksi komponen hanya tersedia pada mode semi-OBE.']);
        }

        return [
            $score->krsDetail,
            ['nilai_angka' => $score->nilai_angka],
            'nilai_komponen_mahasiswa_id',
            $score->id,
        ];
    }

    private function resolveCpmkTarget(int $id): array
    {
        $score = KetercapaianCpmkMahasiswa::query()
            ->with('krsDetail.kelas.tahunAkademik')
            ->lockForUpdate()
            ->findOrFail($id);
        if (($score->krsDetail?->kelas?->tahunAkademik?->mode_penilaian ?? 'semi_obe') !== 'full_obe') {
            throw ValidationException::withMessages(['ketercapaian_cpmk_mahasiswa_id' => 'Koreksi skor CPMK langsung hanya tersedia pada mode OBE penuh.']);
        }

        return [
            $score->krsDetail,
            ['nilai_angka' => $score->skor_ketercapaian],
            'ketercapaian_cpmk_mahasiswa_id',
            $score->id,
        ];
    }

    private function assertAssignedLecturer(User $user, int $kelasId): void
    {
        if (!$user->hasPermission('siakad.nilai.koreksi.create')) {
            abort(403, 'Anda tidak memiliki izin untuk mengajukan koreksi nilai.');
        }

        $dosenId = Dosen::query()->where('user_id', $user->id)->value('id');
        if (!$dosenId || !DosenPengampu::query()->where('kelas_id', $kelasId)->where('dosen_id', $dosenId)->exists()) {
            abort(403, 'Hanya dosen yang benar-benar ditugaskan pada kelas ini yang dapat mengajukan koreksi.');
        }
    }

    private function assertReviewer(User $user, KrsDetail $detail): void
    {
        if (!$this->canReview($user)) {
            abort(403, 'Anda tidak memiliki izin untuk memutuskan koreksi nilai.');
        }

        $kelas = $detail->kelas()->with('mataKuliah.kurikulum')->first();
        $programStudiId = $kelas?->mataKuliah?->kurikulum?->program_studi_id ?? $kelas?->program_studi_id;
        if (!$programStudiId || (!$user->isAdmin() && !$user->canManageObeForProdi((int) $programStudiId))) {
            abort(403, 'Pengajuan koreksi berada di luar lingkup program studi Anda.');
        }
    }

    private function assertFinalGrade(KrsDetail $detail): void
    {
        $grade = NilaiMahasiswa::query()->where('krs_detail_id', $detail->id)->first();
        if (!$grade || !$grade->is_final) {
            throw ValidationException::withMessages([
                'nilai' => 'Koreksi hanya dapat diajukan untuk nilai akhir yang sudah dipublikasikan.',
            ]);
        }
    }

    private function assertChangedValues(array $oldValues, array $newValues): void
    {
        foreach ($newValues as $key => $value) {
            if (array_key_exists($key, $oldValues) && (float) $oldValues[$key] === (float) $value) {
                throw ValidationException::withMessages([
                    "nilai_baru.{$key}" => 'Nilai baru harus berbeda dari nilai saat ini.',
                ]);
            }
        }
    }

    private function assertKhsUnlocked(KrsDetail $detail): void
    {
        $detail->loadMissing('krs');
        $krs = $detail->krs;
        if ($krs && Khs::query()
            ->where('mahasiswa_id', $krs->mahasiswa_id)
            ->where('tahun_akademik_id', $krs->tahun_akademik_id)
            ->where('is_locked', true)
            ->exists()) {
            throw ValidationException::withMessages([
                'koreksi' => 'KHS sudah dikunci. Buka kembali KHS melalui alur akademik sebelum menyetujui koreksi nilai.',
            ]);
        }
    }

    private function applyCorrection(KoreksiNilaiMahasiswa $correction, int $reviewerId): void
    {
        $detail = $correction->krsDetail()->with('kelas.tahunAkademik', 'kelas.mataKuliah.kurikulum')->firstOrFail();
        $this->assertCorrectionMode($correction, $detail);
        $newValues = $correction->nilai_baru;

        if ($correction->jenis_nilai === KoreksiNilaiMahasiswa::JENIS_KONVENSIONAL) {
            $grade = NilaiMahasiswa::query()->lockForUpdate()->findOrFail($correction->nilai_mahasiswa_id);
            $this->assertSnapshotMatches($correction->nilai_lama, $grade->only(array_keys($correction->nilai_lama)));
            $updatedValues = [...$grade->only(array_keys($correction->nilai_lama)), ...$newValues];
            $weights = $detail->kelas?->getBobotPenilaian() ?? \App\Models\Siakad\Kelas::defaultBobotPenilaian();
            $score = (float) $updatedValues['nilai_harian'] * $weights['harian']
                + (float) $updatedValues['nilai_uts'] * $weights['uts']
                + (float) $updatedValues['nilai_uas'] * $weights['uas']
                + (float) $updatedValues['nilai_praktik'] * $weights['praktik'];
            $programStudiId = $detail->kelas?->mataKuliah?->kurikulum?->program_studi_id;
            [$letter, $weight] = $this->nilaiKomponenSync->convertGrade($score, $programStudiId);
            $grade->update([
                ...$newValues,
                'nilai_akhir' => round($score, 2),
                'nilai_huruf' => $letter,
                'bobot_mutu' => $weight,
                'diinput_oleh' => $reviewerId,
            ]);

            return;
        }

        if ($correction->jenis_nilai === KoreksiNilaiMahasiswa::JENIS_KOMPONEN_OBE) {
            $score = NilaiKomponenMahasiswa::query()->lockForUpdate()->findOrFail($correction->nilai_komponen_mahasiswa_id);
            $this->assertSnapshotMatches($correction->nilai_lama, ['nilai_angka' => $score->nilai_angka]);
            $this->recordObeScoreChange($score->getTable(), $score->id, ['nilai_angka' => $score->nilai_angka], $newValues);
            $score->update(['nilai_angka' => $newValues['nilai_angka'], 'diinput_oleh' => $reviewerId]);
        } else {
            $score = KetercapaianCpmkMahasiswa::query()->lockForUpdate()->findOrFail($correction->ketercapaian_cpmk_mahasiswa_id);
            $this->assertSnapshotMatches($correction->nilai_lama, ['nilai_angka' => $score->skor_ketercapaian]);
            $newScore = (float) $newValues['nilai_angka'];
            $newCpmkValues = [
                'skor_ketercapaian' => $newScore,
                'status_ketercapaian' => $newScore >= 65 ? 'tercapai' : 'belum_tercapai',
            ];
            $this->recordObeScoreChange(
                $score->getTable(),
                $score->id,
                ['skor_ketercapaian' => $score->skor_ketercapaian, 'status_ketercapaian' => $score->status_ketercapaian],
                $newCpmkValues
            );
            $score->update($newCpmkValues);
        }

        $this->nilaiKomponenSync->recalculateObeGrade($detail, $reviewerId);
    }

    private function assertCorrectionMode(KoreksiNilaiMahasiswa $correction, KrsDetail $detail): void
    {
        $expectedMode = match ($correction->jenis_nilai) {
            KoreksiNilaiMahasiswa::JENIS_KONVENSIONAL => 'konvensional',
            KoreksiNilaiMahasiswa::JENIS_KOMPONEN_OBE => 'semi_obe',
            KoreksiNilaiMahasiswa::JENIS_CPMK_OBE => 'full_obe',
            default => null,
        };
        $currentMode = $detail->kelas?->tahunAkademik?->mode_penilaian ?? $expectedMode;

        if ($expectedMode === null || $currentMode !== $expectedMode) {
            throw ValidationException::withMessages([
                'jenis_nilai' => 'Mode penilaian kelas berubah setelah pengajuan. Buat pengajuan koreksi baru sesuai mode saat ini.',
            ]);
        }
    }

    private function assertSnapshotMatches(array $expected, array $current): void
    {
        foreach ($expected as $key => $value) {
            if (!array_key_exists($key, $current) || round((float) $value, 2) !== round((float) $current[$key], 2)) {
                throw ValidationException::withMessages([
                    'koreksi' => 'Nilai berubah sejak pengajuan dibuat. Buat pengajuan koreksi baru dengan nilai terkini.',
                ]);
            }
        }
    }

    private function recordObeScoreChange(string $table, int $recordId, array $oldValues, array $newValues): void
    {
        try {
            AuditLogService::record(
                module: 'SIAKAD',
                action: 'update',
                tableName: $table,
                recordId: $recordId,
                oldValues: $oldValues,
                newValues: $newValues
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal mencatat audit log perubahan nilai OBE.', [
                'exception' => $e,
                'table_name' => $table,
                'record_id' => $recordId,
            ]);
        }
    }

    private function recalculateKhs(KrsDetail $detail): void
    {
        $detail->loadMissing('krs');
        if ($detail->krs) {
            $this->akademik->hitungKhsDanIpk(
                $detail->krs->mahasiswa_id,
                $detail->krs->tahun_akademik_id
            );
        }
    }

    private function scopeReviewerQuery($query, User $user): void
    {
        if ($user->isAdmin()) {
            return;
        }

        $programStudiIds = $user->getSiakadProdiIds()->all();
        if ($programStudiIds === []) {
            $query->whereRaw('1 = 0');
            return;
        }

        $query->whereHas('krsDetail.kelas', function ($kelasQuery) use ($programStudiIds) {
            $kelasQuery->whereIn('program_studi_id', $programStudiIds)
                ->orWhereHas('mataKuliah.kurikulum', fn ($kurikulumQuery) =>
                    $kurikulumQuery->whereIn('program_studi_id', $programStudiIds)
                );
        });
    }
}