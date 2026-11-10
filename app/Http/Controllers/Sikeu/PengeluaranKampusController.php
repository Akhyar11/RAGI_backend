<?php

namespace App\Http\Controllers\Sikeu;

use App\Http\Controllers\Controller;
use App\Models\Sikeu\AkunKeuangan;
use App\Models\Sikeu\PengeluaranKampus;
use App\Models\Sikeu\UnitKas;
use App\Services\AuditLogService;
use App\Services\Sikeu\JurnalSikeuService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class PengeluaranKampusController extends Controller
{
    /**
     * GET /api/v1/sikeu/pengeluaran
     * List campus expenses with filters, search, and pagination.
     */
    public function index(Request $request)
    {
        $query = PengeluaranKampus::with(['akunBeban', 'akunKas']);

        // Filter search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nomor_transaksi', 'like', "%{$search}%")
                  ->orWhere('nama_vendor', 'like', "%{$search}%")
                  ->orWhere('keterangan', 'like', "%{$search}%");
            });
        }

        // Filter kategori
        if ($request->filled('kategori') && $request->kategori !== 'semua') {
            $query->where('kategori', $request->kategori);
        }

        // Filter jenis pajak
        if ($request->filled('jenis_pajak') && $request->jenis_pajak !== 'semua') {
            $query->where('jenis_pajak', $request->jenis_pajak);
        }

        // Filter status pembayaran
        if ($request->filled('status') && $request->status !== 'semua') {
            $query->where('status_pembayaran', $request->status);
        }

        $perPage = min(100, $request->integer('per_page', 15));
        $data = $query->orderBy('tanggal_transaksi', 'desc')->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'message' => 'Data pengeluaran kampus berhasil dimuat',
            'data' => $data->items(),
            'meta' => [
                'current_page' => $data->currentPage(),
                'per_page' => $data->perPage(),
                'total' => $data->total(),
                'last_page' => $data->lastPage(),
                'from' => $data->firstItem(),
                'to' => $data->lastItem(),
            ],
            'summary' => [
                'total_nominal' => (float) PengeluaranKampus::sum('nominal'),
                'total_pajak' => (float) PengeluaranKampus::sum('nominal_pajak'),
                'total_net' => (float) PengeluaranKampus::sum('net_dibayarkan'),
            ]
        ]);
    }

    /**
     * POST /api/v1/sikeu/pengeluaran
     * Create a new campus expense with tax deduction and automatic balanced journal entry.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'kategori' => 'required|in:operasional,pemeliharaan,laboratorium,kegiatan,honorarium,lainnya',
            'akun_beban_id' => 'nullable|exists:sikeu_akun_keuangan,id',
            'nominal' => 'required|numeric|min:1000',
            'tanggal_transaksi' => 'required|date',
            'nama_vendor' => 'required|string|max:255',
            'npwp_vendor' => 'nullable|string|max:50',
            'jenis_pajak' => 'required|in:tanpa_pajak,pph_21,pph_23,ppn_11',
            'unit_kas_id' => 'required|exists:sikeu_unit_kas,id',
            'keterangan' => 'nullable|string',
            'file_bukti_bayar' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validasi input pengeluaran gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $nominal = (float) $request->nominal;
            $jenisPajak = $request->jenis_pajak;
            $tarifPajakPersen = match ($jenisPajak) {
                'pph_21' => 5.0,
                'pph_23' => 2.0,
                'ppn_11' => 11.0,
                default => 0.0,
            };
            $nominalPajak = round($nominal * ($tarifPajakPersen / 100), 2);
            $netDibayarkan = in_array($jenisPajak, ['pph_21', 'pph_23'], true)
                ? $nominal - $nominalPajak
                : $nominal;
            $user = $request->user();
            $result = DB::transaction(function () use ($request, $nominal, $jenisPajak, $tarifPajakPersen, $nominalPajak, $netDibayarkan, $user) {
                $unitKas = UnitKas::where('status', true)
                    ->lockForUpdate()
                    ->findOrFail($request->integer('unit_kas_id'));
                if ((float) $unitKas->saldo_saat_ini < $netDibayarkan) {
                    abort(422, 'Saldo unit kas tidak mencukupi untuk mencatat pengeluaran ini.');
                }

                $kodeBeban = match ($request->kategori) {
                    'honorarium' => JurnalSikeuService::AKUN_BEBAN_GAJI,
                    'pemeliharaan' => JurnalSikeuService::AKUN_BEBAN_PEMELIHARAAN,
                    'laboratorium' => JurnalSikeuService::AKUN_BEBAN_LABORATORIUM,
                    'operasional', 'kegiatan', 'lainnya' => JurnalSikeuService::AKUN_BEBAN_OPERASIONAL,
                };
                $akunBeban = $request->filled('akun_beban_id')
                    ? AkunKeuangan::whereKey($request->akun_beban_id)->where('kelompok', 'beban')->first()
                    : AkunKeuangan::where('kode_akun', $kodeBeban)->first();
                if (!$akunBeban) {
                    abort(422, 'Akun beban yang dipilih atau dipetakan tidak tersedia pada COA.');
                }

                $akunKas = JurnalSikeuService::akunKasUnit($unitKas);
                $akunUtangPajak = $nominalPajak > 0
                    ? JurnalSikeuService::akunUtangPajak($jenisPajak)
                    : null;
                $nomorTransaksi = 'EXP-' . strtoupper(substr($request->kategori, 0, 3)) . '-' . now()->format('Ymd') . '-' . Str::random(8);
                $pengeluaran = PengeluaranKampus::create([
                    'nomor_transaksi' => $nomorTransaksi,
                    'kategori' => $request->kategori,
                    'akun_beban_id' => $akunBeban->id,
                    'akun_kas_id' => $akunKas->id,
                    'nominal' => $nominal,
                    'keterangan' => $request->keterangan ?? "Pengeluaran {$request->kategori} - {$request->nama_vendor}",
                    'tanggal_transaksi' => $request->tanggal_transaksi,
                    'nama_vendor' => $request->nama_vendor,
                    'npwp_vendor' => $request->npwp_vendor,
                    'jenis_pajak' => $jenisPajak,
                    'tarif_pajak_persen' => $tarifPajakPersen,
                    'nominal_pajak' => $nominalPajak,
                    'net_dibayarkan' => $netDibayarkan,
                    'status_pembayaran' => 'lunas',
                    'file_bukti_bayar' => $request->file_bukti_bayar,
                    'created_by' => $user?->id,
                ]);
                $jurnal = JurnalSikeuService::jurnalPengeluaranKampus(
                    $pengeluaran,
                    $akunBeban,
                    $akunKas,
                    $akunUtangPajak
                );
                $unitKas->decrement('saldo_saat_ini', $netDibayarkan);

                return compact('pengeluaran', 'jurnal', 'unitKas');
            });
        } catch (\DomainException|\InvalidArgumentException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 422);
        }

        try {
            AuditLogService::record(
                module: 'SIKEU',
                action: 'create',
                tableName: 'sikeu_pengeluaran_kampus',
                recordId: (int) $result['pengeluaran']->id,
                newValues: [
                    'nomor_transaksi' => $result['pengeluaran']->nomor_transaksi,
                    'nominal' => $result['pengeluaran']->nominal,
                    'jenis_pajak' => $result['pengeluaran']->jenis_pajak,
                    'nominal_pajak' => $result['pengeluaran']->nominal_pajak,
                    'unit_kas_id' => $result['unitKas']->id,
                    'jurnal_id' => $result['jurnal']->id,
                ],
                request: $request
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal mencatat audit log pengeluaran SIKEU: ' . $e->getMessage());
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Pengeluaran berhasil dicatat, saldo unit kas disesuaikan, dan jurnal akuntansi telah diterbitkan.',
            'data' => $result['pengeluaran']->load(['akunBeban', 'akunKas']),
        ], 201);
    }

    /**
     * GET /api/v1/sikeu/pengeluaran/{id}
     */
    public function show($id)
    {
        $item = PengeluaranKampus::with(['akunBeban', 'akunKas'])->findOrFail($id);
        return response()->json([
            'status' => 'success',
            'data' => $item
        ]);
    }

    /**
     * GET /api/v1/sikeu/pengeluaran/master/kategori
     * Return distinct kategori values from pengeluaran_kampus table.
     */
    public function getKategori()
    {
        $kategori = PengeluaranKampus::select('kategori')
            ->distinct()
            ->orderBy('kategori')
            ->pluck('kategori')
            ->map(fn($k) => [
                'value' => $k,
                'label' => ucwords(str_replace('_', ' ', $k)),
            ]);

        return response()->json([
            'status' => 'success',
            'data' => $kategori,
        ]);
    }
}
