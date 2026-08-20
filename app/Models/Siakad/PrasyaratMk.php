<?php

namespace App\Models\Siakad;

use Illuminate\Database\Eloquent\Model;

class PrasyaratMk extends Model
{
    protected $table = 'siakad_prasyarat_mk';

    protected $fillable = [
        'mata_kuliah_id',
        'prasyarat_id',
        'tipe',
        'nilai_minimum',
    ];

    public function mataKuliah()
    {
        return $this->belongsTo(MataKuliah::class, 'mata_kuliah_id');
    }

    public function prasyarat()
    {
        return $this->belongsTo(MataKuliah::class, 'prasyarat_id');
    }
}
