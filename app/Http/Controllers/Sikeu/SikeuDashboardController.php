<?php

namespace App\Http\Controllers\Sikeu;

use App\Http\Controllers\Controller;
use App\Models\Sikeu\DispensasiTagihan;
use App\Models\Sikeu\JurnalUmum;
use App\Models\Sikeu\PaymentGatewayConfig;
use App\Models\Sikeu\PemasukanKampus;
use App\Models\Sikeu\PengajuanPencairanKas;
use App\Models\Sikeu\PengeluaranKampus;
use App\Models\Sikeu\TagihanMahasiswa;
use App\Models\Sikeu\UnitKas;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class SikeuDashboardController extends Controller
{
    /**
     * GET /api/v1/sikeu/dashboard-summary
     * Comprehensive real-time financial metrics, pending approvals, and payment gateway balance.
     */
    public function summary(Request $request)
    {
        try {
            // 1. Total Penerimaan (Mahasiswa Lunas/Sebagian + Pemasukan Eksternal/Hibah)
            $penerimaanMahasiswa = (float) TagihanMahasiswa::sum('total_bayar');
            $penerimaanEksternal = (float) PemasukanKampus::sum('nominal');
            $totalPenerimaan = $penerimaanMahasiswa + $penerimaanEksternal;

            // 2. Total Pengeluaran (Pengeluaran Operasional/Vendor + Pencairan Kas Unit)
            $pengeluaranOperasional = (float) PengeluaranKampus::sum('net_dibayarkan');
            $pengeluaranKasUnit = (float) PengajuanPencairanKas::where('status', 'dicairkan')->sum('nominal_disetujui');
            $totalPengeluaran = $pengeluaranOperasional + $pengeluaranKasUnit;

            // 3. Saldo Kas
            $kasUtama = UnitKas::where('is_kabag_kas', true)->first();
            $saldoKasUtama = $kasUtama ? (float) $kasUtama->saldo_saat_ini : 0.0;
            $saldoTotalSemuaKas = (float) UnitKas::sum('saldo_saat_ini');

            // 4. Pajak Terutang (PPh 21, PPh 23, PPN)
            $pajakTerutang = (float) PengeluaranKampus::where('jenis_pajak', '!=', 'tanpa_pajak')
                ->where('status_pembayaran', '!=', 'disetor')
                ->sum('nominal_pajak');

            // 5. Counts of Pending Approvals
            $tagihanPending = TagihanMahasiswa::where('status', 'belum_bayar')->count();
            $dispensasiPending = DispensasiTagihan::where('status', 'pending')->count();
            $pengajuanKasPending = PengajuanPencairanKas::where('status', 'pending_keuangan')->count();

            // 6. Payment Gateway (Xendit / Active Provider) Live Balance Tracker
            $activeGateway = PaymentGatewayConfig::where('is_active', true)->first()
                ?? PaymentGatewayConfig::where('gateway_name', 'xendit')->first();

            $gatewayData = [
                'gateway_name' => $activeGateway ? $activeGateway->gateway_name : 'xendit',
                'is_active' => $activeGateway ? (bool) $activeGateway->is_active : false,
                'environment' => $activeGateway ? $activeGateway->environment : 'sandbox',
                'available_balance' => 0,
                'pending_settlement' => 0,
                'total_balance' => 0,
                'status_koneksi' => 'disconnected',
                'last_updated' => now()->format('H:i:s d-m-Y'),
                'error_message' => null,
            ];

            if ($activeGateway && $activeGateway->api_key_encrypted) {
                try {
                    if ($activeGateway->gateway_name === 'xendit') {
                        $response = Http::withoutVerifying()
                            ->timeout(5)
                            ->withBasicAuth($activeGateway->api_key_encrypted, '')
                            ->get('https://api.xendit.co/balance');

                        if ($response->successful()) {
                            $resJson = $response->json();
                            $bal = (float) ($resJson['balance'] ?? 0);
                            $gatewayData['available_balance'] = $bal;
                            $gatewayData['total_balance'] = $bal;
                            $gatewayData['status_koneksi'] = 'connected';
                        } else {
                            $gatewayData['status_koneksi'] = 'error';
                            $gatewayData['error_message'] = 'Gagal sinkronisasi API Xendit (' . $response->status() . ')';
                        }
                    } else {
                        $gatewayData['status_koneksi'] = 'connected';
                    }
                } catch (\Exception $e) {
                    $gatewayData['status_koneksi'] = 'unreachable';
                    $gatewayData['error_message'] = 'Koneksi ke gateway timeout/offline: ' . $e->getMessage();
                }
            } else {
                $gatewayData['status_koneksi'] = 'unconfigured';
                $gatewayData['error_message'] = 'API Key Secret belum dikonfigurasi';
            }

            // 7. Recent Jurnals
            $recentJurnals = JurnalUmum::with('details.akun')
                ->orderBy('id', 'desc')
                ->take(5)
                ->get();

            // 8. Unit Kas List
            $unitKasList = UnitKas::where('status', true)->get();

            return response()->json([
                'status' => 'success',
                'message' => 'Ringkasan finansial SIKEU berhasil dimuat',
                'data' => [
                    'metrics' => [
                        'total_penerimaan' => $totalPenerimaan,
                        'penerimaan_mahasiswa' => $penerimaanMahasiswa,
                        'penerimaan_eksternal' => $penerimaanEksternal,
                        'total_pengeluaran' => $totalPengeluaran,
                        'saldo_kas_utama' => $saldoKasUtama,
                        'saldo_total_kas' => $saldoTotalSemuaKas,
                        'pajak_terutang' => $pajakTerutang,
                        'tagihan_pending_approval' => $tagihanPending,
                        'dispensasi_pending' => $dispensasiPending,
                        'pengajuan_kas_pending' => $pengajuanKasPending,
                        'total_pending_approval' => $dispensasiPending + $pengajuanKasPending,
                    ],
                    'payment_gateway' => $gatewayData,
                    'unit_kas' => $unitKasList,
                    'recent_jurnals' => $recentJurnals,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal memuat ringkasan dashboard SIKEU: ' . $e->getMessage()
            ], 500);
        }
    }
}
