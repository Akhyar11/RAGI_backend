<?php

namespace App\Http\Controllers\Sikeu;

use App\Http\Controllers\Controller;
use App\Models\Sikeu\PengajuanPencairanKas;
use App\Models\Sikeu\TransaksiKasUnit;
use App\Models\Sikeu\UnitKas;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PengajuanKasController extends Controller
{
    /**
     * Get all Pengajuan Kas
     */
    public function index()
    {
        $pengajuan = PengajuanPencairanKas::with('unitKas')->orderBy('created_at', 'desc')->get();
        return response()->json([
            'status' => 'success',
            'data' => $pengajuan
        ]);
    }

    /**
     * Store new Pengajuan Kas
     */
    public function store(Request $request)
    {
        $request->validate([
            'unit_kas_id' => 'required|exists:unit_kas,id',
            'judul_pengajuan' => 'required|string',
            'deskripsi' => 'nullable|string',
            'nominal_diajukan' => 'required|numeric|min:1',
        ]);

        $pengajuan = PengajuanPencairanKas::create([
            'nomor_pengajuan' => 'PK-' . time(),
            'unit_kas_id' => $request->unit_kas_id,
            'judul_pengajuan' => $request->judul_pengajuan,
            'deskripsi' => $request->deskripsi,
            'nominal_diajukan' => $request->nominal_diajukan,
            'status' => 'pending_keuangan',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Pengajuan berhasil dibuat',
            'data' => $pengajuan
        ], 201);
    }

    /**
     * Approve Pengajuan (by Kabag)
     */
    public function approve(Request $request, $id)
    {
        $pengajuan = PengajuanPencairanKas::findOrFail($id);

        if ($pengajuan->status === 'dicairkan') {
            return response()->json(['status' => 'error', 'message' => 'Sudah dicairkan'], 400);
        }

        DB::beginTransaction();
        try {
            $pengajuan->status = 'dicairkan';
            $pengajuan->nominal_disetujui = $pengajuan->nominal_diajukan; // Simplify for now
            $pengajuan->approved_keuangan_by = auth()->id() ?? 1;
            $pengajuan->approved_keuangan_at = now();
            $pengajuan->save();

            // Insert into TransaksiKasUnit
            $unitKas = UnitKas::find($pengajuan->unit_kas_id);
            if ($unitKas) {
                $saldoSebelum = $unitKas->saldo_saat_ini;
                $unitKas->saldo_saat_ini += $pengajuan->nominal_disetujui;
                $unitKas->save();

                TransaksiKasUnit::create([
                    'unit_kas_id' => $unitKas->id,
                    'pengajuan_pencairan_id' => $pengajuan->id,
                    'kode_transaksi' => 'TRX-' . time(),
                    'jenis_transaksi' => 'debet_pemasukan',
                    'nominal' => $pengajuan->nominal_disetujui,
                    'saldo_sebelum' => $saldoSebelum,
                    'saldo_sesudah' => $unitKas->saldo_saat_ini,
                    'keterangan' => 'Pencairan: ' . $pengajuan->judul_pengajuan,
                    'tanggal_transaksi' => now()->toDateString(),
                ]);
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Pengajuan berhasil di-ACC dan dicairkan.',
                'data' => $pengajuan
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal approve: ' . $e->getMessage()
            ], 500);
        }
    }
}
