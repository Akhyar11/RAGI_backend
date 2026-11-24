<?php

namespace App\Console\Commands;

use App\Models\Siakad\Pertemuan;
use App\Models\SystemSetting;
use App\Services\AuditLogService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Tutup otomatis sesi presensi yang sudah lewat jendela waktunya.
 *
 * Dua kriteria yang dipakai:
 * 1. Jam selesai pertemuan sudah lewat (jadwal tatap muka/daring terjadwal).
 * 2. Token terakhir diputar lebih lama dari `window_menit` (dosen lupa
 *    menutup sesi setelah rotasi berhenti).
 *
 * Menutup = presensi_closed_at diisi, token dibersihkan, status jadi
 * 'selesai'. Input token mahasiswa otomatis ditolak setelah ini, sedangkan
 * koreksi manual dosen tetap dimungkinkan.
 */
class LmsTutupPresensiOtomatis extends Command
{
    protected $signature = 'lms:tutup-presensi-otomatis';

    protected $description = 'Tutup otomatis sesi presensi LMS yang jendela waktunya sudah lewat';

    public function handle(): int
    {
        $now = now();

        $today = $now->toDateString();
        $jamSekarang = $now->format('H:i:s');

        $candidates = Pertemuan::whereNull('presensi_closed_at')
            ->where(function ($q) use ($today, $jamSekarang) {
                $q->where(function ($byJadwal) use ($today, $jamSekarang) {
                    $byJadwal->whereNotNull('tanggal')
                        ->whereNotNull('jam_selesai')
                        ->where(function ($waktu) use ($today, $jamSekarang) {
                            $waktu->where('tanggal', '<', $today)
                                ->orWhere(function ($hariIni) use ($today, $jamSekarang) {
                                    $hariIni->where('tanggal', $today)
                                        ->where('jam_selesai', '<', $jamSekarang);
                                });
                        });
                })->orWhere(function ($byWindow) {
                    $byWindow->whereNull('jam_selesai')
                        ->whereNotNull('token_rotated_at');
                });
            })
            ->get();

        $defaultWindow = max(5, (int) SystemSetting::get('lms_presensi_window_menit', 30));

        $pertemuans = $candidates->filter(function (Pertemuan $p) use ($now, $defaultWindow) {
            if ($p->tanggal && $p->jam_selesai) {
                return Carbon::parse($p->tanggal . ' ' . $p->jam_selesai)->lt($now);
            }

            if ($p->token_rotated_at) {
                $window = max(5, min(180, (int) ($p->window_menit ?: $defaultWindow)));

                return Carbon::parse($p->token_rotated_at)->addMinutes($window)->lt($now);
            }

            return false;
        })->values();

        if ($pertemuans->isEmpty()) {
            $this->info('Tidak ada sesi presensi yang perlu ditutup otomatis.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($pertemuans, $now) {
            foreach ($pertemuans as $pertemuan) {
                $oldValues = $pertemuan->getOriginal();

                $pertemuan->update([
                    'presensi_closed_at' => $now,
                    'token_absensi'      => null,
                    'token_expired_at'   => null,
                    'token_rotated_at'   => null,
                    'status_pertemuan'   => Pertemuan::STATUS_SELESAI,
                ]);

                Cache::forget("lms:presensi:prev_token:{$pertemuan->id}");

                try {
                    AuditLogService::record(
                        module: 'SIAKAD',
                        action: 'auto_close_presensi',
                        tableName: 'siakad_pertemuan',
                        recordId: $pertemuan->id,
                        oldValues: $oldValues,
                        newValues: $pertemuan->getChanges(),
                        actorId: null
                    );
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning('Gagal mencatat audit log penutupan presensi otomatis: ' . $e->getMessage());
                }
            }
        });

        $this->info("Berhasil menutup otomatis {$pertemuans->count()} sesi presensi.");

        return self::SUCCESS;
    }
}
