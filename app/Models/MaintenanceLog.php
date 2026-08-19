<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenanceLog extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'maintenance_log';

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
    ];

    protected $casts = [
        'tanggal_lapor' => 'date',
        'tanggal_mulai' => 'date',
        'tanggal_selesai' => 'date',
        'biaya' => 'decimal:2',
    ];

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
}
