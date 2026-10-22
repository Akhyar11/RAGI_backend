<?php

namespace App\Console\Commands\Sikeu;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class SyncSikeuLegacy extends Command
{
    protected $signature = 'sikeu:sync-legacy 
                            {--dry-run : Hanya simulasi tanpa insert/update ke database RAG}
                            {--only=all : Pilih modul: all|mahasiswa|bayar|biayalain|datakas|rkpbyr|trbyr|trkas|dispensasi}
                            {--since= : Sync incremental untuk trbyr/trkas dengan filter tanggal (YYYY-MM-DD)}
                            {--force : Lanjutkan meski ada error per baris}
                            {--limit=0 : Batasi jumlah baris per tabel (0 = semua)}';

    protected $description = 'Sync data SIK Legacy langsung dari MySQL terpisah (192.168.5.167) ke database RAG SIKEU dengan logging & rollback per run.';

    private int $success = 0;
    private int $skipped = 0;
    private int $failed = 0;
    private float $nominal = 0;
    private array $errors = [];
    private int $runLogId = 0;
    private string $logFile = '';

    public function handle(): void
    {
        $this->info('=== SYNC SIKU LEGACY (DB -> RAG) ===');
        $dryRun = $this->option('dry-run') || env('SIK_LEGACY_SYNC_DRY_RUN', false);
        $only = $this->option('only');
        $since = $this->option('since');
        $force = $this->option('force');
        $limit = (int) $this->option('limit');

        if ($dryRun) {
            $this->warn('MODE DRY-RUN: Tidak ada perubahan data di database RAG.');
        }

        $this->logFile = storage_path('logs/sikeu/sikeu-legacy-sync-' . date('Ymd_His') . '.log');
        File::ensureDirectoryExists(dirname($this->logFile));

        $this->log("=== SYNC START ===");
        $this->log("only={$only} since=" . ($since ?: '-') . " limit={$limit} dryRun=" . ($dryRun ? '1' : '0'));
        $this->log("Source: " . env('SIK_LEGACY_DB_HOST') . ':' . env('SIK_LEGACY_DB_PORT') . '/' . env('SIK_LEGACY_DB_DATABASE'));
        $this->log("Target: " . config('database.default') . ' (' . config('database.connections.' . config('database.default') . '.database') . ')');

        try {
            DB::connection('mysql_sik_legacy')->getPdo();
            $this->info('[OK] Koneksi mysql_sik_legacy berhasil');
            $this->log('[OK] Koneksi mysql_sik_legacy berhasil');
        } catch (\Throwable $e) {
            $this->error('[FAIL] Tidak bisa konek ke DB legacy: ' . $e->getMessage());
            $this->log('[FAIL] ' . $e->getMessage());
            return;
        }

        if (!$dryRun) {
            try {
                if (!Schema::hasTable('_mig_run_log')) {
                    $this->warn('Tabel _mig_run_log belum ada, akan melanjutkan tanpa log run.');
                } else {
                    $this->runLogId = DB::table('_mig_run_log')->insertGetId([
                        'command_name' => 'sikeu:sync-legacy',
                        'source' => 'MYSQL_LEGACY_DIRECT',
                        'status' => 'running',
                        'started_at' => now(),
                        'total_records' => 0,
                        'success_count' => 0,
                        'skipped_count' => 0,
                        'failed_count' => 0,
                        'total_nominal_migrated' => 0,
                        'notes' => json_encode(['only' => $only, 'since' => $since, 'limit' => $limit]),
                    ]);
                }
            } catch (\Throwable $e) {
                $this->log('[WARN] gagal buat run log: ' . $e->getMessage());
            }
        }

        DB::beginTransaction();
        try {
            if ($only === 'all' || $only === 'mahasiswa') $this->syncMahasiswa($dryRun, $limit);
            if ($only === 'all' || $only === 'bayar') $this->syncBayar($dryRun, $limit);
            if ($only === 'all' || $only === 'biayalain') $this->syncBiayaLain($dryRun, $limit);
            if ($only === 'all' || $only === 'datakas') $this->syncDatakasAkun($dryRun, $limit);
            if ($only === 'all' || $only === 'rkpbyr') $this->syncRkpbyr($dryRun, $limit);
            if ($only === 'all' || $only === 'trbyr') $this->syncTrbyr($dryRun, $limit, $since);
            if ($only === 'all' || $only === 'trkas') $this->syncTrkas($dryRun, $limit, $since);
            if ($only === 'all' || $only === 'dispensasi') $this->syncDispensasi($dryRun, $limit);

            if ($dryRun) {
                DB::rollBack();
                $this->info('[ROLLBACK] Dry-run, tidak ada perubahan tersimpan');
            } else {
                DB::commit();
                $this->info('[COMMIT] Perubahan tersimpan ke database RAG');
            }

            $this->printSummary();
            $this->logSummary();

            if (!$dryRun && $this->runLogId && Schema::hasTable('_mig_run_log')) {
                try {
                    DB::table('_mig_run_log')->where('id', $this->runLogId)->update([
                        'status' => 'completed',
                        'finished_at' => now(),
                        'total_records' => $this->success + $this->failed + $this->skipped,
                        'success_count' => $this->success,
                        'skipped_count' => $this->skipped,
                        'failed_count' => $this->failed,
                        'total_nominal_migrated' => $this->nominal,
                        'notes' => json_encode(['errors_count' => count($this->errors), 'log_file' => $this->logFile]),
                    ]);
                } catch (\Throwable $e) {}
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('[ERROR] ' . $e->getMessage());
            $this->log('[FATAL] ' . $e->getMessage());
            if (!$dryRun && $this->runLogId && Schema::hasTable('_mig_run_log')) {
                try {
                    DB::table('_mig_run_log')->where('id', $this->runLogId)->update([
                        'status' => 'failed',
                        'finished_at' => now(),
                        'notes' => json_encode(['error' => substr($e->getMessage(), 0, 500)]),
                    ]);
                } catch (\Throwable $e2) {}
            }
            return;
        }

        $this->info('Log tersimpan: ' . $this->logFile);
    }

    private function syncMahasiswa(bool $dryRun, int $limit): void
    {
        $this->info('[SYNC] mahasiswa');
        $q = DB::connection('mysql_sik_legacy')->table('mahasiswa')->orderBy('nim');
        if ($limit > 0) $q->limit($limit);
        $rows = $q->get();
        $bar = $this->output->createProgressBar($rows->count());
        foreach ($rows as $r) {
            try {
                $nim = trim($r->nim ?? '');
                if ($nim === '') { $this->skipped++; $bar->advance(); continue; }
                if (!$dryRun) {
                    try {
                        DB::table('_mig_mahasiswa_mapping')->updateOrInsert(
                            ['nim_lama' => $nim],
                            [
                                'nama_lama' => trim($r->nama ?? ''),
                                'kode_lama' => trim($r->kode ?? ($r->kode_kelas ?? '')),
                                'updated_at' => now(),
                            ]
                        );
                        // coba mapping ke siakad_mahasiswas
                        $mhsBaru = DB::table('siakad_mahasiswas')->where('nim', $nim)->first();
                        if (!$mhsBaru) {
                            $mhsBaru = DB::table('siakad_mahasiswas')->whereRaw('LOWER(REPLACE(nama_lengkap," ","")) = ?', [strtolower(str_replace(' ', '', trim($r->nama ?? '')))])->first();
                        }
                        if ($mhsBaru) {
                            DB::table('_mig_mahasiswa_mapping')->where('nim_lama', $nim)->update([
                                'mahasiswa_id_baru' => $mhsBaru->id,
                                'nim_baru' => $mhsBaru->nim,
                                'status' => 'mapped',
                                'updated_at' => now(),
                            ]);
                        }
                    } catch (\Throwable $e) {
                        $this->recordError('mahasiswa', $nim, $e->getMessage());
                        if (!$this->option('force')) throw $e;
                        $this->failed++;
                        $bar->advance();
                        continue;
                    }
                }
                $this->success++;
            } catch (\Throwable $e) {
                $this->recordError('mahasiswa', 'unknown', $e->getMessage());
                $this->failed++;
            }
            $bar->advance();
        }
        $bar->finish(); $this->newLine();
    }

    private function syncBayar(bool $dryRun, int $limit): void
    {
        $this->info('[SYNC] bayar (master tarif)');
        $rows = DB::connection('mysql_sik_legacy')->table('bayar')->get();
        if ($limit > 0) $rows = $rows->take($limit);
        if (!$dryRun) {
            if (!Schema::hasTable('_mig_bayar_mapping')) {
                Schema::create('_mig_bayar_mapping', function ($t) {
                    $t->string('kode_bayar_lama')->primary();
                    $t->integer('bdaftar')->default(0); $t->integer('batribut')->default(0); $t->integer('bherreg')->default(0);
                    $t->integer('bikam')->default(0); $t->integer('bspp')->default(0); $t->integer('lama')->default(0);
                    $t->integer('bkp')->default(0); $t->integer('bskripsi')->default(0); $t->integer('bwisuda')->default(0); $t->integer('setspp')->default(0);
                    $t->timestamps();
                });
            }
            $masters = [
                ['kode'=>'BIA-DAFTAR','nama'=>'Biaya Pendaftaran','tipe'=>'spmb_adm','legacy_id'=>'bdaftar'],
                ['kode'=>'BIA-ATRIBUT','nama'=>'Biaya Atribut','tipe'=>'lainnya','legacy_id'=>'batribut'],
                ['kode'=>'BIA-HERREG','nama'=>'Her-Registrasi','tipe'=>'spp','legacy_id'=>'bherreg'],
                ['kode'=>'BIA-IKAM','nama'=>'Biaya IKAM','tipe'=>'lainnya','legacy_id'=>'bikam'],
                ['kode'=>'BIA-SPP','nama'=>'SPP','tipe'=>'spp','legacy_id'=>'bspp'],
                ['kode'=>'BIA-KP','nama'=>'Biaya KP/Praktikum','tipe'=>'praktikum','legacy_id'=>'bkp'],
                ['kode'=>'BIA-SKRIPSI','nama'=>'Biaya Skripsi/TA','tipe'=>'lainnya','legacy_id'=>'bskripsi'],
                ['kode'=>'BIA-WISUDA','nama'=>'Biaya Wisuda','tipe'=>'wisuda','legacy_id'=>'bwisuda'],
            ];
            foreach ($masters as $m) {
                try { DB::table('sikeu_master_biaya')->updateOrInsert(['kode'=>$m['kode']], ['nama'=>$m['nama'],'tipe'=>$m['tipe'],'legacy_id'=>$m['legacy_id'],'is_active'=>true,'is_recurring'=>true,'updated_at'=>now(),'created_at'=>now()]); } catch (\Throwable $e) {}
            }
            foreach ($rows as $r) {
                try {
                    $kode = trim($r->kode ?? '');
                    if ($kode==='') continue;
                    DB::table('_mig_bayar_mapping')->updateOrInsert(['kode_bayar_lama'=>$kode], [
                        'kode_bayar_lama'=>$kode,
                        'bdaftar'=>(int)($r->bdaftar??0),'batribut'=>(int)($r->batribut??0),'bherreg'=>(int)($r->bherreg??0),
                        'bikam'=>(int)($r->bikam??0),'bspp'=>(int)($r->bspp??0),'lama'=>(int)($r->lama??0),
                        'bkp'=>(int)($r->bkp??0),'bskripsi'=>(int)($r->bskripsi??0),'bwisuda'=>(int)($r->bwisuda??0),'setspp'=>(int)($r->setspp??0),
                        'updated_at'=>now(),'created_at'=>now()
                    ]);
                    $this->success++;
                } catch (\Throwable $e) { $this->recordError('bayar',$r->kode??'?', $e->getMessage()); $this->failed++; }
            }
        } else {
            $this->success += $rows->count();
        }
        $this->info('bayar diproses: ' . $rows->count());
    }

    private function syncBiayaLain(bool $dryRun, int $limit): void
    {
        $this->info('[SYNC] biayalain');
        $rows = DB::connection('mysql_sik_legacy')->table('biayalain')->get();
        if ($limit > 0) $rows = $rows->take($limit);
        if (!$dryRun) {
            foreach ($rows as $r) {
                try {
                    $kode = trim($r->kode ?? ''); $nama = trim($r->jenis ?? ''); $jml = (float)($r->jumlah ?? 0);
                    if ($kode===''||$nama==='') { $this->skipped++; continue; }
                    $kodeRag = 'BIA-LAIN-'.str_pad($kode,3,'0',STR_PAD_LEFT);
                    DB::table('sikeu_master_biaya')->updateOrInsert(['kode'=>$kodeRag], ['nama'=>$nama,'tipe'=>'lainnya','legacy_id'=>$kode,'is_active'=>true,'is_recurring'=>false,'updated_at'=>now(),'created_at'=>now()]);
                    $this->success++;
                } catch (\Throwable $e) { $this->recordError('biayalain',$r->kode??'?', $e->getMessage()); $this->failed++; }
            }
        } else { $this->success += $rows->count(); }
    }

    private function syncDatakasAkun(bool $dryRun, int $limit): void
    {
        $this->info('[SYNC] datakas -> _mig_akun_mapping');
        $rows = DB::connection('mysql_sik_legacy')->table('datakas')->get();
        if ($limit > 0) $rows = $rows->take($limit);
        if (!$dryRun) {
            if (!Schema::hasTable('_mig_akun_mapping')) {
                Schema::create('_mig_akun_mapping', function ($t) {
                    $t->id(); $t->string('kd_perkiraan_lama'); $t->string('nama_akun_lama')->nullable();
                    $t->unsignedBigInteger('akun_id_baru')->nullable(); $t->string('kode_akun_baru')->nullable();
                    $t->timestamps(); $t->index('kd_perkiraan_lama');
                });
            }
            foreach ($rows as $r) {
                try {
                    $kd = trim($r->kode ?? '');
                    if ($kd==='') { $this->skipped++; continue; }
                    DB::table('_mig_akun_mapping')->updateOrInsert(['kd_perkiraan_lama'=>$kd], ['nama_akun_lama'=>trim($r->nama??''),'updated_at'=>now(),'created_at'=>now()]);
                    $this->success++;
                } catch (\Throwable $e) { $this->recordError('datakas',$r->kode??'?', $e->getMessage()); $this->failed++; }
            }
        } else { $this->success += $rows->count(); }
        $this->info('datakas diproses: ' . $rows->count() . ' (isi akun_id_baru/kode_akun_baru manual atau via mapping lanjutan)');
    }

    private function syncRkpbyr(bool $dryRun, int $limit): void
    {
        $this->info('[SYNC] rkpbyr');
        $q = DB::connection('mysql_sik_legacy')->table('rkpbyr');
        if ($limit > 0) $q->limit($limit);
        $rows = $q->get();
        $bar = $this->output->createProgressBar($rows->count());
        foreach ($rows as $r) {
            try {
                $nim = trim($r->nim ?? $r->no_pend ?? '');
                $kodeBayar = trim($r->kode ?? '');
                if ($nim===''||$kodeBayar==='') { $this->skipped++; $bar->advance(); continue; }
                if (!$dryRun) {
                    $mhs = DB::table('_mig_mahasiswa_mapping')->where('nim_lama',$nim)->first();
                    if (!$mhs || !$mhs->mahasiswa_id_baru) { $this->recordUnresolved('rkpbyr',$nim,(array)$r,'nim_not_mapped'); $this->failed++; $bar->advance(); continue; }
                    $legacyKey = $nim.'|'.$kodeBayar;
                    if (DB::table('sikeu_tagihan_mahasiswa')->where('legacy_id',$legacyKey)->exists()) { $this->skipped++; $bar->advance(); continue; }
                    $total=0; for ($i=13;$i<=60;$i++){ $col='spp'.$i; if(property_exists($r,$col)) $total+=(float)($r->$col??0); }
                    $pot = (float)($r->potongan??0);
                    if ($total < $pot) $total = $pot;
                    $tagihanId = DB::table('sikeu_tagihan_mahasiswa')->insertGetId([
                        'mahasiswa_id'=>$mhs->mahasiswa_id_baru,
                        'nomor_tagihan'=>'TRX-LEG-'.strtoupper(substr(md5($legacyKey.microtime()),0,14)),
                        'total_tagihan'=>max($total-$pot,0),'total_potongan'=>$pot,'total_denda'=>0,'total_bayar'=>0,
                        'status'=>'belum_bayar','requires_approval'=>false,'status_approval'=>'approved',
                        'source_system'=>'LEGACY_SYNC','legacy_id'=>$legacyKey,'legacy_source'=>'LEGACY_STMIK_2016',
                        'created_at'=>now(),'updated_at'=>now()
                    ]);
                    $this->nominal += max($total-$pot,0);
                }
                $this->success++;
            } catch (\Throwable $e) {
                $this->recordError('rkpbyr',$r->nim??'unknown',$e->getMessage());
                $this->failed++;
            }
            $bar->advance();
        }
        $bar->finish(); $this->newLine();
    }

    private function syncTrbyr(bool $dryRun, int $limit, ?string $since): void
    {
        $this->info('[SYNC] trbyr');
        $q = DB::connection('mysql_sik_legacy')->table('trbyr')->orderBy('tgltran');
        if ($since) $q->where('tgltran','>=',$since);
        if ($limit > 0) $q->limit($limit);
        $rows = $q->get();
        $bar = $this->output->createProgressBar($rows->count());
        foreach ($rows as $r) {
            try {
                $noTran = trim($r->notran ?? ''); $nim = trim($r->nim ?? $r->no_pend ?? ''); $jml = (float)($r->jmlbayar ?? 0);
                if ($noTran===''||$jml==0) { $this->skipped++; $bar->advance(); continue; }
                if (!$dryRun) {
                    if (DB::table('sikeu_pembayaran')->where('kode_transaksi',$noTran)->exists()) { $this->skipped++; $bar->advance(); continue; }
                    $mhs = DB::table('_mig_mahasiswa_mapping')->where('nim_lama',$nim)->first();
                    $tagihanId = null;
                    if ($mhs && $mhs->mahasiswa_id_baru) {
                        $tagihanId = DB::table('sikeu_tagihan_mahasiswa')->where('mahasiswa_id',$mhs->mahasiswa_id_baru)->where('legacy_source','LEGACY_STMIK_2016')->orderByDesc('id')->value('id');
                        if (!$tagihanId) $tagihanId = DB::table('sikeu_tagihan_mahasiswa')->where('mahasiswa_id',$mhs->mahasiswa_id_baru)->orderByDesc('id')->value('id');
                    }
                    if (!$tagihanId) { $this->recordUnresolved('trbyr',$noTran,(array)$r,'tagihan_not_found'); $this->failed++; $bar->advance(); continue; }
                    $tgl = $this->parseDate($r->tgltran);
                    DB::table('sikeu_pembayaran')->insert([
                        'tagihan_id'=>$tagihanId,'kode_transaksi'=>$noTran,'jumlah_bayar'=>$jml,
                        'waktu_bayar'=>$tgl?:now(),'channel_bayar'=>'LOKET_TUNAI','status'=>'success',
                        'legacy_id'=>$noTran,'legacy_source'=>'LEGACY_STMIK_2016','created_at'=>now(),'updated_at'=>now()
                    ]);
                    $this->nominal += $jml;
                }
                $this->success++;
            } catch (\Throwable $e) {
                $this->recordError('trbyr',$r->notran??'unknown',$e->getMessage());
                $this->failed++;
            }
            $bar->advance();
        }
        $bar->finish(); $this->newLine();
    }

    private function syncTrkas(bool $dryRun, int $limit, ?string $since): void
    {
        $this->info('[SYNC] trkas');
        $q = DB::connection('mysql_sik_legacy')->table('trkas')->orderBy('tgl_tran');
        if ($since) $q->where('tgl_tran','>=',$since);
        if ($limit > 0) $q->limit($limit);
        $rows = $q->get();
        $bar = $this->output->createProgressBar($rows->count());
        $unitKasId = !$dryRun ? (DB::table('sikeu_unit_kas')->value('id') ?: DB::table('sikeu_unit_kas')->insertGetId(['nama_kas'=>'Kas Utama STMIK','saldo_awal'=>0,'saldo_saat_ini'=>0,'status'=>true,'created_at'=>now(),'updated_at'=>now()])) : null;
        foreach ($rows as $r) {
            try {
                $noTran = trim($r->no_tran ?? ''); $jenis = (int)($r->jenis ?? 0); $jml = (float)($r->jumlah ?? 0); $kd = trim($r->kd_perkiraan ?? '');
                if ($noTran===''||$jml==0) { $this->skipped++; $bar->advance(); continue; }
                if (!$dryRun) {
                    $map = DB::table('_mig_akun_mapping')->where('kd_perkiraan_lama',$kd)->first();
                    if (!$map || !$map->akun_id_baru) { $this->recordUnresolved('trkas',$noTran,(array)$r,'akun_not_mapped_or_unassigned'); $this->failed++; $bar->advance(); continue; }
                    if (DB::table('sikeu_jurnal_umum')->where('kode_transaksi',$noTran)->exists()) { $this->skipped++; $bar->advance(); continue; }
                    $tgl = $this->parseDate($r->tgl_tran);
                    DB::table('sikeu_jurnal_umum')->insert([
                        'unit_kas_id'=>$unitKasId,'kode_transaksi'=>$noTran,'tanggal_jurnal'=>$tgl?:now(),
                        'jenis_sumber'=>$jenis==1?'pemasukan':'pengeluaran',
                        'total_debet'=>$jenis==1?$jml:0,'total_kredit'=>$jenis==2?$jml:0,
                        'keterangan'=>substr('Migrasi trkas: '.trim($r->ket??''),0,500),
                        'legacy_id'=>$noTran,'legacy_source'=>'LEGACY_STMIK_2016','created_at'=>now(),'updated_at'=>now()
                    ]);
                }
                $this->success++;
            } catch (\Throwable $e) {
                $this->recordError('trkas',$r->no_tran??'unknown',$e->getMessage());
                $this->failed++;
            }
            $bar->advance();
        }
        $bar->finish(); $this->newLine();
    }

    private function syncDispensasi(bool $dryRun, int $limit): void
    {
        $this->info('[SYNC] dispensasi');
        $rows = DB::connection('mysql_sik_legacy')->table('dispensasi')->get();
        if ($limit > 0) $rows = $rows->take($limit);
        if ($rows->count() == 0) { $this->info('dispensasi kosong di legacy'); return; }
        $bar = $this->output->createProgressBar($rows->count());
        foreach ($rows as $r) {
            try {
                $nim = trim($r->nim ?? ''); if ($nim==='') { $this->skipped++; $bar->advance(); continue; }
                if (!$dryRun) {
                    $mhs = DB::table('_mig_mahasiswa_mapping')->where('nim_lama',$nim)->first();
                    if (!$mhs || !$mhs->mahasiswa_id_baru) { $this->recordUnresolved('dispensasi',$nim,(array)$r,'nim_not_mapped'); $this->failed++; $bar->advance(); continue; }
                    $sd = strtolower(trim($r->status_dispen??$r->status??'pending'));
                    $status = in_array($sd,['lunas','approved','disetujui'])?'approved':(in_array($sd,['ditolak','tolak'])?'rejected':'pending');
                    $legacyKey = $nim.'|'.($r->semester??'').'-'.($r->th_akademik??'');
                    if (DB::table('sikeu_dispensasi_tagihan')->where('legacy_id',$legacyKey)->exists()) { $this->skipped++; $bar->advance(); continue; }
                    DB::table('sikeu_dispensasi_tagihan')->insert([
                        'mahasiswa_id'=>$mhs->mahasiswa_id_baru,'tagihan_id'=>1,
                        'tipe_dispensasi'=>'penundaan_jatuh_tempo','alasan'=>'Migrasi dispensasi legacy | pemberi: '.($r->pemberi??'-'),
                        'status'=>$status,'jatuh_tempo_baru'=>$this->parseDate($r->tgl_bayar)?:now()->addMonth(),
                        'nominal_per_cicilan'=>(float)($r->total??0),'jumlah_cicilan'=>1,
                        'legacy_id'=>$legacyKey,'legacy_source'=>'LEGACY_STMIK_2016','created_at'=>now(),'updated_at'=>now()
                    ]);
                }
                $this->success++;
            } catch (\Throwable $e) {
                $this->recordError('dispensasi',$r->nim??'unknown',$e->getMessage());
                $this->failed++;
            }
            $bar->advance();
        }
        $bar->finish(); $this->newLine();
    }

    private function parseDate($d): ?string
    {
        if (!$d) return null; $d = trim((string)$d);
        if ($d===''||$d==='0000-00-00') return null;
        try { return Carbon::parse($d)->format('Y-m-d H:i:s'); } catch (\Throwable $e) { return null; }
    }

    private function recordError(string $table, string $key, string $msg): void
    {
        $this->errors[] = ['table'=>$table,'key'=>$key,'msg'=>substr($msg,0,300)];
        $this->log("[ERR][$table][$key] $msg");
        if (Schema::hasTable('_mig_unresolved') && !$this->option('dry-run')) {
            try {
                DB::table('_mig_unresolved')->insert([
                    'source_table'=>$table,'legacy_key'=>substr($key,0,191),'raw_data'=>json_encode(['sync'=>'direct']),'failure_reason'=>substr($msg,0,255),'status'=>'pending_review','created_at'=>now(),'updated_at'=>now()
                ]);
            } catch (\Throwable $e) {}
        }
    }

    private function recordUnresolved(string $table, string $key, array $raw, string $reason): void
    {
        $this->log("[UNRESOLVED][$table][$key] $reason");
        if (Schema::hasTable('_mig_unresolved') && !$this->option('dry-run')) {
            try {
                DB::table('_mig_unresolved')->insert([
                    'source_table'=>$table,'legacy_key'=>substr($key,0,191),'raw_data'=>json_encode($raw),'failure_reason'=>substr($reason,0,255),'status'=>'pending_review','created_at'=>now(),'updated_at'=>now()
                ]);
            } catch (\Throwable $e) {}
        }
    }

    private function printSummary(): void
    {
        $this->newLine();
        $this->info('=== RINGKASAN ===');
        $this->info("Success : {$this->success}");
        $this->info("Skipped : {$this->skipped}");
        $this->info("Failed  : {$this->failed}");
        $this->info("Nominal : " . number_format($this->nominal, 0, ',', '.'));
        $this->info("Errors  : " . count($this->errors));
        $this->newLine();
        $this->info('Cek unresolved: SELECT source_table, COUNT(*) FROM _mig_unresolved GROUP BY source_table;');
    }

    private function logSummary(): void
    {
        $this->log("=== SUMMARY === success={$this->success} skipped={$this->skipped} failed={$this->failed} nominal={$this->nominal} errors=" . count($this->errors));
    }

    private function log(string $msg): void
    {
        $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
        File::append($this->logFile, $line);
    }
}
