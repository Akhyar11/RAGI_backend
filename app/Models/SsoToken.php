<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'access_token',
    'refresh_token',
    'client_app',
    'access_expires_at',
    'refresh_expires_at',
])]
class SsoToken extends Model
{
    // Tidak ada updated_at (hanya created_at)
    const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'access_expires_at'  => 'datetime',
            'refresh_expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Cek apakah access_token masih berlaku.
     */
    public function isAccessTokenValid(): bool
    {
        return $this->access_expires_at->isFuture();
    }

    /**
     * Cek apakah refresh_token masih berlaku.
     */
    public function isRefreshTokenValid(): bool
    {
        return $this->refresh_expires_at->isFuture();
    }
}
