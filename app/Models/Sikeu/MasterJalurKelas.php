<?php

namespace App\Models\Sikeu;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MasterJalurKelas extends Model
{
    use HasFactory;

    protected $table = 'core_master_jalur_kelas';

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
