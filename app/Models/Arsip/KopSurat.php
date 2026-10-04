<?php

namespace App\Models\Arsip;

use App\Services\Storage\FileStorageService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class KopSurat extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'core_arsip_kop_surat';

    protected $fillable = [
        'nama',
        'versi',
        'tahun_mulai',
        'tahun_selesai',
        'file_path',
        'nama_institusi',
        'alamat_institusi',
        'kontak_institusi',
        'website_institusi',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'tahun_mulai' => 'integer',
        'tahun_selesai' => 'integer',
    ];

    protected $appends = [
        'file_url',
    ];

    public function getFileUrlAttribute(): ?string
    {
        if (!$this->file_path) {
            return null;
        }

        return app(FileStorageService::class)->url($this->file_path);
    }

    public function nomorSurat(): HasMany
    {
        return $this->hasMany(NomorSurat::class, 'kop_surat_id');
    }
}
