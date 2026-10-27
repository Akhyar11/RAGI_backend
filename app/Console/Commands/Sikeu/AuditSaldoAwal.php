<?php

namespace App\Console\Commands\Sikeu;

use App\Models\Sikeu\AkunKeuangan;
use App\Models\Sikeu\DetailJurnalUmum;
use App\Models\Sikeu\JurnalUmum;
use App\Models\Sikeu\SaldoAwal;
use App\Models\Sikeu\UnitKas;
use App\Services\Sikeu\SaldoAwalService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/**
 * Audit pasca cut-over: memastikan saldo awal tidak bertabrakan dengan jurnal
 * legacy, buku seimbang, dan saldo kas unit konsisten dengan buku besar.
 */
class AuditSaldoAwal extends Command
{
    protected $signature = 'sikeu:saldo-awal:audit {--json : Tampilkan hasil dalam format JSON}';

    protected $description = 'Audit saldo awal, cut-off jurnal legacy, keseimbangan buku, dan rekonsiliasi kas unit.';

    public function handle(SaldoAwalService $service): int
    {
        $saldo = SaldoAwal::with('jurnal')
            ->where('status', 'posted')
            ->latest('id')
            ->first();

        $tanggal = $saldo?->tanggal?->toDateString();
        $cutOff = $tanggal ? $service->cutOffStatus($tanggal, $saldo?->jurnal_id) : null;

        $overlap = collect();
        if ($tanggal) {
            $kolom = ['id', 'nomor_jurnal', 'tanggal_jurnal', 'jenis_sumber', 'total_debet', 'total_kredit'];
            if (Schema::hasColumn('sikeu_jurnal_umum', 'legacy_id')) {
                $kolom[] = 'legacy_id';
            }
            if (Schema::hasColumn('sikeu_jurnal_umum', 'legacy_source')) {
                $kolom[] = 'legacy_source';
            }

            $overlap = JurnalUmum::where('status_posting', 'posted')
                ->whereDate('tanggal_jurnal', '<=', $tanggal)
                ->when($saldo?->jurnal_id, fn ($q) => $q->where('id', '!=', $saldo->jurnal_id))
                ->orderBy('tanggal_jurnal')
                ->orderBy('id')
                ->limit(20)
                ->get($kolom);
        }

        $tanpaDetail = JurnalUmum::where('status_posting', 'posted')
            ->whereDoesntHave('details')
            ->count();

        $tidakSeimbang = DetailJurnalUmum::query()
            ->join('sikeu_jurnal_umum as j', 'j.id', '=', 'sikeu_detail_jurnal_umum.jurnal_id')
            ->where('j.status_posting', 'posted')
            ->selectRaw('sikeu_detail_jurnal_umum.jurnal_id, SUM(sikeu_detail_jurnal_umum.debet) as total_debet, SUM(sikeu_detail_jurnal_umum.kredit) as total_kredit')
            ->groupBy('sikeu_detail_jurnal_umum.jurnal_id')
            ->havingRaw('ABS(SUM(sikeu_detail_jurnal_umum.debet) - SUM(sikeu_detail_jurnal_umum.kredit)) > 0.005')
            ->get();

        $trial = DetailJurnalUmum::query()
            ->join('sikeu_jurnal_umum as j', 'j.id', '=', 'sikeu_detail_jurnal_umum.jurnal_id')
            ->where('j.status_posting', 'posted')
            ->selectRaw('COALESCE(SUM(sikeu_detail_jurnal_umum.debet), 0) as total_debet, COALESCE(SUM(sikeu_detail_jurnal_umum.kredit), 0) as total_kredit')
            ->first();

        $totalDebet = (float) ($trial->total_debet ?? 0);
        $totalKredit = (float) ($trial->total_kredit ?? 0);
        $selisihBuku = round($totalDebet - $totalKredit, 2);

        $rekonsiliasiKas = $this->rekonsiliasiKasUnit();

        $adaMasalah = $overlap->isNotEmpty()
            || $tanpaDetail > 0
            || $tidakSeimbang->isNotEmpty()
            || abs($selisihBuku) > 0.005
            || collect($rekonsiliasiKas)->contains(fn (array $row) => $row['selisih'] !== 0.0);

        $hasil = [
            'saldo_awal' => $saldo ? [
                'id' => $saldo->id,
                'tanggal' => $tanggal,
                'status' => $saldo->status,
                'total_debet' => (float) $saldo->total_debet,
                'total_kredit' => (float) $saldo->total_kredit,
                'jurnal_id' => $saldo->jurnal_id,
                'nomor_jurnal' => $saldo->jurnal?->nomor_jurnal,
            ] : null,
            'cut_off' => $cutOff,
            'jurnal_sebelum_cut_off' => $overlap->map(fn (JurnalUmum $j) => [
                'id' => $j->id,
                'nomor_jurnal' => $j->nomor_jurnal,
                'tanggal_jurnal' => $j->tanggal_jurnal?->toDateString(),
                'jenis_sumber' => $j->jenis_sumber,
                'total_debet' => (float) $j->total_debet,
                'total_kredit' => (float) $j->total_kredit,
                'legacy_id' => $j->getAttribute('legacy_id'),
                'legacy_source' => $j->getAttribute('legacy_source'),
            ])->all(),
            'jurnal_tanpa_detail' => $tanpaDetail,
            'jurnal_tidak_seimbang' => $tidakSeimbang->map(fn ($row) => [
                'jurnal_id' => (int) $row->jurnal_id,
                'total_debet' => (float) $row->total_debet,
                'total_kredit' => (float) $row->total_kredit,
                'selisih' => round((float) $row->total_debet - (float) $row->total_kredit, 2),
            ])->all(),
            'neraca_saldo' => [
                'total_debet' => $totalDebet,
                'total_kredit' => $totalKredit,
                'selisih' => $selisihBuku,
            ],
            'rekonsiliasi_kas_unit' => $rekonsiliasiKas,
        ];

        if ($this->option('json')) {
            $this->line((string) json_encode($hasil, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } else {
            $this->renderRingkasan($hasil);
        }

        return $adaMasalah ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Bandingkan saldo buku besar akun kas/bank dengan saldo_saat_ini unit kas
     * yang dipetakan ke akun tersebut.
     *
     * @return array<int,array{akun_id:int,kode_akun:string,nama_akun:string,gl:float,unit_kas:float,selisih:float}>
     */
    private function rekonsiliasiKasUnit(): array
    {
        $unitKas = UnitKas::whereNotNull('akun_keuangan_id')->get();
        $hasil = [];

        foreach ($unitKas->groupBy('akun_keuangan_id') as $akunId => $rows) {
            $akun = AkunKeuangan::find($akunId);
            if (! $akun) {
                continue;
            }

            $buku = DetailJurnalUmum::query()
                ->join('sikeu_jurnal_umum as j', 'j.id', '=', 'sikeu_detail_jurnal_umum.jurnal_id')
                ->where('j.status_posting', 'posted')
                ->where('sikeu_detail_jurnal_umum.akun_id', $akunId)
                ->selectRaw('COALESCE(SUM(sikeu_detail_jurnal_umum.debet), 0) as total_debet, COALESCE(SUM(sikeu_detail_jurnal_umum.kredit), 0) as total_kredit')
                ->first();

            $gl = round((float) ($buku->total_debet ?? 0) - (float) ($buku->total_kredit ?? 0), 2);
            $kas = round((float) $rows->sum('saldo_saat_ini'), 2);

            if (abs($gl - $kas) <= 0.005) {
                continue;
            }

            $hasil[] = [
                'akun_id' => (int) $akunId,
                'kode_akun' => $akun->kode_akun,
                'nama_akun' => $akun->nama_akun,
                'gl' => $gl,
                'unit_kas' => $kas,
                'selisih' => round($gl - $kas, 2),
            ];
        }

        return $hasil;
    }

    private function renderRingkasan(array $hasil): void
    {
        $saldo = $hasil['saldo_awal'];

        if (! $saldo) {
            $this->warn('Belum ada saldo awal berstatus posted. Audit hanya memeriksa buku dan kas unit.');
        } else {
            $this->info("Saldo awal #{$saldo['id']} tanggal {$saldo['tanggal']} — jurnal {$saldo['nomor_jurnal']}");
            $this->line('Total debet/kredit: ' . $this->rupiah($saldo['total_debet']) . ' / ' . $this->rupiah($saldo['total_kredit']));
        }

        if ($hasil['cut_off']) {
            $this->newLine();
            $this->line('Cut-off: ' . $hasil['cut_off']['pesan']);
        }

        if ($hasil['jurnal_sebelum_cut_off'] !== []) {
            $this->newLine();
            $this->warn('Jurnal pada/sebelum cut-off (berpotensi dihitung dua kali):');
            $this->table(
                ['ID', 'Nomor Jurnal', 'Tanggal', 'Jenis', 'Debet', 'Kredit', 'Legacy'],
                array_map(fn (array $row) => [
                    $row['id'],
                    $row['nomor_jurnal'],
                    $row['tanggal_jurnal'],
                    $row['jenis_sumber'],
                    $this->rupiah($row['total_debet']),
                    $this->rupiah($row['total_kredit']),
                    $row['legacy_source'] ?: '-',
                ], $hasil['jurnal_sebelum_cut_off'])
            );
        }

        $this->newLine();
        $this->line('Jurnal posted tanpa detail: ' . $hasil['jurnal_tanpa_detail']);
        $this->line('Jurnal posted tidak seimbang: ' . count($hasil['jurnal_tidak_seimbang']));
        $this->line(
            'Neraca saldo: ' . $this->rupiah($hasil['neraca_saldo']['total_debet'])
            . ' / ' . $this->rupiah($hasil['neraca_saldo']['total_kredit'])
            . ' (selisih ' . $this->rupiah($hasil['neraca_saldo']['selisih']) . ')'
        );

        if ($hasil['rekonsiliasi_kas_unit'] === []) {
            $this->line('Rekonsiliasi kas unit: konsisten dengan buku besar.');
        } else {
            $this->newLine();
            $this->warn('Selisih saldo kas unit vs buku besar:');
            $this->table(
                ['Akun', 'Nama Akun', 'Buku Besar', 'Unit Kas', 'Selisih'],
                array_map(fn (array $row) => [
                    $row['kode_akun'],
                    $row['nama_akun'],
                    $this->rupiah($row['gl']),
                    $this->rupiah($row['unit_kas']),
                    $this->rupiah($row['selisih']),
                ], $hasil['rekonsiliasi_kas_unit'])
            );
        }

        $this->newLine();
        if ($hasil['jurnal_sebelum_cut_off'] !== [] || $hasil['jurnal_tanpa_detail'] > 0
            || $hasil['jurnal_tidak_seimbang'] !== [] || abs($hasil['neraca_saldo']['selisih']) > 0.005
            || $hasil['rekonsiliasi_kas_unit'] !== []) {
            $this->error('Audit menemukan masalah. Perbaiki temuan di atas sebelum menutup periode.');
        } else {
            $this->info('Audit bersih: saldo awal, buku, dan kas unit konsisten.');
        }
    }

    private function rupiah(float $nilai): string
    {
        return 'Rp ' . number_format($nilai, 0, ',', '.');
    }
}
