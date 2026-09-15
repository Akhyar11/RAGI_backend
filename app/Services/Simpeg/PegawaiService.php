<?php

namespace App\Services\Simpeg;

use App\Models\Simpeg\Pegawai;

class PegawaiService
{
    public function getFiltered(array $filters = [])
    {
        $query = Pegawai::with(['user', 'user.roles', 'unitKerja', 'shiftTemplate', 'officeLocation', 'riwayatJabatan.jabatan', 'riwayatPendidikan', 'dosen', 'dosen.programStudi', 'roles']);

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('nama_lengkap', 'like', "%{$search}%")
                  ->orWhere('nip', 'like', "%{$search}%")
                  ->orWhere('nidn', 'like', "%{$search}%")
                  ->orWhere('nuptk', 'like', "%{$search}%")
                  ->orWhere('nik', 'like', "%{$search}%");
            });
        }

        if (!empty($filters['unit_kerja_id'])) {
            $query->where('unit_kerja_id', $filters['unit_kerja_id']);
        }

        if (!empty($filters['role_id'])) {
            $roleId = $filters['role_id'];
            $query->whereHas('roles', function ($q) use ($roleId) {
                $q->where('core_roles.id', $roleId);
            });
        }

        if (!empty($filters['jenis_pegawai'])) {
            $jp = $filters['jenis_pegawai'];
            $query->where(function ($q) use ($jp) {
                $q->where('jenis_pegawai', 'like', "%{$jp}%")
                  ->orWhereHas('roles', function ($r) use ($jp) {
                      $r->where('slug', $jp)
                        ->orWhere('name', 'like', "%{$jp}%")
                        ->orWhere('core_roles.id', $jp);
                  });
            });
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['shift_template_id'])) {
            $query->where('shift_template_id', $filters['shift_template_id']);
        }

        return $query->paginate($filters['per_page'] ?? 15);
    }

    public function create(array $data)
    {
        return \Illuminate\Support\Facades\DB::transaction(function () use ($data) {
            $roleIds = $data['role_ids'] ?? [];
            unset($data['role_ids']);

            if (empty($roleIds) && !empty($data['jenis_pegawai'])) {
                $role = \App\Models\Role::where('slug', strtolower(trim($data['jenis_pegawai'])))->first();
                if ($role) {
                    $roleIds = [$role->id];
                }
            }

            if (!empty($roleIds)) {
                $roles = \App\Models\Role::whereIn('id', $roleIds)->get();
                $data['jenis_pegawai'] = $roles->pluck('name')->implode(', ');
            }

            // Otomatis buatkan akun SSO di core_users dengan password default 'indonusa' jika user_id belum ada
            if (empty($data['user_id'])) {
                $nama = $data['nama_lengkap'] ?? 'Pegawai Baru';
                $nip = !empty($data['nip']) ? preg_replace('/[^0-9]/', '', (string) $data['nip']) : null;
                $cleanName = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', explode(' ', $nama)[0] ?? 'user'));

                $email = !empty($data['email']) ? trim($data['email']) : (($nip ?: $cleanName . rand(100, 999)) . '@campus.ac.id');
                $username = !empty($data['username']) ? trim($data['username']) : ($nip ?: $cleanName . rand(10, 99));

                while (\App\Models\User::where('username', $username)->exists()) {
                    $username = $username . '_' . rand(10, 99);
                }

                $user = \App\Models\User::where('email', $email)->first();
                if (!$user) {
                    $user = \App\Models\User::create([
                        'username' => $username,
                        'email' => $email,
                        'password' => \Illuminate\Support\Facades\Hash::make('indonusa'),
                        'phone' => $data['telepon'] ?? null,
                        'is_active' => true,
                        'is_verified' => true,
                    ]);

                    if (!empty($roleIds)) {
                        $user->roles()->syncWithoutDetaching($roleIds);
                    }
                }

                $data['user_id'] = $user->id;
            }

            unset($data['email'], $data['username']);

            $pegawai = Pegawai::create($data);

            if (!empty($roleIds)) {
                $pegawai->roles()->sync($roleIds);
            }

            $pegawai->load(['roles', 'user.roles']);
            return $pegawai;
        });
    }

    public function update(Pegawai $pegawai, array $data)
    {
        return \Illuminate\Support\Facades\DB::transaction(function () use ($pegawai, $data) {
            unset($data['email'], $data['username']);

            if (isset($data['role_ids'])) {
                $roleIds = (array)$data['role_ids'];
                unset($data['role_ids']);

                $roles = \App\Models\Role::whereIn('id', $roleIds)->get();
                $data['jenis_pegawai'] = $roles->pluck('name')->implode(', ');

                $pegawai->roles()->sync($roleIds);

                if ($pegawai->user) {
                    $pegawai->user->roles()->sync($roleIds);
                }
            }

            $pegawai->update($data);
            $pegawai->load(['roles', 'user.roles']);
            return $pegawai;
        });
    }

    public function delete(Pegawai $pegawai)
    {
        return $pegawai->delete();
    }
}
