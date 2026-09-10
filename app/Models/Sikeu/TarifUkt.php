<?php

namespace App\Models\Sikeu;

use Illuminate\Database\Eloquent\Model;

class TarifUkt extends Model
{
    protected $table = 'sikeu_tarif_ukt';

    protected $fillable = [
        'jenis_biaya_id',
        'tahun_angkatan',
        'jalur_kelas',
        'kelompok_ukt',
        'prodi',
        'nama_kelompok',
        'nominal',
    ];

    protected $casts = [
        'nominal' => 'float',
        'tahun_angkatan' => 'integer',
        'kelompok_ukt' => 'integer',
    ];

    public function jenisBiaya()
    {
        return $this->belongsTo(MasterBiaya::class, 'jenis_biaya_id');
    }
}
