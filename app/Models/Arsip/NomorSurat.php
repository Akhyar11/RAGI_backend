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
        'perihal',
        'tujuan',
        'status',
        'module_origin',
        'request_id',
        'pegawai_id',
        'reference_type',
        'reference_id',
        'kop_surat_id',
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
        'file_surat_uploaded_at' => 'datetime',
    ];

    protected $appends = [
        'has_file',
    ];

    public function getHasFileAttribute(): bool
    {
        return !empty($this->file_surat_path);
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
