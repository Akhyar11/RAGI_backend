<?php

namespace App\Models\Sikeu;

use Illuminate\Database\Eloquent\Model;

class JalurKelas extends Model
{
    protected $table = 'sikeu_jalur_kelas';

    protected $fillable = [
        'kode',
        'nama_jalur',
        'deskripsi',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
