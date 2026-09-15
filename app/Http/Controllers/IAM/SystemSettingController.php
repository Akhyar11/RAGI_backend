<?php

namespace App\Http\Controllers\IAM;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SystemSetting;
use App\Services\IAM\RestrictedRoleService;
use Illuminate\Support\Facades\Cache;

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

        return response()->json([
            'status' => 'success',
            'data'   => $settings,
        ]);
    }

    public function update(Request $request, RestrictedRoleService $restrictedRoles)
    {
        $request->validate([
            'settings'         => 'required|array',
            'settings.*.key'   => 'required|string',
            'settings.*.value' => 'nullable|string',
        ]);

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
    public function testSmtp(Request $request)
    {
        $request->validate([
            'email'             => 'required|email',
            'mail_host'         => 'nullable|string',
            'mail_port'         => 'nullable|numeric',
            'mail_scheme'       => 'nullable|string',
            'mail_username'     => 'nullable|string',
            'mail_password'     => 'nullable|string',
            'mail_from_address' => 'nullable|email',
            'mail_from_name'    => 'nullable|string',
        ]);

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
}
