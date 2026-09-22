<?php

namespace App\Models\Siakad;

use Illuminate\Database\Eloquent\Model;

class StatusAkademikLog extends Model
{
    protected $table = 'siakad_status_akademik_log';

    protected $fillable = [
        'mahasiswa_id',
        'status_lama',
        'status_baru',
        'alasan',
        'diubah_oleh',
    ];

    public function mahasiswa()
    {
        return $this->belongsTo(Mahasiswa::class, 'mahasiswa_id');
    }
}
