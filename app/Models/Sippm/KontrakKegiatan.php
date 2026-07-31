<?php

namespace App\Models\Sippm;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class KontrakKegiatan extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'kontrak_kegiatan';

    protected $fillable = [
        'proposal_id',
        'nomor_kontrak',
        'dana_disetujui',
        'tgl_mulai',
        'tgl_selesai',
        'file_kontrak',
        'status',
    ];

    protected $casts = [
        'dana_disetujui' => 'decimal:2',
        'tgl_mulai' => 'date',
        'tgl_selesai' => 'date',
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
