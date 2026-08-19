<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PeminjamanRuangan extends Model
{
    use HasFactory;

    protected $table = 'peminjaman_ruangan';

    protected $fillable = [
        'ruangan_id',
        'user_id',
        'keperluan',
        'tanggal',
        'jam_mulai',
        'jam_selesai',
        'status',
        'disetujui_oleh',
        'catatan_penolakan',
    ];

    protected $casts = [
        'tanggal' => 'date',
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
     * Relasi ke User Approver (Yang Menyetujui)
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'disetujui_oleh');
    }
}
