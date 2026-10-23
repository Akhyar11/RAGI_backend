<?php

namespace App\Models\Siakad;

use Illuminate\Database\Eloquent\Model;

class PaketMkRombel extends Model
{
    protected $table = 'siakad_paket_mk_rombel';

    protected $fillable = [
        'master_kelas_id',
        'mata_kuliah_id',
        'tahun_akademik_id',
        'nomor_surat_id',
        'nomor_sk',
        'semester_anjuran',
        'is_wajib',
    ];

    protected $casts = [
        'master_kelas_id' => 'integer',
        'mata_kuliah_id' => 'integer',
        'tahun_akademik_id' => 'integer',
        'nomor_surat_id' => 'integer',
        'nomor_sk' => 'string',
        'semester_anjuran' => 'integer',
        'is_wajib' => 'boolean',
    ];

    public function masterKelas()
    {
        return $this->belongsTo(MasterKelas::class, 'master_kelas_id');
    }

    public function mataKuliah()
    {
        return $this->belongsTo(MataKuliah::class, 'mata_kuliah_id');
    }

    public function tahunAkademik()
    {
        return $this->belongsTo(TahunAkademik::class, 'tahun_akademik_id');
    }

    public function nomorSurat()
    {
        return $this->belongsTo(\App\Models\Arsip\NomorSurat::class, 'nomor_surat_id');
    }
}
