<?php

namespace App\Models\Siakad;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class KomponenPenilaian extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'siakad_komponen_penilaian';

    protected $fillable = [
        'kelas_id',
        'cpmk_id',
        'sub_cpmk_id',
        'nama_komponen',
        'teknik_penilaian',
        'bobot',
        'urutan',
        'is_aktif',
    ];

    protected $casts = [
        'bobot' => 'decimal:2',
        'is_aktif' => 'boolean',
    ];

    public function kelas()
    {
        return $this->belongsTo(Kelas::class, 'kelas_id');
    }

    public function cpmk()
    {
        return $this->belongsTo(Cpmk::class, 'cpmk_id');
    }

    public function subCpmk()
    {
        return $this->belongsTo(SubCpmk::class, 'sub_cpmk_id');
    }

    public function nilaiMahasiswas()
    {
        return $this->hasMany(NilaiKomponenMahasiswa::class, 'komponen_penilaian_id');
    }
}
