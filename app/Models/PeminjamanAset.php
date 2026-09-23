<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PeminjamanAset extends Model
{
    use HasFactory;

    protected $table = 'sinapra_peminjaman_aset';

    protected $fillable = [
        'aset_id',
        'user_id',
        'keperluan',
        'tanggal_pinjam',
        'tanggal_kembali_rencana',
        'tanggal_kembali_aktual',
        'kondisi_kembali',
        'status',
        'laboran_approved_by',
        'laboran_approved_at',
        'catatan_laboran',
        'catatan_penolakan',
        'disetujui_oleh',
        'admin_approved_at',
    ];

    protected $casts = [
        'tanggal_pinjam' => 'date',
        'tanggal_kembali_rencana' => 'date',
        'tanggal_kembali_aktual' => 'date',
        'laboran_approved_at' => 'datetime',
        'admin_approved_at' => 'datetime',
    ];

    /**
     * Relasi ke Aset yang dipinjam
     */
    public function aset(): BelongsTo
    {
        return $this->belongsTo(Aset::class, 'aset_id');
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
