<?php

namespace App\Models\Simpeg;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JabatanFungsionalAkademik extends Model
{
    use HasFactory;

    protected $table = 'jabatan_fungsional_akademik';

    protected $fillable = [
        'nama',
        'angka_kredit_min',
        'angka_kredit_max',
        'golongan',
    ];

    protected $casts = [
        'angka_kredit_min' => 'integer',
        'angka_kredit_max' => 'integer',
    ];

    public function riwayatJabatan()
    {
        return $this->hasMany(RiwayatJabatan::class, 'jabatan_fungsional_id');
    }
}
