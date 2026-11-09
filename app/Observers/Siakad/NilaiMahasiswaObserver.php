<?php

namespace App\Observers\Siakad;

use App\Models\Siakad\NilaiMahasiswa;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\Log;

class NilaiMahasiswaObserver
{
    private const AUDITED_FIELDS = [
        'krs_detail_id',
        'nilai_harian',
        'nilai_uts',
        'nilai_uas',
        'nilai_praktik',
        'nilai_akhir',
        'nilai_huruf',
        'bobot_mutu',
        'is_final',
        'diinput_oleh',
    ];

    public function created(NilaiMahasiswa $nilai): void
    {
        $this->record($nilai, 'create', null, $nilai->only(self::AUDITED_FIELDS));
    }

    public function updated(NilaiMahasiswa $nilai): void
    {
        $changes = array_intersect_key($nilai->getChanges(), array_flip(self::AUDITED_FIELDS));
        if ($changes === []) {
            return;
        }

        $oldValues = array_intersect_key($nilai->getOriginal(), array_flip(array_keys($changes)));
        $this->record($nilai, 'update', $oldValues, $changes);
    }

    public function deleted(NilaiMahasiswa $nilai): void
    {
        $this->record($nilai, 'delete', $nilai->only(self::AUDITED_FIELDS), null);
    }

    private function record(NilaiMahasiswa $nilai, string $action, ?array $oldValues, ?array $newValues): void
    {
        try {
            AuditLogService::record(
                module: 'SIAKAD',
                action: $action,
                tableName: 'siakad_nilai_mahasiswa',
                recordId: $nilai->id,
                oldValues: $oldValues,
                newValues: $newValues
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal mencatat audit log nilai mahasiswa.', [
                'exception' => $e,
                'nilai_mahasiswa_id' => $nilai->id,
            ]);
        }
    }
}