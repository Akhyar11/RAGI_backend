<?php

namespace App\Models\Simpeg;

use App\Models\Siakad\Dosen;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PresensiPengajaranException extends Model
{
    protected $table = 'simpeg_presensi_pengajaran_exceptions';

    protected $fillable = [
        'dosen_id',
        'periode',
        'valid_until',
        'reason',
        'granted_by',
        'revoked_at',
        'revoked_by',
        'revocation_reason',
    ];

    protected $casts = [
        'valid_until' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function dosen(): BelongsTo
    {
        return $this->belongsTo(Dosen::class, 'dosen_id');
    }

    public function grantor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by');
    }

    public function revoker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }
}
