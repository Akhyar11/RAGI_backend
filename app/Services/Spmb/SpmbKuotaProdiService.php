<?php

namespace App\Services\Spmb;

use App\Models\Spmb\SpmbKuotaProdi;
use Illuminate\Support\Facades\DB;

class SpmbKuotaProdiService
{
    public function upsert(array $data): SpmbKuotaProdi
    {
        return DB::transaction(function () use ($data) {
            $kuota = SpmbKuotaProdi::updateOrCreate(
                [
                    'tahun_akademik_id' => $data['tahun_akademik_id'],
                    'program_studi_id' => $data['program_studi_id'],
                ],
                [
                    'kuota_total' => $data['kuota_total'],
                ]
            );

            return $kuota->load(['tahunAkademik', 'programStudi']);
        });
    }

    public function update(SpmbKuotaProdi $kuota, array $data): SpmbKuotaProdi
    {
        return DB::transaction(function () use ($kuota, $data) {
            $kuota->update([
                'kuota_total' => $data['kuota_total'],
            ]);

            return $kuota->load(['tahunAkademik', 'programStudi']);
        });
    }

    public function delete(SpmbKuotaProdi $kuota): bool
    {
        return DB::transaction(function () use ($kuota) {
            return (bool) $kuota->delete();
        });
    }
}
