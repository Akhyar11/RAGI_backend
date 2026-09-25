<?php

namespace App\Models\Sinapra;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MasterVendor extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'sinapra_master_vendor';

    protected $fillable = [
        'kode',
        'nama',
        'jenis_rekanan',
        'alamat',
        'telepon',
        'email',
        'pic_nama',
        'pic_kontak',
        'nomor_npwp',
        'is_active',
        'urutan',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'urutan' => 'integer',
    ];

    public function kalibrasi()
    {
        return $this->hasMany(\App\Models\AlatKalibrasi::class, 'vendor_id');
    }

    public function pengajuanPengadaan()
    {
        return $this->hasMany(\App\Models\PengajuanPengadaan::class, 'vendor_id');
    }
}
