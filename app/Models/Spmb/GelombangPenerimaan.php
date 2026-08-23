<?php

namespace App\Models\Spmb;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Spmb\JalurMasuk;
use App\Models\Spmb\PendaftaranCalonMhs;
use App\Models\Spmb\KuesionerSpmb;
use App\Models\Spmb\PengumumanSpmb;
use App\Models\Siakad\TahunAkademik;

class GelombangPenerimaan extends Model
{
    use HasFactory;

    protected $table = 'spmb_gelombang_penerimaan';

    protected $fillable = [
        'jalur_masuk_id',
        'tahun_akademik_id',
        'nama',
        'tanggal_buka',
        'tanggal_tutup',
        'tanggal_pengumuman',
        'kuota_total',
        'kuota_terisi',
        'biaya_pendaftaran',
        'status',
    ];

    protected $casts = [
        'tanggal_buka' => 'date',
        'tanggal_tutup' => 'date',
        'tanggal_pengumuman' => 'date',
        'biaya_pendaftaran' => 'decimal:2',
    ];

    public function jalurMasuk()
    {
        return $this->belongsTo(JalurMasuk::class, 'jalur_masuk_id');
    }

    public function tahunAkademik()
    {
        // Add related method even if Siakad\TahunAkademik not yet created
        return $this->belongsTo(TahunAkademik::class, 'tahun_akademik_id');
    }

    public function pendaftaranCalonMhs()
    {
        return $this->hasMany(PendaftaranCalonMhs::class, 'gelombang_id');
    }

    public function kuesionerSpmb()
    {
        return $this->hasMany(KuesionerSpmb::class, 'gelombang_id');
    }

    public function pengumumanSpmb()
    {
        return $this->hasMany(PengumumanSpmb::class, 'gelombang_id');
    }
}
