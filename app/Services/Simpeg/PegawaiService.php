<?php

namespace App\Services\Simpeg;

use App\Models\Simpeg\Pegawai;

class PegawaiService
{
    public function getFiltered(array $filters = [])
    {
        $query = Pegawai::with(['user', 'unitKerja', 'shiftTemplate', 'officeLocation', 'riwayatJabatan.jabatan', 'riwayatPendidikan']);

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('nama_lengkap', 'like', "%{$search}%")
                  ->orWhere('nip', 'like', "%{$search}%")
                  ->orWhere('nik', 'like', "%{$search}%");
            });
        }

        if (!empty($filters['unit_kerja_id'])) {
            $query->where('unit_kerja_id', $filters['unit_kerja_id']);
        }

        if (!empty($filters['jenis_pegawai'])) {
            $query->where('jenis_pegawai', $filters['jenis_pegawai']);
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

                    $jenisPegawai = $data['jenis_pegawai'] ?? 'dosen';
                    $roleSlug = ($jenisPegawai === 'dosen') ? 'dosen' : 'tendik';
                    $role = \App\Models\Role::where('slug', $roleSlug)->first();
                    if ($role) {
                        $user->roles()->syncWithoutDetaching([$role->id]);
                    }
                }

                $data['user_id'] = $user->id;
            }

            unset($data['email'], $data['username']);

            return Pegawai::create($data);
        });
    }

    public function update(Pegawai $pegawai, array $data)
    {
        unset($data['email'], $data['username']);
        $pegawai->update($data);
        return $pegawai;
    }

    public function delete(Pegawai $pegawai)
    {
        return $pegawai->delete();
    }
}
