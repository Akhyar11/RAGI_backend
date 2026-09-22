<?php

namespace App\Http\Controllers\Sikeu;

use App\Http\Controllers\Controller;
use App\Models\Sikeu\DispensasiTagihan;
use App\Models\Sikeu\JurnalUmum;
use App\Models\Sikeu\KasKecilPengajuan;
use App\Models\Sikeu\KasKecilTransaksi;
use App\Models\Sikeu\PaymentGatewayConfig;
use App\Models\Sikeu\PemasukanKampus;
use App\Models\Sikeu\Pembayaran;
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
     * Supports ?start_date=YYYY-MM-DD&end_date=YYYY-MM-DD for dynamic period reporting.
     */
    public function summary(Request $request)
    {
        try {
            $startDate = $request->query('start_date');
            $endDate = $request->query('end_date');
            $hasDateFilter = !empty($startDate) && !empty($endDate);
            $startDateTime = $hasDateFilter ? $startDate . ' 00:00:00' : null;
            $endDateTime = $hasDateFilter ? $endDate . ' 23:59:59' : null;

            $user = $request->user();
            $isAdminKeuangan = $user && (
                $user->hasRole('operator_sikeu')
                || $user->hasRole('kabag_keuangan')
                || $user->hasRole('admin_keuangan_akuntansi')
            );
            $isPetugasKasKecil = $user && (
                $user->hasRole('petugas_kas_kecil') || $user->hasRole('petugas_kaskecil')
            ) && !$user->isSuperAdmin() && !$user->isAdmin() && !$isAdminKeuangan;

            // Khusus Petugas Kas Kecil: Hanya tampilkan unit petty cash & transaksi dari unit miliknya
            if ($isPetugasKasKecil) {
                $myUnits = UnitKas::with(['fakultas', 'akunKeuangan'])
                    ->where('penanggung_jawab_id', $user->id)
                    ->where('tipe_kas', 'petty_cash')
                    ->get();

                $myUnitIds = $myUnits->pluck('id')->toArray();

                $totalSaldoSaatIni = (float) $myUnits->sum('saldo_saat_ini');
                $totalSaldoAwal = (float) $myUnits->sum('saldo_awal');

                $transaksiQuery = KasKecilTransaksi::with(['kategori', 'unitKas.fakultas'])
                    ->whereIn('unit_kas_id', $myUnitIds)
                    ->when($hasDateFilter, function ($q) use ($startDate, $endDate) {
                        $q->whereDate('tanggal_transaksi', '>=', $startDate)
                            ->whereDate('tanggal_transaksi', '<=', $endDate);
                    })
                    ->orderBy('tanggal_transaksi', 'desc')
                    ->orderBy('id', 'desc');

                $totalPengeluaranKasKecil = (float) (clone $transaksiQuery)->sum('nominal');
                $recentTransaksis = $transaksiQuery->take(15)->get();

                $pengajuanQuery = KasKecilPengajuan::with(['unitKas.fakultas'])
                    ->whereIn('unit_kas_id', $myUnitIds)
                    ->when($hasDateFilter, function ($q) use ($startDate, $endDate) {
                        $q->whereDate('created_at', '>=', $startDate)
                            ->whereDate('created_at', '<=', $endDate);
                    })
                    ->orderBy('created_at', 'desc');

                $recentPengajuans = $pengajuanQuery->take(10)->get();
                $pendingPengajuanCount = KasKecilPengajuan::whereIn('unit_kas_id', $myUnitIds)
                    ->where('status', 'pending_keuangan')
                    ->count();

                return response()->json([
                    'status' => 'success',
                    'message' => 'Ringkasan kas kecil unit penanggung jawab berhasil dimuat',
                    'data' => [
                        'filter' => [
                            'start_date' => $startDate,
                            'end_date' => $endDate,
                            'has_filter' => $hasDateFilter,
                        ],
                        'is_petugas_kas_kecil' => true,
                        'metrics' => [
                            'saldo_saat_ini' => $totalSaldoSaatIni,
                            'saldo_awal' => $totalSaldoAwal,
                            'total_pengeluaran' => $totalPengeluaranKasKecil,
                            'total_transaksi' => (clone $transaksiQuery)->count(),
                            'pengajuan_pending' => $pendingPengajuanCount,
                            'unit_count' => $myUnits->count(),
                        ],
                        'unit_kas' => $myUnits,
                        'recent_transaksis' => $recentTransaksis,
                        'recent_pengajuans' => $recentPengajuans,
                    ]
                ]);
            }

            // 1. Total Penerimaan (Mahasiswa Lunas/Sebagian + Pemasukan Eksternal/Hibah)
            if ($hasDateFilter) {
                $penerimaanMahasiswa = (float) Pembayaran::where('status', '!=', 'reversed')
                    ->whereDate('waktu_bayar', '>=', $startDate)
                    ->whereDate('waktu_bayar', '<=', $endDate)
                    ->sum('jumlah_bayar');

                // Fallback jika pembayaran tercatat langsung pada TagihanMahasiswa
                if ($penerimaanMahasiswa == 0) {
                    $penerimaanMahasiswa = (float) TagihanMahasiswa::whereDate('updated_at', '>=', $startDate)
                        ->whereDate('updated_at', '<=', $endDate)
                        ->whereIn('status', ['lunas', 'sebagian'])
                        ->sum('total_bayar');
                }
            } else {
                $penerimaanMahasiswa = (float) TagihanMahasiswa::sum('total_bayar');
                if ($penerimaanMahasiswa == 0) {
                    $penerimaanMahasiswa = (float) Pembayaran::where('status', '!=', 'reversed')->sum('jumlah_bayar');
                }
            }

            $pemasukanQuery = PemasukanKampus::query();
            if ($hasDateFilter) {
                $pemasukanQuery->whereDate('tanggal_terima', '>=', $startDate)
                    ->whereDate('tanggal_terima', '<=', $endDate);
            }
            $penerimaanEksternal = (float) $pemasukanQuery->sum('nominal');
            $totalPenerimaan = $penerimaanMahasiswa + $penerimaanEksternal;

            // 2. Total Pengeluaran (Pengeluaran Operasional/Vendor + Pencairan Kas Unit)
            $pengeluaranQuery = PengeluaranKampus::query();
            if ($hasDateFilter) {
                $pengeluaranQuery->whereDate('tanggal_transaksi', '>=', $startDate)
                    ->whereDate('tanggal_transaksi', '<=', $endDate);
            }
            $pengeluaranOperasional = (float) $pengeluaranQuery->sum('net_dibayarkan');

            $pengeluaranKasQuery = PengajuanPencairanKas::where('status', 'dicairkan');
            if ($hasDateFilter) {
                $pengeluaranKasQuery->where(function ($q) use ($startDate, $endDate) {
                    $q->whereDate('approved_keuangan_at', '>=', $startDate)
                        ->whereDate('approved_keuangan_at', '<=', $endDate)
                        ->orWhere(function ($sub) use ($startDate, $endDate) {
                            $sub->whereNull('approved_keuangan_at')
                                ->whereDate('created_at', '>=', $startDate)
                                ->whereDate('created_at', '<=', $endDate);
                        });
                });
            }
            $pengeluaranKasUnit = (float) $pengeluaranKasQuery->sum('nominal_disetujui');
            $totalPengeluaran = $pengeluaranOperasional + $pengeluaranKasUnit;

            // 3. Saldo Kas
            $kasUtama = UnitKas::where('is_kabag_kas', true)->first();
            $saldoKasUtama = $kasUtama ? (float) $kasUtama->saldo_saat_ini : 0.0;
            $saldoTotalSemuaKas = (float) UnitKas::sum('saldo_saat_ini');

            // 4. Total Piutang Mahasiswa (Sisa Tagihan Belum Lunas + Dispensasi)
            $totalPiutangAkumulasi = (float) (TagihanMahasiswa::whereIn('status', ['belum_bayar', 'sebagian', 'dispensasi'])
                ->selectRaw('SUM(total_tagihan + total_denda - total_potongan - total_bayar) as sisa')
                ->value('sisa') ?? 0.0);

            $totalPiutangMahasiswa = $hasDateFilter
                ? (float) (TagihanMahasiswa::whereIn('status', ['belum_bayar', 'sebagian', 'dispensasi'])
                    ->whereDate('created_at', '>=', $startDate)
                    ->whereDate('created_at', '<=', $endDate)
                    ->selectRaw('SUM(total_tagihan + total_denda - total_potongan - total_bayar) as sisa')
                    ->value('sisa') ?? 0.0)
                : $totalPiutangAkumulasi;

            // 5. Pajak Terutang (PPh 21, PPh 23, PPN)
            $pajakQuery = PengeluaranKampus::where('jenis_pajak', '!=', 'tanpa_pajak')
                ->where('status_pembayaran', '!=', 'disetor');
            if ($hasDateFilter) {
                $pajakQuery->whereDate('tanggal_transaksi', '>=', $startDate)
                    ->whereDate('tanggal_transaksi', '<=', $endDate);
            }
            $pajakTerutang = (float) $pajakQuery->sum('nominal_pajak');

            // 6. Counts of Pending Approvals
            $tagihanPending = TagihanMahasiswa::where('status', 'belum_bayar')->count();
            $dispensasiPending = DispensasiTagihan::where('status', 'pending')->count();
            $pengajuanKasPending = PengajuanPencairanKas::where('status', 'pending_keuangan')->count();

            // 7. Payment Gateway (Xendit / Active Provider) Live Balance Tracker
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

            // 8. Recent Jurnals with optional date filter
            $recentJurnalsQuery = JurnalUmum::with('details.akun')
                ->when($hasDateFilter, function ($q) use ($startDate, $endDate) {
                    $q->whereDate('tanggal_jurnal', '>=', $startDate)
                        ->whereDate('tanggal_jurnal', '<=', $endDate);
                })
                ->orderBy('tanggal_jurnal', 'desc')
                ->orderBy('id', 'desc');

            $totalJurnalCount = (clone $recentJurnalsQuery)->count();

            $recentJurnals = $recentJurnalsQuery->take(10)->get()->map(function ($jurnal) {
                return [
                    'id' => $jurnal->id,
                    'nomor_jurnal' => $jurnal->nomor_jurnal,
                    'tanggal_jurnal' => $jurnal->tanggal_jurnal ? (is_string($jurnal->tanggal_jurnal) ? $jurnal->tanggal_jurnal : $jurnal->tanggal_jurnal->format('Y-m-d')) : null,
                    'keterangan' => $jurnal->keterangan,
                    'total_debet' => (float) $jurnal->total_debet,
                    'total_kredit' => (float) $jurnal->total_kredit,
                    'status' => $jurnal->status_posting ?? 'posted',
                    'status_posting' => $jurnal->status_posting ?? 'posted',
                    'jenis_sumber' => $jurnal->jenis_sumber,
                    'details' => $jurnal->details,
                ];
            });

            // 9. Unit Kas List
            $unitKasList = UnitKas::where('status', true)->get();

            return response()->json([
                'status' => 'success',
                'message' => 'Ringkasan finansial SIKEU berhasil dimuat',
                'data' => [
                    'filter' => [
                        'start_date' => $startDate,
                        'end_date' => $endDate,
                        'has_filter' => $hasDateFilter,
                    ],
                    'metrics' => [
                        'total_penerimaan' => $totalPenerimaan,
                        'penerimaan_mahasiswa' => $penerimaanMahasiswa,
                        'penerimaan_eksternal' => $penerimaanEksternal,
                        'total_pengeluaran' => $totalPengeluaran,
                        'pengeluaran_operasional' => $pengeluaranOperasional,
                        'pengeluaran_kas_unit' => $pengeluaranKasUnit,
                        'total_piutang_mahasiswa' => $totalPiutangMahasiswa,
                        'total_piutang_akumulasi' => $totalPiutangAkumulasi,
                        'saldo_kas_utama' => $saldoKasUtama,
                        'saldo_total_kas' => $saldoTotalSemuaKas,
                        'pajak_terutang' => $pajakTerutang,
                        'tagihan_pending_approval' => $tagihanPending,
                        'dispensasi_pending' => $dispensasiPending,
                        'pengajuan_kas_pending' => $pengajuanKasPending,
                        'total_pending_approval' => $dispensasiPending + $pengajuanKasPending,
                        'total_transaksi_jurnal' => $totalJurnalCount,
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
