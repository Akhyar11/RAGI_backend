<?php

namespace App\Models\Simpeg;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UsulanJafung extends Model
{
    use HasFactory;

    protected $table = 'simpeg_usulan_jafung';

    protected $fillable = [
        'pegawai_id',
        'jafung_asal_id',
        'jafung_tujuan_id',
        'nomor_sk',
        'tanggal_mulai_berlaku',
        'angka_kredit_usulan',
        'status_usulan',
        'file_sk_hasil',
        'catatan_reviewer',
    ];

    protected $casts = [
        'tanggal_mulai_berlaku' => 'date:Y-m-d',
        'angka_kredit_usulan' => 'integer',
    ];

    protected $appends = [
        'file_sk_hasil_url',
    ];

    public function getFileSkHasilUrlAttribute(): ?string
    {
        if (empty($this->file_sk_hasil)) {
            return null;
        }

        if (str_starts_with($this->file_sk_hasil, 'http://') || str_starts_with($this->file_sk_hasil, 'https://')) {
            return $this->file_sk_hasil;
        }

        $files = app(\App\Services\Storage\FileStorageService::class);
        return $files->signedUrl($this->file_sk_hasil)
            ?? $files->url($this->file_sk_hasil, private: true);
    }

    public function pegawai(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class, 'pegawai_id');
    }

    public function jafungAsal(): BelongsTo
    {
        return $this->belongsTo(JabatanFungsionalAkademik::class, 'jafung_asal_id');
    }

    public function jafungTujuan(): BelongsTo
    {
        return $this->belongsTo(JabatanFungsionalAkademik::class, 'jafung_tujuan_id');
    }
}
