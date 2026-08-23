<?php

namespace App\Models\Spmb;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Spmb\PendaftaranCalonMhs;
use App\Models\Siakad\ProgramStudi;
use App\Models\Spmb\KonversiMahasiswa;

class HasilSeleksi extends Model
{
    use HasFactory;

    protected $table = 'hasil_seleksi';

    public const STATUS_LULUS = 'lulus';
    public const STATUS_CADANGAN = 'cadangan';
    public const STATUS_TIDAK_LULUS = 'tidak_lulus';

    protected $fillable = [
        'pendaftaran_id',
        'program_studi_diterima_id',
        'nilai_total',
        'peringkat',
        'status',
        'catatan',
        'diumumkan_at',
    ];

    protected $casts = [
        'nilai_total' => 'decimal:2',
        'diumumkan_at' => 'datetime',
    ];

    public function pendaftaranCalonMhs()
    {
        return $this->belongsTo(PendaftaranCalonMhs::class, 'pendaftaran_id');
    }

    public function programStudiDiterima()
    {
        return $this->belongsTo(ProgramStudi::class, 'program_studi_diterima_id');
    }

    public function konversiMahasiswa()
    {
        return $this->hasOne(KonversiMahasiswa::class, 'pendaftaran_id');
    }
}
