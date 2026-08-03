<?php

namespace App\Models\Sikeu;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Beasiswa extends Model
{
    use HasFactory;

    protected $table = 'beasiswa';

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
        'nilai_potongan' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function jenisBiaya()
    {
        return $this->belongsTo(JenisBiaya::class, 'jenis_biaya_id');
    }

    public function mahasiswaBeasiswa()
    {
        return $this->hasMany(MahasiswaBeasiswa::class, 'beasiswa_id');
    }
}
