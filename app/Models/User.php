<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;

#[Fillable([
    'username',
    'name',
    'email',
    'password',
    'phone',
    'referral_code',
    'is_active',
    'is_verified',
    'last_login_at',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $table = 'core_users';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'is_active' => 'boolean',
            'is_verified' => 'boolean',
            'password' => 'hashed',
        ];
    }

    public function pegawai()
    {
        return $this->hasOne(\App\Models\Simpeg\Pegawai::class, 'user_id');
    }

    public function tandaTangan()
    {
        return $this->hasMany(\App\Models\Simpeg\TandaTanganPegawai::class, 'user_id');
    }

    public function activeTandaTangan()
    {
        return $this->hasOne(\App\Models\Simpeg\TandaTanganPegawai::class, 'user_id')->where('is_active', true)->latest();
    }

    public function employee()
    {
        return $this->hasOne(\App\Models\Simpeg\Pegawai::class, 'user_id');
    }

    public function mahasiswa()
    {
        return $this->hasOne(\App\Models\Siakad\Mahasiswa::class, 'user_id');
    }

    public function ssoTokens()
    {
        return $this->hasMany(SsoToken::class);
    }

    public function userSessions()
    {
        return $this->hasMany(UserSessionIam::class);
    }

    public function passwordResets()
    {
        return $this->hasMany(PasswordReset::class);
    }

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'core_user_roles', 'user_id', 'role_id')
            ->withPivot(['valid_from', 'valid_until'])
            ->where(function ($q) {
                $q->whereNull('core_user_roles.valid_until')
                  ->orWhere('core_user_roles.valid_until', '>=', now()->toDateString());
            });
    }

    public function laboranRuangan()
    {
        return $this->belongsToMany(Ruangan::class, 'sinapra_laboran_ruangan', 'user_id', 'ruangan_id')
            ->withPivot('is_primary')
            ->withTimestamps();
    }

    public function laboranProdi()
    {
        return $this->belongsToMany(\App\Models\Siakad\ProgramStudi::class, 'sinapra_laboran_prodi', 'user_id', 'program_studi_id')
            ->withPivot('is_primary')
            ->withTimestamps();
    }

    /**
     * Dapatkan daftar ID Program Studi yang terikat wewenangnya dengan user di SINAPRA.
     * Bersumber dari:
     * 1. Mapping Role ke Prodi pada tabel `sinapra_prodi_roles`.
     * 2. Direct assignment ke Prodi pada tabel `sinapra_laboran_prodi` (bila ada).
     *
     * @return \Illuminate\Support\Collection<int>
     */
    public function getSinapraProdiIds(): \Illuminate\Support\Collection
    {
        $roleIds = $this->roles()->pluck('core_roles.id');

        $prodiIdsFromRoles = \Illuminate\Support\Facades\DB::table('sinapra_prodi_roles')
            ->whereIn('role_id', $roleIds)
            ->pluck('program_studi_id');

        $prodiIdsDirect = $this->laboranProdi()->pluck('siakad_program_studi.id');

        return $prodiIdsFromRoles->concat($prodiIdsDirect)->unique()->values();
    }

    /**
     * Dapatkan seluruh ID Ruangan yang berada dalam lingkup wewenang laboran di SINAPRA.
     * Meliputi:
     * 1. Ruangan yang bernaung di bawah program studi binaan user (dari role maupun direct prodi).
     * 2. Ruangan yang ditugaskan secara langsung ke user pada sinapra_laboran_ruangan.
     *
     * @return \Illuminate\Support\Collection<int>
     */
    public function getSinapraAccessibleRuanganIds(): \Illuminate\Support\Collection
    {
        $prodiIds = $this->getSinapraProdiIds();

        $ruanganIdsFromProdi = $prodiIds->isNotEmpty()
            ? \App\Models\Ruangan::whereIn('program_studi_id', $prodiIds)->pluck('id')
            : collect();

        $ruanganIdsDirect = $this->laboranRuangan()->pluck('sinapra_ruangan.id');

        return $ruanganIdsFromProdi->concat($ruanganIdsDirect)->unique()->values();
    }

    /**
     * Cek apakah user adalah laboran / staf dengan batasan wewenang prodi/lab tertentu di SINAPRA.
     * Mengembalikan false untuk SuperAdmin, Admin Sarpras, atau Admin Global.
     */
    public function isSinapraLaboranRestricted(): bool
    {
        if ($this->isSuperAdmin() || $this->hasRole('admin') || $this->hasRole('admin_sarpras')) {
            return false;
        }

        return $this->getSinapraProdiIds()->isNotEmpty()
            || $this->laboranRuangan()->exists()
            || $this->hasRole('admin_laboratorium');
    }

    public function isLaboran(): bool
    {
        return $this->hasRole('admin_laboratorium') 
            || $this->getSinapraProdiIds()->isNotEmpty()
            || $this->laboranRuangan()->exists() 
            || $this->laboranProdi()->exists();
    }

    protected $appends = ['is_superadmin', 'is_admin', 'referral_code'];

    public function getNameAttribute($value): ?string
    {
        if (!empty($value)) {
            return $value;
        }

        if ($this->relationLoaded('pegawai') && $this->pegawai?->nama_lengkap) {
            return $this->pegawai->nama_lengkap;
        }

        return $this->username ?? '';
    }

    protected static function booted(): void
    {
        static::creating(function ($user) {
            if (empty($user->referral_code)) {
                $user->referral_code = static::generateUniqueReferralCode();
            }
        });
    }

    public static function generateUniqueReferralCode(): string
    {
        do {
            $code = 'REF-' . strtoupper(\Illuminate\Support\Str::random(6));
        } while (static::where('referral_code', $code)->exists());

        return $code;
    }

    public function getReferralCodeAttribute($value): ?string
    {
        // Saat serialisasi atribut appended, Laravel dapat memanggil accessor dengan
        // nilai null. Ambil langsung dari atribut mentah agar kode tidak tergenerate ulang.
        $value = $value ?? ($this->attributes['referral_code'] ?? null);

        if (empty($value) && $this->exists) {
            $code = static::generateUniqueReferralCode();
            $this->attributes['referral_code'] = $code;
            $this->saveQuietly();
            return $code;
        }

        return $value;
    }

    public function isSuperAdmin(): bool
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('core_system_settings')) {
            return false;
        }

        $superAdminRole = \App\Models\SystemSetting::where('key', 'superadmin_role')->value('value');
        
        if ($superAdminRole) {
            return $this->hasRole($superAdminRole);
        }

        // Fallback for bootstrap / missing settings
        return $this->id === 1;
    }

    public function isAdmin(): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }
        
        // Admin adalah user yang memiliki role administratif (bukan dosen, tendik, atau mahasiswa)
        return $this->roles()->where(function ($q) {
            $q->where('slug', 'admin')
              ->orWhere('slug', 'like', 'admin_%')
              ->orWhere('slug', 'like', '%_admin')
              ->orWhere('slug', 'operator_sdm');
        })->exists();
    }

    public function getIsSuperadminAttribute(): bool
    {
        return $this->isSuperAdmin();
    }

    public function getIsAdminAttribute(): bool
    {
        return $this->isAdmin();
    }

    public function hasPermission(string $permissionSlug): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        $shortSlug = str_replace('iam.', '', $permissionSlug);

        return $this->roles()
            ->whereHas('permissions', fn($q) => 
                $q->where('slug', $permissionSlug)
                  ->orWhere('slug', "iam.{$shortSlug}")
                  ->orWhere('slug', $shortSlug)
            )
            ->exists();
    }

    public function hasPermissionTo(string $permission): bool
    {
        return $this->hasPermission($permission);
    }

    public function hasRole(string $roleSlug): bool
    {
        return $this->roles()->where('slug', $roleSlug)->exists();
    }
}
