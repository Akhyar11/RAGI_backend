<?php

namespace App\Http\Controllers\Sikeu;

use App\Http\Controllers\Controller;
use App\Models\Sikeu\PengeluaranKampus;
use App\Models\Sikeu\UnitKas;
use App\Services\AuditLogService;
use App\Services\Sikeu\JurnalSikeuService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

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
                'nominal_pajak' => (float) $item->nominal_pajak,
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
            'unit_kas_id' => 'required|exists:sikeu_unit_kas,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Nomor NTPN wajib diisi dengan benar',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $ntpn = trim($request->ntpn);
            $tanggalSetor = $request->tanggal_setor ?? now()->toDateString();
            $result = DB::transaction(function () use ($id, $request, $ntpn, $tanggalSetor) {
                $pengeluaran = PengeluaranKampus::lockForUpdate()->findOrFail($id);
                if ($pengeluaran->status_pembayaran === 'disetor') {
                    abort(422, 'Kewajiban pajak ini sudah pernah disetorkan sebelumnya.');
                }

                $unitKas = UnitKas::where('status', true)
                    ->lockForUpdate()
                    ->findOrFail($request->integer('unit_kas_id'));
                $nominalPajak = (float) $pengeluaran->nominal_pajak;
                if ($nominalPajak <= 0) {
                    abort(422, 'Kewajiban pajak tidak memiliki nominal yang dapat disetorkan.');
                }
                if ((float) $unitKas->saldo_saat_ini < $nominalPajak) {
                    abort(422, 'Saldo unit kas tidak mencukupi untuk menyetorkan kewajiban pajak ini.');
                }

                JurnalSikeuService::akunUtangPajak((string) $pengeluaran->jenis_pajak);
                JurnalSikeuService::akunKasUnit($unitKas);

                $oldValues = [
                    'status_pembayaran' => $pengeluaran->status_pembayaran,
                    'file_bukti_bayar' => $pengeluaran->file_bukti_bayar,
                ];
                $pengeluaran->status_pembayaran = 'disetor';
                $pengeluaran->file_bukti_bayar = $ntpn;
                $pengeluaran->save();

                $unitKas->decrement('saldo_saat_ini', $nominalPajak);
                $jurnal = JurnalSikeuService::jurnalPenyetoranPajak(
                    $pengeluaran,
                    $unitKas,
                    $ntpn,
                    $tanggalSetor
                );

                return compact('pengeluaran', 'oldValues', 'unitKas', 'jurnal');
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
                action: 'update',
                tableName: 'sikeu_pengeluaran_kampus',
                recordId: (int) $result['pengeluaran']->id,
                oldValues: $result['oldValues'],
                newValues: [
                    'status_pembayaran' => $result['pengeluaran']->status_pembayaran,
                    'file_bukti_bayar' => $result['pengeluaran']->file_bukti_bayar,
                    'unit_kas_id' => $result['unitKas']->id,
                    'jurnal_id' => $result['jurnal']->id,
                ],
                request: $request
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal mencatat audit log penyetoran pajak SIKEU: ' . $e->getMessage());
        }

        return response()->json([
            'status' => 'success',
            'message' => "Penyetoran pajak berhasil dicatat dengan NTPN: {$ntpn}.",
            'data' => [
                'id' => $result['pengeluaran']->id,
                'status' => 'disetor',
                'ntpn' => $ntpn,
                'tanggal_setor' => $tanggalSetor,
                'jurnal_id' => $result['jurnal']->id,
            ],
        ]);
    }
}
