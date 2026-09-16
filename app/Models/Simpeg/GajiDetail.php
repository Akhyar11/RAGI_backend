<?php

namespace App\Models\Simpeg;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GajiDetail extends Model
{
    use HasFactory;

    protected $table = 'simpeg_gaji_detail';

    protected $fillable = [
        'gaji_pegawai_id',
        'komponen_gaji_id',
        'nama_komponen',
        'jenis',
        'nominal',
        'keterangan',
    ];

    protected $casts = [
        'nominal' => 'float',
    ];

    public function gajiPegawai()
    {
        return $this->belongsTo(GajiPegawai::class, 'gaji_pegawai_id');
    }

    public function komponen()
    {
        return $this->belongsTo(MasterKomponenGaji::class, 'komponen_gaji_id');
    }
}
