<?php

namespace App\Models\Simpeg;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PegawaiKomponenGaji extends Model
{
    use HasFactory;

    protected $table = 'simpeg_pegawai_komponen_gaji';

    protected $fillable = [
        'pegawai_id',
        'komponen_gaji_id',
        'nominal_kustom',
        'is_active',
        'catatan',
    ];

    protected $casts = [
        'nominal_kustom' => 'float',
        'is_active' => 'boolean',
    ];

    public function pegawai()
    {
        return $this->belongsTo(Pegawai::class, 'pegawai_id');
    }

    public function komponen()
    {
        return $this->belongsTo(MasterKomponenGaji::class, 'komponen_gaji_id');
    }
}
