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

    /**
     * Pembuatan topik default "Diskusi Umum".
     *
     * Berbeda dari `create` (topik buatan pengguna), topik default adalah
     * artefak sistem yang wajib tersedia bagi seluruh anggota kelas agar
     * mahasiswa bisa berdiskusi tanpa harus menunggu dosen membuat topik.
     * Karena itu hak aksesnya mengikuti `lms.forum.create`.
     */
    public function ensureDefault(User $user): bool
    {
        return $user->hasPermission('lms.forum.create');
    }

    public function delete(User $user, ForumTopik $topik): bool
    {
        return $user->hasPermission('lms.forum.manage');
    }

    /**
     * Ubah judul / pin topik — hanya pengelola kelas.
     */
    public function update(User $user, ForumTopik $topik): bool
    {
        return $user->hasPermission('lms.forum.manage');
    }
}