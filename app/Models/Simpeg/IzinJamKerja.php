<?php

namespace App\Models\Simpeg;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class IzinJamKerja extends Model
{
    use SoftDeletes;

    protected $table = 'simpeg_izin_jam_kerja';

    protected $fillable = [
        'pegawai_id',
        'master_jenis_izin_id',
        'tanggal',
        'jam_mulai',
        'jam_selesai',
        'alasan',
        'file_bukti',
        'status',
        'catatan_approval',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'approved_at' => 'datetime',
    ];

    protected $appends = [
        'file_bukti_url',
    ];

    public function getFileBuktiUrlAttribute(): ?string
    {
        if (empty($this->file_bukti)) {
            return null;
        }

        if (str_starts_with($this->file_bukti, 'http://') || str_starts_with($this->file_bukti, 'https://')) {
            return $this->file_bukti;
        }

        $files = app(\App\Services\Storage\FileStorageService::class);
        return $files->signedUrl($this->file_bukti)
            ?? $files->temporaryUrl($this->file_bukti, now()->addMinutes(60))
            ?? $files->url($this->file_bukti, private: true);
    }

    public function pegawai()
    {
        return $this->belongsTo(Pegawai::class, 'pegawai_id');
    }

    public function jenisIzin()
    {
        return $this->belongsTo(MasterJenisIzinJamKerja::class, 'master_jenis_izin_id');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
