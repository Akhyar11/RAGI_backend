<?php

namespace App\Http\Controllers\IAM;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SystemSetting;
use App\Services\IAM\RestrictedRoleService;
use Illuminate\Support\Facades\Cache;
use App\Http\Requests\IAM\UpdateSystemSettingRequest;
use App\Http\Requests\IAM\TestSmtpSettingRequest;
use App\Http\Requests\IAM\TestR2SettingRequest;

class SystemSettingController extends Controller
{
    public function index()
    {
        $settings = SystemSetting::all()->keyBy('key')->toArray();

        $defaultSmtp = [
            'mail_mailer'       => config('mail.default', 'smtp'),
            'mail_host'         => config('mail.mailers.smtp.host', 'smtp.gmail.com'),
            'mail_port'         => config('mail.mailers.smtp.port', 465),
            'mail_scheme'       => config('mail.mailers.smtp.scheme') ?? 'smtps',
            'mail_username'     => config('mail.mailers.smtp.username', ''),
            'mail_password'     => config('mail.mailers.smtp.password', ''),
            'mail_from_address' => config('mail.from.address', ''),
            'mail_from_name'    => config('mail.from.name', 'Sistem Terintegrasi Kampus'),
        ];

        foreach ($defaultSmtp as $key => $val) {
            if (!isset($settings[$key])) {
                $settings[$key] = [
                    'id'          => null,
                    'key'         => $key,
                    'value'       => (string) $val,
                    'description' => 'Konfigurasi SMTP default dari sistem',
                ];
            }
        }

        if (!isset($settings[RestrictedRoleService::SETTING_KEY])) {
            $settings[RestrictedRoleService::SETTING_KEY] = [
                'id'          => null,
                'key'         => RestrictedRoleService::SETTING_KEY,
                'value'       => '[]',
                'description' => 'Daftar ID role (JSON array) yang disembunyikan dari daftar roles untuk non-pengelola IAM',
            ];
        }

        $defaultFeeder = [
            'feeder_url'      => 'http://localhost:8100/ws/live2.php',
            'feeder_username' => 'admin_siakad',
            'feeder_password' => '',
        ];

        foreach ($defaultFeeder as $key => $val) {
            if (!isset($settings[$key])) {
                $settings[$key] = [
                    'id'          => null,
                    'key'         => $key,
                    'value'       => (string) $val,
                    'description' => 'Kredensial Neo Feeder PDDikti default (diubah via IAM → Pengaturan Sistem)',
                ];
            }
        }

        $defaultR2 = [
            'filesystem_disk'            => (string) config('filesystems.default', 'local'),
            'filesystem_public_disk'     => (string) config('filesystems.public_disk', 'public'),
            'filesystem_private_disk'    => (string) config('filesystems.private_disk', 'public'),
            'r2_access_key_id'           => (string) (config('filesystems.disks.r2.key') ?? ''),
            'r2_secret_access_key'       => (string) (config('filesystems.disks.r2.secret') ?? ''),
            'r2_default_region'          => (string) (config('filesystems.disks.r2.region') ?? 'auto'),
            'r2_bucket'                  => (string) (config('filesystems.disks.r2.bucket') ?? ''),
            'r2_private_bucket'          => (string) (config('filesystems.disks.r2-private.bucket') ?? ''),
            'r2_url'                     => (string) (config('filesystems.disks.r2.url') ?? ''),
            'r2_private_url'             => (string) (config('filesystems.disks.r2-private.url') ?? ''),
            'r2_endpoint'                => (string) (config('filesystems.disks.r2.endpoint') ?? ''),
            'r2_use_path_style_endpoint' => config('filesystems.disks.r2.use_path_style_endpoint') ? 'true' : 'false',
        ];

        foreach ($defaultR2 as $key => $val) {
            if (!isset($settings[$key])) {
                $settings[$key] = [
                    'id'          => null,
                    'key'         => $key,
                    'value'       => (string) $val,
                    'description' => 'Konfigurasi Cloudflare R2 / Object Storage',
                ];
            }
        }

        $defaultLms = [
            'lms_storage_disk'        => (string) SystemSetting::get('lms_storage_disk', 'r2'),
            'lms_max_file_materi_mb'  => (string) SystemSetting::get('lms_max_file_materi_mb', '50'),
            'lms_max_video_mb'        => (string) SystemSetting::get('lms_max_video_mb', '500'),
            'lms_max_file_tugas_mb'   => (string) SystemSetting::get('lms_max_file_tugas_mb', '50'),
            'lms_allow_token_absensi' => (string) SystemSetting::get('lms_allow_token_absensi', 'true'),
            'lms_token_ttl_minutes'   => (string) SystemSetting::get('lms_token_ttl_minutes', '15'),
        ];

        foreach ($defaultLms as $key => $val) {
            if (!isset($settings[$key])) {
                $settings[$key] = [
                    'id'          => null,
                    'key'         => $key,
                    'value'       => (string) $val,
                    'description' => 'Konfigurasi LMS & Absensi Perkuliahan',
                ];
            }
        }

        return response()->json([
            'status' => 'success',
            'data'   => $settings,
        ]);
    }

