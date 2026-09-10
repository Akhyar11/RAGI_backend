<?php

namespace App\Models\Sikeu;

use App\Models\Simpeg\Pegawai;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MasterGajiPegawai extends Model
{
    use HasFactory;

    protected $table = 'sikeu_master_gaji_pegawai';

    protected $fillable = [
        'pegawai_id',
        'gaji_pokok',
        'tunjangan_tetap',
        'potongan_tetap',
        'tarif_transport_harian',
        'catatan',
    ];

    protected $casts = [
        'gaji_pokok' => 'float',
        'tunjangan_tetap' => 'float',
        'potongan_tetap' => 'float',
        'tarif_transport_harian' => 'float',
    ];

    public function pegawai()
    {
        return $this->belongsTo(Pegawai::class, 'pegawai_id');
    }
}
