<?php

namespace App\Models\Spmb;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Spmb\JalurMasuk;
use App\Models\Spmb\GelombangPenerimaan;
use App\Models\Module;
use App\Models\Arsip\KlasifikasiSurat;

class TemplateSuratSpmb extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'spmb_template_surat';

    // ── Logika keputusan surat (nilai tetap domain, bukan master) ──
    public const HASIL_DITERIMA = 'diterima';
    public const HASIL_DITOLAK = 'ditolak';

    protected $fillable = [
        'kode',
        'nama',
        'jenis_surat',
        'hasil',
        'module_id',
        'klasifikasi_surat_id',
        'unit_surat_id',
        'jalur_masuk_id',
        'gelombang_id',
        'is_active',
        'kop_nama_institusi',
        'kop_nama_sub',
        'kop_alamat_kontak',
        'format_nomor_surat',
        'judul_surat',
        'teks_pembuka',
        'teks_pernyataan',
        'teks_keputusan',
        'label_keputusan',
        'teks_prodi',
        'teks_penutup',
        'petunjuk_daftar_ulang',
        'judul_petunjuk',
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

    public function module()
    {
        return $this->belongsTo(Module::class, 'module_id');
    }

    public function klasifikasiSurat()
    {
        return $this->belongsTo(KlasifikasiSurat::class, 'klasifikasi_surat_id');
    }

    public function unitSurat()
    {
        return $this->belongsTo(KlasifikasiSurat::class, 'unit_surat_id');
    }
}
