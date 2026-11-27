<?php

namespace App\Models\Spmb;

use Illuminate\Database\Eloquent\Model;

class MasterSekolah extends Model
{
    public const SUMBER_MANUAL = 'manual';

    protected $table = 'spmb_master_sekolah';

    protected $fillable = ['nama', 'nama_normal', 'alamat', 'jenis', 'sumber', 'created_by'];

    public static function normalize(string $nama): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $nama)));
    }
}
