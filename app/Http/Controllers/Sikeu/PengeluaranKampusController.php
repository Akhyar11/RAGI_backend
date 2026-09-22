<?php

namespace App\Http\Controllers\Sikeu;

use App\Http\Controllers\Controller;
use App\Models\Sikeu\AkunKeuangan;
use App\Models\Sikeu\DetailJurnalUmum;
use App\Models\Sikeu\JurnalUmum;
use App\Models\Sikeu\PengeluaranKampus;
use App\Models\Sikeu\UnitKas;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
            'unit_kas_id' => 'nullable|exists:sikeu_unit_kas,id',
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
            DB::beginTransaction();

            $nominal = (float) $request->nominal;
            $jenisPajak = $request->jenis_pajak;
            $tarifPajakPersen = 0.0;
            $nominalPajak = 0.0;

            if ($jenisPajak === 'pph_21') {
                $tarifPajakPersen = 5.0; // Standard default 5%
                $nominalPajak = $nominal * ($tarifPajakPersen / 100);
            } elseif ($jenisPajak === 'pph_23') {
                $tarifPajakPersen = 2.0; // Standard 2%
                $nominalPajak = $nominal * ($tarifPajakPersen / 100);
            } elseif ($jenisPajak === 'ppn_11') {
                $tarifPajakPersen = 11.0;
                $nominalPajak = $nominal * ($tarifPajakPersen / 100);
            }

            // Net amount to be paid out from cash
            // For withholding tax (PPh 21/23), net is gross minus tax
            // For PPN, institution pays net gross
            $netDibayarkan = in_array($jenisPajak, ['pph_21', 'pph_23']) ? ($nominal - $nominalPajak) : $nominal;

            $nomorTransaksi = 'EXP-' . strtoupper(substr($request->kategori, 0, 3)) . '-' . date('Ymd') . '-' . Str::random(4);

            // Unit Kas deduct
            $unitKas = UnitKas::find($request->unit_kas_id ?? 1) ?? UnitKas::first();
            if ($unitKas) {
                $unitKas->decrement('saldo_saat_ini', $netDibayarkan);
            }

            // Accounting COA mapping: beban mengikuti kategori, utang mengikuti jenis pajak.
            // (Sebelumnya selalu memakai akun pertama tiap kelompok sehingga seluruh
            // beban menumpuk di 501.01 dan seluruh utang pajak di 201.01.)
            // Pengguna boleh meng-override akun beban secara eksplisit lewat akun_beban_id
            // (mis. operasional yang ternyata belanja ATK, bukan listrik/air/internet).
            $akunBeban = null;
            if ($request->filled('akun_beban_id')) {
                $akunBeban = AkunKeuangan::where('id', $request->akun_beban_id)
                    ->where('kelompok', 'beban')
                    ->first();
                if (!$akunBeban) {
                    DB::rollBack();
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Akun beban yang dipilih tidak valid (harus akun kelompok beban).',
                    ], 422);
                }
            }
            if (!$akunBeban) {
                $kodeBeban = match ($request->kategori) {
                    'honorarium' => '501.01',
                    'pemeliharaan' => '502.02',
                    'laboratorium' => '502.03',
                    default => '502.01',
                };
                $akunBeban = AkunKeuangan::where('kode_akun', $kodeBeban)->first()
                    ?? AkunKeuangan::where('kelompok', 'beban')->first();
            }
            $akunKas = \App\Services\Sikeu\JurnalSikeuService::akunKasUnit($unitKas, '101.01');
            $kodeUtangPajak = match ($jenisPajak) {
                'pph_21' => '202.01',
                'pph_23' => '202.02',
                'ppn_11' => '202.03',
                default => null,
            };
            $akunUtangPajak = $kodeUtangPajak
                ? AkunKeuangan::where('kode_akun', $kodeUtangPajak)->first()
                : null;
            $akunUtangPajak ??= AkunKeuangan::where('kelompok', 'liabilitas')->first();

            $pengeluaran = PengeluaranKampus::create([
                'nomor_transaksi' => $nomorTransaksi,
                'kategori' => $request->kategori,
                'akun_beban_id' => $akunBeban ? $akunBeban->id : null,
                'akun_kas_id' => $akunKas ? $akunKas->id : null,
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
                'created_by' => auth()->id() ?? 1,
            ]);

            // Automatic Balanced Journal Posting
            if ($akunBeban && $akunKas) {
                $jurnal = JurnalUmum::create([
                    'nomor_jurnal' => \App\Services\Sikeu\JurnalSikeuService::prefix('pengeluaran') . '-' . date('Ymd') . '-' . Str::random(4),
                    'tanggal_jurnal' => $request->tanggal_transaksi,
                    'jenis_sumber' => 'pengeluaran_manual',
                    'referensi_id' => $pengeluaran->id,
                    'keterangan' => "Pengeluaran {$request->kategori} - {$request->nama_vendor}",
                    'status_posting' => 'posted',
                    'total_debet' => $nominal,
                    'total_kredit' => $nominal,
                    'created_by' => auth()->id() ?? 1,
                    'posted_by' => auth()->id() ?? 1,
                    'posted_at' => now(),
                ]);

                // Dr Beban Operasional (Gross Nominal)
                DetailJurnalUmum::create([
                    'jurnal_id' => $jurnal->id,
                    'akun_id' => $akunBeban->id,
                    'debet' => $nominal,
                    'kredit' => 0,
                    'keterangan' => "Beban {$request->kategori} {$request->nama_vendor}",
                ]);

                // Cr Kas/Bank (Net Dibayarkan)
                DetailJurnalUmum::create([
                    'jurnal_id' => $jurnal->id,
                    'akun_id' => $akunKas->id,
                    'debet' => 0,
                    'kredit' => $netDibayarkan,
                    'keterangan' => "Pembayaran kas/bank ke {$request->nama_vendor}",
                ]);

                // Cr Utang Pajak (jika ada potongan pajak)
                if ($nominalPajak > 0 && $akunUtangPajak) {
                    DetailJurnalUmum::create([
                        'jurnal_id' => $jurnal->id,
                        'akun_id' => $akunUtangPajak->id,
                        'debet' => 0,
                        'kredit' => $nominalPajak,
                        'keterangan' => "Utang Pajak {$jenisPajak} atas transaksi {$nomorTransaksi}",
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Pengeluaran berhasil dicatat, kas unit disesuaikan, dan jurnal akuntansi telah diterbitkan.',
                'data' => $pengeluaran->load(['akunBeban', 'akunKas'])
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal mencatat pengeluaran: ' . $e->getMessage()
            ], 500);
        }
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
