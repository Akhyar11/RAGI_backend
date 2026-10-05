<?php

namespace App\Models\Siakad;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BankSoalKategori extends Model
{
    use SoftDeletes;

    protected $table = 'siakad_bank_soal_kategori';

    protected $fillable = [
        'nama',
        'mata_kuliah_id',
        'dibuat_oleh',
    ];

    public function mataKuliah()
    {
        return $this->belongsTo(MataKuliah::class, 'mata_kuliah_id');
    }

    public function soal()
    {
        return $this->hasMany(BankSoal::class, 'kategori_id');
    }
}
