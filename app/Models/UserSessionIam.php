<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'token',
    'ip_address',
    'user_agent',
    'expires_at',
])]
class UserSessionIam extends Model
{
    protected $table = 'user_sessions_iam';

    const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Cek apakah sesi masih aktif (belum expired).
     */
    public function isActive(): bool
    {
        return is_null($this->expires_at) || $this->expires_at->isFuture();
    }
}
