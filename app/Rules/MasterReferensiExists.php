<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\DB;

/**
 * Validasi nilai terhadap tabel master referensi.
 *
 * Pengganti `in:STATIS` untuk data referensi dinamis sesuai
 * Zero Hardcode Policy: nilai wajib terdaftar sebagai item aktif
 * pada (modul, tipe) tertentu di `spmb_master_referensi`.
 */
class MasterReferensiExists implements ValidationRule
{
    public function __construct(
        private string $tipe,
        private string $modul = 'siakad'
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $exists = DB::table('spmb_master_referensi')
            ->where('modul', $this->modul)
            ->where('tipe', $this->tipe)
            ->where('kode', $value)
            ->where('is_active', true)
            ->exists();

        if (!$exists) {
            $fail("Nilai :attribute tidak terdaftar di master referensi {$this->tipe}.");
        }
    }
}
