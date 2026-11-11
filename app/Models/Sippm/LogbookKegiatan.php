<?php

namespace App\Models\Sippm;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LogbookKegiatan extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'sippm_logbook_kegiatan';

    protected $fillable = [
        'proposal_id',
        'tgl_kegiatan',
        'persentase_capaian',
        'uraian_kegiatan',
        'hambatan',
        'status_verifikasi',
    ];

    protected $casts = [
        'tgl_kegiatan' => 'date:Y-m-d',
        'persentase_capaian' => 'float',
    ];

    public function proposal()
    {
        return $this->belongsTo(ProposalKegiatan::class, 'proposal_id');
    }
}
