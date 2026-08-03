<?php

namespace App\Http\Controllers\Sikeu;

use App\Http\Controllers\Controller;
use App\Models\Sikeu\AkunKeuangan;
use App\Models\Sikeu\JurnalUmum;
use App\Models\Sikeu\DetailJurnalUmum;
use App\Models\Sikeu\PeriodeAkuntansi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AkuntansiController extends Controller
{
    /**
     * GET /api/v1/sikeu/akuntansi/coa
     * List Chart of Accounts (COA).
     */
    public function indexCoa(Request $request)
    {
        $query = AkunKeuangan::query();

        if ($request->has('kelompok')) {
            $query->where('kelompok', $request->kelompok);
        }

        $coa = $query->orderBy('kode_akun', 'asc')->get();

        return response()->json([
            'status' => 'success',
            'data' => $coa
        ]);
    }

    /**
     * POST /api/v1/sikeu/akuntansi/coa
     * Create new COA Account.
     */
    public function storeCoa(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'kode_akun' => 'required|string|unique:akun_keuangan,kode_akun',
            'nama_akun' => 'required|string',
            'kelompok' => 'required|in:aset,liabilitas,ekuitas,pendapatan,beban',
            'saldo_normal' => 'required|in:debet,kredit',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        $akun = AkunKeuangan::create([
            'kode_akun' => $request->kode_akun,
            'nama_akun' => $request->nama_akun,
            'kelompok' => $request->kelompok,
            'saldo_normal' => $request->saldo_normal,
            'is_active' => true,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Akun COA berhasil ditambahkan.',
            'data' => $akun
        ], 201);
    }

    /**
     * GET /api/v1/sikeu/akuntansi/jurnal
     * List General Journal entries.
     */
    public function indexJurnal(Request $request)
    {
        $query = JurnalUmum::with(['details.akun']);

        if ($request->has('jenis_sumber')) {
            $query->where('jenis_sumber', $request->jenis_sumber);
        }

        if ($request->has('status_posting')) {
            $query->where('status_posting', $request->status_posting);
        }

        $jurnal = $query->orderBy('tanggal_jurnal', 'desc')
            ->orderBy('id', 'desc')
            ->paginate($request->input('per_page', 15));

        return response()->json([
            'status' => 'success',
            'data' => $jurnal
        ]);
    }

    /**
     * POST /api/v1/sikeu/akuntansi/jurnal
     * Create manual / adjustment journal entry.
     */
    public function storeJurnal(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'tanggal_jurnal' => 'required|date',
            'jenis_sumber' => 'required|in:pembayaran_mahasiswa,pemasukan_hibah,pencairan_kas,pengeluaran_manual,penyesuaian,penutupan',
            'keterangan' => 'required|string',
            'details' => 'required|array|min:2',
            'details.*.akun_id' => 'required|exists:akun_keuangan,id',
            'details.*.debet' => 'required|numeric|min:0',
            'details.*.kredit' => 'required|numeric|min:0',
            'details.*.keterangan' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        // Validate debet == kredit
        $totalDebet = 0;
        $totalKredit = 0;
        foreach ($request->details as $d) {
            $totalDebet += (float) $d['debet'];
            $totalKredit += (float) $d['kredit'];
        }

        if (abs($totalDebet - $totalKredit) > 0.01) {
            return response()->json([
                'status' => 'error',
                'message' => 'Total Debet (Rp ' . number_format($totalDebet, 2) . ') dan Total Kredit (Rp ' . number_format($totalKredit, 2) . ') tidak seimbang (Unbalanced Journal).'
            ], 422);
        }

        try {
            DB::beginTransaction();

            $nomorJurnal = 'JRN-' . date('Ymd') . '-' . Str::random(4);

            $jurnal = JurnalUmum::create([
                'nomor_jurnal' => strtoupper($nomorJurnal),
                'tanggal_jurnal' => $request->tanggal_jurnal,
                'jenis_sumber' => $request->jenis_sumber,
                'keterangan' => $request->keterangan,
                'status_posting' => 'posted',
                'total_debet' => $totalDebet,
                'total_kredit' => $totalKredit,
                'created_by' => auth()->id() ?? 1,
                'posted_by' => auth()->id() ?? 1,
                'posted_at' => now(),
            ]);

            foreach ($request->details as $detail) {
                DetailJurnalUmum::create([
                    'jurnal_id' => $jurnal->id,
                    'akun_id' => $detail['akun_id'],
                    'debet' => $detail['debet'],
                    'kredit' => $detail['kredit'],
                    'keterangan' => $detail['keterangan'] ?? $request->keterangan,
                ]);
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Jurnal umum/penyesuaian berhasil disimpan.',
                'data' => $jurnal->load('details.akun')
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal membuat jurnal: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/v1/sikeu/akuntansi/buku-besar
     * General Ledger summary per Account.
     */
    public function bukuBesar(Request $request)
    {
        $akunId = $request->akun_id;

        $query = DetailJurnalUmum::with(['jurnal', 'akun']);

        if ($akunId) {
            $query->where('akun_id', $akunId);
        }

        $items = $query->orderBy('created_at', 'desc')->paginate($request->input('per_page', 20));

        return response()->json([
            'status' => 'success',
            'data' => $items
        ]);
    }
}
