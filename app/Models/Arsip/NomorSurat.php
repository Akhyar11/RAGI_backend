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
        'reference_type',
        'reference_id',
        'kop_surat_id',
        'catatan',
        'created_by',
    ];

    protected $casts = [
        'nomor_urut' => 'integer',
        'tahun' => 'integer',
        'tanggal_surat' => 'date',
    ];

    public function pembuat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
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
