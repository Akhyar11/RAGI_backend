<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'admin_id',
    'target_user_id',
    'impersonation_token_id',
    'admin_token_id',
    'ip_address',
    'user_agent',
    'started_at',
    'ended_at',
])]
class ImpersonationSession extends Model
{
    protected $table = 'core_impersonation_sessions';

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function target(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }

    /**
     * Apakah sesi masih aktif (belum diakhiri).
     */
    public function isActive(): bool
    {
        return is_null($this->ended_at);
    }
}
