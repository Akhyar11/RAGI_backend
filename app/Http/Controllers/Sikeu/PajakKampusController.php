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

class PajakKampusController extends Controller
{
    /**
     * GET /api/v1/sikeu/pajak
     * List all tax obligations (PPh 21, PPh 23, PPN) and settlement status.
     */
    public function index(Request $request)
    {
        $query = PengeluaranKampus::where('jenis_pajak', '!=', 'tanpa_pajak');

        if ($request->filled('jenis') && $request->jenis !== 'semua') {
            $query->where('jenis_pajak', $request->jenis);
        }

        if ($request->filled('status') && $request->status !== 'semua') {
            if ($request->status === 'disetor') {
                $query->where('status_pembayaran', 'disetor');
            } elseif ($request->status === 'terutang') {
                $query->where('status_pembayaran', '!=', 'disetor');
            }
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nomor_transaksi', 'like', "%{$search}%")
                  ->orWhere('nama_vendor', 'like', "%{$search}%")
                  ->orWhere('file_bukti_bayar', 'like', "%{$search}%");
            });
        }

        $perPage = min(100, $request->integer('per_page', 20));
        $data = $query->orderBy('tanggal_transaksi', 'desc')->paginate($perPage);

        $mappedItems = collect($data->items())->map(function ($item) {
            $isDisetor = $item->status_pembayaran === 'disetor';
            $jenisLabel = match ($item->jenis_pajak) {
                'pph_21' => 'PPh 21',
                'pph_23' => 'PPh 23',
                'ppn_11' => 'PPN 11%',
                default => strtoupper($item->jenis_pajak)
            };

            return [
                'id' => $item->id,
                'nomor' => 'TAX-' . date('Ym', strtotime($item->tanggal_transaksi)) . '-' . str_pad($item->id, 3, '0', STR_PAD_LEFT),
                'nomor_transaksi_asal' => $item->nomor_transaksi,
                'jenis' => $jenisLabel,
                'jenis_raw' => $item->jenis_pajak,
                'deskripsi' => "Pajak atas {$item->kategori} - {$item->nama_vendor}",
                'nominal' => (float) $item->nominal_pajak,
                'status' => $isDisetor ? 'disetor' : 'terutang',
                'jatuhTempo' => date('Y-m-10', strtotime($item->tanggal_transaksi . ' +1 month')),
                'ntpn' => $isDisetor ? ($item->file_bukti_bayar ?? 'NTPN-' . $item->id) : '-',
                'vendor' => $item->nama_vendor,
                'npwp' => $item->npwp_vendor ?? '-',
                'tanggal_transaksi' => $item->tanggal_transaksi ? $item->tanggal_transaksi->format('Y-m-d') : null,
            ];
        });

        // Summary calculations
        $totalTerutang = (float) PengeluaranKampus::where('jenis_pajak', '!=', 'tanpa_pajak')
            ->where('status_pembayaran', '!=', 'disetor')
            ->sum('nominal_pajak');

        $totalDisetor = (float) PengeluaranKampus::where('jenis_pajak', '!=', 'tanpa_pajak')
            ->where('status_pembayaran', 'disetor')
            ->sum('nominal_pajak');

        return response()->json([
            'status' => 'success',
            'message' => 'Data pajak kampus berhasil dimuat',
            'data' => $mappedItems,
            'meta' => [
                'current_page' => $data->currentPage(),
                'per_page' => $data->perPage(),
                'total' => $data->total(),
                'last_page' => $data->lastPage(),
            ],
            'summary' => [
                'total_terutang' => $totalTerutang,
                'total_disetor' => $totalDisetor,
                'total_keseluruhan' => $totalTerutang + $totalDisetor,
            ]
        ]);
    }

    /**
     * POST /api/v1/sikeu/pajak/{id}/setor
     * Settle tax obligation with official NTPN number and post settlement accounting journal.
     */
    public function setorPajak(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'ntpn' => 'required|string|min:6|max:50',
            'tanggal_setor' => 'nullable|date',
            'unit_kas_id' => 'nullable|exists:unit_kas,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Nomor NTPN wajib diisi dengan benar',
                'errors' => $validator->errors()
            ], 422);
        }

        $pengeluaran = PengeluaranKampus::findOrFail($id);

        if ($pengeluaran->status_pembayaran === 'disetor') {
            return response()->json([
                'status' => 'error',
                'message' => 'Kewajiban pajak ini sudah pernah disetorkan sebelumnya.',
            ], 400);
        }

        try {
            DB::beginTransaction();

            $nominalPajak = (float) $pengeluaran->nominal_pajak;
            $ntpn = trim($request->ntpn);
            $tanggalSetor = $request->tanggal_setor ?? date('Y-m-d');

            // Mark as disetor and save NTPN in file_bukti_bayar or note
            $pengeluaran->status_pembayaran = 'disetor';
            $pengeluaran->file_bukti_bayar = $ntpn;
            $pengeluaran->save();

            // Deduct cash for tax remittance
            $unitKas = UnitKas::find($request->unit_kas_id ?? 1) ?? UnitKas::first();
            if ($unitKas) {
                $unitKas->decrement('saldo_saat_ini', $nominalPajak);
            }

            // Post Balanced Accounting Journal: Dr Utang Pajak, Cr Kas/Bank
            $akunUtangPajak = AkunKeuangan::where('kelompok', 'kewajiban')->first();
            $akunKas = AkunKeuangan::where('kelompok', 'aset')->first();

            if ($akunUtangPajak && $akunKas) {
                $jurnal = JurnalUmum::create([
                    'nomor_jurnal' => 'JRN-TAX-' . date('Ymd') . '-' . Str::random(4),
                    'tanggal_jurnal' => $tanggalSetor,
                    'jenis_sumber' => 'pengeluaran_manual',
                    'referensi_id' => $pengeluaran->id,
                    'keterangan' => "Penyetoran Pajak {$pengeluaran->jenis_pajak} ke Kas Negara (NTPN: {$ntpn})",
                    'status_posting' => 'posted',
                    'total_debet' => $nominalPajak,
                    'total_kredit' => $nominalPajak,
                    'created_by' => auth()->id() ?? 1,
                    'posted_by' => auth()->id() ?? 1,
                    'posted_at' => now(),
                ]);

                // Dr Utang Pajak
                DetailJurnalUmum::create([
                    'jurnal_id' => $jurnal->id,
                    'akun_id' => $akunUtangPajak->id,
                    'debet' => $nominalPajak,
                    'kredit' => 0,
                    'keterangan' => "Pelunasan Utang Pajak {$pengeluaran->jenis_pajak} NTPN: {$ntpn}",
                ]);

                // Cr Kas / Bank
                DetailJurnalUmum::create([
                    'jurnal_id' => $jurnal->id,
                    'akun_id' => $akunKas->id,
                    'debet' => 0,
                    'kredit' => $nominalPajak,
                    'keterangan' => "Setoran Pajak Kas Negara NTPN: {$ntpn}",
                ]);
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => "Penyetoran pajak berhasil dicatat dengan NTPN: {$ntpn}.",
                'data' => [
                    'id' => $pengeluaran->id,
                    'status' => 'disetor',
                    'ntpn' => $ntpn,
                    'tanggal_setor' => $tanggalSetor,
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal mencatat penyetoran pajak: ' . $e->getMessage()
            ], 500);
        }
    }
}
