<?php

namespace App\Models\Siakad;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Rps extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'siakad_rps';

    protected $fillable = [
        'mata_kuliah_id',
        'kode_rps',
        'tahun_ajaran',
        'semester',
        'tanggal_penyusunan',
        'dosen_pengembang_id',
        'dosen_anggota_ids',
        'koordinator_rmk_id',
        'kaprodi_id',
        'dosen_bisa_edit',
        'deskripsi_singkat',
        'pustaka_utama',
        'pustaka_pendukung',
        'media_pembelajaran_software',
        'media_pembelajaran_hardware',
        'status',
        'catatan_revisi',
        'disetujui_at',
    ];

    protected $casts = [
        'disetujui_at' => 'datetime',
        'tanggal_penyusunan' => 'date',
        'dosen_anggota_ids' => 'array',
        'dosen_bisa_edit' => 'boolean',
    ];

    public function mataKuliah()
    {
        return $this->belongsTo(MataKuliah::class, 'mata_kuliah_id');
    }

    public function dosenPengembang()
    {
        return $this->belongsTo(Dosen::class, 'dosen_pengembang_id');
    }

    public function koordinatorRmk()
    {
        return $this->belongsTo(Dosen::class, 'koordinator_rmk_id');
    }

    public function kaprodi()
    {
        return $this->belongsTo(Dosen::class, 'kaprodi_id');
    }

    public function mingguan()
    {
        return $this->hasMany(RpsMingguan::class, 'rps_id')->orderBy('minggu_ke');
    }
}
