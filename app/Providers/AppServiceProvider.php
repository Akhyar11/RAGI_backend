<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;
use Illuminate\Support\Facades\Gate;
use App\Models\User;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        \Illuminate\Support\Facades\Event::listen(
            \App\Events\Sikeu\PembayaranSpmbLunas::class,
            \App\Listeners\Spmb\UpdateStatusPembayaranSpmb::class
        );

        \Illuminate\Support\Facades\Event::listen(
            \App\Events\Simpeg\SuratTugasDisetujui::class,
            \App\Listeners\Simpeg\SetPresensiDinasLuar::class
        );

        \Illuminate\Support\Facades\Event::listen(
            \App\Events\Simpeg\IzinJamKerjaDisetujui::class,
            \App\Listeners\Simpeg\SinkronisasiPresensiIzinJamKerja::class
        );
    }

    public function boot(): void
    {
        // Observers
        \App\Models\User::observe(\App\Observers\UserObserver::class);
        \App\Models\Role::observe(\App\Observers\RoleObserver::class);
        \App\Models\Permission::observe(\App\Observers\PermissionObserver::class);
        
        // SPMB Observers
        \App\Models\Spmb\PendaftaranCalonMhs::observe(\App\Observers\Spmb\PendaftaranCalonMhsObserver::class);
        \App\Models\Spmb\GelombangPenerimaan::observe(\App\Observers\Spmb\GelombangPenerimaanObserver::class);

        // SIAKAD Observers
        \App\Models\Siakad\Mahasiswa::observe(\App\Observers\MahasiswaObserver::class);
        \App\Models\Siakad\KonversiTransfer::observe(\App\Observers\KonversiTransferObserver::class);

        // SINAPRA Policies
        Gate::policy(\App\Models\Gedung::class, \App\Policies\Sinapra\GedungPolicy::class);
        Gate::policy(\App\Models\Ruangan::class, \App\Policies\Sinapra\RuanganPolicy::class);
        Gate::policy(\App\Models\KategoriAset::class, \App\Policies\Sinapra\KategoriAsetPolicy::class);
        Gate::policy(\App\Models\Aset::class, \App\Policies\Sinapra\AsetPolicy::class);
        Gate::policy(\App\Models\PeminjamanRuangan::class, \App\Policies\Sinapra\PeminjamanRuanganPolicy::class);
        Gate::policy(\App\Models\PeminjamanAset::class, \App\Policies\Sinapra\PeminjamanAsetPolicy::class);
        Gate::policy(\App\Models\MaintenanceLog::class, \App\Policies\Sinapra\MaintenanceLogPolicy::class);
        Gate::policy(\App\Models\PengajuanPengadaan::class, \App\Policies\Sinapra\PengajuanPengadaanPolicy::class);

        Gate::before(function (User $user, string $ability) {
            // Super admin bypass semua permission (dinamis berdasarkan system_settings superadmin_role)
            if ($user->isSuperAdmin()) {
                return true;
            }
        });

        Passport::$validateKeyPermissions = false;

        // Arahkan Passport ke halaman login SSO kustom kita
        // saat user mengakses /oauth/authorize tanpa sesi web aktif
        Passport::authorizationView(fn ($params) =>
            redirect('/sso/login?' . http_build_query($params))
        );

        // Token access Passport berlaku 1 hari
        Passport::tokensExpireIn(now()->addDay());

        // Refresh token berlaku 30 hari
        Passport::refreshTokensExpireIn(now()->addDays(30));

        // Personal access token berlaku 1 tahun
        Passport::personalAccessTokensExpireIn(now()->addYear());

        // Konfigurasi dinamis mail/SMTP dari core_system_settings
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('core_system_settings')) {
                $mailHost = \App\Models\SystemSetting::get('mail_host');
                if (!empty($mailHost)) {
                    $scheme = \App\Models\SystemSetting::get('mail_scheme');
                    config([
                        'mail.default'                => \App\Models\SystemSetting::get('mail_mailer', config('mail.default', 'smtp')),
                        'mail.mailers.smtp.transport' => 'smtp',
                        'mail.mailers.smtp.host'      => $mailHost,
                        'mail.mailers.smtp.port'      => (int) \App\Models\SystemSetting::get('mail_port', config('mail.mailers.smtp.port', 587)),
                        'mail.mailers.smtp.username'  => \App\Models\SystemSetting::get('mail_username', config('mail.mailers.smtp.username')),
                        'mail.mailers.smtp.password'  => \App\Models\SystemSetting::get('mail_password', config('mail.mailers.smtp.password')),
                        'mail.mailers.smtp.scheme'    => $scheme === 'none' ? null : $scheme,
                        'mail.from.address'           => \App\Models\SystemSetting::get('mail_from_address', config('mail.from.address')),
                        'mail.from.name'              => \App\Models\SystemSetting::get('mail_from_name', config('mail.from.name')),
                    ]);
                }
            }
        } catch (\Throwable $th) {
            // Lewati jika database belum siap / saat proses migrasi
        }
    }
}
