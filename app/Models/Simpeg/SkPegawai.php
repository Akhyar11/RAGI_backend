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

    protected $appends = [
        'file_sk_url',
    ];

    public function getFileSkUrlAttribute(): ?string
    {
        if (empty($this->file_sk)) {
            return null;
        }

        if (str_starts_with($this->file_sk, 'http://') || str_starts_with($this->file_sk, 'https://')) {
            return $this->file_sk;
        }

        $files = app(\App\Services\Storage\FileStorageService::class);
        return $files->signedUrl($this->file_sk)
            ?? $files->temporaryUrl($this->file_sk, now()->addMinutes(60))
            ?? $files->url($this->file_sk, private: true);
    }

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
