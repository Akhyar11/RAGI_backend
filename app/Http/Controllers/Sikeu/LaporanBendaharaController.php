<?php

namespace App\Http\Controllers\Sikeu;

use App\Http\Controllers\Controller;
use App\Services\AuditLogService;
use App\Services\Sikeu\LaporanBendaharaService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Laporan Bendahara SIKEU (parity legacy menuLaporan, basis kas).
 *
 * Mengandalkan middleware grup (CheckMenuAccess); seluruh endpoint read
 * mengikuti envelope standar. Hanya export yang mencatat audit log.
 */
class LaporanBendaharaController extends Controller
{
    public function __construct(private LaporanBendaharaService $service) {}

    private function tolakTanpaAkses(Request $request): ?\Illuminate\Http\JsonResponse
    {
        $user = $request->user();
        if (!$user || (!$user->isSuperAdmin() && !$user->isAdmin() && !$user->hasPermission('sikeu.laporan.read'))) {
            return response()->json(['status' => 'error', 'message' => 'Anda tidak memiliki akses laporan bendahara.'], 403);
        }

        return null;
    }

    private function tanggalOrFail(?string $value, string $field = 'tanggal'): string
    {
        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            abort(response()->json([
                'status' => 'error',
                'message' => "Format {$field} tidak valid.",
                'errors' => [
                    $field => ["Format {$field} harus YYYY-MM-DD."],
                ],
            ], 422));
        }
    }

    /**
     * GET /api/v1/sikeu/laporan-bendahara/saldo-harian?tanggal=YYYY-MM-DD
     * Parity legacy GET /laporan/saldo/harian/:tanggal.
     */
    public function saldoHarian(Request $request)
    {
        if ($tolak = $this->tolakTanpaAkses($request)) {
            return $tolak;
        }

        $request->validate([
            'tanggal' => 'required|date',
            'unit_kas_id' => 'nullable|integer|exists:sikeu_unit_kas,id',
            'metode' => 'nullable|string|max:20',
        ]);

        $tanggal = $this->tanggalOrFail($request->query('tanggal'));
        $saldo = $this->service->saldoPeriode($tanggal, $tanggal, $request->integer('unit_kas_id') ?: null, $request->query('metode'));

        return response()->json([
            'status' => 'success',
            'message' => 'Saldo harian berhasil dimuat',
            'data' => ['tanggal' => $tanggal] + $saldo,
        ]);
    }

    /**
     * GET /api/v1/sikeu/laporan-bendahara/saldo-bulanan?tanggal=YYYY-MM
     * Parity legacy GET /laporan/saldo/bulanan/:tanggal.
     */
    public function saldoBulanan(Request $request)
    {
        if ($tolak = $this->tolakTanpaAkses($request)) {
            return $tolak;
        }

        $request->validate([
            'tanggal' => 'required|date_format:Y-m',
            'unit_kas_id' => 'nullable|integer|exists:sikeu_unit_kas,id',
            'metode' => 'nullable|string|max:20',
        ]);

        $bulan = Carbon::createFromFormat('Y-m', $request->query('tanggal'));
        $awal = $bulan->copy()->firstOfMonth()->toDateString();
        $akhir = $bulan->copy()->lastOfMonth()->toDateString();
        $saldo = $this->service->saldoPeriode($awal, $akhir, $request->integer('unit_kas_id') ?: null, $request->query('metode'));

        return response()->json([
            'status' => 'success',
            'message' => 'Saldo bulanan berhasil dimuat',
            'data' => ['bulan' => $bulan->format('Y-m'), 'awal' => $awal, 'akhir' => $akhir] + $saldo,
        ]);
    }

    /**
     * GET /api/v1/sikeu/laporan-bendahara/harian?tanggal=YYYY-MM-DD
     * Parity legacy GET /laporan/harian/:tanggal (chain: saldo bulan lalu + UMK aktif + koreksi negatif).
     */
    public function harian(Request $request)
    {
        if ($tolak = $this->tolakTanpaAkses($request)) {
            return $tolak;
        }

        $request->validate([
            'tanggal' => 'required|date|before_or_equal:today',
            'unit_kas_id' => 'nullable|integer|exists:sikeu_unit_kas,id',
            'metode' => 'nullable|string|max:20',
            'search' => 'nullable|string|max:191',
            'sort_by' => 'nullable|string|in:tanggal,jumlah',
            'sort_order' => 'nullable|string|in:asc,desc',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $tanggal = $this->tanggalOrFail($request->query('tanggal'));
        $unitKasId = $request->integer('unit_kas_id') ?: null;
        $tunai = $this->service->isTunai($request->query('metode'));

        try {
            $saldoAwal = $this->service->saldoAwal($unitKasId, $tanggal, $tunai);
        } catch (\RuntimeException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }

        $perPage = min(100, $request->integer('per_page', 15));
        $lines = $this->service->lines($tanggal, $tanggal, $unitKasId, $request->query('metode'), $request->query('search'), $perPage, (int) $request->integer('page', 1), false, $request->query('sort_by', 'tanggal'), $request->query('sort_order', 'asc'));
        $saldoHari = $this->service->saldoPeriode($tanggal, $tanggal, $unitKasId, $request->query('metode'));

        return response()->json([
            'status' => 'success',
            'message' => 'Laporan harian berhasil dimuat',
            'data' => [
                'tanggal' => $tanggal,
                'saldo_awal' => $saldoAwal['saldo_awal'],
                'saldo_akhir' => $saldoAwal['saldo_awal'] + $saldoHari['saldo_bersih'],
                'saldo_periode' => $saldoHari,
                'umk_aktif' => $tunai ? $this->service->umkAktif($unitKasId) : [],
                'transaksi' => $lines->items(),
            ],
            'meta' => [
                'current_page' => $lines->currentPage(),
                'per_page' => $lines->perPage(),
                'total' => $lines->total(),
                'last_page' => $lines->lastPage(),
                'from' => $lines->firstItem(),
                'to' => $lines->lastItem(),
            ],
        ]);
    }

    /**
     * GET /api/v1/sikeu/laporan-bendahara/bulanan?tanggal=YYYY-MM
     * Parity legacy GET /laporan/bulanan/:tanggal.
     */
    public function bulanan(Request $request)
    {
        if ($tolak = $this->tolakTanpaAkses($request)) {
            return $tolak;
        }

        $request->validate([
            'tanggal' => 'required|date_format:Y-m',
            'unit_kas_id' => 'nullable|integer|exists:sikeu_unit_kas,id',
            'metode' => 'nullable|string|max:20',
            'search' => 'nullable|string|max:191',
            'sort_by' => 'nullable|string|in:tanggal,jumlah',
            'sort_order' => 'nullable|string|in:asc,desc',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $bulan = Carbon::createFromFormat('Y-m', $request->query('tanggal'));
        if ($bulan->isFuture()) {
            return response()->json(['status' => 'error', 'message' => 'Bulan laporan tidak boleh di masa depan.'], 422);
        }
        $awal = $bulan->copy()->firstOfMonth()->toDateString();
        $akhir = $bulan->copy()->lastOfMonth()->toDateString();
        $unitKasId = $request->integer('unit_kas_id') ?: null;
        $tunai = $this->service->isTunai($request->query('metode'));

        try {
            $saldoAwal = $this->service->saldoAwal($unitKasId, $awal, $tunai);
        } catch (\RuntimeException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }

        $perPage = min(100, $request->integer('per_page', 15));
        $lines = $this->service->lines($awal, $akhir, $unitKasId, $request->query('metode'), $request->query('search'), $perPage, (int) $request->integer('page', 1), false, $request->query('sort_by', 'tanggal'), $request->query('sort_order', 'asc'));
        $saldoBulan = $this->service->saldoPeriode($awal, $akhir, $unitKasId, $request->query('metode'));

        return response()->json([
            'status' => 'success',
            'message' => 'Laporan bulanan berhasil dimuat',
            'data' => [
                'bulan' => $bulan->format('Y-m'),
                'awal' => $awal,
                'akhir' => $akhir,
                'saldo_awal' => $saldoAwal['saldo_awal'],
                'saldo_akhir' => $saldoAwal['saldo_awal'] + $saldoBulan['saldo_bersih'],
                'saldo_periode' => $saldoBulan,
                'umk_aktif' => $tunai ? $this->service->umkAktif($unitKasId) : [],
                'transaksi' => $lines->items(),
            ],
            'meta' => [
                'current_page' => $lines->currentPage(),
                'per_page' => $lines->perPage(),
                'total' => $lines->total(),
                'last_page' => $lines->lastPage(),
                'from' => $lines->firstItem(),
                'to' => $lines->lastItem(),
            ],
        ]);
    }

    /**
     * GET /api/v1/sikeu/laporan-bendahara/periodik?awal=&akhir=&code=&only_mhs=
     * Parity legacy GET /laporan/periodik/:awal/:akhir/:code.
     */
    public function periodik(Request $request)
    {
        if ($tolak = $this->tolakTanpaAkses($request)) {
            return $tolak;
        }

        $request->validate([
            'awal' => 'required|date',
            'akhir' => 'required|date|after_or_equal:awal',
            'code' => 'nullable|string|max:20',
            'only_mhs' => 'nullable|boolean',
            'search' => 'nullable|string|max:191',
            'sort_by' => 'nullable|string|in:tanggal,jumlah',
            'sort_order' => 'nullable|string|in:asc,desc',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $awal = $this->tanggalOrFail($request->query('awal'), 'awal');
        $akhir = $this->tanggalOrFail($request->query('akhir'), 'akhir');
        $code = $request->query('code');
        if ($code === 'Semua' || $code === 'semua' || $code === '0') {
            $code = null;
        }

        $perPage = min(100, $request->integer('per_page', 15));
        $lines = $this->service->periodik($awal, $akhir, $code, $request->boolean('only_mhs'), $request->query('search'), $perPage, (int) $request->integer('page', 1), $request->query('sort_by', 'tanggal'), $request->query('sort_order', 'asc'));

        return response()->json([
            'status' => 'success',
            'message' => 'Laporan periodik berhasil dimuat',
            'data' => $lines->items(),
            'meta' => [
                'current_page' => $lines->currentPage(),
                'per_page' => $lines->perPage(),
                'total' => $lines->total(),
                'last_page' => $lines->lastPage(),
                'from' => $lines->firstItem(),
                'to' => $lines->lastItem(),
            ],
        ]);
    }

    /**
     * GET /api/v1/sikeu/laporan-bendahara/perakun?awal=&akhir=&code=
     * Parity legacy GET /laporan/perakun/:awal/:akhir/:code (group by kode akun).
     */
    public function perAkun(Request $request)
    {
        if ($tolak = $this->tolakTanpaAkses($request)) {
            return $tolak;
        }

        $request->validate([
            'awal' => 'required|date',
            'akhir' => 'required|date|after_or_equal:awal',
            'code' => 'nullable|string|max:20',
        ]);

        $awal = $this->tanggalOrFail($request->query('awal'), 'awal');
        $akhir = $this->tanggalOrFail($request->query('akhir'), 'akhir');
        $code = $request->query('code');
        if ($code === 'Semua' || $code === '0') {
            $code = null;
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Laporan per akun berhasil dimuat',
            'data' => $this->service->perAkun($awal, $akhir, $code),
        ]);
    }

    /**
     * GET /api/v1/sikeu/laporan-bendahara/rekap-mahasiswa?mahasiswa_id=&cutoff=
     * Parity legacy GET /laporan/rekap/:tahun/:prodi/:no_pend/:jenis + history.
     */
    public function rekapMahasiswa(Request $request)
    {
        if ($tolak = $this->tolakTanpaAkses($request)) {
            return $tolak;
        }

        $request->validate([
            'mahasiswa_id' => 'required|integer|exists:siakad_mahasiswa,id',
            'cutoff' => 'nullable|date|before_or_equal:today',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Rekap pembayaran mahasiswa berhasil dimuat',
            'data' => $this->service->rekapMahasiswa(
                (int) $request->query('mahasiswa_id'),
                $request->query('cutoff') ? substr((string) $request->query('cutoff'), 0, 10) : null
            ),
        ]);
    }

    /**
     * POST /api/v1/sikeu/laporan-bendahara/rekap-perprodikelas
     * Parity legacy POST /laporan/rekapperprodikelas (batch per prodi/kelas).
     */
    public function rekapPerprodiKelas(Request $request)
    {
        if ($tolak = $this->tolakTanpaAkses($request)) {
            return $tolak;
        }

        $validated = $request->validate([
            'data' => 'required|array|min:1|max:500',
            'data.*.mahasiswa_id' => 'required|integer|exists:siakad_mahasiswa,id',
            'data.*.nim' => 'nullable|string|max:20',
            'data.*.nama' => 'nullable|string|max:191',
            'batas_semester' => 'nullable|integer|min:1|max:14',
            'batas_tanggal' => 'nullable|date|before_or_equal:today',
        ]);

        try {
            $hasil = $this->service->rekapPerprodiKelas(
                $validated['data'],
                $validated['batas_semester'] ?? null,
                isset($validated['batas_tanggal']) ? substr((string) $validated['batas_tanggal'], 0, 10) : null
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Rekap per prodi/kelas berhasil dimuat',
            'data' => $hasil['data'],
            'header' => $hasil['header'],
            'ringkasan' => $hasil['ringkasan'],
        ]);
    }

    /**
     * GET /api/v1/sikeu/laporan-bendahara/export-excel?jenis=harian&...
     * Unduh .xls ala PiutangMahasiswaController::exportExcel.
     */
    public function exportExcel(Request $request)
    {
        if ($tolak = $this->tolakTanpaAkses($request)) {
            return $tolak;
        }

        $request->validate([
            'jenis' => 'required|in:harian,bulanan,periodik,perakun',
            'tanggal' => 'nullable|date',
            'awal' => 'nullable|date',
            'akhir' => 'nullable|date|after_or_equal:awal',
            'unit_kas_id' => 'nullable|integer|exists:sikeu_unit_kas,id',
            'metode' => 'nullable|string|max:20',
            'code' => 'nullable|string|max:20',
        ]);

        $jenis = $request->query('jenis');
        $unitKasId = $request->integer('unit_kas_id') ?: null;
        $metode = $request->query('metode');

        if ($jenis === 'harian') {
            $tanggal = $this->tanggalOrFail($request->query('tanggal'));
            $awal = $akhir = $tanggal;
            $judul = 'LAPORAN_KAS_HARIAN_' . str_replace('-', '', $tanggal);
        } elseif ($jenis === 'bulanan') {
            $request->validate(['tanggal' => 'required|date_format:Y-m']);
            $bulan = Carbon::createFromFormat('Y-m', $request->query('tanggal'));
            $awal = $bulan->copy()->firstOfMonth()->toDateString();
            $akhir = $bulan->copy()->lastOfMonth()->toDateString();
            $judul = 'LAPORAN_KAS_BULANAN_' . str_replace('-', '', $bulan->format('Y-m'));
        } else {
            $request->validate(['awal' => 'required|date', 'akhir' => 'required|date|after_or_equal:awal']);
            $awal = $this->tanggalOrFail($request->query('awal'), 'awal');
            $akhir = $this->tanggalOrFail($request->query('akhir'), 'akhir');
            $judul = 'LAPORAN_KAS_' . strtoupper($jenis) . '_' . str_replace('-', '', $awal) . '_' . str_replace('-', '', $akhir);
        }

        if ($jenis === 'perakun') {
            $rows = $this->service->perAkun($awal, $akhir, $request->query('code'));
        } else {
            $paginator = $this->service->lines($awal, $akhir, $unitKasId, $metode, null, PHP_INT_MAX, 1);
            $rows = collect($paginator->items());
            if ($request->filled('code')) {
                $rows = $rows->filter(fn ($l) => str_starts_with((string) ($l['kode_akun'] ?? ''), trim((string) $request->query('code'))))->values();
            }
            $rows = $rows->toArray();
        }

        try {
            AuditLogService::record(
                module: 'SIKEU',
                action: 'export',
                tableName: 'sikeu_pembayaran',
                recordId: 0,
                oldValues: null,
                newValues: ['jenis' => $jenis, 'awal' => $awal, 'akhir' => $akhir],
                request: $request,
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Audit log export laporan bendahara gagal: ' . $e->getMessage());
        }

        $filename = $judul . '_' . date('Ymd_His') . '.xls';
        $headers = [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        return response()->stream(function () use ($rows, $jenis, $awal, $akhir) {
            $out = fopen('php://output', 'w');
            fwrite($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fwrite($out, '<html><head><meta http-equiv="Content-Type" content="text/html; charset=UTF-8">'
                . '<style>th{background:#0f172a;color:#fff;}th,td{border:1px solid #cbd5e1;padding:6px 8px;}table{border-collapse:collapse;}</style>'
                . '</head><body>');
            fwrite($out, '<h2>LAPORAN BENDAHARA (' . strtoupper($jenis) . ') ' . e($awal) . ' s.d. ' . e($akhir) . '</h2>');
            fwrite($out, '<p>Dicetak: ' . date('d F Y H:i:s') . ' | Basis: kas (pembayaran success + mutasi kas unit, koreksi negatif)</p>');

            if ($jenis === 'perakun') {
                fwrite($out, '<table><thead><tr><th>KODE AKUN</th><th>JUMLAH (RP)</th><th>JML TRANSAKSI</th></tr></thead><tbody>');
                $total = 0;
                foreach ($rows as $r) {
                    $total += (float) $r['jumlah'];
                    fwrite($out, '<tr><td>' . e($r['kode_akun']) . '</td><td>' . number_format((float) $r['jumlah'], 0, ',', '.') . '</td><td>' . (int) $r['jumlah_transaksi'] . '</td></tr>');
                }
                fwrite($out, '</tbody><tfoot><tr><th>Total</th><th>' . number_format($total, 0, ',', '.') . '</th><th></th></tr></tfoot></table>');
            } else {
                fwrite($out, '<table><thead><tr><th>TANGGAL</th><th>KODE</th><th>URAIAN</th><th>MAHASISWA</th><th>JUMLAH (RP)</th><th>JENIS</th></tr></thead><tbody>');
                $total = 0;
                foreach ($rows as $l) {
                    $total += (float) $l['jumlah'];
                    fwrite($out, '<tr><td>' . e($l['tanggal']) . '</td><td>' . e($l['kode_transaksi']) . '</td><td>' . e($l['uraian']) . '</td><td>' . e(trim(($l['mahasiswa_nim'] ?? '') . ' ' . ($l['mahasiswa_nama'] ?? ''))) . '</td><td>' . number_format((float) $l['jumlah'], 0, ',', '.') . '</td><td>' . e($l['jenis_transaksi']) . '</td></tr>');
                }
                fwrite($out, '</tbody><tfoot><tr><th colspan="4">Total bersih</th><th>' . number_format($total, 0, ',', '.') . '</th><th></th></tr></tfoot></table>');
            }
            fwrite($out, '</body></html>');
            fclose($out);
        }, 200, $headers);
    }
}
