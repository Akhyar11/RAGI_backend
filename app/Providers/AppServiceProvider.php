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

        // Konversi calon mahasiswa lulus daftar ulang -> Mahasiswa (SIAKAD).
        \Illuminate\Support\Facades\Event::listen(
            \App\Events\Spmb\MahasiswaDiterima::class,
            \App\Listeners\Spmb\ProsesKonversiMahasiswa::class
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
        \App\Models\Spmb\SpmbKuotaProdi::observe(\App\Observers\Spmb\SpmbKuotaProdiObserver::class);
        \App\Models\Spmb\MasterBiaya::observe(\App\Observers\Spmb\MasterBiayaObserver::class);
        \App\Models\Spmb\MasterBiayaItem::observe(\App\Observers\Spmb\MasterBiayaItemObserver::class);
        \App\Models\Spmb\MasterKomponenBiaya::observe(\App\Observers\Spmb\MasterKomponenBiayaObserver::class);
        \App\Models\Spmb\ReferralUsage::observe(\App\Observers\Spmb\ReferralUsageObserver::class);

        // SIAKAD Observers
        \App\Models\Siakad\Mahasiswa::observe(\App\Observers\MahasiswaObserver::class);
        \App\Models\Siakad\KonversiTransfer::observe(\App\Observers\KonversiTransferObserver::class);
        \App\Models\Siakad\TahunAkademik::observe(\App\Observers\Siakad\TahunAkademikObserver::class);
        \App\Models\Siakad\ProgramStudi::observe(\App\Observers\Siakad\ProgramStudiObserver::class);

        // SIKEU Observers
        \App\Models\Sikeu\PengajuanPencairanKas::observe(\App\Observers\Sikeu\PengajuanPencairanKasObserver::class);
        \App\Models\Sikeu\PengeluaranKampus::observe(\App\Observers\Sikeu\PengeluaranKampusObserver::class);
        \App\Models\Sikeu\UnitKas::observe(\App\Observers\Sikeu\UnitKasObserver::class);
        \App\Models\Sikeu\TransaksiKasUnit::observe(\App\Observers\Sikeu\TransaksiKasUnitObserver::class);

        // SIMPEG Observers
        \App\Models\Simpeg\SuratTugas::observe(\App\Observers\Simpeg\SuratTugasObserver::class);
        \App\Models\Simpeg\UsulanJafung::observe(\App\Observers\Simpeg\UsulanJafungObserver::class);
        \App\Models\Simpeg\PegawaiKomponenGaji::observe(\App\Observers\Simpeg\PegawaiKomponenGajiObserver::class);
        \App\Models\Simpeg\JabatanFungsionalAkademik::observe(\App\Observers\JabatanFungsionalAkademikObserver::class);

        // SINAPRA Observers
        \App\Models\LabBhp::observe(\App\Observers\Sinapra\LabBhpObserver::class);
        \App\Models\LabBhpTransaksi::observe(\App\Observers\Sinapra\LabBhpTransaksiObserver::class);
        \App\Models\BebasTanggungan::observe(\App\Observers\Sinapra\BebasTanggunganObserver::class);
        \App\Models\AlatKalibrasi::observe(\App\Observers\Sinapra\AlatKalibrasiObserver::class);
        \App\Models\LaboranRuangan::observe(\App\Observers\Sinapra\LaboranRuanganObserver::class);

        // SINAPRA Policies
        Gate::policy(\App\Models\Gedung::class, \App\Policies\Sinapra\GedungPolicy::class);
        Gate::policy(\App\Models\Ruangan::class, \App\Policies\Sinapra\RuanganPolicy::class);
        Gate::policy(\App\Models\KategoriAset::class, \App\Policies\Sinapra\KategoriAsetPolicy::class);
        Gate::policy(\App\Models\Aset::class, \App\Policies\Sinapra\AsetPolicy::class);
        Gate::policy(\App\Models\PeminjamanRuangan::class, \App\Policies\Sinapra\PeminjamanRuanganPolicy::class);
        Gate::policy(\App\Models\PeminjamanAset::class, \App\Policies\Sinapra\PeminjamanAsetPolicy::class);
        Gate::policy(\App\Models\MaintenanceLog::class, \App\Policies\Sinapra\MaintenanceLogPolicy::class);
        Gate::policy(\App\Models\PengajuanPengadaan::class, \App\Policies\Sinapra\PengajuanPengadaanPolicy::class);
        Gate::policy(\App\Models\LabBhp::class, \App\Policies\Sinapra\LabBhpPolicy::class);
        Gate::policy(\App\Models\BebasTanggungan::class, \App\Policies\Sinapra\BebasTanggunganPolicy::class);
        Gate::policy(\App\Models\AlatKalibrasi::class, \App\Policies\Sinapra\AlatKalibrasiPolicy::class);

        Gate::before(function (User $user, string $ability) {
            // Super admin bypass semua permission (dinamis berdasarkan system_settings superadmin_role)
            if ($user->isSuperAdmin()) {
                return true;
            }

            if ($user->hasPermission($ability)) {
                return true;
            }
        });

        // Simulasi pembayaran SPMB (local/testing): admin atau pemilik pendaftaran.
        Gate::define('simulate-spmb-payment', function (User $user, \App\Models\Spmb\PendaftaranCalonMhs $pendaftaran) {
            return $user->hasRole('superadmin')
                || $user->hasRole('admin')
                || $user->hasPermission('spmb.manage')
                || (int) $pendaftaran->user_id === (int) $user->id;
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

                // Konfigurasi dinamis Cloudflare R2 / Filesystem dari core_system_settings
                $fsDisk = \App\Models\SystemSetting::get('filesystem_disk');
                if (!empty($fsDisk)) {
                    config(['filesystems.default' => $fsDisk]);
                }
                $fsPublic = \App\Models\SystemSetting::get('filesystem_public_disk');
                if (!empty($fsPublic)) {
                    config(['filesystems.public_disk' => $fsPublic]);
                }
                $fsPrivate = \App\Models\SystemSetting::get('filesystem_private_disk');
                if (!empty($fsPrivate)) {
                    config(['filesystems.private_disk' => $fsPrivate]);
                }

                $r2Endpoint = \App\Models\SystemSetting::get('r2_endpoint');
                $r2Key = \App\Models\SystemSetting::get('r2_access_key_id');
                $r2Secret = \App\Models\SystemSetting::get('r2_secret_access_key');
                $r2Bucket = \App\Models\SystemSetting::get('r2_bucket');

                if (!empty($r2Endpoint) || !empty($r2Key) || !empty($r2Bucket)) {
                    $r2Region = \App\Models\SystemSetting::get('r2_default_region', config('filesystems.disks.r2.region', 'auto'));
                    $r2Url = \App\Models\SystemSetting::get('r2_url', config('filesystems.disks.r2.url'));
                    $r2PathStyle = filter_var(\App\Models\SystemSetting::get('r2_use_path_style_endpoint', config('filesystems.disks.r2.use_path_style_endpoint', true)), FILTER_VALIDATE_BOOLEAN);

                    $r2PrivateBucket = \App\Models\SystemSetting::get('r2_private_bucket', $r2Bucket);
                    $r2PrivateUrl = \App\Models\SystemSetting::get('r2_private_url', config('filesystems.disks.r2-private.url'));

                    config([
                        'filesystems.disks.r2.key' => $r2Key ?: config('filesystems.disks.r2.key'),
                        'filesystems.disks.r2.secret' => $r2Secret ?: config('filesystems.disks.r2.secret'),
                        'filesystems.disks.r2.region' => $r2Region ?: 'auto',
                        'filesystems.disks.r2.bucket' => $r2Bucket ?: config('filesystems.disks.r2.bucket'),
                        'filesystems.disks.r2.url' => $r2Url ?: config('filesystems.disks.r2.url'),
                        'filesystems.disks.r2.endpoint' => $r2Endpoint ?: config('filesystems.disks.r2.endpoint'),
                        'filesystems.disks.r2.use_path_style_endpoint' => $r2PathStyle,

                        'filesystems.disks.r2-private.key' => $r2Key ?: config('filesystems.disks.r2-private.key'),
                        'filesystems.disks.r2-private.secret' => $r2Secret ?: config('filesystems.disks.r2-private.secret'),
                        'filesystems.disks.r2-private.region' => $r2Region ?: 'auto',
                        'filesystems.disks.r2-private.bucket' => $r2PrivateBucket ?: config('filesystems.disks.r2-private.bucket'),
                        'filesystems.disks.r2-private.url' => $r2PrivateUrl ?: config('filesystems.disks.r2-private.url'),
                        'filesystems.disks.r2-private.endpoint' => $r2Endpoint ?: config('filesystems.disks.r2-private.endpoint'),
                        'filesystems.disks.r2-private.use_path_style_endpoint' => $r2PathStyle,
                    ]);
                }
            }
        } catch (\Throwable $th) {
            // Lewati jika database belum siap / saat proses migrasi
        }
    }
}
