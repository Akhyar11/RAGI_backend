<?php

namespace App\Models\Sippm;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Simpeg\Pegawai;

class HkiDanBuku extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'hki_dan_buku';

    protected $fillable = [
        'proposal_id',
        'pegawai_id',
        'jenis_luaran',
        'judul',
        'nomor_pencatatan_isbn',
        'penerbit_lembaga',
        'tgl_terbit_catat',
        'file_sertifikat_buku',
        'is_verified_lppm',
    ];

    protected $casts = [
        'tgl_terbit_catat' => 'date',
        'is_verified_lppm' => 'boolean',
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
