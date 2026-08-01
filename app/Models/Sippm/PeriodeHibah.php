<?php

namespace App\Models\Sippm;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PeriodeHibah extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'periode_hibah';

    protected $fillable = [
        'tahun_anggaran',
        'nama_gelombang',
        'tgl_buka_proposal',
        'tgl_tutup_proposal',
        'tgl_tutup_monev',
        'tgl_tutup_laporan',
        'is_active',
    ];

    protected $casts = [
        'tahun_anggaran' => 'string',
        'tgl_buka_proposal' => 'date',
        'tgl_tutup_proposal' => 'date',
        'tgl_tutup_monev' => 'date',
        'tgl_tutup_laporan' => 'date',
        'is_active' => 'boolean',
    ];

    public function proposalKegiatan()
    {
        return $this->hasMany(ProposalKegiatan::class, 'periode_id');
    }
}
