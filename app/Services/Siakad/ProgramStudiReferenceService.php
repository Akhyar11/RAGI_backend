<?php

namespace App\Services\Siakad;

use App\Models\System\MasterReferensi;

class ProgramStudiReferenceService
{
    public function hasActiveJenjang(string $code): bool
    {
        return $this->hasActiveReference('jenjang_prodi', $code);
    }

    public function hasActiveAkreditasi(string $code): bool
    {
        return $this->hasActiveReference('akreditasi_prodi', $code);
    }

    private function hasActiveReference(string $type, string $code): bool
    {
        return MasterReferensi::query()
            ->active()
            ->forModule('siakad')
            ->where('tipe', $type)
            ->where('kode', $code)
            ->exists();
    }
}