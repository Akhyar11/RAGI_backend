<?php

namespace App\Http\Controllers\Sikeu;

use App\Http\Controllers\Controller;
use App\Models\Sikeu\TagihanMahasiswa;
use App\Services\Sikeu\BsnH2hService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Jembatan H2H BTN Syariah (bridge Go indonusa_h2h_v2):
 * terbitkan billing VA + sinkron pembayaran terbayar ke tagihan RAG.
 */
class BsnH2hController extends Controller
{
    /**
     * POST /api/v1/sikeu/tagihan/{id}/terbitkan-h2h
     * Buat billing VA BTN Syariah. CUSTID = no_pendaftaran (calon) / NIM (mhs).
     * Body opsional: { "force": true } untuk terbitkan ulang (billing ganda
     * sebelumnya kedaluwarsa/terhapus di bridge).
     */
    public function terbitkan(Request $request, $id)
    {
        $tagihan = TagihanMahasiswa::with(['details.masterBiaya', 'calonMahasiswa', 'mahasiswa', 'tipeTagihanMahasiswa'])->findOrFail($id);

        try {
            $hasil = BsnH2hService::terbitkanBilling($tagihan, $request->boolean('force'));

            $pesan = !empty($hasil['reused'])
                ? "Billing H2H sudah pernah terbit (CUSTID {$hasil['custid']}). Gunakan opsi terbitkan ulang bila billing di bridge kedaluwarsa."
                : "Billing H2H terbit. CUSTID {$hasil['custid']} dapat dibayar di kanal BTN Syariah.";

            return response()->json([
                'status' => 'success',
                'message' => $pesan,
                'data' => $hasil + ['tagihan' => $tagihan->fresh()],
            ], 201);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            \Log::error('Terbitkan H2H gagal: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'Gagal menerbitkan billing: ' . $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/v1/sikeu/h2h/sync
     * Tarik manual pembayaran terbayar dari bridge (selain jadwal 5 menit).
     */
    public function sync(Request $request)
    {
        $hasil = BsnH2hService::sinkronTerbayar((int) $request->input('limit', 100));

        if (isset($hasil['error'])) {
            return response()->json(['status' => 'error', 'message' => $hasil['error']], 500);
        }

        return response()->json([
            'status' => 'success',
            'message' => "Sinkron selesai: {$hasil['diproses']} terbayar diterapkan.",
            'data' => $hasil,
        ]);
    }

    /**
     * GET /api/v1/sikeu/h2h/status
     * Diagnostik konektivitas bridge API + database bridge.
     */
    public function status()
    {
        $out = [
            'bridge_url' => BsnH2hService::bridgeUrl(),
            'sumber_config' => BsnH2hService::configSource(),
            'h2h_aktif' => BsnH2hService::isEnabled(),
            'bridge_api' => 'down',
            'bridge_db' => 'down',
        ];

        try {
            $res = \Illuminate\Support\Facades\Http::timeout(8)->get(BsnH2hService::bridgeUrl() . '/');
            if ($res->successful()) {
                $out['bridge_api'] = 'up';
            }
        } catch (\Throwable $e) {
            $out['bridge_api_error'] = $e->getMessage();
        }

        try {
            BsnH2hService::applyDbConfig();
            DB::connection('mysql_h2h')->select('select 1');
            $out['bridge_db'] = 'up';
            $out['va_billing_count'] = DB::connection('mysql_h2h')->table('va_billings')->count();
        } catch (\Throwable $e) {
            $out['bridge_db_error'] = $e->getMessage();
        }

        return response()->json(['status' => 'success', 'data' => $out]);
    }
}
