<?php

namespace App\Models\Simpeg;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DokumenPegawai extends Model
{
    use HasFactory;

    protected $table = 'simpeg_dokumen_pegawai';

    /** Jenis dokumen e-file: nilai tetap domain sesuai enum kolom `jenis_dokumen`. */
    public const JENIS_DOKUMEN = ['ktp', 'kk', 'ijazah', 'sk', 'serdos', 'sertifikat', 'lainnya'];

    protected $fillable = [
        'pegawai_id',
        'nama_dokumen',
        'jenis_dokumen',
        'file_path',
        'file_size',
        'status_verifikasi',
        'catatan_verifikasi',
    ];

    public function pegawai(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class, 'pegawai_id');
    }
}
