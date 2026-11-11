<?php

namespace App\Services\Notifications;

use App\Models\CoreNotification;
use App\Models\User;
use App\Notifications\SystemInboxEmailNotification;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class SystemNotificationService
{
    public const PER_PAGE = 15;

    public const PER_PAGE_MAX = 100;

    public const SORTABLE = ['created_at', 'read_at', 'priority'];

    public function create(User $user, array $data): ?CoreNotification
    {
        $dedupKey = trim((string) ($data['dedup_key'] ?? ''));
        if ($dedupKey === '') {
            $dedupKey = (string) Str::uuid();
        }

        try {
            $notification = CoreNotification::query()->firstOrCreate(
                ['user_id' => $user->id, 'dedup_key' => $dedupKey],
                [
                    'module_id' => $data['module_id'] ?? null,
                    'type' => $data['type'],
                    'title' => $data['title'],
                    'message' => $data['message'] ?? null,
                    'url' => $data['url'] ?? null,
                    'entity_type' => $data['entity_type'] ?? null,
                    'entity_id' => $data['entity_id'] ?? null,
                    'priority' => $data['priority'] ?? 'normal',
                    'is_read' => false,
                ]
            );
        } catch (UniqueConstraintViolationException) {
            return null;
        }

        if (! $notification->wasRecentlyCreated) {
            return null;
        }

        if (($data['email'] ?? true) && filled($user->email)) {
            DB::afterCommit(function () use ($user, $notification): void {
                try {
                    $user->notify(new SystemInboxEmailNotification($notification));
                } catch (Throwable $exception) {
                    Log::warning('Gagal mengantrikan email notifikasi sistem.', [
                        'notification_id' => $notification->id,
                        'user_id' => $user->id,
                        'message' => $exception->getMessage(),
                    ]);
                }
            });
        }

        return $notification;
    }

    public function index(User $user, array $filters = []): LengthAwarePaginator
    {
        $perPage = max(1, min(self::PER_PAGE_MAX, (int) ($filters['per_page'] ?? self::PER_PAGE)));
        $query = CoreNotification::query()->with('module:id,name,code,primary_color')->where('user_id', $user->id);

        if (! empty($filters['module_id'])) {
            $query->where('module_id', $filters['module_id']);
        }
        if (array_key_exists('is_read', $filters) && $filters['is_read'] !== null) {
            $query->where('is_read', (bool) $filters['is_read']);
        }
        if (! empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $query->where(fn ($builder) => $builder
                ->where('title', 'like', "%{$search}%")
                ->orWhere('message', 'like', "%{$search}%"));
        }

        $sortBy = in_array($filters['sort_by'] ?? 'created_at', self::SORTABLE, true)
            ? ($filters['sort_by'] ?? 'created_at')
            : 'created_at';
        $sortOrder = ($filters['sort_order'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        return $query->orderBy($sortBy, $sortOrder)->orderBy('id', $sortOrder)->paginate($perPage);
    }

    public function unreadCount(User $user): int
    {
        return CoreNotification::query()
            ->where('user_id', $user->id)
            ->where('is_read', false)
            ->count();
    }

    public function markAsRead(User $user, int $id): CoreNotification
    {
        $notification = CoreNotification::query()->where('user_id', $user->id)->findOrFail($id);
        CoreNotification::query()->whereKey($notification->id)->where('is_read', false)
            ->update(['is_read' => true, 'read_at' => now(), 'updated_at' => now()]);

        return $notification->refresh()->load('module:id,name,code,primary_color');
    }

    public function markAllAsRead(User $user): int
    {
        return CoreNotification::query()
            ->where('user_id', $user->id)
            ->where('is_read', false)
            ->update(['is_read' => true, 'read_at' => now(), 'updated_at' => now()]);
    }
}
