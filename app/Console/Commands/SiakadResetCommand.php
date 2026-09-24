<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SiakadResetCommand extends Command
{
    protected $signature = 'siakad:reset
        {--force : Lewati konfirmasi dan langsung kosongkan}';

    protected $description = 'Kosongkan seluruh data modul SIAKAD (tabel siakad_*) agar production mulai dari awal. Master SPMB (prodi, tahun akademik, referensi) dan IAM tidak ikut dihapus.';

    public function handle(): int
    {
        $tables = collect(DB::select("SELECT name FROM sqlite_master WHERE type = 'table'"))
            ->map(fn ($r) => $r->name)
            ->filter(fn ($t) => str_starts_with($t, 'siakad_'))
            ->values();

        if ($tables->isEmpty()) {
            // Non-sqlite (mysql/pgsql): ambil dari information_schema
            $db = DB::getDatabaseName();
            $tables = collect(DB::select(
                "SELECT TABLE_NAME AS name FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME LIKE 'siakad\\_%'",
                [$db]
            ))->map(fn ($r) => $r->name)->values();
        }

        if ($tables->isEmpty()) {
            $this->warn('Tidak ada tabel siakad_* yang ditemukan.');
            return self::SUCCESS;
        }

        $counts = [];
        foreach ($tables as $t) {
            try {
                $counts[$t] = DB::table($t)->count();
            } catch (\Throwable) {
                $counts[$t] = '?';
            }
        }

        $this->table(['Tabel', 'Baris'], collect($counts)->map(fn ($c, $t) => [$t, $c])->values()->toArray());
        $this->info('Master SPMB (prodi, tahun akademik, referensi) dan IAM TIDAK ikut dihapus.');

        if (!$this->option('force') && !$this->confirm('Kosongkan SEMUA data SIAKAD di atas? Tindakan ini tidak dapat dibatalkan.', false)) {
            $this->info('Dibatalkan.');
            return self::SUCCESS;
        }

        Schema::disableForeignKeyConstraints();
        try {
            foreach ($tables as $t) {
                DB::table($t)->truncate();
            }
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        $this->info('Selesai: ' . $tables->count() . ' tabel SIAKAD dikosongkan. Siap input dari awal di production.');
        return self::SUCCESS;
    }
}
