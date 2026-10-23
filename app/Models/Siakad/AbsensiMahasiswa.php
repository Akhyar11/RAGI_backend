<?php

namespace App\Models\Siakad;

use App\Models\Concerns\HasPresensiSyncStatus;
use Illuminate\Database\Eloquent\Model;

class AbsensiMahasiswa extends Model
{
    use HasPresensiSyncStatus;

    protected $table = 'siakad_absensi_mahasiswa';

    /**
     * Status kehadiran yang sah (closed-set domain, sesuai kolom `status`).
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
        'catatan',
        'sync_status',
        'sync_error',
        'synced_at',
    ];

    protected $casts = [
        'synced_at' => 'datetime',
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
