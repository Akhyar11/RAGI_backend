<?php

namespace App\Models\Siakad;

use Illuminate\Database\Eloquent\Model;

class KrsDetail extends Model
{
    protected $table = 'siakad_krs_detail';

    protected $fillable = [
        'krs_id',
        'kelas_id',
        'status',
    ];

    public function krs()
    {
        return $this->belongsTo(Krs::class, 'krs_id');
    }

    public function kelas()
    {
        return $this->belongsTo(Kelas::class, 'kelas_id');
    }

    public function nilaiMahasiswa()
    {
        return $this->hasOne(NilaiMahasiswa::class, 'krs_detail_id');
    }

    public function nilai()
    {
        return $this->hasOne(NilaiMahasiswa::class, 'krs_detail_id');
    }

    public function nilaiKomponens()
    {
        return $this->hasMany(NilaiKomponenMahasiswa::class, 'krs_detail_id');
    }

    public function ketercapaianCpmks()
    {
        return $this->hasMany(KetercapaianCpmkMahasiswa::class, 'krs_detail_id');
    }
}
