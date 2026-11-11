<?php

namespace App\Models\Sippm;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PeriodeHibah extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'sippm_periode_hibah';

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
        'tgl_buka_proposal' => 'date:Y-m-d',
        'tgl_tutup_proposal' => 'date:Y-m-d',
        'tgl_tutup_monev' => 'date:Y-m-d',
        'tgl_tutup_laporan' => 'date:Y-m-d',
        'is_active' => 'boolean',
    ];

    protected $appends = ['nama_periode', 'tgl_buka', 'tgl_tutup'];

    public function getNamaPeriodeAttribute()
    {
        return $this->nama_gelombang;
    }

    public function getTglBukaAttribute()
    {
        if (!$this->tgl_buka_proposal) return null;
        return is_string($this->tgl_buka_proposal) ? substr($this->tgl_buka_proposal, 0, 10) : $this->tgl_buka_proposal->format('Y-m-d');
    }

    public function getTglTutupAttribute()
    {
        if (!$this->tgl_tutup_proposal) return null;
        return is_string($this->tgl_tutup_proposal) ? substr($this->tgl_tutup_proposal, 0, 10) : $this->tgl_tutup_proposal->format('Y-m-d');
    }

    public function proposalKegiatan()
    {
        return $this->hasMany(ProposalKegiatan::class, 'periode_id');
    }
}
