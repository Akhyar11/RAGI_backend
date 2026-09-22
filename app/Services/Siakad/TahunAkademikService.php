<?php

namespace App\Services\Siakad;

use App\Models\Siakad\TahunAkademik;
use Illuminate\Support\Facades\DB;

class TahunAkademikService
{
    /**
     * Buat periode tahun akademik baru. Bila ditandai aktif, nonaktifkan
     * periode lain terlebih dahulu dalam satu transaksi.
     */
    public function create(array $data): TahunAkademik
    {
        return DB::transaction(function () use ($data) {
            if (!empty($data['is_active'])) {
                foreach (TahunAkademik::where('is_active', true)->get() as $active) {
                    $active->update(['is_active' => false]);
                }
            }

            return TahunAkademik::create($data);
        });
    }

    /**
     * Jadikan satu periode sebagai tahun akademik aktif.
     */
    public function setActive(TahunAkademik $tahunAkademik): TahunAkademik
    {
        return DB::transaction(function () use ($tahunAkademik) {
            foreach (TahunAkademik::where('is_active', true)->get() as $active) {
                $active->update(['is_active' => false]);
            }
            $tahunAkademik->update(['is_active' => true]);

            return $tahunAkademik;
        });
    }

    /**
     * Ubah mode penilaian periode akademik.
     */
    public function updateModePenilaian(TahunAkademik $tahunAkademik, string $mode): TahunAkademik
    {
        return DB::transaction(function () use ($tahunAkademik, $mode) {
            $tahunAkademik->update(['mode_penilaian' => $mode]);

            return $tahunAkademik;
        });
    }
}
