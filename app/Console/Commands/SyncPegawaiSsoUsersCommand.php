<?php

namespace App\Console\Commands;

use App\Models\Simpeg\Pegawai;
use App\Services\Simpeg\PegawaiService;
use Illuminate\Console\Command;

class SyncPegawaiSsoUsersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'simpeg:sync-sso-users';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sinkronisasi dan pembuatan akun SSO otomatis untuk seluruh data Pegawai berdasarkan skala prioritas username (NIDN -> NUPTK -> NIP)';

    /**
     * Execute the console command.
     */
    public function handle(PegawaiService $pegawaiService): int
    {
        $this->info('🚀 Memulai sinkronisasi akun SSO Pegawai (Hanya Pegawai/Dosen Aktif)...');

        // 1. Bersihkan akun SSO untuk pegawai/dosen yang tidak aktif
        $inactivePegawais = Pegawai::where('status', '!=', 'aktif')
            ->whereNotNull('user_id')
            ->get();

        $cleaned = 0;
        foreach ($inactivePegawais as $inact) {
            $uId = $inact->user_id;
            $inact->update(['user_id' => null]);
            if ($inact->dosen) {
                $inact->dosen->update(['user_id' => null]);
            }
            $userObj = \App\Models\User::find($uId);
            if ($userObj) {
                $userObj->roles()->detach();
                $userObj->delete();
            }
            $cleaned++;
        }

        // 2. Buat / sinkronkan akun SSO hanya untuk pegawai yang aktif
        $pegawais = Pegawai::with(['dosen', 'roles', 'user'])
            ->where('status', 'aktif')
            ->get();

        $bar = $this->output->createProgressBar($pegawais->count());
        $bar->start();

        $synced = 0;
        $created = 0;

        foreach ($pegawais as $pegawai) {
            $hadUser = !empty($pegawai->user_id);
            $user = $pegawaiService->ensureSsoUserForPegawai($pegawai);

            if (!$hadUser && $user) {
                $created++;
            } else {
                $synced++;
            }

            // Pastikan relasi dosen di siakad juga terhubung
            $pegawaiService->syncDosenRecord($pegawai);

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("✅ Sinkronisasi selesai!");
        $this->table(
            ['Kategori', 'Jumlah'],
            [
                ['Total Pegawai Aktif Diproses', $pegawais->count()],
                ['Akun SSO Baru Dibuat', $created],
                ['Akun SSO Terhubung/Diperbarui', $synced],
                ['Akun SSO Pegawai Tidak Aktif yang Dihapus', $cleaned],
            ]
        );

        return self::SUCCESS;
    }
}
