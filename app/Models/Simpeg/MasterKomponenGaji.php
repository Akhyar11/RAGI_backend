<?php

namespace App\Models\Simpeg;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MasterKomponenGaji extends Model
{
    use HasFactory;

    protected $table = 'simpeg_master_komponen_gaji';

    protected $fillable = [
        'kode',
        'nama',
        'jenis',
        'tipe_nilai',
        'nilai_default',
        'is_taxable',
        'is_active',
        'urutan',
        'keterangan',
    ];

    protected $casts = [
        'nilai_default' => 'float',
        'is_taxable' => 'boolean',
        'is_active' => 'boolean',
        'urutan' => 'integer',
    ];

    public function pegawaiKomponen()
    {
        return $this->hasMany(PegawaiKomponenGaji::class, 'komponen_gaji_id');
    }

    public function gajiDetails()
    {
        return $this->hasMany(GajiDetail::class, 'komponen_gaji_id');
    }
}
