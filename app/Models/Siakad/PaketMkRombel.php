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
        'semester_anjuran',
        'is_wajib',
    ];

    protected $casts = [
        'master_kelas_id' => 'integer',
        'mata_kuliah_id' => 'integer',
        'tahun_akademik_id' => 'integer',
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
}
