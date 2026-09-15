<?php

namespace App\Services\Simpeg;

use App\Models\Simpeg\Pegawai;
use App\Models\Siakad\Dosen;
use App\Models\Spmb\MasterProgramStudi;
use App\Models\User;
use App\Models\Role;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

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

            $email = !empty($data['email']) ? trim($data['email']) : null;
            $username = !empty($data['username']) ? trim($data['username']) : null;
            unset($data['email'], $data['username']);

            $pegawai = Pegawai::create($data);

            if (!empty($roleIds)) {
                $pegawai->roles()->sync($roleIds);
            }

            $pegawai->load(['roles']);

            // Buat atau pastikan akun SSO di core_users dengan skala prioritas: NIDN -> NUPTK -> NIP
            $this->ensureSsoUserForPegawai($pegawai, [
                'role_ids' => $roleIds,
                'email' => $email,
                'username' => $username,
            ]);

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

                $roles = Role::whereIn('id', $roleIds)->get();
                $data['jenis_pegawai'] = $roles->pluck('name')->implode(', ');

                $pegawai->roles()->sync($roleIds);

                if ($pegawai->user) {
                    $pegawai->user->roles()->sync($roleIds);
                }
            }

            $pegawai->update($data);
            $pegawai->load(['roles']);

            // Pastikan akun SSO tetap ada dan sinkron dengan perannya
            $this->ensureSsoUserForPegawai($pegawai, [
                'role_ids' => $roleIds ?? [],
            ]);

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

    /**
     * Membersihkan nilai identifier (NIDN/NUPTK/NIP) dari placeholder kosong atau karakter tidak valid.
     */
    public function sanitizeIdentifier(?string $val): ?string
    {
        if ($val === null) {
            return null;
        }
        $trimmed = trim($val);
        $invalid = ['-', '--', '---', '0', 'n/a', 'none', 'null', ''];
        if (in_array(strtolower($trimmed), $invalid, true)) {
            return null;
        }
        return $trimmed;
    }

    /**
     * Menentukan username berdasarkan skala prioritas: NIDN -> NUPTK -> NIP.
     * Jika memiliki ketiganya, gunakan NIDN. Jika hanya NUPTK dan NIP, gunakan NUPTK.
     * Jika hanya NIP, gunakan NIP. Fallback ke nama depan + angka acak.
     */
    public function resolveUsernamePriority(?string $nidn, ?string $nuptk, ?string $nip, ?string $nama = null): string
    {
        $cleanNidn = $this->sanitizeIdentifier($nidn);
        if ($cleanNidn !== null) {
            return $cleanNidn;
        }

        $cleanNuptk = $this->sanitizeIdentifier($nuptk);
        if ($cleanNuptk !== null) {
            return $cleanNuptk;
        }

        $cleanNip = $this->sanitizeIdentifier($nip);
        if ($cleanNip !== null) {
            return $cleanNip;
        }

        $cleanName = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', explode(' ', $nama ?? 'user')[0] ?? 'user'));
        if (empty($cleanName)) {
            $cleanName = 'pegawai';
        }

        return $cleanName . rand(10, 99);
    }

    /**
     * Menentukan alamat email akun SSO.
     */
    public function resolveEmail(?string $email, string $username, ?string $nama = null): string
    {
        $cleanEmail = $this->sanitizeIdentifier($email);
        if ($cleanEmail !== null && filter_var($cleanEmail, FILTER_VALIDATE_EMAIL)) {
            return strtolower($cleanEmail);
        }

        return strtolower($username) . '@campus.ac.id';
    }

    /**
     * Memastikan akun SSO di core_users dibuat atau dihubungkan ke Pegawai,
     * dengan username mematuhi skala prioritas (NIDN -> NUPTK -> NIP).
     */
    public function ensureSsoUserForPegawai(Pegawai $pegawai, array $options = []): User
    {
        // 1. Cek jika user sudah ada dan terhubung
        if (!empty($pegawai->user_id)) {
            $existingUser = User::find($pegawai->user_id);
            if ($existingUser) {
                $this->assignRolesToUser($existingUser, $pegawai, $options['role_ids'] ?? []);
                return $existingUser;
            }
        }

        // 2. Tentukan username berdasarkan skala prioritas: NIDN -> NUPTK -> NIP -> cleanName
        $username = $options['username'] ?? $this->resolveUsernamePriority(
            $pegawai->nidn,
            $pegawai->nuptk,
            $pegawai->nip,
            $pegawai->nama_lengkap
        );

        // 3. Tentukan email
        $email = $options['email'] ?? $this->resolveEmail(
            $options['email'] ?? null,
            $username,
            $pegawai->nama_lengkap
        );

        // 4. Cek apakah user dengan username atau email ini sudah pernah ada di database
        $user = User::where('username', $username)->first();
        if (!$user) {
            $user = User::where('email', $email)->first();
        }

        if (!$user) {
            // Pastikan username unik jika kebetulan ada konflik
            $baseUsername = $username;
            while (User::where('username', $username)->exists()) {
                $username = $baseUsername . '_' . rand(10, 99);
            }

            // Pastikan email unik jika kebetulan ada konflik
            $baseEmail = $email;
            $emailParts = explode('@', $baseEmail);
            while (User::where('email', $email)->exists()) {
                $email = $emailParts[0] . rand(10, 99) . '@' . ($emailParts[1] ?? 'campus.ac.id');
            }

            $user = User::create([
                'username' => $username,
                'email' => $email,
                'password' => Hash::make('indonusa'),
                'phone' => $pegawai->telepon ?? null,
                'is_active' => true,
                'is_verified' => true,
            ]);
        }

        // 5. Hubungkan user_id ke pegawai
        if ($pegawai->user_id !== $user->id) {
            $pegawai->update(['user_id' => $user->id]);
        }

        // 6. Hubungkan user_id ke record siakad_dosen jika ada
        if ($pegawai->dosen && $pegawai->dosen->user_id !== $user->id) {
            $pegawai->dosen->update(['user_id' => $user->id]);
        }

        // 7. Lampirkan roles yang sesuai
        $this->assignRolesToUser($user, $pegawai, $options['role_ids'] ?? []);

        return $user;
    }

    /**
     * Sinkronisasi roles antara User SSO dan Pegawai.
     */
    public function assignRolesToUser(User $user, Pegawai $pegawai, array $roleIds = []): void
    {
        $allRoleIds = $roleIds;

        // Ambil role_ids dari pegawai jika ada
        if (empty($allRoleIds) && $pegawai->relationLoaded('roles')) {
            $allRoleIds = $pegawai->roles->pluck('id')->toArray();
        } elseif (empty($allRoleIds)) {
            $allRoleIds = $pegawai->roles()->pluck('core_roles.id')->toArray();
        }

        // Cek jika pegawai adalah dosen, pastikan role 'dosen' terlampir
        if ($this->isPegawaiDosen($pegawai, $allRoleIds)) {
            $dosenRole = Role::firstOrCreate(
                ['slug' => 'dosen'],
                ['name' => 'Dosen Pengajar', 'is_active' => true]
            );
            if ($dosenRole && !in_array($dosenRole->id, $allRoleIds, true)) {
                $allRoleIds[] = $dosenRole->id;
            }
        }

        if (!empty($allRoleIds)) {
            $user->roles()->syncWithoutDetaching($allRoleIds);
            $pegawai->roles()->syncWithoutDetaching($allRoleIds);
        }
    }
}