    public function update(UpdateSystemSettingRequest $request, RestrictedRoleService $restrictedRoles)
    {
        $feederChanged = false;

        foreach ($request->settings as $setting) {
            $value = $setting['value'];

            if ($setting['key'] === RestrictedRoleService::SETTING_KEY) {
                $decoded = json_decode((string) $value, true);

                if (!is_array($decoded)) {
                    return response()->json([
                        'status'  => 'error',
                        'message' => 'Format restricted_role_ids tidak valid. Gunakan JSON array dari ID role, contoh: "[1,2]".',
                    ], 422);
                }

                // Hanya ID role yang benar-benar ada yang disimpan.
                $value = json_encode($restrictedRoles->sanitizeIds($decoded));
            }

            SystemSetting::updateOrCreate(
                ['key' => $setting['key']],
                ['value' => $value]
            );

            if (in_array($setting['key'], ['feeder_url', 'feeder_username', 'feeder_password'], true)) {
                $feederChanged = true;
            }
        }

        // Kredensial berubah: buang token cache agar Tes Koneksi memakai nilai baru,
        // bukan token staging basi (TTL cache 1 jam).
        if ($feederChanged) {
            Cache::forget('neo_feeder_token');
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'System settings updated successfully.',
        ]);
    }

    /**
     * Mengirimkan email uji coba untuk memverifikasi konfigurasi SMTP.
     */
    public function testSmtp(TestSmtpSettingRequest $request)
    {
        try {
            // Jika dikirim kredensial on-the-fly untuk diuji sebelum disimpan:
            if ($request->filled('mail_host')) {
                config([
                    'mail.default'               => 'smtp',
                    'mail.mailers.smtp.transport' => 'smtp',
                    'mail.mailers.smtp.host'      => $request->input('mail_host'),
                    'mail.mailers.smtp.port'      => (int) $request->input('mail_port', 587),
                    'mail.mailers.smtp.scheme'    => $request->input('mail_scheme') === 'none' ? null : $request->input('mail_scheme'),
                    'mail.mailers.smtp.username'  => $request->input('mail_username'),
                    'mail.mailers.smtp.password'  => $request->input('mail_password'),
                    'mail.from.address'           => $request->input('mail_from_address', config('mail.from.address')),
                    'mail.from.name'              => $request->input('mail_from_name', config('mail.from.name')),
                ]);
            }

            \Illuminate\Support\Facades\Mail::raw(
                "Halo,\n\nIni adalah email uji coba dari Sistem Terintegrasi Kampus (IAM).\nJika Anda menerima email ini, berarti konfigurasi server SMTP telah berhasil dan berfungsi dengan baik.\n\nWaktu pengiriman: " . now()->format('d M Y H:i:s') . "\nHost: " . config('mail.mailers.smtp.host') . "\nPort: " . config('mail.mailers.smtp.port') . "\n\nSalam,\nTim IT Kampus",
                function ($message) use ($request) {
                    $message->to($request->email)
                        ->subject('Uji Coba Konfigurasi SMTP - Sistem Terintegrasi Kampus');
                }
            );

            return response()->json([
                'status'  => 'success',
                'message' => 'Email uji coba berhasil dikirim ke ' . $request->email,
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Gagal mengirim email uji coba: ' . $th->getMessage(),
            ], 500);
        }
    }

    /**
     * Menguji koneksi ke Cloudflare R2 Object Storage.
     */
    public function testR2(TestR2SettingRequest $request)
    {
        try {
            $key = $request->input('r2_access_key_id') ?: SystemSetting::get('r2_access_key_id', config('filesystems.disks.r2.key'));
            $secret = $request->input('r2_secret_access_key') ?: SystemSetting::get('r2_secret_access_key', config('filesystems.disks.r2.secret'));
            $endpoint = $request->input('r2_endpoint') ?: SystemSetting::get('r2_endpoint', config('filesystems.disks.r2.endpoint'));
            $bucket = $request->input('r2_bucket') ?: SystemSetting::get('r2_bucket', config('filesystems.disks.r2.bucket'));
            $region = $request->input('r2_default_region') ?: SystemSetting::get('r2_default_region', config('filesystems.disks.r2.region', 'auto'));
            $usePathStyle = filter_var($request->input('r2_use_path_style_endpoint', SystemSetting::get('r2_use_path_style_endpoint', config('filesystems.disks.r2.use_path_style_endpoint', true))), FILTER_VALIDATE_BOOLEAN);

            if (empty($key) || empty($secret) || empty($endpoint) || empty($bucket)) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Kredensial R2 belum lengkap. Mohon lengkapi Access Key ID, Secret Key, Endpoint, dan Nama Bucket.',
                ], 422);
            }

            // Inisiasi temporary disk untuk pengujian
            config([
                'filesystems.disks._r2_test' => [
                    'driver'                  => 's3',
                    'key'                     => $key,
                    'secret'                  => $secret,
                    'region'                  => $region ?: 'auto',
                    'bucket'                  => $bucket,
                    'endpoint'                => $endpoint,
                    'use_path_style_endpoint' => $usePathStyle,
                    'throw'                   => true,
                ],
            ]);

            $disk = \Illuminate\Support\Facades\Storage::disk('_r2_test');
            $pingFile = '.r2-ping-test-' . time() . '.txt';
            $disk->put($pingFile, 'R2_CONNECTION_TEST_' . now()->toIso8601String());
            $exists = $disk->exists($pingFile);
            $disk->delete($pingFile);

            if ($exists) {
                return response()->json([
                    'status'  => 'success',
                    'message' => "Koneksi ke Cloudflare R2 berhasil! Bucket '{$bucket}' dapat diakses dan ditulis dengan baik.",
                ]);
            }

            return response()->json([
                'status'  => 'error',
                'message' => "Gagal memverifikasi file uji pada bucket '{$bucket}'.",
            ], 500);
        } catch (\Throwable $th) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Gagal terhubung ke Cloudflare R2: ' . $th->getMessage(),
            ], 500);
        }
    }
}

