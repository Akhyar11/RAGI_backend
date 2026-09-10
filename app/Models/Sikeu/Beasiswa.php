<?php

namespace App\Models\Sikeu;

use Illuminate\Database\Eloquent\Model;

class Beasiswa extends Model
{
    protected $table = 'sikeu_beasiswa';

    protected $fillable = [
        'kode',
        'nama',
        'sumber',
        'tipe_potongan',
        'nilai_potongan',
        'jenis_biaya_id',
        'berlaku_angkatan_mulai',
        'berlaku_angkatan_sampai',
        'deskripsi',
        'is_active',
    ];

    protected $casts = [
        'nilai_potongan' => 'float',
        'is_active' => 'boolean',
        'berlaku_angkatan_mulai' => 'integer',
        'berlaku_angkatan_sampai' => 'integer',
    ];

    public function jenisBiaya()
    {
        return $this->belongsTo(MasterBiaya::class, 'jenis_biaya_id');
    }
}
