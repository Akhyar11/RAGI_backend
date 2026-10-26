<?php

namespace App\Models\Arsip;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class NomorSurat extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'core_arsip_nomor_surat';

    protected $fillable = [
        'nomor_surat',
        'nomor_urut',
        'kode_unit',
        'kode_klasifikasi',
        'bulan_romawi',
        'tahun',
        'tanggal_surat',
        'is_backdate',
        'alasan_backdate',
        'perihal',
        'tujuan',
        'status',
        'module_origin',
        'request_id',
        'pegawai_id',
        'reference_type',
        'reference_id',
        'kop_surat_id',
        'file_path',
        'file_surat_path',
        'file_surat_uploaded_at',
        'file_surat_uploaded_by',
        'catatan',
        'created_by',
    ];

    protected $casts = [
        'nomor_urut' => 'integer',
        'tahun' => 'integer',
        'tanggal_surat' => 'date',
        'is_backdate' => 'boolean',
        'file_surat_uploaded_at' => 'datetime',
    ];

    protected $appends = [
        'has_file',
        'dokumen_url',
        'has_dokumen',
    ];

    public function getHasFileAttribute(): bool
    {
        return !empty($this->file_surat_path) || !empty($this->file_path);
    }

    public function getDokumenUrlAttribute(): ?string
    {
        $path = $this->file_path ?: ($this->file_surat_path ?: $this->request?->dokumen_lampiran_path);
        if (!$path) {
            return null;
        }

        return app(\App\Services\Storage\FileStorageService::class)->url($path);
    }

    public function getHasDokumenAttribute(): bool
    {
        return !empty($this->file_path) || !empty($this->file_surat_path) || !empty($this->request?->dokumen_lampiran_path);
    }

    public function pembuat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function pegawai(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Simpeg\Pegawai::class, 'pegawai_id');
    }

    public function fileUploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'file_surat_uploaded_by');
    }

    public function kopSurat(): BelongsTo
    {
        return $this->belongsTo(KopSurat::class, 'kop_surat_id');
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(RequestNomorSurat::class, 'request_id');
    }
}
