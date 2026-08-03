<?php

namespace App\Http\Controllers\Sikeu;

use App\Http\Controllers\Controller;
use App\Models\Sikeu\PemasukanKampus;
use App\Models\Sikeu\UnitKas;
use App\Models\Sikeu\AkunKeuangan;
use App\Models\Sikeu\JurnalUmum;
use App\Models\Sikeu\DetailJurnalUmum;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class PemasukanKampusController extends Controller
{
    /**
     * GET /api/v1/sikeu/pemasukan
     * List all campus income records.
     */
    public function index(Request $request)
    {
        $query = PemasukanKampus::with(['unitKas', 'akunPendapatan']);

        if ($request->has('sumber')) {
            $query->where('sumber_pemasukan', $request->sumber);
        }

        $data = $query->orderBy('tanggal_terima', 'desc')->paginate($request->input('per_page', 15));

        return response()->json([
            'status' => 'success',
            'data' => $data
        ]);
    }

    /**
     * POST /api/v1/sikeu/pemasukan/external
     * Record incoming funds from SIPPM (Hibah Riset), Donors, or External Partners.
     */
    public function storeExternalIncome(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'sumber_pemasukan' => 'required|in:hibah_sippm,donatur,kerjasama,pendapatan_lainnya',
            'unit_kas_id' => 'nullable|exists:unit_kas,id',
            'akun_pendapatan_kode' => 'nullable|string',
            'nominal' => 'required|numeric|min:1000',
            'tanggal_terima' => 'required|date',
            'nama_donor_instansi' => 'required|string',
            'nomor_kontrak_ref' => 'nullable|string',
            'file_bukti_transfer' => 'nullable|string',
            'keterangan' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validasi pencatatan pemasukan gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Default Kas Utama
            $unitKas = UnitKas::find($request->unit_kas_id ?? 1) ?? UnitKas::first();

            // Account COA for income
            $akunKode = $request->akun_pendapatan_kode ?? '402.01'; // Default Pendapatan Hibah Riset & PkM
            $akunPendapatan = AkunKeuangan::where('kode_akun', $akunKode)->first()
                ?? AkunKeuangan::where('kelompok', 'pendapatan')->first();

            $akunKas = AkunKeuangan::where('kode_akun', '102.01')->first()
                ?? AkunKeuangan::where('kelompok', 'aset')->first();

            $nomorTransaksi = 'INC-' . strtoupper($request->sumber_pemasukan) . '-' . date('Ymd') . '-' . Str::random(4);
            $nominal = (float) $request->nominal;

            $pemasukan = PemasukanKampus::create([
                'nomor_transaksi' => $nomorTransaksi,
                'sumber_pemasukan' => $request->sumber_pemasukan,
                'unit_kas_id' => $unitKas ? $unitKas->id : null,
                'akun_pendapatan_id' => $akunPendapatan ? $akunPendapatan->id : null,
                'nominal' => $nominal,
                'tanggal_terima' => $request->tanggal_terima,
                'nama_donor_instansi' => $request->nama_donor_instansi,
                'nomor_kontrak_ref' => $request->nomor_kontrak_ref,
                'file_bukti_transfer' => $request->file_bukti_transfer,
                'keterangan' => $request->keterangan ?? 'Penerimaan dana ' . $request->sumber_pemasukan . ' dari ' . $request->nama_donor_instansi,
                'created_by' => auth()->id() ?? 1,
            ]);

            // Auto-update Kas balance
            if ($unitKas) {
                $unitKas->increment('saldo_saat_ini', $nominal);
            }

            // Auto-journal entry (Debet Bank, Kredit Pendapatan)
            if ($akunKas && $akunPendapatan) {
                $jurnal = JurnalUmum::create([
                    'nomor_jurnal' => 'JRN-INC-' . date('Ymd') . '-' . Str::random(4),
                    'tanggal_jurnal' => $request->tanggal_terima,
                    'jenis_sumber' => 'pemasukan_hibah',
                    'referensi_id' => $pemasukan->id,
                    'keterangan' => 'Pemasukan Dana ' . $request->sumber_pemasukan . ' - ' . $request->nama_donor_instansi,
                    'status_posting' => 'posted',
                    'total_debet' => $nominal,
                    'total_kredit' => $nominal,
                    'created_by' => auth()->id() ?? 1,
                    'posted_by' => auth()->id() ?? 1,
                    'posted_at' => now(),
                ]);

                // Debet Bank Kampus
                DetailJurnalUmum::create([
                    'jurnal_id' => $jurnal->id,
                    'akun_id' => $akunKas->id,
                    'debet' => $nominal,
                    'kredit' => 0,
                    'keterangan' => 'Penerimaan kas/bank dari ' . $request->nama_donor_instansi,
                ]);

                // Kredit Pendapatan Hibah/Kerjasama
                DetailJurnalUmum::create([
                    'jurnal_id' => $jurnal->id,
                    'akun_id' => $akunPendapatan->id,
                    'debet' => 0,
                    'kredit' => $nominal,
                    'keterangan' => 'Pengakuan pendapatan ' . $request->sumber_pemasukan,
                ]);
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Pemasukan dana hibah/eksternal berhasil dicatat, saldo kas diperbarui, dan jurnal akuntansi telah dibuat.',
                'data' => $pemasukan->load(['unitKas', 'akunPendapatan'])
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal mencatat pemasukan dana: ' . $e->getMessage()
            ], 500);
        }
    }
}
