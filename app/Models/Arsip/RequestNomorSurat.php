<?php

namespace App\Models\Arsip;

use App\Models\User;
use App\Services\Storage\FileStorageService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class RequestNomorSurat extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'core_arsip_request_nomor';

    protected $fillable = [
        'kode_request',
        'module_origin',
        'reference_type',
        'reference_id',
        'user_id',
        'perihal',
        'tujuan',
        'tanggal_surat',
        'kode_unit',
        'kode_klasifikasi',
        'jumlah_nomor',
        'catatan_pemohon',
        'dokumen_lampiran_path',
        'status',
        'verified_by',
        'verified_at',
        'catatan_verifikasi',
    ];

    protected $casts = [
        'tanggal_surat' => 'date',
        'jumlah_nomor' => 'integer',
        'verified_at' => 'datetime',
    ];

    protected $appends = [
        'dokumen_lampiran_url',
    ];

    public function getDokumenLampiranUrlAttribute(): ?string
    {
        if (!$this->dokumen_lampiran_path) {
            return null;
        }

        return app(FileStorageService::class)->url($this->dokumen_lampiran_path);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function verifikator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function nomorSurat(): HasMany
    {
        return $this->hasMany(NomorSurat::class, 'request_id');
    }
}
