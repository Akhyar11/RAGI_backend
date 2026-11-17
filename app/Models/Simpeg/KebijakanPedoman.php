<?php

namespace App\Models\Simpeg;

use App\Models\Arsip\NomorSurat;
use App\Models\User;
use App\Services\Storage\FileStorageService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class KebijakanPedoman extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'simpeg_kebijakan_pedoman';

    protected $fillable = [
        'nomor_surat_arsip_id',
        'nomor_surat',
        'judul',
        'kategori_kebijakan_id',
        'tanggal_penetapan',
        'tanggal_berlaku',
        'pejabat_penetap',
        'deskripsi',
        'file_dokumen_path',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'tanggal_penetapan' => 'date:Y-m-d',
        'tanggal_berlaku' => 'date:Y-m-d',
        'is_active' => 'boolean',
    ];

    protected $appends = [
        'file_dokumen_url',
    ];

    public function getFileDokumenUrlAttribute(): ?string
    {
        if (empty($this->file_dokumen_path)) {
            return null;
        }

        /** @var FileStorageService $files */
        $files = app(FileStorageService::class);

        return $files->signedUrl($this->file_dokumen_path);
    }

    public function nomorSuratArsip(): BelongsTo
    {
        return $this->belongsTo(NomorSurat::class, 'nomor_surat_arsip_id');
    }

    public function kategori(): BelongsTo
    {
        return $this->belongsTo(MasterKategoriKebijakan::class, 'kategori_kebijakan_id');
    }

    public function pembuat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function pengubah(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
