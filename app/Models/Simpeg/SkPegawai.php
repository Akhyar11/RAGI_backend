<?php

namespace App\Models\Simpeg;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SkPegawai extends Model
{
    use SoftDeletes;

    protected $table = 'simpeg_sk_pegawai';

    protected $fillable = [
        'pegawai_id',
        'kategori_sk_id',
        'nomor_sk',
        'judul_sk',
        'tanggal_sk',
        'tmt_sk',
        'tmt_selesai',
        'pejabat_penetap',
        'file_sk',
        'keterangan',
        'status_verifikasi',
        'catatan_verifikasi',
        'verified_by',
        'verified_at',
    ];

    protected $casts = [
        'tanggal_sk' => 'date',
        'tmt_sk' => 'date',
        'tmt_selesai' => 'date',
        'verified_at' => 'datetime',
    ];

    public function pegawai()
    {
        return $this->belongsTo(Pegawai::class, 'pegawai_id');
    }

    public function kategoriSk()
    {
        return $this->belongsTo(MasterKategoriSk::class, 'kategori_sk_id');
    }

    public function kategori()
    {
        return $this->kategoriSk();
    }

    public function verifier()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
