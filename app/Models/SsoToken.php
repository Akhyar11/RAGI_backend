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
    'revoked_at',
    'rotated_to_id',
])]
class SsoToken extends Model
{
    protected $table = 'core_sso_tokens';

    // Tidak ada updated_at (hanya created_at)
    const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'access_expires_at'  => 'datetime',
            'refresh_expires_at' => 'datetime',
            'revoked_at'         => 'datetime',
            'rotated_to_id'      => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function rotatedTo(): BelongsTo
    {
        return $this->belongsTo(self::class, 'rotated_to_id');
    }

    /**
     * Cek apakah access_token masih berlaku dan belum di-revoke.
     */
    public function isAccessTokenValid(): bool
    {
        return empty($this->revoked_at) && $this->access_expires_at->isFuture();
    }

    /**
     * Cek apakah refresh_token masih berlaku.
     */
    public function isRefreshTokenValid(): bool
    {
        return $this->refresh_expires_at->isFuture();
    }

    /**
     * Cek apakah token berada dalam grace period setelah rotasi (mis. 30 detik).
     */
    public function isInGracePeriod(int $graceSeconds = 30): bool
    {
        if (empty($this->revoked_at)) {
            return false;
        }

        return $this->revoked_at->diffInSeconds(now()) <= $graceSeconds;
    }
}
