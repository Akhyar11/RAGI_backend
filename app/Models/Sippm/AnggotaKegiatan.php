<?php

namespace App\Models\Sippm;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Simpeg\Pegawai;

class AnggotaKegiatan extends Model
{
    use HasFactory;

    protected $table = 'sippm_anggota_kegiatan';

    protected $fillable = [
        'proposal_id',
        'jenis_tim',
        'pegawai_id',
        'mahasiswa_id',
        'mata_kuliah_id',
        'nama_eksternal',
        'instansi_eksternal',
        'nidn_eksternal',
        'peran_dalam_tim',
        'tugas_kegiatan',
    ];

    protected $appends = ['nama', 'peran', 'tugas'];

    public function getNamaAttribute()
    {
        return $this->pegawai?->nama_lengkap ?? $this->nama_eksternal ?? ($this->mahasiswa_id ? ('Mahasiswa #' . $this->mahasiswa_id) : 'Anggota');
    }

    public function getPeranAttribute()
    {
        return $this->peran_dalam_tim ?? 'anggota';
    }

    public function getTugasAttribute()
    {
        return $this->tugas_kegiatan;
    }

    public function proposal()
    {
        return $this->belongsTo(ProposalKegiatan::class, 'proposal_id');
    }

    public function pegawai()
    {
        return $this->belongsTo(Pegawai::class, 'pegawai_id');
    }
}
