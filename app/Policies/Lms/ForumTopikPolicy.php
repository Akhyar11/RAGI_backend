<?php

namespace App\Policies\Lms;

use App\Models\Lms\ForumTopik;
use App\Models\User;

/**
 * Otorisasi pembuatan & penghapusan topik forum.
 *
 * Topik adalah konstruksi kelas (dosen pengampu/admin), berbeda dari pesan yang
 * boleh ditulis mahasiswa. Karena itu `create` dan `delete` memakai
 * `lms.forum.manage`, bukan `lms.forum.create`.
 */
class ForumTopikPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('lms.forum.read');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('lms.forum.manage');
    }

    public function delete(User $user, ForumTopik $topik): bool
    {
        return $user->hasPermission('lms.forum.manage');
    }
}