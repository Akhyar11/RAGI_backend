<?php

namespace App\Models\Simpeg;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CampusAnnouncement extends Model
{
    use SoftDeletes;

    public const CATEGORIES = ['seragam', 'rapat', 'deadline', 'umum'];
    public const TARGET_ROLES = ['all_staff', 'dosen', 'tendik'];
    public const PRIORITIES = ['normal', 'high'];

    protected $table = 'simpeg_campus_announcements';

    protected $fillable = [
        'sender_id',
        'title',
        'category',
        'target_role',
        'content',
        'is_agenda',
        'event_date',
        'event_time',
        'event_location',
        'event_url',
        'priority',
        'recipients_count',
    ];

    protected $casts = [
        'is_agenda' => 'boolean',
        'event_date' => 'date',
        'recipients_count' => 'integer',
    ];

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function scopeForRole(Builder $query, string $role): Builder
    {
        return $query->whereIn('target_role', ['all_staff', $role]);
    }
}
