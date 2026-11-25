<?php

namespace App\Console\Commands\Sikeu;

use App\Models\Siakad\Mahasiswa;
use App\Models\Sikeu\TagihanMahasiswa;
use App\Models\Spmb\PendaftaranCalonMhs;
use App\Services\AuditLogService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class ReconcileLegacyPayments extends Command
{
    protected $signature = 'sikeu:reconcile-legacy-payments
        {--dry-run : Simulasi pencocokan tanpa menyimpan perubahan ke database}
        {--limit=0 : Batasi jumlah tagihan yang diproses (0 = semua)}';

    protected $description = 'Mencocokkan tagihan/pembayaran migrasi yang belum terhubung (mahasiswa_id/calon_mahasiswa_id null) menggunakan h2h_custid dan catatan pembayaran (NIM / No Pendaftaran / Nama).';

    private function updateAndAudit(TagihanMahasiswa $tagihan, array $attributes): void
    {
        $oldValues = $tagihan->getOriginal();
        $tagihan->update($attributes);

        try {
            AuditLogService::record(
                module: 'SIKEU',
                action: 'update',
                tableName: 'sikeu_tagihan_mahasiswa',
                recordId: (int) $tagihan->id,
                oldValues: $oldValues,
                newValues: $tagihan->getChanges()
            );
        } catch (\Throwable $e) {
            Log::warning("Gagal mencatat audit log reconcile command #{$tagihan->id}: " . $e->getMessage());
        }
    }

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $limit = max(0, (int) $this->option('limit'));

        $query = TagihanMahasiswa::with(['pembayarans' => fn ($q) => $q->orderBy('id')])
            ->where(function ($q) {
                $q->where(function ($sub) {
                    $sub->whereNull('mahasiswa_id')->whereNull('calon_mahasiswa_id');
                })->orWhere(function ($sub) {
                    $sub->whereNotNull('mahasiswa_id')
                        ->whereNotExists(function ($ex) {
                            $ex->select(DB::raw(1))
                                ->from('siakad_mahasiswa')
                                ->whereColumn('siakad_mahasiswa.id', 'sikeu_tagihan_mahasiswa.mahasiswa_id');
                        });
                });
            })
            ->orderBy('id');

        if ($limit > 0) {
            $query->limit($limit);
        }

        $tagihans = $query->get();
        $hasMigMapping = Schema::hasTable('sikeu_mig_mahasiswa_mapping');

        $stats = [
            'scanned' => $tagihans->count(),
            'matched_by_nim' => 0,
            'matched_by_calon' => 0,
            'matched_by_unique_name' => 0,
            'unmatched' => 0,
        ];

        $this->info(($dryRun ? '[DRY-RUN] ' : '') . "Memeriksa {$stats['scanned']} tagihan belum terhubung...");

        foreach ($tagihans as $tagihan) {
            $catatanList = $tagihan->pembayarans->pluck('catatan')->filter()->values();
            $matched = false;

            // 1. Prioritas 1: Cocokkan NIM dari h2h_custid atau pola "NIM: <nim>" di catatan
            $nimCandidates = [];
            if (!empty($tagihan->h2h_custid)) {
                $nimCandidates[] = trim((string) $tagihan->h2h_custid);
            }
            foreach ($catatanList as $catatan) {
                if (preg_match('/\bNIM\s*[:\-]?\s*([A-Za-z0-9.\-]+)/i', (string) $catatan, $m)) {
                    $nimCandidates[] = trim($m[1]);
                }
            }

            foreach (array_unique(array_filter($nimCandidates)) as $nim) {
                $mhsId = Mahasiswa::where('nim', $nim)->value('id');
                if (!$mhsId && $hasMigMapping) {
                    $mhsId = DB::table('sikeu_mig_mahasiswa_mapping')
                        ->where('nim_lama', $nim)
                        ->value('mahasiswa_id_baru');
                }
                if ($mhsId) {
                    if (!$dryRun) {
                        $this->updateAndAudit($tagihan, [
                            'mahasiswa_id' => $mhsId,
                            'h2h_custid' => $tagihan->h2h_custid ?: $nim,
                        ]);
                    }
                    $stats['matched_by_nim']++;
                    $matched = true;
                    break;
                }

                // Cek apakah kandidat sebenarnya no_pendaftaran calon mahasiswa
                $calonId = PendaftaranCalonMhs::where('no_pendaftaran', $nim)->value('id');
                if ($calonId) {
                    if (!$dryRun) {
                        $this->updateAndAudit($tagihan, ['calon_mahasiswa_id' => $calonId]);
                    }
                    $stats['matched_by_calon']++;
                    $matched = true;
                    break;
                }
            }

            if ($matched) {
                continue;
            }

            // 2. Prioritas 2: Ekstrak nama pengirim / mahasiswa dari catatan (hanya jika unik = 1 record)
            foreach ($catatanList as $catatan) {
                if (preg_match('/(?:AN\s+PENGR?I+M|NAMA\s*[:\-])\s*([A-Za-z.\'\s]{3,80})/i', (string) $catatan, $m)) {
                    $candidateName = trim(preg_replace('/\s+/', ' ', $m[1]));
                    if ($candidateName === '') {
                        continue;
                    }

                    $mhsMatches = Mahasiswa::whereRaw('UPPER(nama_lengkap) = ?', [mb_strtoupper($candidateName)])
                        ->limit(2)
                        ->pluck('id');
                    if ($mhsMatches->count() === 1) {
                        if (!$dryRun) {
                            $this->updateAndAudit($tagihan, ['mahasiswa_id' => $mhsMatches->first()]);
                        }
                        $stats['matched_by_unique_name']++;
                        $matched = true;
                        break;
                    }

                    $calonMatches = PendaftaranCalonMhs::whereRaw('UPPER(nama_lengkap) = ?', [mb_strtoupper($candidateName)])
                        ->limit(2)
                        ->pluck('id');
                    if ($calonMatches->count() === 1) {
                        if (!$dryRun) {
                            $this->updateAndAudit($tagihan, ['calon_mahasiswa_id' => $calonMatches->first()]);
                        }
                        $stats['matched_by_calon']++;
                        $matched = true;
                        break;
                    }
                }
            }

            if (!$matched) {
                $stats['unmatched']++;
            }
        }

        $this->table(
            ['Metrik', 'Jumlah'],
            [
                ['Tagihan Diperiksa', $stats['scanned']],
                ['Cocok via NIM', $stats['matched_by_nim']],
                ['Cocok via Calon Mahasiswa (SPMB)', $stats['matched_by_calon']],
                ['Cocok via Nama Unik', $stats['matched_by_unique_name']],
                ['Belum Cocok (Perlu Review Manual)', $stats['unmatched']],
            ]
        );

        return self::SUCCESS;
    }
}
