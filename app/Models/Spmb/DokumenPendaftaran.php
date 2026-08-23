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
        'jenis_dokumen',
        'file_path',
        'is_verified',
        'catatan',
    ];

    protected $casts = [
        'is_verified' => 'boolean',
    ];

    public function pendaftaranCalonMhs()
    {
        return $this->belongsTo(PendaftaranCalonMhs::class, 'pendaftaran_id');
    }
}
