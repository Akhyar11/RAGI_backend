<?php

namespace App\Http\Controllers\Sikeu;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sikeu\H2hBankRequest;
use App\Models\Sikeu\DetailTagihan;
use App\Models\Sikeu\H2hInquiry;
use App\Models\Sikeu\H2hReversal;
use App\Models\Sikeu\PaymentGatewayConfig;
use App\Models\Sikeu\Pembayaran;
use App\Models\Sikeu\TagihanMahasiswa;
use App\Models\Sikeu\UnitKas;
use App\Services\AuditLogService;
use App\Services\Sikeu\AutoJournalService;
use App\Services\Sikeu\JurnalSikeuService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Controller Native H2H Bank (BTN Syariah) terpusat di RAG.
 *
 * Menggantikan microservice Go (indonusa_h2h_v2) dan database sikeudb secara penuh.
 * Menangani 3 aksi perbankan:
 * 1. inquiry  : Pengecekan data tagihan oleh ATM / m-banking BTN Syariah.
 * 2. payment  : Eksekusi pembayaran realtime, update tagihan, kas bank, & jurnal otomatis.
 * 3. reversal : Pembatalan transaksi jika terjadi kegagalan di core banking.
 */
class H2hBankController extends Controller
{
    /**
     * Endpoint Ping / Health Check untuk Bank
     */
    public function ping(): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'message' => 'Berhasil Akses POLINUS-API H2H Native RAG Via BTNS',
            'data' => [
                'rc' => 'OK',
                'msg' => 'Berhasil Akses POLINUS-API H2H Native RAG Via BTNS',
                'system' => 'RAG Unified Core SIKEU',
                'timestamp' => now()->toIso8601String(),
            ],
            'rc' => 'OK',
            'msg' => 'Berhasil Akses POLINUS-API H2H Native RAG Via BTNS',
            'system' => 'RAG Unified Core SIKEU',
            'timestamp' => now()->toIso8601String(),
        ], 200);
    }

    /**
     * Router Utama Request Bank BTN Syariah (POST /api/v1/h2h/btns dan /btns)
     */
    public function handleBtns(H2hBankRequest $request): JsonResponse
    {
        $action = strtolower((string) $request->input('action'));

        Log::info("H2H BTNS Request [{$action}]", [
            'payload' => $request->except(['password']),
        ]);

        return match ($action) {
            'inquiry'  => $this->handleInquiry($request),
            'payment'  => $this->handlePayment($request),
            'reversal' => $this->handleReversal($request),
            default    => response()->json([
                'status'  => 'error',
                'message' => 'Salah format message - action tidak dikenal',
                'rc'      => '30',
                'msg'     => 'Salah format message - action tidak dikenal',
            ], 400),
        };
    }

    /**
     * ==========================================
     * 1. INQUIRY HANDLER
     * ==========================================
     */
    protected function handleInquiry(Request $request): JsonResponse
    {
        $kodeBank = (string) $request->input('kodeBank');
        if ($kodeBank !== 'BTNS') {
            return response()->json([
                'status'  => 'error',
                'message' => 'Collecting Agent (Bank) tidak dikenal',
                'rc'      => '31',
                'msg'     => 'Collecting Agent (Bank) tidak dikenal',
            ], 400);
        }

        $nomorPembayaran = trim((string) $request->input('nomorPembayaran'));
        $tanggalTransaksi = (string) $request->input('tanggalTransaksi');
        $idTransaksi = (string) $request->input('idTransaksi');
        $kodeBiller = (string) ($request->input('kodeBiller') ?? config('services.sikeu.h2h.default_biller', '31310'));
        $kodeChannel = (string) ($request->input('kodeChannel') ?? config('services.sikeu.h2h.default_channel', 'MBANK'));
        $kodeTerminal = (string) ($request->input('kodeTerminal') ?? config('services.sikeu.h2h.default_terminal', 'TERM_BTNS'));
        $checksum = (string) $request->input('checksum');

        if (empty($nomorPembayaran) || empty($tanggalTransaksi) || empty($idTransaksi) || empty($checksum)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Salah format message - field wajib belum lengkap',
                'rc'      => '30',
                'msg'     => 'Salah format message - field wajib belum lengkap',
            ], 400);
        }

        // Validasi Checksum: SHA1(nomorPembayaran + SECRET_KEY_BTNS + tanggalTransaksi)
        $secretKey = $this->resolveSecretKey();
        $expectedChecksum = sha1($nomorPembayaran . $secretKey . $tanggalTransaksi);

        if (!hash_equals($expectedChecksum, $checksum)) {
            Log::warning("H2H BTNS Checksum Invalid on Inquiry", [
                'expected' => $expectedChecksum,
                'received' => $checksum,
            ]);
            return response()->json([
                'status'  => 'error',
                'message' => 'Checksum invalid',
                'rc'      => '30',
                'msg'     => 'Checksum invalid',
            ], 400);
        }

        // Cari tagihan aktif mahasiswa
        $tagihan = $this->resolveActiveTagihan($nomorPembayaran);

        if (!$tagihan) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Nomor Pembayaran yg dimasukan belum ada invoice',
                'rc'      => '01',
                'msg'     => 'Nomor Pembayaran yg dimasukan belum ada invoice',
            ], 404);
        }

        $sisaTagihan = max(0, (float) ($tagihan->total_tagihan + $tagihan->total_denda - $tagihan->total_potongan) - (float) $tagihan->total_bayar);
        if ($sisaTagihan <= 0) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Tagihan tidak dikenal / invoice sudah terbayar',
                'rc'      => '14',
                'msg'     => 'Tagihan tidak dikenal / invoice sudah terbayar',
            ], 400);
        }

        // Generate / Sinkronkan idTagihan (format: YYYYMMDD + urut 2 digit)
        $idTagihan = $tagihan->h2h_id_tagihan ?: (date('Ymd') . str_pad((string) (($tagihan->id % 90) + 1), 2, '0', STR_PAD_LEFT));
        $idBilling = $nomorPembayaran . $idTagihan;

        $tagihan->updateQuietly([
            'h2h_custid' => $nomorPembayaran,
            'h2h_id_tagihan' => $idTagihan,
            'h2h_billing_id' => $idBilling,
        ]);

        // Catat Audit Inquiry ke sikeu_h2h_inquiries
        H2hInquiry::updateOrCreate(
            ['id_transaksi' => $idTransaksi],
            [
                'tanggal_transaksi' => $tanggalTransaksi,
                'id_tagihan' => $idTagihan,
                'nomor_pembayaran' => $nomorPembayaran,
                'kode_bank' => $kodeBank,
                'kode_biller' => $kodeBiller,
                'kode_channel' => $kodeChannel,
                'kode_terminal' => $kodeTerminal,
                'checksum' => $checksum,
            ]
        );

        // Identitas Mahasiswa / Pendaftar
        $identitas = $this->resolveIdentitasMahasiswa($tagihan);

        // Rincian Tagihan
        $rincian = [];
        $tagihan->loadMissing('details.masterBiaya');
        if ($tagihan->details && $tagihan->details->isNotEmpty()) {
            foreach ($tagihan->details as $d) {
                $subTotal = (float) $d->nominal - (float) ($d->potongan ?? 0);
                if ($subTotal > 0) {
                    $rincian[] = [
                        'kode_rincian' => $d->masterBiaya?->nama ?: ($d->masterBiaya?->nama_biaya ?: ($d->keterangan ?: 'Biaya Pendidikan')),
                        'nominal' => (string) (int) $subTotal,
                    ];
                }
            }
        }

        if (empty($rincian)) {
            $rincian[] = [
                'kode_rincian' => 'Tagihan ' . $tagihan->nomor_tagihan,
                'nominal' => (string) (int) $sisaTagihan,
            ];
        }

        $dataPayload = [
            'id' => $idBilling,
            'nomorPembayaran' => $nomorPembayaran,
            'idPelanggan' => $nomorPembayaran,
            'nama' => $identitas['nama'],
            'idTagihan' => $idTagihan,
            'totalNominal' => (string) (int) $sisaTagihan,
            'apiBank' => 'BTNS',
            'createdAt' => now()->format('Y-m-d H:i:s'),
            'tanggalTransaksi' => $tanggalTransaksi,
            'rc' => '00',
            'msg' => 'Inquiry Success',
            'informasi' => [
                ['label_key' => 'NIM', 'label_value' => $identitas['nim']],
                ['label_key' => 'Program Studi', 'label_value' => $identitas['prodi']],
            ],
            'rincian' => $rincian,
        ];

        return response()->json([
            'status' => 'success',
            'message' => 'Inquiry Success',
            'data' => $dataPayload,
            // Flat response root untuk kompatibilitas parser antarmuka core banking eksternal
            'id' => $idBilling,
            'nomorPembayaran' => $nomorPembayaran,
            'idPelanggan' => $nomorPembayaran,
            'nama' => $identitas['nama'],
            'idTagihan' => $idTagihan,
            'totalNominal' => (string) (int) $sisaTagihan,
            'apiBank' => 'BTNS',
            'createdAt' => now()->format('Y-m-d H:i:s'),
            'tanggalTransaksi' => $tanggalTransaksi,
            'rc' => '00',
            'msg' => 'Inquiry Success',
            'informasi' => [
                ['label_key' => 'NIM', 'label_value' => $identitas['nim']],
                ['label_key' => 'Program Studi', 'label_value' => $identitas['prodi']],
            ],
            'rincian' => $rincian,
        ], 200);
    }

    /**
     * ==========================================
     * 2. PAYMENT HANDLER
     * ==========================================
     */
    protected function handlePayment(Request $request): JsonResponse
    {
        $kodeBank = (string) $request->input('kodeBank');
        if ($kodeBank !== 'BTNS') {
            return response()->json([
                'status'  => 'error',
                'message' => 'Collecting Agent (Bank) tidak dikenal',
                'rc'      => '31',
                'msg'     => 'Collecting Agent (Bank) tidak dikenal',
            ], 400);
        }

        $nomorPembayaran = trim((string) $request->input('nomorPembayaran'));
        $tanggalTransaksi = (string) $request->input('tanggalTransaksi');
        $idTransaksi = (string) $request->input('idTransaksi');
        $idTagihan = (string) $request->input('idTagihan');
        $totalNominal = (string) $request->input('totalNominal');
        $nomorJurnalPembukuan = (string) $request->input('nomorJurnalPembukuan');
        $kodeTerminal = (string) ($request->input('kodeTerminal') ?? config('services.sikeu.h2h.default_terminal', 'TERM_BTNS'));
        $checksum = (string) $request->input('checksum');

        if (empty($nomorPembayaran) || empty($tanggalTransaksi) || empty($totalNominal) || empty($nomorJurnalPembukuan) || empty($checksum)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Salah format message',
                'rc'      => '30',
                'msg'     => 'Salah format message',
            ], 400);
        }

        // Validasi Checksum: SHA1(nomorPembayaran + SECRET_KEY_BTNS + tanggalTransaksi + totalNominal + nomorJurnalPembukuan)
        $secretKey = $this->resolveSecretKey();
        $expectedChecksum = sha1($nomorPembayaran . $secretKey . $tanggalTransaksi . $totalNominal . $nomorJurnalPembukuan);

        if (!hash_equals($expectedChecksum, $checksum)) {
            Log::warning("H2H BTNS Checksum Invalid on Payment", [
                'expected' => $expectedChecksum,
                'received' => $checksum,
            ]);
            return response()->json([
                'status'  => 'error',
                'message' => 'Checksum invalid',
                'rc'      => '30',
                'msg'     => 'Checksum invalid',
            ], 400);
        }

        // Idempotensi: Jika jurnal/transaksi ini sudah pernah diproses sukses, langsung return OK
        $existingPembayaran = Pembayaran::where('kode_transaksi', $nomorJurnalPembukuan)->first();
        if ($existingPembayaran) {
            $tagihan = $existingPembayaran->tagihan;
            $identitas = $this->resolveIdentitasMahasiswa($tagihan);

            $dataPayload = [
                'id' => $nomorPembayaran . $idTagihan,
                'nomorPembayaran' => $nomorPembayaran,
                'idPelanggan' => $nomorPembayaran,
                'nama' => $identitas['nama'],
                'idTagihan' => $idTagihan,
                'totalNominal' => (string) (int) $existingPembayaran->jumlah_bayar,
                'nomorJurnalPembukuan' => $nomorJurnalPembukuan,
                'apiBank' => 'BTNS',
                'rc' => '00',
                'msg' => 'Payment Success (Already Processed)',
                'informasi' => [
                    ['label_key' => 'NIM', 'label_value' => $identitas['nim']],
                    ['label_key' => 'Program Studi', 'label_value' => $identitas['prodi']],
                ],
                'rincian' => [
                    ['kode_rincian' => 'Pelunasan Tagihan', 'nominal' => (string) (int) $existingPembayaran->jumlah_bayar],
                ],
            ];

            return response()->json([
                'status' => 'success',
                'message' => 'Payment Success (Already Processed)',
                'data' => $dataPayload,
                'id' => $nomorPembayaran . $idTagihan,
                'nomorPembayaran' => $nomorPembayaran,
                'idPelanggan' => $nomorPembayaran,
                'nama' => $identitas['nama'],
                'idTagihan' => $idTagihan,
                'totalNominal' => (string) (int) $existingPembayaran->jumlah_bayar,
                'nomorJurnalPembukuan' => $nomorJurnalPembukuan,
                'apiBank' => 'BTNS',
                'rc' => '00',
                'msg' => 'Payment Success (Already Processed)',
                'informasi' => [
                    ['label_key' => 'NIM', 'label_value' => $identitas['nim']],
                    ['label_key' => 'Program Studi', 'label_value' => $identitas['prodi']],
                ],
                'rincian' => [
                    ['kode_rincian' => 'Pelunasan Tagihan', 'nominal' => (string) (int) $existingPembayaran->jumlah_bayar],
                ],
            ], 200);
        }

        // Cari tagihan yang akan dibayar
        $tagihan = TagihanMahasiswa::where('h2h_id_tagihan', $idTagihan)
            ->orWhere('h2h_custid', $nomorPembayaran)
            ->orWhere('h2h_billing_id', $nomorPembayaran . $idTagihan)
            ->where('status', '!=', 'lunas')
            ->first();

        if (!$tagihan) {
            // Fallback mencari tagihan aktif
            $tagihan = $this->resolveActiveTagihan($nomorPembayaran);
        }

        if (!$tagihan) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Tagihan tidak dikenal / invoice sudah terbayar',
                'rc'      => '14',
                'msg'     => 'Tagihan tidak dikenal / invoice sudah terbayar',
            ], 404);
        }

        $totalBersih = (float) ($tagihan->total_tagihan + $tagihan->total_denda - $tagihan->total_potongan);
        $sisaTagihan = max(0, $totalBersih - (float) $tagihan->total_bayar);

        $nominalBayar = (float) $totalNominal;
        if ($nominalBayar <= 0) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Nominal pembayaran salah',
                'rc'      => '13',
                'msg'     => 'Nominal pembayaran salah',
            ], 400);
        }

        // Multi-write dibungkus Transaction aman
        try {
            DB::beginTransaction();

            // Lock row tagihan untuk mencegah race condition
            $tagihanLocked = TagihanMahasiswa::where('id', $tagihan->id)->lockForUpdate()->first();
            $oldTagihan = $tagihanLocked->getOriginal();

            // Tentukan Unit Kas penerima (Bank BSN H2H)
            $unitKas = JurnalSikeuService::resolveUnitKasUntukChannel('VA_BSN', 'BSN')
                ?: UnitKas::where('bank_name', 'BSN')->orWhere('kanal', 'bank_h2h')->first();

            // 1. Buat record di sikeu_pembayaran
            $pembayaran = Pembayaran::create([
                'tagihan_id' => $tagihanLocked->id,
                'unit_kas_id' => $unitKas?->id,
                'kode_transaksi' => $nomorJurnalPembukuan,
                'jumlah_bayar' => $nominalBayar,
                'waktu_bayar' => $tanggalTransaksi,
                'channel_bayar' => 'VA_BSN',
                'bank_pengirim' => 'BSN',
                'status' => 'success',
                'diverifikasi_oleh' => auth()->id() ?: null,
                'catatan' => "Pelunasan Native H2H BTN Syariah (Jurnal: {$nomorJurnalPembukuan}, Terminal: {$kodeTerminal})",
            ]);

            // 2. Tambah saldo di Unit Kas Bank BTN Syariah
            if ($unitKas) {
                $unitKas->increment('saldo_saat_ini', $nominalBayar);
            }

            // 3. Update status tagihan mahasiswa
            $newTotalBayar = (float) $tagihanLocked->total_bayar + $nominalBayar;
            $newStatus = $newTotalBayar >= $totalBersih ? 'lunas' : ($newTotalBayar > 0 ? 'sebagian' : 'belum_bayar');

            $tagihanLocked->update([
                'total_bayar' => $newTotalBayar,
                'status' => $newStatus,
            ]);

            // 4. Alokasikan ke detail tagihan
            DetailTagihan::alokasikan($tagihanLocked, $nominalBayar);

            // 5. Update audit H2H inquiry status
            H2hInquiry::where('nomor_pembayaran', $nomorPembayaran)
                ->whereNull('paid_at')
                ->latest()
                ->first()
                ?->update(['paid_at' => $tanggalTransaksi]);

            // 6. Terbitkan Jurnal Akuntansi Otomatis: Dr Kas Bank BSN / Cr Piutang Mahasiswa
            AutoJournalService::recordStudentPaymentJournal($tagihanLocked, $nominalBayar, $unitKas);

            DB::commit();

            // 7. Catat Audit Trail
            try {
                AuditLogService::record(
                    module: 'SIKEU',
                    action: 'h2h_payment',
                    tableName: 'sikeu_pembayaran',
                    recordId: $pembayaran->id,
                    oldValues: null,
                    newValues: $pembayaran->toArray(),
                    request: $request,
                );

                AuditLogService::record(
                    module: 'SIKEU',
                    action: 'h2h_payment_tagihan',
                    tableName: 'sikeu_tagihan_mahasiswa',
                    recordId: $tagihanLocked->id,
                    oldValues: $oldTagihan,
                    newValues: $tagihanLocked->getChanges(),
                    request: $request,
                );
            } catch (\Throwable $e) {
                Log::warning('Gagal mencatat audit log pembayaran H2H: ' . $e->getMessage());
            }

            $identitas = $this->resolveIdentitasMahasiswa($tagihanLocked);

            $dataPayload = [
                'id' => $nomorPembayaran . $idTagihan,
                'nomorPembayaran' => $nomorPembayaran,
                'idPelanggan' => $nomorPembayaran,
                'nama' => $identitas['nama'],
                'idTagihan' => $idTagihan,
                'totalNominal' => (string) (int) $nominalBayar,
                'nomorJurnalPembukuan' => $nomorJurnalPembukuan,
                'apiBank' => 'BTNS',
                'rc' => '00',
                'msg' => 'Payment Success',
                'informasi' => [
                    ['label_key' => 'NIM', 'label_value' => $identitas['nim']],
                    ['label_key' => 'Program Studi', 'label_value' => $identitas['prodi']],
                ],
                'rincian' => [
                    ['kode_rincian' => 'Pelunasan Biaya Kuliah', 'nominal' => (string) (int) $nominalBayar],
                ],
            ];

            return response()->json([
                'status' => 'success',
                'message' => 'Payment Success',
                'data' => $dataPayload,
                // Flat response root untuk kompatibilitas parser antarmuka core banking eksternal
                'id' => $nomorPembayaran . $idTagihan,
                'nomorPembayaran' => $nomorPembayaran,
                'idPelanggan' => $nomorPembayaran,
                'nama' => $identitas['nama'],
                'idTagihan' => $idTagihan,
                'totalNominal' => (string) (int) $nominalBayar,
                'nomorJurnalPembukuan' => $nomorJurnalPembukuan,
                'apiBank' => 'BTNS',
                'rc' => '00',
                'msg' => 'Payment Success',
                'informasi' => [
                    ['label_key' => 'NIM', 'label_value' => $identitas['nim']],
                    ['label_key' => 'Program Studi', 'label_value' => $identitas['prodi']],
                ],
                'rincian' => [
                    ['kode_rincian' => 'Pelunasan Biaya Kuliah', 'nominal' => (string) (int) $nominalBayar],
                ],
            ], 200);

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('H2H BTNS Payment Failed: ' . $e->getMessage(), ['exception' => $e]);

            return response()->json([
                'status'  => 'error',
                'message' => 'Database problem - gagal memproses transaksi pembayaran',
                'rc'      => '91',
                'msg'     => 'Database problem - gagal memproses transaksi pembayaran',
            ], 500);
        }
    }

    /**
     * ==========================================
     * 3. REVERSAL HANDLER
     * ==========================================
     */
    protected function handleReversal(Request $request): JsonResponse
    {
        $kodeBank = (string) $request->input('kodeBank');
        if ($kodeBank !== 'BTNS') {
            return response()->json([
                'status'  => 'error',
                'message' => 'Collecting Agent (Bank) tidak dikenal',
                'rc'      => '31',
                'msg'     => 'Collecting Agent (Bank) tidak dikenal',
            ], 400);
        }

        $nomorPembayaran = trim((string) $request->input('nomorPembayaran'));
        $tanggalTransaksi = (string) $request->input('tanggalTransaksi');
        $tanggalTransaksiAsal = (string) $request->input('tanggalTransaksiAsal');
        $idTransaksi = (string) $request->input('idTransaksi');
        $totalNominal = (string) $request->input('totalNominal');
        $nomorJurnalPembukuan = (string) $request->input('nomorJurnalPembukuan');
        $checksum = (string) $request->input('checksum');

        // Validasi Checksum Reversal
        $secretKey = $this->resolveSecretKey();
        $expectedChecksum = sha1($nomorPembayaran . $secretKey . $tanggalTransaksi . $totalNominal . $nomorJurnalPembukuan);

        if (!hash_equals($expectedChecksum, $checksum)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Checksum invalid',
                'rc'      => '30',
                'msg'     => 'Checksum invalid',
            ], 400);
        }

        // Cek Reversal sebelumnya
        $sudahReversal = H2hReversal::where('nomor_jurnal_pembukuan', $nomorJurnalPembukuan)->first();
        if ($sudahReversal) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Telah berhasil melakukan reversal sebelumnya',
                'rc'      => '94',
                'msg'     => 'Telah berhasil melakukan reversal sebelumnya',
            ], 400);
        }

        // Cari data pembayaran yang akan dibatalkan
        $pembayaran = Pembayaran::where('kode_transaksi', $nomorJurnalPembukuan)->first();
        if (!$pembayaran) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Transaksi pembatalan ditolak, tidak ditemukan data pembayaran',
                'rc'      => '12',
                'msg'     => 'Transaksi pembatalan ditolak, tidak ditemukan data pembayaran',
            ], 404);
        }

        try {
            DB::beginTransaction();

            $nominal = (float) $pembayaran->jumlah_bayar;
            $tagihan = $pembayaran->tagihan;
            $unitKas = $pembayaran->unitKas;

            // 1. Kurangi kembali saldo kas
            if ($unitKas && $nominal > 0) {
                $unitKas->decrement('saldo_saat_ini', $nominal);
            }

            // 2. Kembalikan status tagihan
            $oldTagihan = $tagihan ? $tagihan->getOriginal() : null;
            if ($tagihan) {
                $newTotalBayar = max(0, (float) $tagihan->total_bayar - $nominal);
                $totalBersih = (float) ($tagihan->total_tagihan + $tagihan->total_denda - $tagihan->total_potongan);
                $newStatus = $newTotalBayar >= $totalBersih ? 'lunas' : ($newTotalBayar > 0 ? 'sebagian' : 'belum_bayar');

                $tagihan->update([
                    'total_bayar' => $newTotalBayar,
                    'status' => $newStatus,
                ]);
            }

            // 3. Update status pembayaran menjadi dibatalkan / reversed
            $oldPembayaran = $pembayaran->getOriginal();
            $pembayaran->update([
                'status' => 'cancelled',
                'catatan' => $pembayaran->catatan . " [REVERSAL OLEH BANK: {$tanggalTransaksi}]",
            ]);
            $newPembayaran = $pembayaran->getChanges();

            // 4. Catat riwayat Reversal
            H2hReversal::create([
                'id_transaksi' => $idTransaksi,
                'tanggal_transaksi' => $tanggalTransaksi,
                'tanggal_transaksi_asal' => $tanggalTransaksiAsal,
                'nomor_pembayaran' => $nomorPembayaran,
                'nomor_jurnal_pembukuan' => $nomorJurnalPembukuan,
                'total_nominal' => (float) $totalNominal,
                'kode_bank' => $kodeBank,
                'checksum' => $checksum,
            ]);

            DB::commit();

            try {
                AuditLogService::record(
                    module: 'SIKEU',
                    action: 'h2h_reversal',
                    tableName: 'sikeu_pembayaran',
                    recordId: $pembayaran->id,
                    oldValues: $oldPembayaran,
                    newValues: $newPembayaran,
                    request: $request,
                );

                if ($tagihan) {
                    AuditLogService::record(
                        module: 'SIKEU',
                        action: 'h2h_reversal_tagihan',
                        tableName: 'sikeu_tagihan_mahasiswa',
                        recordId: $tagihan->id,
                        oldValues: $oldTagihan,
                        newValues: $tagihan->getChanges(),
                        request: $request,
                    );
                }
            } catch (\Throwable $e) {
                Log::warning('Gagal mencatat audit log reversal H2H: ' . $e->getMessage());
            }

            $dataPayload = [
                'rc' => '00',
                'msg' => 'Reversal Success',
                'nomorJurnalPembukuan' => $nomorJurnalPembukuan,
                'nomorPembayaran' => $nomorPembayaran,
            ];

            return response()->json([
                'status'  => 'success',
                'message' => 'Reversal Success',
                'data'    => $dataPayload,
                'rc'      => '00',
                'msg'     => 'Reversal Success',
                'nomorJurnalPembukuan' => $nomorJurnalPembukuan,
            ], 200);

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('H2H BTNS Reversal Failed: ' . $e->getMessage());

            return response()->json([
                'status'  => 'error',
                'message' => 'Database problem - gagal memproses reversal',
                'rc'      => '91',
                'msg'     => 'Database problem - gagal memproses reversal',
            ], 500);
        }
    }

    /**
     * ==========================================
     * HELPER RESOLVERS
     * ==========================================
     */

    protected function resolveSecretKey(): string
    {
        try {
            $config = PaymentGatewayConfig::where('gateway_name', 'bsn_h2h')->first();
            if ($config && !empty($config->webhook_token_encrypted)) {
                return (string) $config->webhook_token_encrypted;
            }
        } catch (\Throwable $e) {
            // ignore
        }

        $secret = config('services.sikeu.btns_secret_key');
        if (!empty($secret)) {
            return (string) $secret;
        }

        if (app()->environment(['local', 'testing'])) {
            return 'test-btns-secret-key';
        }

        throw new \RuntimeException('Secret key BTN Syariah belum dikonfigurasi pada environment atau database.');
    }

    protected function resolveActiveTagihan(string $nomorPembayaran): ?TagihanMahasiswa
    {
        // 1. Coba match langsung via h2h_custid atau h2h_billing_id
        $tagihan = TagihanMahasiswa::where(function ($q) use ($nomorPembayaran) {
            $q->where('h2h_custid', $nomorPembayaran)
              ->orWhere('h2h_billing_id', 'like', "{$nomorPembayaran}%");
        })->where('status', '!=', 'lunas')->orderBy('id', 'desc')->first();

        if ($tagihan) {
            return $tagihan;
        }

        // 2. Bersihkan prefix dinamis berdasarkan konfigurasi sistem
        $prefixes = array_merge(
            (array) config('services.sikeu.h2h.prefixes.mahasiswa', ['13']),
            (array) config('services.sikeu.h2h.prefixes.calon_mhs', ['01'])
        );

        $cleanId = $nomorPembayaran;
        foreach ($prefixes as $p) {
            if (str_starts_with($nomorPembayaran, (string) $p)) {
                $cleanId = substr($nomorPembayaran, strlen((string) $p));
                break;
            }
        }

        // 3. Cari berdasarkan Mahasiswa Aktif (NIM)
        $mhs = DB::table('siakad_mahasiswa')
            ->where(function ($q) use ($cleanId, $nomorPembayaran) {
                $q->where('nim', $cleanId)
                  ->orWhere('nim', "'{$cleanId}'")
                  ->orWhere('nim', $nomorPembayaran)
                  ->orWhere('nim', "'{$nomorPembayaran}'");
            })->first();

        if ($mhs) {
            $tagihanMhs = TagihanMahasiswa::where('mahasiswa_id', $mhs->id)
                ->where('status', '!=', 'lunas')
                ->orderBy('created_at', 'desc')
                ->first();

            if ($tagihanMhs) {
                return $tagihanMhs;
            }
        }

        // 4. Cari berdasarkan Calon Mahasiswa (No Pendaftaran)
        $calon = DB::table('spmb_pendaftaran_calon_mhs')
            ->where(function ($q) use ($cleanId, $nomorPembayaran) {
                $q->where('no_pendaftaran', $cleanId)
                  ->orWhere('no_pendaftaran', $nomorPembayaran);
            })->first();

        if ($calon) {
            $tagihanCalon = TagihanMahasiswa::where('calon_mahasiswa_id', $calon->id)
                ->where('status', '!=', 'lunas')
                ->orderBy('created_at', 'desc')
                ->first();

            if ($tagihanCalon) {
                return $tagihanCalon;
            }
        }

        return null;
    }

    protected function resolveIdentitasMahasiswa(TagihanMahasiswa $tagihan): array
    {
        $tagihan->loadMissing(['calonMahasiswa', 'mahasiswa.programStudi', 'tipeTagihanMahasiswa']);

        if ($tagihan->calon_mahasiswa_id && $tagihan->calonMahasiswa) {
            return [
                'nama' => $tagihan->calonMahasiswa->nama_lengkap,
                'nim' => $tagihan->calonMahasiswa->no_pendaftaran,
                'prodi' => 'PMB - Calon Mahasiswa',
            ];
        }

        $nama = $tagihan->mahasiswa?->nama_lengkap
            ?? $tagihan->tipeTagihanMahasiswa?->nama_mahasiswa
            ?? ('Mahasiswa #' . $tagihan->mahasiswa_id);

        $nim = trim((string) ($tagihan->mahasiswa?->nim ?? $tagihan->tipeTagihanMahasiswa?->nim ?? ''), "'\" ");
        $prodi = $tagihan->mahasiswa?->programStudi?->nama ?? 'Politeknik Indonusa Surakarta';

        return [
            'nama' => $nama,
            'nim' => $nim,
            'prodi' => $prodi,
        ];
    }
}
