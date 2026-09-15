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
        $this->info('🚀 Memulai sinkronisasi akun SSO Pegawai...');
        $pegawais = Pegawai::with(['dosen', 'roles', 'user'])->get();

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
                ['Total Pegawai', $pegawais->count()],
                ['Akun SSO Baru Dibuat', $created],
                ['Akun SSO Terhubung/Diperbarui', $synced],
            ]
        );

        return self::SUCCESS;
    }
}
