<?php

namespace App\Services\Sikeu;

use App\Models\Sikeu\AkunKeuangan;
use App\Models\Sikeu\SikeuLegacyAkunMapping;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class SikeuMigratorService
{
    /**
     * Mengambil ringkasan statistik status data migrasi di database RAG.
     */
    public function getStatus(): array
    {
        $moduleKey = (string) config('sikeu.migrator.module_key', 'sikeu');

        // 1. Rekap Jurnal Umum berdasarkan legacy_source
        $jurnalRows = DB::table('sikeu_jurnal_umum')
            ->select(
                DB::raw("COALESCE(legacy_source, 'INPUT_SISTEM_RAG') as source"),
                DB::raw('COUNT(*) as total_count'),
                DB::raw('SUM(total_debet) as total_nominal'),
                DB::raw('MIN(tanggal_jurnal) as min_date'),
                DB::raw('MAX(tanggal_jurnal) as max_date')
            )
            ->groupBy('legacy_source')
            ->get();

        $jurnalStats = [];
        $totalJurnal = 0;
        foreach ($jurnalRows as $r) {
            $totalJurnal += (int) $r->total_count;
            $jurnalStats[] = [
                'source'   => $r->source,
                'count'    => (int) $r->total_count,
                'nominal'  => (float) ($r->total_nominal ?? 0),
                'min_date' => $r->min_date,
                'max_date' => $r->max_date,
            ];
        }

        // 2. Rekap Pembayaran berdasarkan legacy_source
        $pembayaranRows = DB::table('sikeu_pembayaran')
            ->select(
                DB::raw("COALESCE(legacy_source, 'INPUT_SISTEM_RAG') as source"),
                DB::raw('COUNT(*) as total_count'),
                DB::raw('SUM(jumlah_bayar) as total_nominal'),
                DB::raw('MIN(waktu_bayar) as min_date'),
                DB::raw('MAX(waktu_bayar) as max_date')
            )
            ->groupBy('legacy_source')
            ->get();

        $pembayaranStats = [];
        $totalPembayaran = 0;
        foreach ($pembayaranRows as $r) {
            $totalPembayaran += (int) $r->total_count;
            $pembayaranStats[] = [
                'source'   => $r->source,
                'count'    => (int) $r->total_count,
                'nominal'  => (float) ($r->total_nominal ?? 0),
                'min_date' => $r->min_date,
                'max_date' => $r->max_date,
            ];
        }

        // 3. Rekap Mapping Akun (sikeu_mig_akun_mapping)
        $totalMappings = DB::table('sikeu_mig_akun_mapping')->count();
        $mappedCount = DB::table('sikeu_mig_akun_mapping')->whereNotNull('akun_id_baru')->count();
        $unmappedCount = DB::table('sikeu_mig_akun_mapping')->whereNull('akun_id_baru')->count();

        // 4. Rekap Unresolved (sikeu_mig_unresolved)
        $unresolvedRows = DB::table('sikeu_mig_unresolved')
            ->select('source_table', 'failure_reason', DB::raw('COUNT(*) as total'))
            ->groupBy('source_table', 'failure_reason')
            ->get();

        // 5. Riwayat Sinkronisasi Terakhir (sikeu_mig_sync_state)
        $syncStates = DB::table('sikeu_mig_sync_state')
            ->where('module_id', $moduleKey)
            ->get();

        // 6. Status Koneksi Database Sumber (Cache non-blocking)
        $configuredConnections = config('sikeu.migrator.source_connections', []);
        $connectionsStatus = Cache::remember('sikeu_migrator_conn_status', 300, function () use ($configuredConnections) {
            $status = [];
            foreach ($configuredConnections as $sourceKey => $connectionName) {
                $status[$connectionName] = $this->testConnection($connectionName);
            }
            return $status;
        });

        return [
            'summary' => [
                'total_jurnal'      => $totalJurnal,
                'total_pembayaran'  => $totalPembayaran,
                'total_mappings'    => $totalMappings,
                'mapped_accounts'   => $mappedCount,
                'unmapped_accounts' => $unmappedCount,
                'total_unresolved'  => $unresolvedRows->sum('total'),
            ],
            'jurnal_stats'     => $jurnalStats,
            'pembayaran_stats' => $pembayaranStats,
            'unresolved_stats' => $unresolvedRows,
            'sync_states'      => $syncStates,
            'connections'      => $connectionsStatus,
            'config'           => [
                'mode'             => config('sikeu.migrator.mode', 'direct_db'),
                'auth_scheme'      => config('sikeu.migrator.auth_scheme', 'bearer'),
                'api_url'          => config('sikeu.migrator.api_url', ''),
                'token_configured' => !empty(config('sikeu.migrator.api_token')),
                'batch_size'       => (int) config('sikeu.migrator.batch_size', 500),
            ],
        ];
    }

    /**
     * Menampilkan daftar mapping akun dengan paginasi, filter, dan search.
     */
    public function getMappings(Request $request): array
    {
        $perPage = min(100, $request->integer('per_page', 15));
        $query = SikeuLegacyAkunMapping::with('akunBaru');

        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('kd_perkiraan_lama', 'like', "%{$search}%")
                  ->orWhere('nama_akun_lama', 'like', "%{$search}%")
                  ->orWhere('kode_akun_baru', 'like', "%{$search}%")
                  ->orWhereHas('akunBaru', function ($sub) use ($search) {
                      $sub->where('nama_akun', 'like', "%{$search}%");
                  });
            });
        }

        if ($request->filled('status')) {
            if ($request->status === 'mapped') {
                $query->whereNotNull('akun_id_baru');
            } elseif ($request->status === 'unmapped') {
                $query->whereNull('akun_id_baru');
            }
        }

        $allowedSorts = ['kd_perkiraan_lama', 'nama_akun_lama', 'kode_akun_baru', 'created_at', 'updated_at'];
        $sortBy = in_array($request->sort_by, $allowedSorts, true) ? $request->sort_by : 'created_at';
        $sortOrder = $request->sort_order === 'asc' ? 'asc' : 'desc';

        $query->orderBy($sortBy, $sortOrder);
        $paginator = $query->paginate($perPage);

        return [
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
                'last_page'    => $paginator->lastPage(),
                'from'         => $paginator->firstItem(),
                'to'           => $paginator->lastItem(),
            ],
            'filters' => [
                'search'     => $request->search,
                'status'     => $request->status,
                'sort_by'    => $sortBy,
                'sort_order' => $sortOrder,
            ],
        ];
    }

    /**
     * Memperbarui target COA untuk sebuah kode perkiraan legacy.
     */
    public function updateMapping(int $id, array $data): array
    {
        $mapping = SikeuLegacyAkunMapping::findOrFail($id);
        $oldValues = $mapping->getOriginal();

        $targetAkun = AkunKeuangan::findOrFail($data['akun_id_baru']);

        $mapping->fill([
            'akun_id_baru'   => $targetAkun->id,
            'kode_akun_baru' => $targetAkun->kode_akun,
            'nama_akun_lama' => $data['nama_akun_lama'] ?? $mapping->nama_akun_lama,
        ]);

        $mapping->save();
        $newValues = $mapping->getChanges();

        return [
            'model'      => $mapping->load('akunBaru'),
            'old_values' => $oldValues,
            'new_values' => $newValues,
        ];
    }

    /**
     * Reset data migrasi pada tabel jurnal dan/atau pembayaran (hanya yang memiliki legacy_source).
     */
    public function resetData(string $type = 'jurnal', string $source = 'all'): array
    {
        $allowedTypes = config('sikeu.migrator.types', ['all', 'jurnal', 'pembayaran']);
        $allowedSources = config('sikeu.migrator.sources', ['all', 'indonusa', 'sikeudb']);

        if (!in_array($type, $allowedTypes, true)) {
            throw new \InvalidArgumentException("Tipe data migrasi '{$type}' tidak valid.");
        }
        if (!in_array($source, $allowedSources, true)) {
            throw new \InvalidArgumentException("Sumber migrasi '{$source}' tidak valid.");
        }

        $moduleKey = (string) config('sikeu.migrator.module_key', 'SIKEU');
        $legacySourceMap = config('sikeu.migrator.legacy_sources', []);
        $unresolvedTables = config('sikeu.migrator.unresolved_source_tables', []);

        $legacySources = match ($source) {
            'all'   => array_values($legacySourceMap),
            default => isset($legacySourceMap[$source]) ? [$legacySourceMap[$source]] : throw new \InvalidArgumentException("Sumber legacy '{$source}' tidak terdaftar."),
        };

        $result = [
            'jurnal_dihapus'         => 0,
            'detail_jurnal_dihapus'  => 0,
            'pembayaran_dihapus'     => 0,
            'unresolved_dibersihkan' => 0,
        ];
        $deletedJurnalIds = [];
        $deletedPembayaranIds = [];

        DB::transaction(function () use ($type, $legacySources, $moduleKey, $unresolvedTables, &$result, &$deletedJurnalIds, &$deletedPembayaranIds) {
            if ($type === 'all' || $type === 'jurnal') {
                $jurnalIds = DB::table('sikeu_jurnal_umum')
                    ->whereIn('legacy_source', $legacySources)
                    ->pluck('id')
                    ->toArray();

                $deletedJurnalIds = $jurnalIds;

                if (!empty($jurnalIds)) {
                    $result['detail_jurnal_dihapus'] = DB::table('sikeu_detail_jurnal_umum')
                        ->whereIn('jurnal_id', $jurnalIds)
                        ->delete();

                    $result['jurnal_dihapus'] = DB::table('sikeu_jurnal_umum')
                        ->whereIn('id', $jurnalIds)
                        ->delete();
                }

                $jurnalUnresolved = $unresolvedTables['jurnal'] ?? ['trkas', 'transaksi_kas'];
                $result['unresolved_dibersihkan'] += DB::table('sikeu_mig_unresolved')
                    ->whereIn('source_table', $jurnalUnresolved)
                    ->delete();

                DB::table('sikeu_mig_sync_state')
                    ->where('module_id', $moduleKey)
                    ->where('entity_type', 'jurnal')
                    ->delete();
            }

            if ($type === 'all' || $type === 'pembayaran') {
                $pembayaranIds = DB::table('sikeu_pembayaran')
                    ->whereIn('legacy_source', $legacySources)
                    ->pluck('id')
                    ->toArray();

                $deletedPembayaranIds = $pembayaranIds;

                if (!empty($pembayaranIds)) {
                    $result['pembayaran_dihapus'] = DB::table('sikeu_pembayaran')
                        ->whereIn('id', $pembayaranIds)
                        ->delete();
                }

                $pembayaranUnresolved = $unresolvedTables['pembayaran'] ?? ['trbyr', 'transaksi_bayar', 'rkpbyr'];
                $result['unresolved_dibersihkan'] += DB::table('sikeu_mig_unresolved')
                    ->whereIn('source_table', $pembayaranUnresolved)
                    ->delete();

                DB::table('sikeu_mig_sync_state')
                    ->where('module_id', $moduleKey)
                    ->where('entity_type', 'pembayaran')
                    ->delete();
            }
        });

        return [
            'result'   => $result,
            'snapshot' => [
                'deleted_jurnal_ids'     => $deletedJurnalIds,
                'deleted_pembayaran_ids' => $deletedPembayaranIds,
            ],
        ];
    }

    /**
     * Memproses sekumpulan baris transaksi keuangan dari API atau direct query
     * menjadi Jurnal Akuntansi RAG secara otomatis dan seimbang (Double-Entry).
     * Seluruh resolusi akun murni menggunakan tabel pemetaan dinamis sikeu_mig_akun_mapping.
     */
    public function processJurnalBatch(array $rows, string $sourceSystem): array
    {
        $accountsCache = DB::table('sikeu_akun_keuangan')->get()->keyBy('id');
        $mappingCache = DB::table('sikeu_mig_akun_mapping')
            ->whereNotNull('akun_id_baru')
            ->pluck('akun_id_baru', 'kd_perkiraan_lama')
            ->toArray();

        // Resolusi akun kas aktif secara dinamis dari unit kas aktif atau akun kas/bank resmi
        $defaultKasId = DB::table('sikeu_unit_kas')
            ->where('is_aktif', true)
            ->value('akun_keuangan_id');

        if (!$defaultKasId) {
            $defaultKasId = DB::table('sikeu_akun_keuangan')
                ->where('is_kas_bank', true)
                ->where('is_active', true)
                ->value('id');
        }

        $legacySourceMap = config('sikeu.migrator.legacy_sources', []);
        $legacySource = $legacySourceMap[$sourceSystem] ?? 'LEGACY_MIGRATOR';

        $stats = [
            'fetched'      => count($rows),
            'created'      => 0,
            'updated'      => 0,
            'skipped'      => 0,
            'failed'       => 0,
            'skip_reasons' => [
                'duplicate'        => 0,
                'unmapped_account' => 0,
                'missing_kas_akun' => 0,
                'invalid_nominal'  => 0,
            ],
        ];

        foreach ($rows as $row) {
            $nominal = (float) ($row['jumlah'] ?? $row['nominal'] ?? $row['total'] ?? 0);
            $noTran = trim((string) ($row['no_tran'] ?? $row['kode'] ?? $row['id'] ?? ''));

            if ($noTran === '' || $nominal <= 0) {
                $stats['skipped']++;
                $stats['skip_reasons']['invalid_nominal']++;
                continue;
            }

            $currentLegacySource = $row['legacy_source'] ?? $legacySource;
            $legacyId = (string) ($row['legacy_id'] ?? $noTran);

            // Cek pencegahan duplikasi
            $exists = DB::table('sikeu_jurnal_umum')
                ->where('legacy_id', $legacyId)
                ->where('legacy_source', $currentLegacySource)
                ->exists();

            if ($exists) {
                $stats['skipped']++;
                $stats['skip_reasons']['duplicate']++;
                continue;
            }

            // Validasi akun kas aktif
            if (!$defaultKasId) {
                DB::table('sikeu_mig_unresolved')->insert([
                    'source_table'   => ($sourceSystem === 'indonusa') ? 'trkas' : 'transaksi_kas',
                    'legacy_key'     => $noTran,
                    'raw_data'       => json_encode($row),
                    'failure_reason' => "Akun unit kas aktif belum dikonfigurasi di sistem",
                    'status'         => 'pending_review',
                    'created_at'     => now(),
                    'updated_at'     => now(),
                ]);
                $stats['skipped']++;
                $stats['skip_reasons']['missing_kas_akun']++;
                continue;
            }

            // Identifikasi Akun Lawan via tabel pemetaan dinamis
            $kdPerkiraan = trim((string) ($row['kd_perkiraan'] ?? $row['akun_id'] ?? ''));
            $targetAkunId = $mappingCache[$kdPerkiraan] ?? null;

            if (!$targetAkunId || !isset($accountsCache[$targetAkunId])) {
                DB::table('sikeu_mig_unresolved')->insert([
                    'source_table'   => ($sourceSystem === 'indonusa') ? 'trkas' : 'transaksi_kas',
                    'legacy_key'     => $noTran,
                    'raw_data'       => json_encode($row),
                    'failure_reason' => "Kode perkiraan legacy '{$kdPerkiraan}' belum terpetakan ke akun COA baru",
                    'status'         => 'pending_review',
                    'created_at'     => now(),
                    'updated_at'     => now(),
                ]);
                $stats['skipped']++;
                $stats['skip_reasons']['unmapped_account']++;
                continue;
            }

            $targetAkun = $accountsCache[$targetAkunId];
            $jenis = (int) ($row['jenis'] ?? 0);

            // Tentukan posisi debet dan kredit berdasarkan arah kas / saldo normal akun lawan
            if ($jenis === 1) {
                // Penerimaan kas (Cash-in)
                $debetId = $defaultKasId;
                $kreditId = $targetAkunId;
            } elseif ($jenis === 2) {
                // Pengeluaran kas (Cash-out)
                $debetId = $targetAkunId;
                $kreditId = $defaultKasId;
            } else {
                // Fallback berdasarkan saldo normal akun lawan
                if ($targetAkun->saldo_normal === 'kredit') {
                    $debetId = $defaultKasId;
                    $kreditId = $targetAkunId;
                } else {
                    $debetId = $targetAkunId;
                    $kreditId = $defaultKasId;
                }
            }

            $keterangan = trim((string) ($row['keterangan'] ?? $row['ket'] ?? $row['nama'] ?? ''));

            try {
                DB::transaction(function () use ($noTran, $row, $nominal, $legacyId, $currentLegacySource, $keterangan, $debetId, $kreditId) {
                    $tgl = $row['tgl_tran'] ?? $row['tanggal'] ?? now()->toDateString();
                    $nomorJurnal = 'JRN-MIG-' . substr(md5($currentLegacySource . $legacyId), 0, 10);

                    $jurnalId = DB::table('sikeu_jurnal_umum')->insertGetId([
                        'nomor_jurnal'   => $nomorJurnal,
                        'tanggal_jurnal' => substr($tgl, 0, 10),
                        'jenis_sumber'   => 'penyesuaian',
                        'keterangan'     => $keterangan ?: 'Migrasi Transaksi Kas',
                        'status_posting' => 'posted',
                        'total_debet'    => $nominal,
                        'total_kredit'   => $nominal,
                        'legacy_id'      => $legacyId,
                        'legacy_source'  => $currentLegacySource,
                        'created_at'     => now(),
                        'updated_at'     => now(),
                    ]);

                    // Detail Debet
                    DB::table('sikeu_detail_jurnal_umum')->insert([
                        'jurnal_id'  => $jurnalId,
                        'akun_id'    => $debetId,
                        'posisi'     => 'debet',
                        'nominal'    => $nominal,
                        'keterangan' => $keterangan,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    // Detail Kredit
                    DB::table('sikeu_detail_jurnal_umum')->insert([
                        'jurnal_id'  => $jurnalId,
                        'akun_id'    => $kreditId,
                        'posisi'     => 'kredit',
                        'nominal'    => $nominal,
                        'keterangan' => $keterangan,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                });

                $stats['created']++;
            } catch (Throwable $e) {
                Log::error("Gagal simpan jurnal migrasi #{$noTran}: " . $e->getMessage());
                $stats['failed']++;
            }
        }

        return $stats;
    }

    /**
     * Memproses sekumpulan baris transaksi pembayaran mahasiswa dari API
     * menjadi Tagihan & Pembayaran RAG secara idempoten tanpa membuat baris yatim (orphan).
     */
    public function processPembayaranBatch(array $rows, string $sourceSystem): array
    {
        $legacySourceMap = config('sikeu.migrator.legacy_sources', []);
        $legacySource = $legacySourceMap[$sourceSystem] ?? 'LEGACY_MIGRATOR';

        $stats = [
            'fetched'      => count($rows),
            'created'      => 0,
            'updated'      => 0,
            'skipped'      => 0,
            'failed'       => 0,
            'skip_reasons' => [
                'duplicate'         => 0,
                'missing_mahasiswa' => 0,
                'invalid_nominal'   => 0,
            ],
        ];

        foreach ($rows as $row) {
            $nominal = (float) ($row['jumlah'] ?? $row['nominal'] ?? $row['total'] ?? 0);
            $legacyId = trim((string) ($row['legacy_id'] ?? $row['id'] ?? $row['no_tran'] ?? ''));
            $currentLegacySource = $row['legacy_source'] ?? $legacySource;
            $nim = trim((string) ($row['nim'] ?? $row['no_pend'] ?? ''));

            if ($legacyId === '' || $nominal <= 0) {
                $stats['skipped']++;
                $stats['skip_reasons']['invalid_nominal']++;
                continue;
            }

            // Cek pencegahan duplikasi di sikeu_pembayaran
            $exists = DB::table('sikeu_pembayaran')
                ->where('legacy_id', $legacyId)
                ->where('legacy_source', $currentLegacySource)
                ->exists();

            if ($exists) {
                $stats['skipped']++;
                $stats['skip_reasons']['duplicate']++;
                continue;
            }

            // Cari ID Mahasiswa (hanya baca melalui Model Siakad Mahasiswa resmi)
            $mhsId = null;
            if ($nim !== '') {
                $mhsId = \App\Models\Siakad\Mahasiswa::where('nim', $nim)->value('id');
            }

            // Guard ketat: Jangan membuat tagihan/pembayaran jika mahasiswa tidak ditemukan (Domain Isolation)
            if (!$mhsId) {
                DB::table('sikeu_mig_unresolved')->insert([
                    'source_table'   => ($sourceSystem === 'indonusa') ? 'trbyr' : 'transaksi_bayar',
                    'legacy_key'     => $legacyId,
                    'raw_data'       => json_encode($row),
                    'failure_reason' => "NIM '{$nim}' tidak ditemukan pada siakad_mahasiswa",
                    'status'         => 'pending_review',
                    'created_at'     => now(),
                    'updated_at'     => now(),
                ]);

                $stats['skipped']++;
                $stats['skip_reasons']['missing_mahasiswa']++;
                continue;
            }

            try {
                DB::transaction(function () use ($row, $nominal, $legacyId, $currentLegacySource, $mhsId, $nim) {
                    $tgl = $row['tgl_tran'] ?? $row['tanggal'] ?? now()->toDateString();
                    $tglStr = substr($tgl, 0, 10);
                    $nomorTagihan = 'TRX-LEG-' . strtoupper(substr(md5($legacyId . $currentLegacySource), 0, 12));
                    $kodeTransaksi = 'BYR-MIG-' . substr(md5($legacyId . $currentLegacySource), 0, 10);
                    $channel = strtoupper(str_replace(' ', '_', $row['metode_bayar'] ?? 'TRANSFER_BANK'));
                    $catatan = $row['keterangan'] ?? ('NIM: ' . $nim);
                    $now = now();

                    // Insert Tagihan
                    $tagihanId = DB::table('sikeu_tagihan_mahasiswa')->insertGetId([
                        'mahasiswa_id'     => $mhsId,
                        'h2h_custid'       => $nim ?: null,
                        'nomor_tagihan'    => $nomorTagihan,
                        'total_tagihan'    => $nominal,
                        'total_potongan'   => 0,
                        'total_denda'      => 0,
                        'total_bayar'      => $nominal,
                        'status'           => 'lunas',
                        'requires_approval'=> 0,
                        'status_approval'  => 'approved',
                        'source_system'    => 'LEGACY_MIGRATOR',
                        'jatuh_tempo'      => $tglStr,
                        'legacy_id'        => $legacyId,
                        'legacy_source'    => $currentLegacySource,
                        'created_at'       => $tgl,
                        'updated_at'       => $now,
                    ]);

                    // Insert Pembayaran
                    DB::table('sikeu_pembayaran')->insert([
                        'tagihan_id'       => $tagihanId,
                        'kode_transaksi'   => $kodeTransaksi,
                        'jumlah_bayar'     => $nominal,
                        'waktu_bayar'      => $tgl,
                        'channel_bayar'    => $channel,
                        'status'           => 'success',
                        'catatan'          => $catatan,
                        'legacy_id'        => $legacyId,
                        'legacy_source'    => $currentLegacySource,
                        'created_at'       => $now,
                        'updated_at'       => $now,
                    ]);
                });

                $stats['created']++;
            } catch (Throwable $e) {
                Log::error("Gagal simpan pembayaran migrasi #{$legacyId}: " . $e->getMessage());
                $stats['failed']++;
            }
        }

        return $stats;
    }

    /**
     * Menjalankan proses migrasi data secara menyeluruh (dipanggil oleh Background Queue Job).
     */
    public function runMigration(array $options): array
    {
        $mode = $options['mode'] ?? config('sikeu.migrator.mode', 'direct_db');
        $source = $options['source'] ?? 'all';
        $type = $options['type'] ?? 'all';
        $limit = (int) ($options['limit'] ?? 0);
        $batchSize = (int) ($options['batch_size'] ?? 500);

        Log::info("Memulai MigrateSikeuJob [mode: {$mode}, source: {$source}, type: {$type}]");

        if ($mode === 'api') {
            return $this->runFromApi($options);
        }

        // Mode Direct DB
        return $this->runFromDirectDb($source, $type, $limit, $batchSize);
    }

    /**
     * Menarik data dari endpoint API eksternal dengan paginasi berbatas aman.
     */
    protected function runFromApi(array $options): array
    {
        $apiUrl = !empty($options['api_url']) ? $options['api_url'] : config('sikeu.migrator.api_url');
        $apiToken = !empty($options['api_token']) ? $options['api_token'] : config('sikeu.migrator.api_token', '');
        $authScheme = $options['auth_scheme'] ?? config('sikeu.migrator.auth_scheme', 'bearer');
        $maxPages = (int) config('sikeu.migrator.max_pages', 1000);
        $batchSize = (int) ($options['batch_size'] ?? 500);

        if (empty($apiUrl)) {
            throw new \RuntimeException('URL API sumber migrasi belum dikonfigurasi.');
        }

        $client = Http::timeout(60);
        if (!empty($apiToken)) {
            if ($authScheme === 'header_key') {
                $client = $client->withHeaders(['X-API-KEY' => $apiToken]);
            } else {
                $client = $client->withToken($apiToken);
            }
        }

        $typeReq = $options['type'] ?? 'jurnal';
        $typesToProcess = match ($typeReq) {
            'all'        => ['pembayaran', 'jurnal'],
            'pembayaran' => ['pembayaran'],
            default      => ['jurnal'],
        };

        $totalFetched = 0;
        $totalCreated = 0;
        $totalUpdated = 0;
        $totalSkipped = 0;
        $totalFailed = 0;
        $aggregatedSkipReasons = [];

        foreach ($typesToProcess as $currentType) {
            $page = 1;
            $previousFingerprint = null;

            while ($page <= $maxPages) {
                $queryParams = [
                    'page'     => $page,
                    'per_page' => $batchSize,
                    'source'   => $options['source'] ?? 'all',
                    'type'     => $currentType,
                ];
                if (!empty($options['since'])) {
                    $queryParams['since'] = $options['since'];
                }
                if (!empty($options['limit'])) {
                    $queryParams['limit'] = $options['limit'];
                }

                $response = $client->get($apiUrl, $queryParams);

                if (!$response->successful()) {
                    throw new \RuntimeException("Gagal mengambil data dari API sumber ({$currentType}): HTTP {$response->status()}");
                }

                $payload = $response->json();
                $items = $payload['data'] ?? (array_is_list($payload) ? $payload : []);

                if (empty($items)) {
                    break;
                }

                // Guard: Deteksi infinite loop jika upstream mengabaikan parameter page
                $fingerprint = md5(json_encode($items));
                if ($fingerprint === $previousFingerprint) {
                    Log::warning("Paginasi API upstream mengembalikan data yang sama pada halaman {$page}. Menghentikan loop.");
                    break;
                }
                $previousFingerprint = $fingerprint;

                if ($currentType === 'pembayaran') {
                    $batchResult = $this->processPembayaranBatch($items, $options['source'] ?? 'api');
                } else {
                    $batchResult = $this->processJurnalBatch($items, $options['source'] ?? 'api');
                }

                $totalFetched += $batchResult['fetched'];
                $totalCreated += $batchResult['created'];
                $totalUpdated += $batchResult['updated'];
                $totalSkipped += $batchResult['skipped'];
                $totalFailed  += $batchResult['failed'];

                foreach ($batchResult['skip_reasons'] as $reason => $count) {
                    $aggregatedSkipReasons[$reason] = ($aggregatedSkipReasons[$reason] ?? 0) + $count;
                }

                $lastPage = $payload['meta']['last_page'] ?? null;
                if ($lastPage !== null && $page >= $lastPage) {
                    break;
                }
                if (count($items) < $batchSize) {
                    break;
                }

                $page++;
            }

            if ($page > $maxPages) {
                throw new \RuntimeException("Batas maksimum halaman sync ({$maxPages}) terlampaui.");
            }
        }

        return [
            'mode'         => 'api',
            'fetched'      => $totalFetched,
            'created'      => $totalCreated,
            'updated'      => $totalUpdated,
            'skipped'      => $totalSkipped,
            'failed'       => $totalFailed,
            'skip_reasons' => $aggregatedSkipReasons,
        ];
    }

    /**
     * Membaca langsung dari database MySQL yang terdaftar di konfigurasi.
     */
    protected function runFromDirectDb(string $source, string $type, int $limit, int $batchSize): array
    {
        $totalFetched = 0;
        $totalCreated = 0;
        $totalUpdated = 0;
        $totalSkipped = 0;
        $totalFailed = 0;
        $aggregatedSkipReasons = [];

        $conns = config('sikeu.migrator.source_connections', []);
        $sourceTablesConfig = config('sikeu.migrator.source_tables', []);
        $typesToProcess = match ($type) {
            'all'        => ['pembayaran', 'jurnal'],
            'pembayaran' => ['pembayaran'],
            default      => ['jurnal'],
        };

        $connectionErrors = [];
        $attemptedConnections = 0;

        foreach ($conns as $srcKey => $connName) {
            if ($source !== 'all' && $source !== $srcKey) {
                continue;
            }

            $attemptedConnections++;

            foreach ($typesToProcess as $currentType) {
                $tableMap = $sourceTablesConfig[$currentType] ?? [];
                $sourceTable = $tableMap[$connName] ?? null;

                if (!$sourceTable) {
                    continue;
                }

                try {
                    $query = DB::connection($connName)->table($sourceTable);
                    if ($limit > 0) {
                        $query->limit($limit);
                    }

                    $query->chunk($batchSize, function ($rows) use (&$totalFetched, &$totalCreated, &$totalUpdated, &$totalSkipped, &$totalFailed, &$aggregatedSkipReasons, $srcKey, $currentType) {
                        $arrayRows = json_decode(json_encode($rows), true);
                        if ($currentType === 'pembayaran') {
                            $res = $this->processPembayaranBatch($arrayRows, $srcKey);
                        } else {
                            $res = $this->processJurnalBatch($arrayRows, $srcKey);
                        }

                        $totalFetched += $res['fetched'];
                        $totalCreated += $res['created'];
                        $totalUpdated += $res['updated'];
                        $totalSkipped += $res['skipped'];
                        $totalFailed  += $res['failed'];

                        foreach ($res['skip_reasons'] as $reason => $count) {
                            $aggregatedSkipReasons[$reason] = ($aggregatedSkipReasons[$reason] ?? 0) + $count;
                        }
                    });
                } catch (Throwable $e) {
                    $connectionErrors[$connName] = $e->getMessage();
                    Log::warning("Koneksi direct_db {$connName} untuk tabel {$sourceTable} gagal: " . $e->getMessage());
                }
            }
        }

        if ($attemptedConnections > 0 && count($connectionErrors) >= $attemptedConnections) {
            throw new \RuntimeException('Semua koneksi database sumber migrasi tidak dapat dijangkau atau gagal dieksekusi.');
        }

        return [
            'mode'         => 'direct_db',
            'fetched'      => $totalFetched,
            'created'      => $totalCreated,
            'updated'      => $totalUpdated,
            'skipped'      => $totalSkipped,
            'failed'       => $totalFailed,
            'skip_reasons' => $aggregatedSkipReasons,
        ];
    }

    /**
     * Tes koneksi ke database tertentu tanpa mengekspos detail kredensial.
     */
    protected function testConnection(string $connectionName): array
    {
        try {
            DB::connection($connectionName)->getPdo();
            return ['status' => 'connected', 'message' => 'Koneksi berhasil'];
        } catch (Throwable $e) {
            Log::warning('Koneksi migrator database gagal', [
                'connection' => $connectionName,
                'error'      => $e->getMessage(),
            ]);
            return ['status' => 'disconnected', 'message' => 'Koneksi database sumber tidak tersedia'];
        }
    }
}
