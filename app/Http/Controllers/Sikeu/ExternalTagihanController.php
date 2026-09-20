<?php

namespace App\Http\Controllers\Sikeu;

use App\Http\Controllers\Controller;
use App\Models\Sikeu\TagihanMahasiswa;
use App\Models\Sikeu\DetailTagihan;
use App\Models\Sikeu\PotonganTagihan;
use App\Models\Sikeu\MasterBiaya;
use App\Models\Sikeu\VirtualAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ExternalTagihanController extends Controller
{
    /**
     * POST /api/v1/sikeu/tagihan/external
     * Generate bill from external systems (SPMB, SIAKAD, SIMPEG, SIPPM).
     */
    public function createExternalBill(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'mahasiswa_id' => 'nullable|integer|required_without:calon_mahasiswa_id',
            'calon_mahasiswa_id' => 'nullable|integer|required_without:mahasiswa_id',
            'tipe_referensi' => 'nullable|string|max:30',
            'tahun_akademik_id' => 'nullable|integer',
            'source_system' => 'required|string|max:50',
            'requires_approval' => 'nullable|boolean',
            'jatuh_tempo' => 'nullable|date',
            'keterangan' => 'nullable|string',
            'details' => 'required|array|min:1',
            'details.*.master_biaya_kode' => 'required|string',
            'details.*.nominal' => 'required|numeric|min:0',
            'details.*.keterangan' => 'nullable|string',
            'potongan' => 'nullable|array',
            'potongan.*.tipe' => 'nullable|string',
            'potongan.*.nominal_potongan' => 'required_with:potongan|numeric|min:0',
            'potongan.*.keterangan' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validasi pembuatan tagihan eksternal gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $sourceSystem = strtoupper($request->source_system);
            $requiresApproval = $request->boolean('requires_approval', false);
            $nomorTagihan = 'INV-' . $sourceSystem . '-' . date('Ymd') . '-' . Str::random(5);

            $totalNominal = 0;
            $totalPotongan = 0;

            // Compute details
            $detailsData = [];
            foreach ($request->details as $item) {
                $masterBiaya = MasterBiaya::where('kode', $item['master_biaya_kode'])->first();
                $masterBiayaId = $masterBiaya ? $masterBiaya->id : 1;
                $nominal = (float) $item['nominal'];
                $totalNominal += $nominal;

                $detailsData[] = [
                    'master_biaya_id' => $masterBiayaId,
                    'nominal' => $nominal,
                    'potongan' => 0,
                    'nominal_bersih' => $nominal,
                    'keterangan' => $item['keterangan'] ?? 'Komponen tagihan ' . $item['master_biaya_kode'],
                ];
            }

            // Compute deductions
            $potonganData = [];
            if ($request->has('potongan') && is_array($request->potongan)) {
                foreach ($request->potongan as $pot) {
                    $nomPot = (float) $pot['nominal_potongan'];
                    $totalPotongan += $nomPot;
                    $potonganData[] = [
                        'tipe' => $pot['tipe'] ?? 'diskon',
                        'nominal_potongan' => $nomPot,
                        'keterangan' => $pot['keterangan'] ?? 'Potongan khusus eksternal',
                        'diinput_oleh' => auth()->id() ?? 1,
                    ];
                }
            }

            $totalBayar = max(0, $totalNominal - $totalPotongan);
            $initialStatus = $requiresApproval ? 'pending_approval' : 'belum_bayar';
            $statusApproval = $requiresApproval ? 'pending' : 'approved';
            $tipeReferensi = $request->input('tipe_referensi', $request->filled('calon_mahasiswa_id') ? 'calon_mahasiswa' : 'mahasiswa');

            $tagihan = TagihanMahasiswa::create([
                'mahasiswa_id' => $request->mahasiswa_id,
                'calon_mahasiswa_id' => $request->calon_mahasiswa_id,
                'tipe_referensi' => $tipeReferensi,
                'tahun_akademik_id' => $request->tahun_akademik_id ?? 1,
                'nomor_tagihan' => strtoupper($nomorTagihan),
                'total_tagihan' => $totalNominal,
                'total_potongan' => $totalPotongan,
                'total_denda' => 0,
                'total_bayar' => 0,
                'status' => $initialStatus,
                'requires_approval' => $requiresApproval,
                'status_approval' => $statusApproval,
                'source_system' => $sourceSystem,
                'catatan_approval' => $request->keterangan,
                'jatuh_tempo' => $request->jatuh_tempo ?? date('Y-m-d', strtotime('+30 days')),
            ]);

            // Save details
            foreach ($detailsData as $detail) {
                $detail['tagihan_id'] = $tagihan->id;
                DetailTagihan::create($detail);
            }

            // Save deductions
            foreach ($potonganData as $pot) {
                $pot['tagihan_id'] = $tagihan->id;
                PotonganTagihan::create($pot);
            }

            // If no approval required, generate VA automatically (Xendit Integration or Local VA)
            $vaData = null;
            if (!$requiresApproval) {
                $bankCode = 'BNI';
                $vaNumber = '888' . date('ymd') . str_pad($tagihan->id, 5, '0', STR_PAD_LEFT);

                // Check active Payment Gateway Config
                $pgConfig = \App\Models\Sikeu\PaymentGatewayConfig::where('is_active', true)->first();
                $apiKey = $pgConfig->api_key_encrypted ?? $pgConfig->public_key_encrypted ?? null;

                if ($pgConfig && $pgConfig->gateway_name === 'xendit' && !empty($apiKey)) {
                    try {
                        $xenditRes = \Illuminate\Support\Facades\Http::withoutVerifying()
                            ->withBasicAuth($apiKey, '')
                            ->post('https://api.xendit.co/callback_virtual_accounts', [
                                'external_id' => $tagihan->nomor_tagihan,
                                'bank_code' => $bankCode,
                                'name' => 'SPMB Calon Mhs #' . ($request->calon_mahasiswa_id ?? $tagihan->id),
                                'expected_amount' => (int) $totalBayar,
                                'is_closed' => true,
                                'is_single_use' => true,
                                'expiration_date' => date('c', strtotime('+30 days')),
                            ]);

                        if ($xenditRes->successful()) {
                            $xData = $xenditRes->json();
                            $vaNumber = $xData['account_number'] ?? $vaNumber;
                            $bankCode = $xData['bank_code'] ?? $bankCode;
                            \Illuminate\Support\Facades\Log::info("Xendit VA Created Successfully: VA {$vaNumber} for Tagihan {$tagihan->nomor_tagihan}");
                        } else {
                            \Illuminate\Support\Facades\Log::error("Xendit VA Creation Error ({$xenditRes->status()}): " . $xenditRes->body());
                        }
                    } catch (\Throwable $e) {
                        \Illuminate\Support\Facades\Log::warning("Xendit VA Creation Exception: " . $e->getMessage());
                    }
                }

                $vaData = VirtualAccount::create([
                    'tagihan_id' => $tagihan->id,
                    'va_number' => $vaNumber,
                    'bank_kode' => $bankCode,
                    'bank_nama' => 'Bank ' . $bankCode,
                    'nominal' => $totalBayar,
                    'expired_at' => date('Y-m-d H:i:s', strtotime('+30 days')),
                    'status' => 'aktif',
                ]);
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => $requiresApproval
                    ? 'Tagihan eksternal berhasil diterbitkan dan masuk ke antrean approval pimpinan.'
                    : 'Tagihan eksternal berhasil diterbitkan dan Virtual Account aktif.',
                'data' => [
                    'tagihan' => $tagihan->load(['detailTagihan', 'potonganTagihan']),
                    'virtual_account' => $vaData,
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal menerbitkan tagihan eksternal: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/v1/sikeu/pembayaran
     * List payment transactions with date range, search, status, & channel filters.
     */
    public function indexPembayaran(Request $request)
    {
        $perPage = min(100, $request->integer('per_page', 15));
        $query = \App\Models\Sikeu\Pembayaran::with([
            'tagihan.details.masterBiaya',
            'tagihan.mahasiswa.programStudi',
            'tagihan.tipeTagihanMahasiswa',
            'tagihan.calonMahasiswa.programStudi',
            'virtualAccount'
        ]);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('channel')) {
            $query->where('channel_bayar', $request->channel);
        }

        if ($request->filled('tgl_mulai')) {
            $query->whereDate('waktu_bayar', '>=', $request->tgl_mulai);
        }

        if ($request->filled('tgl_selesai')) {
            $query->whereDate('waktu_bayar', '<=', $request->tgl_selesai);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('kode_transaksi', 'like', "%{$search}%")
                  ->orWhereHas('tagihan', function ($tq) use ($search) {
                      $tq->where('nomor_tagihan', 'like', "%{$search}%")
                         ->orWhere('mahasiswa_id', 'like', "%{$search}%")
                         ->orWhere('calon_mahasiswa_id', 'like', "%{$search}%")
                         ->orWhereHas('mahasiswa', fn($m) => $m->where('nim', 'like', "%{$search}%")->orWhere('nama_lengkap', 'like', "%{$search}%")->orWhere('nik', 'like', "%{$search}%"))
                         ->orWhereHas('tipeTagihanMahasiswa', fn($tm) => $tm->where('nim', 'like', "%{$search}%")->orWhere('nama_mahasiswa', 'like', "%{$search}%"))
                         ->orWhereHas('calonMahasiswa', fn($cm) => $cm->where('no_pendaftaran', 'like', "%{$search}%")->orWhere('nama_lengkap', 'like', "%{$search}%")->orWhere('nik', 'like', "%{$search}%"));
                  });
            });
        }

        $sortBy = $request->input('sort_by', 'waktu_bayar');
        $sortOrder = strtolower($request->input('sort_order', $request->input('sort_dir', 'desc'))) === 'asc' ? 'asc' : 'desc';
        if (in_array($sortBy, ['waktu_bayar', 'jumlah_bayar', 'kode_transaksi', 'id', 'created_at', 'status'])) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->orderBy('waktu_bayar', 'desc');
        }
        $query->orderBy('id', 'desc');

        $pembayaran = $query->paginate($perPage);

        $mappedItems = collect($pembayaran->items())->map(function ($p) {
            $t = $p->tagihan;
            $mhs = $t?->mahasiswa;
            $tipeMhs = $t?->tipeTagihanMahasiswa;
            $calon = $t?->calonMahasiswa;

            $nim = $mhs?->nim ?? $tipeMhs?->nim ?? $calon?->nim ?? ($calon?->no_pendaftaran ?: ($t?->mahasiswa_id ? (string)$t->mahasiswa_id : '-'));
            $nama = $mhs?->nama_lengkap ?? $tipeMhs?->nama_mahasiswa ?? $calon?->nama_lengkap ?? ('Mahasiswa #' . ($t?->mahasiswa_id ?? $t?->calon_mahasiswa_id ?? '-'));
            $prodi = $mhs?->programStudi?->nama ?? $mhs?->programStudi?->nama_prodi ?? $calon?->programStudi?->nama ?? '-';

            $rincian = $t?->details?->map(function ($d) {
                return $d->keterangan ?: ($d->masterBiaya->nama ?? 'Komponen Biaya');
            })->filter()->implode(', ') ?: ($t?->catatan_approval ?? 'Tagihan Mahasiswa');

            return [
                'id' => $p->id,
                'kode_transaksi' => $p->kode_transaksi,
                'nim' => $nim,
                'no_pendaftaran' => $calon?->no_pendaftaran,
                'is_calon_mahasiswa' => (bool)$calon,
                'nama_mahasiswa' => $nama,
                'program_studi' => $prodi,
                'rincian_pembayaran' => $rincian,
                'tagihan_id' => $p->tagihan_id,
                'tagihan' => [
                    'id' => $t?->id,
                    'nomor_tagihan' => $t?->nomor_tagihan,
                    'mahasiswa_id' => $t?->mahasiswa_id,
                    'calon_mahasiswa_id' => $t?->calon_mahasiswa_id,
                    'total_tagihan' => (float)($t?->total_tagihan ?? 0),
                    'total_bayar' => (float)($t?->total_bayar ?? 0),
                    'status' => $t?->status,
                    'rincian' => $rincian,
                ],
                'virtual_account' => $p->virtualAccount ? [
                    'va_number' => $p->virtualAccount->va_number,
                    'bank_nama' => $p->virtualAccount->bank_nama,
                ] : null,
                'jumlah_bayar' => (float)$p->jumlah_bayar,
                'waktu_bayar' => $p->waktu_bayar ? (is_object($p->waktu_bayar) && method_exists($p->waktu_bayar, 'format') ? $p->waktu_bayar->format('Y-m-d H:i:s') : (string)$p->waktu_bayar) : null,
                'channel_bayar' => $p->channel_bayar,
                'status' => $p->status,
                'catatan' => $p->catatan,
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => $mappedItems,
            'meta' => [
                'current_page' => $pembayaran->currentPage(),
                'per_page' => $pembayaran->perPage(),
                'total' => $pembayaran->total(),
                'last_page' => $pembayaran->lastPage(),
                'from' => $pembayaran->firstItem(),
                'to' => $pembayaran->lastItem(),
            ]
        ]);
    }

    /**
     * Public Payment Receipt Verification Endpoint
     * Accessible by scanning QR Code on printed physical receipt
     */
    public function validasiPembayaranPublik(string $kode_transaksi)
    {
        $pembayaran = \App\Models\Sikeu\Pembayaran::where('kode_transaksi', $kode_transaksi)
            ->with([
                'tagihan.details.masterBiaya',
                'tagihan.mahasiswa.programStudi',
                'tagihan.tipeTagihanMahasiswa',
                'tagihan.calonMahasiswa.programStudi',
                'virtualAccount',
            ])
            ->first();

        if (!$pembayaran) {
            return response()->json([
                'status' => 'error',
                'message' => 'Dokumen transaksi pembayaran tidak ditemukan atau tidak valid.',
                'data' => null,
            ], 404);
        }

        $t = $pembayaran->tagihan;
        $mhs = $t?->mahasiswa;
        $tipeMhs = $t?->tipeTagihanMahasiswa;
        $calon = $t?->calonMahasiswa;

        $nim = $mhs?->nim ?? $tipeMhs?->nim ?? $calon?->nim ?? ($calon?->no_pendaftaran ?: ($t?->mahasiswa_id ? (string)$t->mahasiswa_id : '-'));
        $nama = $mhs?->nama_lengkap ?? $tipeMhs?->nama_mahasiswa ?? $calon?->nama_lengkap ?? ('Mahasiswa #' . ($t?->mahasiswa_id ?? $t?->calon_mahasiswa_id ?? '-'));
        $prodi = $mhs?->programStudi?->nama ?? $mhs?->programStudi?->nama_prodi ?? $calon?->programStudi?->nama ?? '-';
        $angkatan = $mhs?->tahun_angkatan ?? $mhs?->angkatan ?? $calon?->tahun_akademik ?? null;

        $rincian = $t?->details?->map(function ($d) {
            return $d->keterangan ?: ($d->masterBiaya->nama ?? 'Komponen Biaya');
        })->filter()->implode(', ') ?: ($t?->catatan_approval ?? 'Tagihan Mahasiswa');

        $isValid = ($pembayaran->status === 'success');
        $channelName = match ($pembayaran->channel_bayar) {
            'LOKET_TUNAI' => 'Tunai di Loket Kasir Kampus',
            'LOKET_TRANSFER' => 'Transfer Manual Rekening Resmi Kampus',
            default => 'Virtual Account Online (Xendit)',
        };

        $kasirName = match ($pembayaran->channel_bayar) {
            'LOKET_TUNAI', 'LOKET_TRANSFER' => 'Petugas Administrasi Keuangan (Loket Kasir Kampus)',
            default => 'Sistem Payment Gateway (Xendit)',
        };

        return response()->json([
            'status' => 'success',
            'message' => $isValid
                ? 'Dokumen pembayaran sah dan terverifikasi di sistem keuangan kampus.'
                : 'Catatan transaksi ditemukan namun berstatus: ' . strtoupper($pembayaran->status),
            'data' => [
                'kode_transaksi' => $pembayaran->kode_transaksi,
                'status' => $pembayaran->status,
                'is_valid' => $isValid,
                'verified_at' => now()->format('Y-m-d H:i:s'),
                'waktu_bayar' => $pembayaran->waktu_bayar ? (is_object($pembayaran->waktu_bayar) && method_exists($pembayaran->waktu_bayar, 'format') ? $pembayaran->waktu_bayar->format('Y-m-d H:i:s') : (string)$pembayaran->waktu_bayar) : null,
                'jumlah_bayar' => (float)$pembayaran->jumlah_bayar,
                'channel_bayar' => $pembayaran->channel_bayar,
                'channel_label' => $channelName,
                'kasir' => $kasirName,
                'catatan' => $pembayaran->catatan,
                'mahasiswa' => [
                    'nama_mahasiswa' => $nama,
                    'nim' => $nim,
                    'no_pendaftaran' => $calon?->no_pendaftaran,
                    'is_calon_mahasiswa' => (bool)$calon,
                    'program_studi' => $prodi,
                    'tahun_angkatan' => $angkatan,
                ],
                'tagihan' => [
                    'id' => $t?->id,
                    'nomor_tagihan' => $t?->nomor_tagihan,
                    'uraian' => $rincian,
                    'total_tagihan' => (float)($t?->total_tagihan ?? 0),
                    'total_bayar' => (float)($t?->total_bayar ?? 0),
                    'sisa' => (float)($t?->sisa ?? 0),
                    'status' => $t?->status,
                ],
                'security_hash' => hash('sha256', $pembayaran->kode_transaksi . '|' . $pembayaran->jumlah_bayar . '|' . ($pembayaran->waktu_bayar ?? '')),
            ],
        ]);
    }
}
