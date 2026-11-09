<?php

namespace App\Observers\Siakad;

use App\Models\Siakad\KoreksiNilaiMahasiswa;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\Log;

class KoreksiNilaiMahasiswaObserver
{
    private const AUDITED_FIELDS = [
        'krs_detail_id',
        'jenis_nilai',
        'nilai_mahasiswa_id',
        'nilai_komponen_mahasiswa_id',
        'ketercapaian_cpmk_mahasiswa_id',
        'nilai_lama',
        'nilai_baru',
        'alasan_koreksi',
        'status',
        'diajukan_oleh',
        'diputuskan_oleh',
        'diputuskan_at',
        'catatan_reviewer',
    ];

    public function created(KoreksiNilaiMahasiswa $koreksi): void
    {
        $this->record($koreksi, 'create', null, $koreksi->only(self::AUDITED_FIELDS));
    }

    public function updated(KoreksiNilaiMahasiswa $koreksi): void
    {
        $changes = array_intersect_key($koreksi->getChanges(), array_flip(self::AUDITED_FIELDS));
        if ($changes === []) {
            return;
        }

        $oldValues = array_intersect_key($koreksi->getOriginal(), array_flip(array_keys($changes)));
        $this->record($koreksi, 'update', $oldValues, $changes);
    }

    public function deleted(KoreksiNilaiMahasiswa $koreksi): void
    {
        $this->record($koreksi, 'delete', $koreksi->only(self::AUDITED_FIELDS), null);
    }

    private function record(KoreksiNilaiMahasiswa $koreksi, string $action, ?array $oldValues, ?array $newValues): void
    {
        try {
            AuditLogService::record(
                module: 'SIAKAD',
                action: $action,
                tableName: 'siakad_nilai_koreksi',
                recordId: $koreksi->id,
                oldValues: $oldValues,
                newValues: $newValues
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal mencatat audit log koreksi nilai SIAKAD.', [
                'exception' => $e,
                'koreksi_nilai_id' => $koreksi->id,
            ]);
        }
    }
}