<?php

namespace App\Models\Sikeu;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PeriodeAkuntansi extends Model
{
    use HasFactory;

    protected $table = 'sikeu_periode_akuntansi';

    protected $fillable = [
        'nama_periode',
        'tahun',
        'bulan',
        'tanggal_mulai',
        'tanggal_selesai',
        'status',
        'ditutup_oleh',
        'ditutup_pada',
    ];

    protected $casts = [
        'tanggal_mulai' => 'date',
        'tanggal_selesai' => 'date',
        'ditutup_pada' => 'datetime',
    ];

    public function jurnalUmum()
    {
        return $this->hasMany(JurnalUmum::class, 'periode_id');
    }
}
