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
    'email',
    'password',
    'phone',
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

    protected $appends = ['is_superadmin', 'is_admin'];

    public function isSuperAdmin(): bool
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('core_system_settings')) {
            return false;
        }

        $superAdminRole = \App\Models\SystemSetting::where('key', 'superadmin_role')->value('value');
        return $superAdminRole ? $this->hasRole($superAdminRole) : false;
    }

    public function isAdmin(): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }
        
        // Admin adalah user yang memiliki setidaknya satu permission "update", "delete", atau "manage" (tindakan di atas read/create biasa)
        return $this->roles()->whereHas('permissions', function($q) {
            $q->whereIn('action', ['update', 'delete', 'approve', 'manage']);
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

    public function hasRole(string $roleSlug): bool
    {
        return $this->roles()->where('slug', $roleSlug)->exists();
    }
}
