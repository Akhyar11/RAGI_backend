<?php

namespace App\Models\Sikeu;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TarifUkt extends Model
{
    use HasFactory;

    protected $table = 'tarif_ukt';

    protected $fillable = [
        'program_studi_id',
        'jenis_biaya_id',
        'tahun_akademik_id',
        'tahun_angkatan',
        'jalur_kelas',
        'kelompok_ukt',
        'nama_kelompok',
        'nominal',
        'is_active',
    ];

    protected $casts = [
        'nominal' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function jenisBiaya()
    {
        return $this->belongsTo(JenisBiaya::class, 'jenis_biaya_id');
    }
}
