<?php

namespace App\Models\Siakad;

use Illuminate\Database\Eloquent\Model;

class DosenPengampu extends Model
{
    protected $table = 'siakad_dosen_pengampu';

    protected $fillable = [
        'kelas_id',
        'dosen_id',
        'penugasan_id',
        'peran',
        'sks_substansi_total',
        'rencana_minggu_pertemuan',
        'realisasi_minggu_pertemuan',
        'jenis_evaluasi_id',
        'id_feeder',
        'sync_status',
        'last_synced_at',
    ];

    protected $casts = [
        'sks_substansi_total' => 'decimal:2',
        'rencana_minggu_pertemuan' => 'integer',
        'realisasi_minggu_pertemuan' => 'integer',
        'jenis_evaluasi_id' => 'integer',
        'last_synced_at' => 'datetime',
    ];

    public function kelas()
    {
        return $this->belongsTo(Kelas::class, 'kelas_id');
    }

    public function dosen()
    {
        return $this->belongsTo(Dosen::class, 'dosen_id');
    }

    public function penugasan()
    {
        return $this->belongsTo(DosenPenugasan::class, 'penugasan_id');
    }
}
