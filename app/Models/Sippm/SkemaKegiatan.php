<?php

namespace App\Models\Sippm;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SkemaKegiatan extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'sippm_skema_kegiatan';

    protected $fillable = [
        'kode',
        'nama',
        'tipe',
        'sumber_dana',
        'maksimal_anggaran',
        'deskripsi',
        'is_active',
    ];

    protected $casts = [
        'maksimal_anggaran' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    protected $appends = ['nama_skema', 'kode_skema', 'maksimal_dana', 'jenis_kegiatan'];

    public function getNamaSkemaAttribute()
    {
        return $this->nama;
    }

    public function getKodeSkemaAttribute()
    {
        return $this->kode;
    }

    public function getMaksimalDanaAttribute()
    {
        return (float) $this->maksimal_anggaran;
    }

    public function getJenisKegiatanAttribute()
    {
        return $this->tipe;
    }

    public function proposalKegiatan()
    {
        return $this->hasMany(ProposalKegiatan::class, 'skema_id');
    }
}
