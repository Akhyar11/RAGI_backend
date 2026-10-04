<?php

namespace App\Models\Simpeg;

use App\Models\User;
use App\Services\Storage\FileStorageService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TandaTanganPegawai extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'simpeg_tanda_tangan';

    protected $fillable = [
        'user_id',
        'pegawai_id',
        'file_path',
        'tipe',
        'judul',
        'qr_token',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function pegawai(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class, 'pegawai_id');
    }
}
