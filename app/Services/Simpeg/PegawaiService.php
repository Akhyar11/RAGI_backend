<?php

namespace App\Services\Simpeg;

use App\Models\Simpeg\Pegawai;
use App\Models\Siakad\Dosen;
use App\Models\Spmb\MasterProgramStudi;

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
            $this->syncDosenRecord($pegawai, $roleIds);

            return $pegawai;
        });
    }

    public function update(Pegawai $pegawai, array $data)
    {
        return \Illuminate\Support\Facades\DB::transaction(function () use ($pegawai, $data) {
            unset($data['email'], $data['username']);

            $roleIds = null;
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
            $this->syncDosenRecord($pegawai, $roleIds ?? []);

            return $pegawai;
        });
    }

    public function delete(Pegawai $pegawai)
    {
        if ($pegawai->dosen) {
            $pegawai->dosen->update([
                'status_aktif' => 'Tidak Aktif',
                'is_active' => false,
            ]);
        }
        return $pegawai->delete();
    }

    /**
     * Cek apakah pegawai memiliki status atau peran sebagai Dosen.
     */
    public function isPegawaiDosen(Pegawai $pegawai, array $roleIds = []): bool
    {
        // 1. Cek field jenis_pegawai
        if (!empty($pegawai->jenis_pegawai) && stripos($pegawai->jenis_pegawai, 'dosen') !== false) {
            return true;
        }

        // 2. Cek roleIds yang dipass
        if (!empty($roleIds)) {
            $roles = \App\Models\Role::whereIn('id', $roleIds)->get();
            foreach ($roles as $r) {
                if (stripos($r->slug, 'dosen') !== false || stripos($r->name, 'dosen') !== false) {
                    return true;
                }
            }
        }

        // 3. Cek relasi roles pada pegawai
        if ($pegawai->relationLoaded('roles')) {
            foreach ($pegawai->roles as $r) {
                if (stripos($r->slug, 'dosen') !== false || stripos($r->name, 'dosen') !== false) {
                    return true;
                }
            }
        } else {
            if ($pegawai->roles()->where(function ($q) {
                $q->where('slug', 'like', '%dosen%')
                  ->orWhere('name', 'like', '%dosen%');
            })->exists()) {
                return true;
            }
        }

        // 4. Cek role pada akun SSO jika ada
        if ($pegawai->user) {
            if ($pegawai->user->roles()->where(function ($q) {
                $q->where('slug', 'like', '%dosen%')
                  ->orWhere('name', 'like', '%dosen%');
            })->exists()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Sinkronisasi data Pegawai berstatus Dosen ke modul SIAKAD (siakad_dosen).
     */
    public function syncDosenRecord(Pegawai $pegawai, array $roleIds = []): ?Dosen
    {
        $isDosen = $this->isPegawaiDosen($pegawai, $roleIds);

        // Cari record Dosen yang sudah ada
        $existingDosen = Dosen::where('pegawai_id', $pegawai->id)->first();
        if (!$existingDosen && !empty($pegawai->nidn)) {
            $existingDosen = Dosen::where('nidn', $pegawai->nidn)->first();
        }
        if (!$existingDosen && !empty($pegawai->nip)) {
            $existingDosen = Dosen::where('nip', $pegawai->nip)->first();
        }
        if (!$existingDosen && !empty($pegawai->user_id)) {
            $existingDosen = Dosen::where('user_id', $pegawai->user_id)->first();
        }

        if (!$isDosen) {
            // Jika bukan dosen tapi sebelumnya ada di siakad_dosen, nonaktifkan
            if ($existingDosen) {
                $existingDosen->update([
                    'status_aktif' => 'Tidak Aktif',
                    'is_active' => false,
                ]);
            }
            return $existingDosen;
        }

        // Tentukan Program Studi (Homebase)
        $prodiId = $existingDosen?->program_studi_id;
        if (!$prodiId && $pegawai->unit_kerja_id) {
            $unitKerja = $pegawai->unitKerja ?? \App\Models\Simpeg\UnitKerja::find($pegawai->unit_kerja_id);
            if ($unitKerja) {
                $matchedProdi = MasterProgramStudi::where('nama', 'like', "%{$unitKerja->nama}%")->first();
                if ($matchedProdi) {
                    $prodiId = $matchedProdi->id;
                }
            }
        }

        $prodi = $prodiId ? MasterProgramStudi::find($prodiId) : null;
        if (!$prodi) {
            $prodi = MasterProgramStudi::first();
            if (!$prodi) {
                $prodi = MasterProgramStudi::create([
                    'kode_prodi' => 'PRODI-DEFAULT',
                    'nama' => 'Program Studi Umum',
                    'jenjang' => 'S1',
                    'is_active' => true,
                ]);
            }
            $prodiId = $prodi->id;
        }

        $isActive = ($pegawai->status === 'aktif');
        $statusAktif = $isActive ? 'Aktif' : 'Tidak Aktif';

        $dosenData = [
            'user_id' => $pegawai->user_id,
            'pegawai_id' => $pegawai->id,
            'nama_lengkap' => $pegawai->nama_lengkap,
            'gelar_depan' => $pegawai->gelar_depan ?: ($existingDosen?->gelar_depan ?? null),
            'gelar_belakang' => $pegawai->gelar_belakang ?: ($existingDosen?->gelar_belakang ?? null),
            'nidn' => $pegawai->nidn ?: ($existingDosen?->nidn ?? null),
            'nuptk' => $pegawai->nuptk ?: ($existingDosen?->nuptk ?? null),
            'nip' => $pegawai->nip ?: ($existingDosen?->nip ?? null),
            'nik' => $pegawai->nik ?: ($existingDosen?->nik ?? null),
            'jenis_kelamin' => in_array($pegawai->jenis_kelamin, ['L', 'P']) ? $pegawai->jenis_kelamin : ($existingDosen?->jenis_kelamin ?? 'L'),
            'tempat_lahir' => $pegawai->tempat_lahir ?: ($existingDosen?->tempat_lahir ?? null),
            'tanggal_lahir' => $pegawai->tanggal_lahir ?: ($existingDosen?->tanggal_lahir ?? null),
            'agama' => $pegawai->agama ?: ($existingDosen?->agama ?? null),
            'telepon' => $pegawai->telepon ?: ($existingDosen?->telepon ?? null),
            'handphone' => $pegawai->telepon ?: ($existingDosen?->handphone ?? null),
            'email' => $pegawai->user?->email ?: ($existingDosen?->email ?? null),
            'program_studi_id' => $prodiId,
            'jabatan_akademik' => $existingDosen?->jabatan_akademik ?? 'Tenaga Pendidik / Dosen',
            'status_aktif' => $statusAktif,
            'is_active' => $isActive,
        ];

        if ($existingDosen) {
            $existingDosen->update($dosenData);
            return $existingDosen;
        }

        return Dosen::create($dosenData);
    }
}
