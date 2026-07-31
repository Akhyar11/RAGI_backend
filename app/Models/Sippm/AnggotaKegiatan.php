<?php

namespace App\Models\Sippm;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Simpeg\Pegawai;

class AnggotaKegiatan extends Model
{
    use HasFactory;

    protected $table = 'anggota_kegiatan';

    protected $fillable = [
        'proposal_id',
        'jenis_anggota',
        'pegawai_id',
        'mahasiswa_id',
        'nama_eksternal',
        'instansi_eksternal',
        'peran',
        'tugas_kegiatan',
    ];

    public function proposal()
    {
        return $this->belongsTo(ProposalKegiatan::class, 'proposal_id');
    }

    public function pegawai()
    {
        return $this->belongsTo(Pegawai::class, 'pegawai_id');
    }
}
