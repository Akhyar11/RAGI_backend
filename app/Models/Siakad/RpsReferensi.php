<?php

namespace App\Models\Siakad;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Model referensi RPS (Bentuk, Metode, Kriteria, Komponen).
 */
class RpsReferensi extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'siakad_rps_referensi';

    protected $fillable = [
        'tipe',
        'program_studi_id',
        'kode',
        'nama',
        'deskripsi',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function programStudi()
    {
        return $this->belongsTo(ProgramStudi::class, 'program_studi_id');
    }
}
