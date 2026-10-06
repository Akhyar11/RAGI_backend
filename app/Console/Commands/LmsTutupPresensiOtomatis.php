<?php

namespace App\Console\Commands;

use App\Models\Siakad\Pertemuan;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Tutup otomatis sesi presensi yang jam selesainya sudah lewat.
 *
 * Menutup = presensi_closed_at diisi, token dibersihkan, status jadi
 * 'selesai'. Input token mahasiswa otomatis ditolak setelah ini
 * (lihat LmsService::inputTokenAbsensi), sedangkan koreksi manual
 * dosen via bulk tetap dimungkinkan.
 */
class LmsTutupPresensiOtomatis extends Command
{
    protected $signature = 'lms:tutup-presensi-otomatis';

    protected $description = 'Tutup otomatis sesi presensi LMS yang jam selesainya sudah lewat';

    public function handle(): int
    {
        $now = now();

        $ids = Pertemuan::whereNull('presensi_closed_at')
            ->whereNotNull('tanggal')
            ->whereNotNull('jam_selesai')
            ->whereRaw("CONCAT(tanggal, ' ', jam_selesai) < ?", [$now->toDateTimeString()])
            ->pluck('id');

        if ($ids->isEmpty()) {
            $this->info('Tidak ada sesi presensi yang perlu ditutup otomatis.');
            return self::SUCCESS;
        }

        DB::transaction(function () use ($ids, $now) {
            Pertemuan::whereIn('id', $ids)->update([
                'presensi_closed_at' => $now,
                'token_absensi' => null,
                'token_expired_at' => null,
                'status_pertemuan' => 'selesai',
                'updated_at' => $now,
            ]);
        });

        $this->info("Berhasil menutup otomatis {$ids->count()} sesi presensi.");

        return self::SUCCESS;
    }
}
