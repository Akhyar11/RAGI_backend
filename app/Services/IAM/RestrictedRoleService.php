<?php

namespace App\Services\IAM;

use App\Models\Role;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class RestrictedRoleService
{
    /**
     * Key pengaturan sistem untuk daftar ID role yang direstriksi.
     * Nilai tersimpan sebagai JSON string, contoh: "[1,2]".
     */
    public const SETTING_KEY = 'restricted_role_ids';

    /**
     * Mengambil daftar ID role yang direstriksi dari pengaturan sistem.
     * Selalu mengembalikan array of int yang valid (bukan array mentah).
     *
     * @return int[]
     */
    public function getRestrictedRoleIds(): array
    {
        $raw = SystemSetting::get(self::SETTING_KEY, '[]');
        $decoded = json_decode((string) $raw, true);

        if (!is_array($decoded)) {
            return [];
        }

        return array_values(array_unique(array_filter(
            array_map('intval', $decoded),
            fn ($id) => $id > 0
        )));
    }

    /**
     * Apakah user boleh melihat roles yang direstriksi?
     * Pengelola IAM (superadmin atau pemegang izin kelola roles)
     * selalu melihat semua roles, sesuai kebijakan RolePolicy::create.
     */
    public function canViewRestrictedRoles(User $user): bool
    {
        return $user->can('create', Role::class);
    }

    /**
     * Menerapkan penyembunyian roles terestriksi pada query daftar roles
     * untuk user yang bukan pengelola IAM.
     *
     * @return bool True jika ada roles yang dikecualikan dari hasil.
     */
    public function applyVisibilityScope(Builder $query, User $user): bool
    {
        if ($this->canViewRestrictedRoles($user)) {
            return false;
        }

        $restrictedIds = $this->getRestrictedRoleIds();

        if (empty($restrictedIds)) {
            return false;
        }

        $query->whereKeyNot($restrictedIds);

        return true;
    }

    /**
     * Sanitasi daftar ID dari input pengaturan: hanya ID integer positif
     * yang benar-benar ada di tabel roles yang dipertahankan.
     *
     * @param mixed[] $ids
     * @return int[]
     */
    public function sanitizeIds(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(
            array_map('intval', $ids),
            fn ($id) => $id > 0
        )));

        if (empty($ids)) {
            return [];
        }

        return Role::whereIn('id', $ids)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}
