<?php

namespace App\Policies\Lms;

use App\Models\Lms\ForumPost;
use App\Models\User;

/**
 * Otorisasi forum diskusi kelas.
 *
 * Dua lapis yang sengaja dipisah:
 *   - `viewAny` / `create`  → permission modul (`lms.forum.read` / `lms.forum.create`)
 *   - `delete`              → permission modul ATAU kepemilikan baris pesan
 *
 * Kepemilikan baris (`$post->user_id`) adalah pemeriksaan tingkat resource, bukan
 * permission, sehingga hanya boleh dilakukan di Policy — bukan di controller atau
 * service. Dosen/mahasiswa biasa tetap bisa menghapus pesannya sendiri, sedangkan
 * moderasi pesan milik orang lain butuh `lms.forum.manage`.
 */
class ForumPostPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('lms.forum.read');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('lms.forum.create');
    }

    public function delete(User $user, ForumPost $post): bool
    {
        if ($user->hasPermission('lms.forum.manage')) {
            return true;
        }

        return $user->hasPermission('lms.forum.create')
            && (int) $post->user_id === (int) $user->id;
    }
}