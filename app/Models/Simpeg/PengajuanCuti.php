<?php

namespace App\Models\Simpeg;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PengajuanCuti extends Model
{
    use HasFactory;

    protected $table = 'simpeg_pengajuan_cuti';

    protected $fillable = [
        'pegawai_id',
        'master_jenis_cuti_id',
        'jenis_cuti',
        'tanggal_mulai',
        'tanggal_selesai',
        'jumlah_hari',
        'alasan',
        'status_approval',
        'approved_by',
        'catatan_approval',
        'file_pendukung',
    ];

    protected $appends = [
        'file_pendukung_url',
    ];

    public function getFilePendukungUrlAttribute(): ?string
    {
        if (empty($this->file_pendukung)) {
            return null;
        }

        if (str_starts_with($this->file_pendukung, 'http://') || str_starts_with($this->file_pendukung, 'https://')) {
            return $this->file_pendukung;
        }

        // Berkas disimpan private → URL publik akan 404. Gunakan Signed URL
        // terpusat (FileStorageService) ke endpoint stream generik.
        return app(\App\Services\Storage\FileStorageService::class)->signedUrl($this->file_pendukung);
    }

    public function pegawai(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class, 'pegawai_id');
    }

    public function masterJenisCuti(): BelongsTo
    {
        return $this->belongsTo(MasterJenisCuti::class, 'master_jenis_cuti_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
