<?php

namespace App\Policies\Siakad;

use App\Models\Siakad\KoreksiNilaiMahasiswa;
use App\Models\User;

class NilaiKoreksiMahasiswaPolicy
{
    public function review(User $user, KoreksiNilaiMahasiswa $correction): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        $detail = $correction->krsDetail()->with('kelas.mataKuliah.kurikulum')->first();
        $kelas = $detail?->kelas;
        $programStudiId = $kelas?->mataKuliah?->kurikulum?->program_studi_id ?? $kelas?->program_studi_id;

        return $programStudiId !== null
            && $user->canManageObeForProdi((int) $programStudiId);
    }
}
