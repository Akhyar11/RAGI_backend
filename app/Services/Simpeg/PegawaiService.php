<?php

namespace App\Services\Simpeg;

use App\Models\Simpeg\Pegawai;
use App\Models\Siakad\Dosen;
use App\Models\Spmb\MasterProgramStudi;
use App\Models\User;
use App\Models\Role;
use App\Models\Aset;
use App\Models\PeminjamanAset;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PegawaiService
{
    public function getFiltered(array $filters = [])
    {
        $query = Pegawai::with(['user', 'user.roles', 'unitKerja', 'jabatanFungsional', 'shiftTemplate', 'officeLocation', 'riwayatJabatan.jabatan', 'riwayatPendidikan', 'dosen', 'dosen.programStudi', 'roles']);

        // Sembunyikan akun ber-role Superadmin atau role terestriksi dari daftar pegawai SIMPEG
        $restrictedRoleIds = app(\App\Services\IAM\RestrictedRoleService::class)->getRestrictedRoleIds();
        $query->whereDoesntHave('user.roles', function ($q) use ($restrictedRoleIds) {
            $q->where('core_roles.slug', 'superadmin');
            if (!empty($restrictedRoleIds)) {
                $q->orWhereIn('core_roles.id', $restrictedRoleIds);
            }
        })->whereDoesntHave('roles', function ($q) use ($restrictedRoleIds) {
            $q->where('core_roles.slug', 'superadmin');
            if (!empty($restrictedRoleIds)) {
                $q->orWhereIn('core_roles.id', $restrictedRoleIds);
            }
        });

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
        // Enforce clearance jika status diubah menjadi keluar / pensiun / berhenti
        $newStatus = isset($data['status']) ? strtolower(trim((string)$data['status'])) : null;
        $newStatusKepegawaian = isset($data['status_kepegawaian']) ? strtolower(trim((string)$data['status_kepegawaian'])) : null;

        $isDeactivating = in_array($newStatus, ['keluar', 'pensiun', 'berhenti']) ||
                          in_array($newStatusKepegawaian, ['keluar', 'pensiun', 'berhenti']);

        if ($isDeactivating) {
            $clearance = $this->getClearanceStatus($pegawai);
            if (!$clearance['is_cleared']) {
                $reasons = [];
                if ($clearance['aset_dipegang_count'] > 0) {
                    $reasons[] = "masih memegang {$clearance['aset_dipegang_count']} aset dinas";
                }
                if ($clearance['peminjaman_aktif_count'] > 0) {
                    $reasons[] = "masih memiliki {$clearance['peminjaman_aktif_count']} peminjaman fasilitas aktif";
                }
                throw ValidationException::withMessages([
                    'status' => ['Tidak dapat mengubah status menjadi keluar/pensiun karena pegawai ' . implode(' dan ', $reasons) . '. Selesaikan clearance inventaris di SINAPRA terlebih dahulu.']
                ]);
            }
        }

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
            $pegawai->load(['roles', 'user']);

            // Pastikan nama SSO user juga sinkron dengan nama_lengkap pegawai terbaru
            if ($pegawai->user && !empty($pegawai->nama_lengkap) && $pegawai->user->name !== $pegawai->nama_lengkap) {
                $pegawai->user->update(['name' => $pegawai->nama_lengkap]);
            }

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
        $clearance = $this->getClearanceStatus($pegawai);
        if (!$clearance['is_cleared']) {
            $reasons = [];
            if ($clearance['aset_dipegang_count'] > 0) {
                $reasons[] = "masih memegang {$clearance['aset_dipegang_count']} aset dinas";
            }
            if ($clearance['peminjaman_aktif_count'] > 0) {
                $reasons[] = "masih memiliki {$clearance['peminjaman_aktif_count']} peminjaman fasilitas aktif";
            }
            throw ValidationException::withMessages([
                'pegawai' => ['Tidak dapat menghapus pegawai karena pegawai ' . implode(' dan ', $reasons) . '. Selesaikan clearance inventaris di SINAPRA terlebih dahulu.']
            ]);
        }

        if ($pegawai->dosen) {
            $pegawai->dosen->update([
                'status_aktif' => 'Tidak Aktif',
                'is_active' => false,
            ]);
        }
        return $pegawai->delete();
    }

    /**
     * Cek status clearance aset dinas dan peminjaman fasilitas pegawai.
     */
    public function getClearanceStatus(Pegawai $pegawai): array
    {
        $asetDipegang = Aset::where('penanggung_jawab_pegawai_id', $pegawai->id)
            ->where('status', '!=', 'dihapuskan')
            ->get(['id', 'kode_aset', 'nama_aset', 'nomor_seri', 'status', 'kondisi']);

        $peminjamanAktif = collect();
        if ($pegawai->user_id) {
            $peminjamanAktif = PeminjamanAset::where('user_id', $pegawai->user_id)
                ->whereIn('status', ['pending_laboran', 'pending_admin_sinapra', 'disetujui'])
                ->with(['aset:id,kode_aset,nama_aset'])
                ->get();
        }

        $isCleared = $asetDipegang->isEmpty() && $peminjamanAktif->isEmpty();

        return [
            'is_cleared' => $isCleared,
            'aset_dipegang_count' => $asetDipegang->count(),
            'peminjaman_aktif_count' => $peminjamanAktif->count(),
            'aset_dipegang' => $asetDipegang,
            'peminjaman_aktif' => $peminjamanAktif,
        ];
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

        // Cari record Dosen yang sudah ada menggunakan pencocokan bertingkat (pegawai_id -> nidn -> nuptk -> nip -> nik -> user_id)
        $existingDosen = Dosen::withTrashed()->where('pegawai_id', $pegawai->id)->first();
        if (!$existingDosen && !empty($pegawai->nidn)) {
            $existingDosen = Dosen::withTrashed()->where('nidn', $pegawai->nidn)->first();
        }
        if (!$existingDosen && !empty($pegawai->nuptk)) {
            $existingDosen = Dosen::withTrashed()->where('nuptk', $pegawai->nuptk)->first();
        }
        if (!$existingDosen && !empty($pegawai->nip)) {
            $existingDosen = Dosen::withTrashed()->where('nip', $pegawai->nip)->first();
        }
        if (!$existingDosen && !empty($pegawai->nik)) {
            $existingDosen = Dosen::withTrashed()->where('nik', $pegawai->nik)->first();
        }
        if (!$existingDosen && !empty($pegawai->user_id)) {
            $existingDosen = Dosen::withTrashed()->where('user_id', $pegawai->user_id)->first();
        }

        if ($existingDosen && $existingDosen->trashed()) {
            $existingDosen->restore();
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
        if ($prodiId && !MasterProgramStudi::where('id', $prodiId)->exists()) {
            $prodiId = null;
        }

        if (!$prodiId && $pegawai->unit_kerja_id) {
            $unitKerja = $pegawai->unitKerja ?? \App\Models\Simpeg\UnitKerja::find($pegawai->unit_kerja_id);
            if ($unitKerja) {
                $matchedProdi = MasterProgramStudi::where('nama', trim($unitKerja->nama))->first();
                if (!$matchedProdi && !empty($unitKerja->kode_unit)) {
                    $matchedProdi = MasterProgramStudi::where('kode_prodi', trim($unitKerja->kode_unit))->first();
                }
                if ($matchedProdi) {
                    $prodiId = $matchedProdi->id;
                }
            }
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
            'jenis_kelamin' => in_array($pegawai->jenis_kelamin, ['L', 'P']) ? $pegawai->jenis_kelamin : ($existingDosen?->jenis_kelamin ?? null),
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

    public function normalizeIdentifier(?string $value): ?string
    {
        $value = $this->sanitizeIdentifier($value);
        if ($value === null || !ctype_digit($value)) {
            return $value;
        }

        $normalized = ltrim($value, '0');
        return $normalized === '' ? null : $normalized;
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
        $identifierUsername = $this->sanitizeIdentifier($options['username'] ?? null)
            ?? $this->sanitizeIdentifier($pegawai->nidn)
            ?? $this->sanitizeIdentifier($pegawai->nuptk)
            ?? $this->sanitizeIdentifier($pegawai->nip);
        $preserveUsernameExactly = $identifierUsername !== null;
        $username = $identifierUsername ?? $this->resolveUsernamePriority(
            $pegawai->nidn,
            $pegawai->nuptk,
            $pegawai->nip,
            $pegawai->nama_lengkap
        );

        // SIAKAD may already hold the SSO link while SIMPEG has not been linked yet.
        $dosenRecord = $pegawai->dosen()->withTrashed()->first();
        if ($pegawai->user_id && $dosenRecord?->user_id && $pegawai->user_id !== $dosenRecord->user_id) {
            throw ValidationException::withMessages([
                'username' => ['Data SIMPEG dan SIAKAD tertaut ke akun SSO berbeda; sinkronisasi dibatalkan untuk pemeriksaan manual.'],
            ]);
        }
        $linkedUserId = $pegawai->user_id ?: $dosenRecord?->user_id;

        // 1. Cek user yang sudah terhubung dari SIMPEG atau SIAKAD
        if (!empty($linkedUserId)) {
            $existingUser = User::find($linkedUserId);
            if ($existingUser) {
                if ($existingUser->username !== $username) {
                    $conflictingUsers = $this->findUsersByNormalizedUsername($username, $existingUser->id);
                    if ($conflictingUsers->isNotEmpty()) {
                        throw ValidationException::withMessages([
                            'username' => ['Identifier SSO PDDIKTI sudah dipakai akun lain; sinkronisasi dibatalkan agar username tetap persis.'],
                        ]);
                    }
                }

                $userUpdates = [];
                if ($existingUser->username !== $username) {
                    $userUpdates['username'] = $username;
                }
                if (!empty($pegawai->nama_lengkap) && (empty($existingUser->name) || $existingUser->name !== $pegawai->nama_lengkap)) {
                    $userUpdates['name'] = $pegawai->nama_lengkap;
                }
                if ($userUpdates !== []) {
                    $existingUser->update($userUpdates);
                }
                if ($pegawai->user_id !== $existingUser->id) {
                    $pegawai->update(['user_id' => $existingUser->id]);
                }
                if ($dosenRecord && $dosenRecord->user_id !== $existingUser->id) {
                    $dosenRecord->update(['user_id' => $existingUser->id]);
                }
                $this->assignRolesToUser($existingUser, $pegawai, $options['role_ids'] ?? []);
                return $existingUser;
            }
        }

        // 3. Tentukan email
        $email = $options['email'] ?? $this->resolveEmail(
            $options['email'] ?? null,
            $username,
            $pegawai->nama_lengkap
        );

        // 4. Cari akun user yang cocok secara ketat (HANYA via username, DILARANG membajak akun lain via email rekaan)
        $matchingUsers = $this->findUsersByNormalizedUsername($username);

        if ($matchingUsers->count() > 1) {
            throw ValidationException::withMessages([
                'username' => ['Ditemukan lebih dari satu akun yang cocok dengan identifier SSO PDDIKTI setelah normalisasi. Periksa data akun sebelum sinkronisasi.'],
            ]);
        }
        $user = $matchingUsers->first();

        // Jika user dengan username ini ditemukan, pastikan akun tersebut belum diklaim oleh pegawai lain
        if ($user) {
            $isClaimedByOther = Pegawai::where('user_id', $user->id)
                ->where('id', '!=', $pegawai->id)
                ->exists();
            $isLinkedToAnotherDosen = \App\Models\Siakad\Dosen::query()
                ->where('user_id', $user->id)
                ->where('id', '!=', $dosenRecord?->id ?? 0)
                ->exists();
            if ($isClaimedByOther || $isLinkedToAnotherDosen) {
                throw ValidationException::withMessages([
                    'username' => ['Akun dengan identifier dosen ini sudah terhubung ke pegawai lain. Periksa kemungkinan data duplikat sebelum sinkronisasi.'],
                ]);
            } else {
                if ($user->username !== $username) {
                    $user->update(['username' => $username]);
                }
                if (!empty($pegawai->nama_lengkap) && (empty($user->name) || $user->name !== $pegawai->nama_lengkap)) {
                    $user->update(['name' => $pegawai->nama_lengkap]);
                }
            }
        }

        if (!$user) {
            if (User::where('username', $username)->exists() && $preserveUsernameExactly) {
                throw ValidationException::withMessages([
                    'username' => ['Username identifier PDDIKTI sudah dipakai akun lain; sinkronisasi dibatalkan agar username tidak diubah.'],
                ]);
            }

            $baseUsername = $username;
            while (User::where('username', $username)->exists()) {
                $username = $baseUsername . '_' . rand(10, 99);
            }

            // Pastikan email unik dan TIDAK membajak akun user lain yang kebetulan memiliki email sama
            $baseEmail = $email;
            $emailParts = explode('@', $baseEmail);
            while (User::where('email', $email)->exists()) {
                $email = $emailParts[0] . '.' . rand(100, 999) . '@' . ($emailParts[1] ?? 'campus.ac.id');
            }

            // Default password 'indonusa' (atau dari options jika ada)
            // Jika caller mengirimkan 'hashed_password', gunakan langsung tanpa re-hashing
            $passwordVal = !empty($options['hashed_password'])
                ? $options['hashed_password']
                : Hash::make($options['password'] ?? 'indonusa');

            $user = User::create([
                'username' => $username,
                'name' => $pegawai->nama_lengkap,
                'email' => $email,
                'password' => $passwordVal,
                'phone' => $pegawai->telepon ?? null,
                'is_active' => true,
                'is_verified' => $options['is_verified'] ?? true,
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

    public function updateExistingSsoUserForPegawai(Pegawai $pegawai, array $options = []): ?User
    {
        $identifierUsername = $this->sanitizeIdentifier($options['username'] ?? null)
            ?? $this->sanitizeIdentifier($pegawai->nidn)
            ?? $this->sanitizeIdentifier($pegawai->nuptk)
            ?? $this->sanitizeIdentifier($pegawai->nip);
        if ($identifierUsername === null) {
            return null;
        }

        $dosenRecord = $pegawai->dosen()->withTrashed()->first();
        if ($pegawai->user_id && $dosenRecord?->user_id && $pegawai->user_id !== $dosenRecord->user_id) {
            throw ValidationException::withMessages([
                'username' => ['Data SIMPEG dan SIAKAD tertaut ke akun SSO berbeda; sinkronisasi dibatalkan untuk pemeriksaan manual.'],
            ]);
        }

        $linkedUserId = $pegawai->user_id ?: $dosenRecord?->user_id;
        $existingUser = $linkedUserId ? User::find($linkedUserId) : null;
        if ($existingUser === null) {
            $matchingUsers = $this->findUsersByNormalizedUsername($identifierUsername);
            if ($matchingUsers->count() > 1) {
                throw ValidationException::withMessages([
                    'username' => ['Ditemukan lebih dari satu akun yang cocok dengan identifier SSO PDDIKTI setelah normalisasi. Periksa data akun sebelum sinkronisasi.'],
                ]);
            }
            $existingUser = $matchingUsers->first();
        }

        if ($existingUser === null) {
            return null;
        }

        if (Pegawai::query()->where('user_id', $existingUser->id)->where('id', '!=', $pegawai->id)->exists()
            || \App\Models\Siakad\Dosen::query()->where('user_id', $existingUser->id)->where('id', '!=', $dosenRecord?->id ?? 0)->exists()) {
            throw ValidationException::withMessages([
                'username' => ['Akun dengan identifier dosen ini sudah terhubung ke pegawai lain. Periksa kemungkinan data duplikat sebelum sinkronisasi.'],
            ]);
        }

        if ($existingUser->username !== $identifierUsername) {
            if ($this->findUsersByNormalizedUsername($identifierUsername, $existingUser->id)->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'username' => ['Identifier SSO PDDIKTI sudah dipakai akun lain; sinkronisasi dibatalkan agar username tetap persis.'],
                ]);
            }
            $existingUser->username = $identifierUsername;
        }
        if (!empty($pegawai->nama_lengkap) && $existingUser->name !== $pegawai->nama_lengkap) {
            $existingUser->name = $pegawai->nama_lengkap;
        }
        if ($existingUser->isDirty()) {
            $existingUser->save();
        }

        if ($pegawai->user_id !== $existingUser->id) {
            $pegawai->user_id = $existingUser->id;
            $pegawai->save();
        }
        if ($dosenRecord && $dosenRecord->user_id !== $existingUser->id) {
            $dosenRecord->user_id = $existingUser->id;
            $dosenRecord->save();
        }

        $this->assignRolesToUser($existingUser, $pegawai, $options['role_ids'] ?? []);
        return $existingUser;
    }

    private function findUsersByNormalizedUsername(string $username, ?int $excludeUserId = null)
    {
        $normalized = $this->normalizeIdentifier($username);
        $query = User::query()->where(function ($query) use ($username, $normalized) {
            $query->where('username', $username);
            if ($normalized !== null) {
                $normalizedLength = strlen($normalized);
                $query->orWhere('username', $normalized)
                    ->orWhere(function ($query) use ($normalized, $normalizedLength) {
                        $query->whereRaw('LENGTH(username) >= ?', [$normalizedLength])
                            ->whereRaw('SUBSTR(username, -' . $normalizedLength . ') = ?', [$normalized]);
                    });
            }
        });

        if ($excludeUserId !== null) {
            $query->where('id', '!=', $excludeUserId);
        }

        return $query->get()
            ->filter(function (User $candidate) use ($username, $normalized): bool {
                if ($normalized === null) {
                    return $this->sanitizeIdentifier($candidate->username) === $username;
                }

                return $this->normalizeIdentifier($candidate->username) === $normalized;
            })
            ->values();
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
