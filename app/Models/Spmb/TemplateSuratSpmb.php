<?php

namespace App\Models\Spmb;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Spmb\JalurMasuk;
use App\Models\Spmb\GelombangPenerimaan;

class TemplateSuratSpmb extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'spmb_template_surat';

    protected $fillable = [
        'kode',
        'nama',
        'jenis_surat',
        'jalur_masuk_id',
        'gelombang_id',
        'is_active',
        'kop_nama_institusi',
        'kop_nama_sub',
        'kop_alamat_kontak',
        'format_nomor_surat',
        'judul_surat',
        'teks_pembuka',
        'teks_keputusan',
        'petunjuk_daftar_ulang',
        'kota_penetapan',
        'nama_penandatangan',
        'jabatan_penandatangan',
        'nip_penandatangan',
        'catatan_kaki',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function jalurMasuk()
    {
        return $this->belongsTo(JalurMasuk::class, 'jalur_masuk_id');
    }

    public function gelombang()
    {
        return $this->belongsTo(GelombangPenerimaan::class, 'gelombang_id');
    }
}
