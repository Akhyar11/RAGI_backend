<?php

namespace App\Models\Siakad;

use Illuminate\Database\Eloquent\Model;

class AbsensiMahasiswa extends Model
{
    protected $table = 'siakad_absensi_mahasiswa';

    /**
     * Status bawaan; status tambahan dikelola melalui Master Referensi SIAKAD.
     */
    public const STATUS_HADIR = 'hadir';
    public const STATUS_SAKIT = 'sakit';
    public const STATUS_IZIN = 'izin';
    public const STATUS_ALFA = 'alfa';

    public const STATUSES = [
        self::STATUS_HADIR,
        self::STATUS_SAKIT,
        self::STATUS_IZIN,
        self::STATUS_ALFA,
    ];

    protected $fillable = [
        'pertemuan_id',
        'mahasiswa_id',
        'status',
        'is_submitted',
        'catatan',
    ];

    protected $casts = [
        'is_submitted' => 'boolean',
    ];

    public function pertemuan()
    {
        return $this->belongsTo(Pertemuan::class, 'pertemuan_id');
    }

    public function mahasiswa()
    {
        return $this->belongsTo(Mahasiswa::class, 'mahasiswa_id');
    }
}
