<?php

namespace App\Models\Sinapra;

use App\Models\Aset;
use App\Models\Ruangan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenanceLog extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'sinapra_maintenance_log';

    public const PRIORITAS_RENDAH = 'rendah';
    public const PRIORITAS_SEDANG = 'sedang';
    public const PRIORITAS_TINGGI = 'tinggi';
    public const PRIORITAS_DARURAT = 'darurat';

    public const ALL_PRIORITAS = [
        self::PRIORITAS_RENDAH,
        self::PRIORITAS_SEDANG,
        self::PRIORITAS_TINGGI,
        self::PRIORITAS_DARURAT,
    ];

    protected $fillable = [
        'aset_id',
        'ruangan_id',
        'judul',
        'deskripsi_kerusakan',
        'prioritas',
        'tanggal_lapor',
        'tanggal_mulai',
        'tanggal_selesai',
        'biaya',
        'hasil_perbaikan',
        'status',
        'teknisi_id',
        'pelapor_id',
        'foto_path',
    ];

    protected $casts = [
        'tanggal_lapor' => 'date',
        'tanggal_mulai' => 'date',
        'tanggal_selesai' => 'date',
        'biaya' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(function ($model) {
            if (empty($model->tanggal_lapor)) {
                $model->tanggal_lapor = now()->toDateString();
            }
        });
    }

    /**
     * Relasi ke Aset yang dirawat
     */
    public function aset(): BelongsTo
    {
        return $this->belongsTo(Aset::class, 'aset_id');
    }

    /**
     * Relasi ke Ruangan yang dirawat
     */
    public function ruangan(): BelongsTo
    {
        return $this->belongsTo(Ruangan::class, 'ruangan_id');
    }

    /**
     * Relasi ke Teknisi / User penanggung jawab
     */
    public function teknisi(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teknisi_id');
    }

    /**
     * Relasi ke Pelapor / Staf yang mengajukan perbaikan
     */
    public function pelapor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pelapor_id');
    }
}
