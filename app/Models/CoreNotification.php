<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoreNotification extends Model
{
    protected $table = 'core_notifications';

    protected $hidden = ['dedup_key'];

    protected $fillable = [
        'user_id',
        'module_id',
        'type',
        'title',
        'message',
        'url',
        'entity_type',
        'entity_id',
        'priority',
        'is_read',
        'read_at',
        'dedup_key',
    ];

    protected function casts(): array
    {
        return [
            'entity_id' => 'integer',
            'is_read' => 'boolean',
            'read_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }
}
