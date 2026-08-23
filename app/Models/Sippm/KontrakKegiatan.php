<?php

namespace App\Models\Sippm;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class KontrakKegiatan extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'sippm_kontrak_kegiatan';

    protected $fillable = [
        'proposal_id',
        'nomor_kontrak',
        'dana_disetujui',
        'tgl_mulai',
        'tgl_selesai',
        'file_kontrak',
        'file_spk_ttd',
        'status_spk',
        'status',
    ];

    protected $casts = [
        'dana_disetujui' => 'decimal:2',
        'tgl_mulai' => 'date:Y-m-d',
        'tgl_selesai' => 'date:Y-m-d',
    ];

    public function proposal()
    {
        return $this->belongsTo(ProposalKegiatan::class, 'proposal_id');
    }

    public function pencairanDana()
    {
        return $this->hasMany(PencairanDanaHibah::class, 'kontrak_id');
    }

    public function laporan()
    {
        return $this->hasMany(LaporanKegiatan::class, 'kontrak_id');
    }
}
