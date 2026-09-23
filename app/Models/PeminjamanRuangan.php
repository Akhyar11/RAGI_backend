<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PeminjamanRuangan extends Model
{
    use HasFactory;

    protected $table = 'sinapra_peminjaman_ruangan';

    protected $fillable = [
        'ruangan_id',
        'user_id',
        'keperluan',
        'tanggal',
        'jam_mulai',
        'jam_selesai',
        'status',
        'laboran_approved_by',
        'laboran_approved_at',
        'catatan_laboran',
        'disetujui_oleh',
        'admin_approved_at',
        'catatan_penolakan',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'laboran_approved_at' => 'datetime',
        'admin_approved_at' => 'datetime',
    ];

    /**
     * Relasi ke Ruangan yang dipinjam
     */
    public function ruangan(): BelongsTo
    {
        return $this->belongsTo(Ruangan::class, 'ruangan_id');
    }

    /**
     * Relasi ke User Peminjam
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Relasi ke User Approver (Admin SINAPRA yang Menyetujui)
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'disetujui_oleh');
    }

    /**
     * Relasi ke Laboran Approver
     */
    public function laboranApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'laboran_approved_by');
    }
}
