<?php

namespace App\Models\Spmb;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Spmb\PendaftaranCalonMhs;

class DokumenPendaftaran extends Model
{
    use HasFactory;

    protected $table = 'spmb_dokumen_pendaftaran';

    protected $fillable = [
        'pendaftaran_id',
        'berkas_requirement_id',
        'jenis_dokumen',
        'file_path',
        'is_verified',
        'verified_by',
        'verified_at',
        'catatan',
    ];

    protected $casts = [
        'is_verified' => 'boolean',
        'verified_at' => 'datetime',
    ];

    public function pendaftaranCalonMhs()
    {
        return $this->belongsTo(PendaftaranCalonMhs::class, 'pendaftaran_id');
    }

    public function berkasRequirement()
    {
        return $this->belongsTo(BerkasRequirement::class, 'berkas_requirement_id');
    }
}
