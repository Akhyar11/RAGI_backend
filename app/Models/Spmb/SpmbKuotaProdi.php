<?php

namespace App\Models\Spmb;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SpmbKuotaProdi extends Model
{
    use HasFactory;

    protected $table = 'spmb_kuota_prodi';

    protected $fillable = [
        'tahun_akademik_id',
        'program_studi_id',
        'kuota_total',
        'kuota_terisi',
    ];
}
