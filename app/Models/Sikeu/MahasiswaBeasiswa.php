<?php

namespace App\Models\Sikeu;

use Illuminate\Database\Eloquent\Model;

class MahasiswaBeasiswa extends Model
{
    protected $table = 'sikeu_mahasiswa_beasiswa';

    protected $fillable = [
        'mahasiswa_id',
        'beasiswa_id',
        'nim',
        'nama_mahasiswa',
        'berlaku_mulai',
        'berlaku_sampai',
        'status',
    ];

    public function beasiswa()
    {
        return $this->belongsTo(Beasiswa::class, 'beasiswa_id');
    }

    public function mahasiswa()
    {
        return $this->belongsTo(\App\Models\Siakad\Mahasiswa::class, 'mahasiswa_id');
    }
}
