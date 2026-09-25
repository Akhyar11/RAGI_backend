<?php

namespace App\Models\Simpeg;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JabatanFungsionalAkademik extends Model
{
    use HasFactory;

    protected $table = 'simpeg_jabatan_fungsional_akademik';

    protected $fillable = [
        'nama',
        'angka_kredit_min',
        'angka_kredit_max',
        'golongan',
        'golongan_pangkat_id',
        'tunjangan_nominal',
    ];

    protected $casts = [
        'angka_kredit_min' => 'integer',
        'angka_kredit_max' => 'integer',
        'golongan_pangkat_id' => 'integer',
        'tunjangan_nominal' => 'float',
    ];

    public function golonganPangkat()
    {
        return $this->belongsTo(MasterGolonganPangkat::class, 'golongan_pangkat_id');
    }

    public function riwayatJabatan()
    {
        return $this->hasMany(RiwayatJabatan::class, 'jabatan_fungsional_id');
    }
}
