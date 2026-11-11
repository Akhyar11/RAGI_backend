<?php

namespace App\Services\IAM;

use App\Models\SystemSetting;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use JsonException;
use Throwable;

class SpmbAdminRecipientService
{
    public const SETTING_KEY = 'spmb_admin_notification_user_ids';

    public const MAX_RECIPIENTS = 100;

    public const SORTABLE = ['name', 'username', 'email', 'id'];

    public const DESCRIPTION = 'ID akun penerima notifikasi sistem dan email admin SPMB';

    /** @return list<int> */
    public function configuredIds(): array
    {
        $decoded = json_decode((string) SystemSetting::get(self::SETTING_KEY, '[]'));
        if (! is_array($decoded) || count($decoded) > self::MAX_RECIPIENTS
            || array_filter($decoded, fn ($id) => ! is_int($id) || $id < 1)) {
            return [];
        }

        return array_values(array_unique(array_filter($decoded, fn ($id) => is_int($id) && $id > 0)));
    }

    /** @return Collection<int, User> */
    public function recipients(?int $excludedUserId = null): Collection
    {
        return User::query()->whereIn('id', $this->configuredIds())->where('is_active', true)
            ->when($excludedUserId !== null, fn ($query) => $query->whereKeyNot($excludedUserId))
            ->orderBy('id')->get();
    }

    public function setting(): array
    {
        $setting = SystemSetting::query()->where('key', self::SETTING_KEY)->first();

        return [
            'id' => $setting?->id,
            'key' => self::SETTING_KEY,
            'value' => $setting?->value ?? '[]',
            'description' => $setting?->description ?? self::DESCRIPTION,
            'users' => User::query()->whereIn('id', $this->configuredIds())->orderBy('id')->get()
                ->map(fn (User $user) => $this->userData($user))->all(),
        ];
    }

    /** @return list<int> */
    public function validateValue(?string $value): array
    {
        try {
            $decoded = json_decode((string) $value, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw ValidationException::withMessages(['user_ids' => 'Mapping admin SPMB harus berupa JSON array ID akun.']);
        }

        Validator::make(['user_ids' => $decoded], [
            'user_ids' => ['bail', 'present', 'array', 'list', 'max:'.self::MAX_RECIPIENTS],
            'user_ids.*' => ['bail', 'integer', 'min:1', function (string $attribute, mixed $id, \Closure $fail): void {
                if (! is_int($id)) {
                    $fail('ID akun harus berupa bilangan bulat positif.');
                }
            }],
        ], [
            'user_ids.array' => 'Mapping admin SPMB harus berupa JSON array ID akun.',
            'user_ids.list' => 'Mapping admin SPMB harus berupa daftar ID akun.',
            'user_ids.max' => 'Maksimal '.self::MAX_RECIPIENTS.' akun penerima admin SPMB.',
            'user_ids.exists' => 'Semua akun penerima admin SPMB harus tersedia dan aktif.',
            'user_ids.*.integer' => 'ID akun harus berupa bilangan bulat positif.',
            'user_ids.*.min' => 'ID akun harus berupa bilangan bulat positif.',
        ])->validate();

        Validator::make(['user_ids' => $decoded], [
            'user_ids' => [Rule::exists('core_users', 'id')->where('is_active', true)],
        ], ['user_ids.exists' => 'Semua akun penerima admin SPMB harus tersedia dan aktif.'])->validate();

        return array_values(array_unique($decoded));
    }

    public function save(?string $value): SystemSetting
    {
        $ids = $this->validateValue($value);

        return DB::transaction(function () use ($ids): SystemSetting {
            $setting = SystemSetting::firstOrNew(['key' => self::SETTING_KEY]);
            $oldValues = ['value' => $setting->getOriginal('value')];
            $setting->value = json_encode($ids, JSON_THROW_ON_ERROR);
            $setting->description = self::DESCRIPTION;
            $setting->save();

            try {
                AuditLogService::record(
                    module: 'IAM', action: 'update_system_setting', tableName: $setting->getTable(),
                    recordId: $setting->id, oldValues: $oldValues, newValues: ['value' => $setting->value],
                );
            } catch (Throwable $exception) {
                Log::warning('Gagal mencatat audit mapping admin SPMB: '.$exception->getMessage());
            }

            return $setting;
        });
    }

    public function options(array $filters): LengthAwarePaginator
    {
        $query = User::query()->where('is_active', true);
        if (! empty($filters['search'])) {
            $search = trim($filters['search']);
            $query->where(fn ($builder) => $builder->where('name', 'like', "%{$search}%")
                ->orWhere('username', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"));
        }
        $sortBy = in_array($filters['sort_by'] ?? 'name', self::SORTABLE, true) ? ($filters['sort_by'] ?? 'name') : 'name';
        $sortOrder = ($filters['sort_order'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

        return $query->orderBy($sortBy, $sortOrder)->orderBy('id', $sortOrder)
            ->paginate(max(1, min(100, (int) ($filters['per_page'] ?? 15))))
            ->through(fn (User $user) => $this->userData($user));
    }

    private function userData(User $user): array
    {
        return [
            'id' => $user->id, 'name' => $user->name, 'username' => $user->username,
            'email' => $user->email, 'is_active' => $user->is_active,
        ];
    }
}
