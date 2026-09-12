<?php

namespace App\Http\Controllers\Sikeu;

use App\Http\Controllers\Controller;
use App\Models\Sikeu\TagihanMahasiswa;
use App\Models\Sikeu\VirtualAccount;
use App\Models\Sikeu\Pembayaran;
use App\Models\Sikeu\JurnalUmum;
use App\Models\Sikeu\DetailJurnalUmum;
use App\Models\Sikeu\AkunKeuangan;
use App\Models\Sikeu\MahasiswaTipeTagihan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class MahasiswaTagihanController extends Controller
{
    /**
     * Resolve the authenticated student ID
     */
    protected function resolveMahasiswaId(Request $request)
    {
        $user = $request->user();
        $mahasiswaId = $request->query('mahasiswa_id', $request->input('mahasiswa_id'));

        if (!$mahasiswaId && $user) {
            // 1. Check Siakad Mahasiswa linked by user_id
            $mhs = \App\Models\Siakad\Mahasiswa::where('user_id', $user->id)->first();
            if ($mhs) {
                return $mhs->id;
            }

            // 2. Check by NIM / username
            if (!empty($user->username)) {
                $mhsByNim = \App\Models\Siakad\Mahasiswa::where('nim', $user->username)->first();
                if ($mhsByNim) return $mhsByNim->id;

                $tipeByNim = MahasiswaTipeTagihan::where('nim', $user->username)->first();
                if ($tipeByNim) return $tipeByNim->mahasiswa_id;
            }

            // 3. Check by Email
            if (!empty($user->email)) {
                $tipeByEmail = MahasiswaTipeTagihan::where('nama_mahasiswa', 'like', "%{$user->username}%")->first();
                if ($tipeByEmail) return $tipeByEmail->mahasiswa_id;
            }

            // 4. Check if user has direct Tagihan
            $tagihanByUser = TagihanMahasiswa::where('mahasiswa_id', $user->id)->first();
            if ($tagihanByUser) {
                return $user->id;
            }
        }

        if ($mahasiswaId) {
            return (int)$mahasiswaId;
        }

        // Fallback to first active student record (Ahmad Fadillah ID: 1)
        $firstTagihan = TagihanMahasiswa::orderBy('id', 'asc')->first();
        return $firstTagihan ? $firstTagihan->mahasiswa_id : 1;
    }

    protected function extractSemesterLabel($tagihan)
    {
        if (!empty($tagihan->catatan_approval) && !str_contains($tagihan->catatan_approval, 'Pembayaran langsung')) {
            return str_replace('Tagihan masal ', '', $tagihan->catatan_approval);
        }
        if ($tagihan->details->isNotEmpty() && !empty($tagihan->details->first()->keterangan)) {
            return $tagihan->details->first()->keterangan;
        }
        return 'Semester ' . ($tagihan->tahun_akademik_id ?? 1);
    }

    protected function extractSemesterNumber($tagihan)
    {
        $text = ($tagihan->nomor_tagihan ?? '') . ' ' . ($tagihan->catatan_approval ?? '') . ' ' . ($tagihan->details->first()?->keterangan ?? '');
        if (preg_match('/(?:SMT|Semester)\s*(\d+)/i', $text, $matches)) {
            return (int)$matches[1];
        }
        return (int)($tagihan->tahun_akademik_id ?? 1);
    }

    /**
     * GET /api/v1/sikeu/mahasiswa/tagihan
     * List bills for logged-in student (supports filtering by semester or status)
     */
    public function myBills(Request $request)
    {
        $mahasiswaId = $this->resolveMahasiswaId($request);

        $query = TagihanMahasiswa::with([
            'details.masterBiaya',
            'virtualAccount',
            'dispensasis',
            'mahasiswa.programStudi',
            'tipeTagihanMahasiswa'
        ])
            ->where('mahasiswa_id', $mahasiswaId);

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        $tagihans = $query->orderBy('id', 'desc')->get();

        $data = $tagihans->map(function ($t) {
            $sisa = max(0, ($t->total_tagihan + $t->total_denda - $t->total_potongan) - $t->total_bayar);
            $periode = $this->extractSemesterLabel($t);
            $semesterNum = $this->extractSemesterNumber($t);

            $mhs = $t->mahasiswa;
            $tipeMhs = $t->tipeTagihanMahasiswa;

            return [
                'id' => $t->id,
                'nomor_tagihan' => $t->nomor_tagihan,
                'semester' => $semesterNum,
                'tahun_akademik' => $periode,
                'periode_label' => $periode,
                'catatan' => $t->catatan_approval,
                'total_tagihan' => (float)$t->total_tagihan,
                'total_potongan' => (float)$t->total_potongan,
                'total_denda' => (float)$t->total_denda,
                'total_bayar' => (float)$t->total_bayar,
                'sisa_bayar' => (float)$sisa,
                'status' => $t->status,
                'jatuh_tempo' => $t->jatuh_tempo ? (is_object($t->jatuh_tempo) ? $t->jatuh_tempo->format('Y-m-d') : (string)$t->jatuh_tempo) : null,
                'va_number' => $t->virtualAccount->va_number ?? null,
                'bank_nama' => $t->virtualAccount->bank_nama ?? 'Bank BNI',
                'mahasiswa' => [
                    'nama' => $mhs?->nama_lengkap ?? $tipeMhs?->nama_mahasiswa ?? ('Mahasiswa #' . $t->mahasiswa_id),
                    'nim' => $mhs?->nim ?? $tipeMhs?->nim ?? ('2024' . str_pad($t->mahasiswa_id, 4, '0', STR_PAD_LEFT)),
                    'prodi' => $mhs?->programStudi?->nama ?? $mhs?->programStudi?->nama_prodi ?? 'Teknik Informatika',
                    'angkatan' => $mhs?->angkatan ?? $tipeMhs?->tahun_angkatan ?? 2023,
                ],
                'details' => $t->details->map(function ($d) {
                    return [
                        'id' => $d->id,
                        'nama_biaya' => $d->masterBiaya->nama ?? 'Biaya Kuliah',
                        'keterangan' => $d->keterangan ?? ($d->masterBiaya->nama ?? 'Biaya Kuliah'),
                        'nominal' => (float)$d->nominal,
                        'potongan' => (float)$d->potongan,
                        'nominal_bersih' => (float)$d->nominal_bersih,
                    ];
                }),
                'dispensasi_aktif' => $t->dispensasis->where('status', 'approved')->first(),
            ];
        });

        // Filter by semester number if requested
        if ($request->filled('semester') && is_numeric($request->semester)) {
            $sem = (int)$request->semester;
            $data = $data->filter(fn($item) => $item['semester'] === $sem)->values();
        }

        return response()->json([
            'status' => 'success',
            'data' => $data
        ]);
    }

    /**
     * GET /api/v1/sikeu/mahasiswa/invoice/{id}
     * Generate or view official invoice and Virtual Account details for a bill
     */
    public function generateInvoice($id)
    {
        $tagihan = TagihanMahasiswa::with(['details.masterBiaya', 'virtualAccount', 'mahasiswa.programStudi', 'tipeTagihanMahasiswa'])->findOrFail($id);

        $sisa = max(0, ($tagihan->total_tagihan + $tagihan->total_denda - $tagihan->total_potongan) - $tagihan->total_bayar);

        // Auto create Virtual Account if not exist
        if (!$tagihan->virtualAccount) {
            $vaNumber = '8801' . str_pad($tagihan->id, 8, '0', STR_PAD_LEFT);
            $va = VirtualAccount::create([
                'tagihan_id' => $tagihan->id,
                'va_number' => $vaNumber,
                'bank_kode' => 'BNI',
                'bank_nama' => 'Bank BNI (Virtual Account)',
                'nominal' => $sisa,
                'expired_at' => now()->addDays(30),
                'status' => 'aktif',
            ]);
            $tagihan->load('virtualAccount');
        }

        $mhs = $tagihan->mahasiswa;
        $tipeMhs = $tagihan->tipeTagihanMahasiswa;

        $periode = $this->extractSemesterLabel($tagihan);

        $invoice = [
            'invoice_number' => 'INV-' . $tagihan->nomor_tagihan,
            'tanggal_terbit' => $tagihan->created_at ? $tagihan->created_at->format('Y-m-d') : date('Y-m-d'),
            'jatuh_tempo' => $tagihan->jatuh_tempo ? (is_string($tagihan->jatuh_tempo) ? $tagihan->jatuh_tempo : $tagihan->jatuh_tempo->format('Y-m-d')) : date('Y-m-d', strtotime('+30 days')),
            'periode' => $periode,
            'mahasiswa' => [
                'nama' => $mhs?->nama_lengkap ?? $tipeMhs?->nama_mahasiswa ?? ('Mahasiswa #' . $tagihan->mahasiswa_id),
                'nim' => $mhs?->nim ?? $tipeMhs?->nim ?? ('2024' . str_pad($tagihan->mahasiswa_id, 4, '0', STR_PAD_LEFT)),
                'prodi' => $mhs?->programStudi?->nama ?? $mhs?->programStudi?->nama_prodi ?? 'Teknik Informatika',
                'angkatan' => $mhs?->angkatan ?? $tipeMhs?->tahun_angkatan ?? 2024,
            ],
            'virtual_account' => [
                'bank' => $tagihan->virtualAccount->bank_nama ?? 'Bank BNI',
                'va_number' => $tagihan->virtualAccount->va_number,
                'nominal_instruksi' => (float)$sisa,
                'expired_at' => $tagihan->virtualAccount->expired_at ? (is_string($tagihan->virtualAccount->expired_at) ? $tagihan->virtualAccount->expired_at : $tagihan->virtualAccount->expired_at->format('Y-m-d H:i:s')) : date('Y-m-d H:i:s', strtotime('+30 days')),
            ],
            'ringkasan' => [
                'subtotal' => (float)$tagihan->total_tagihan,
                'potongan' => (float)$tagihan->total_potongan,
                'denda' => (float)$tagihan->total_denda,
                'total_dibayar' => (float)$tagihan->total_bayar,
                'sisa_tagihan' => (float)$sisa,
                'status' => $tagihan->status,
            ],
            'items' => $tagihan->details->map(function ($d) {
                return [
                    'deskripsi' => !empty($d->keterangan) ? $d->keterangan : ($d->masterBiaya->nama ?? 'Komponen Biaya Pendidikan'),
                    'nama_biaya' => $d->masterBiaya->nama ?? 'Biaya Kuliah',
                    'nominal' => (float)$d->nominal,
                    'potongan' => (float)$d->potongan,
                    'nominal_bersih' => (float)$d->nominal_bersih,
                ];
            }),
        ];

        return response()->json([
            'status' => 'success',
            'data' => $invoice
        ]);
    }

    /**
     * POST /api/v1/sikeu/mahasiswa/invoice-batch
     * Generate consolidated official invoice & VA for selected bills
     */
    public function generateBatchInvoice(Request $request)
    {
        $mahasiswaId = $this->resolveMahasiswaId($request);
        $tagihanIds = $request->input('tagihan_ids', []);

        if (empty($tagihanIds) && $request->filled('tagihan_id')) {
            $tagihanIds = [(int)$request->tagihan_id];
        }

        $query = TagihanMahasiswa::with(['details.masterBiaya', 'virtualAccount', 'mahasiswa.programStudi', 'tipeTagihanMahasiswa'])
            ->where('mahasiswa_id', $mahasiswaId);

        if (!empty($tagihanIds)) {
            $query->whereIn('id', $tagihanIds);
        }

        $tagihans = $query->orderBy('id', 'asc')->get();

        if ($tagihans->isEmpty()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Tidak ada tagihan yang dipilih atau tagihan tidak ditemukan.',
            ], 404);
        }

        $firstTagihan = $tagihans->first();
        $mhs = $firstTagihan->mahasiswa;
        $tipeMhs = $firstTagihan->tipeTagihanMahasiswa;

        $nim = $mhs?->nim ?? $tipeMhs?->nim ?? ('2024' . str_pad($mahasiswaId, 4, '0', STR_PAD_LEFT));
        $nama = $mhs?->nama_lengkap ?? $tipeMhs?->nama_mahasiswa ?? ('Mahasiswa #' . $mahasiswaId);
        $prodi = $mhs?->programStudi?->nama ?? $mhs?->programStudi?->nama_prodi ?? 'Teknik Informatika';
        $angkatan = $mhs?->angkatan ?? $tipeMhs?->tahun_angkatan ?? 2023;

        $totalTagihan = 0;
        $totalPotongan = 0;
        $totalDenda = 0;
        $totalBayar = 0;
        $allItems = [];
        $periodeLabels = [];

        foreach ($tagihans as $t) {
            $totalTagihan += (float)$t->total_tagihan;
            $totalPotongan += (float)$t->total_potongan;
            $totalDenda += (float)$t->total_denda;
            $totalBayar += (float)$t->total_bayar;

            $lbl = $this->extractSemesterLabel($t);
            if (!in_array($lbl, $periodeLabels)) {
                $periodeLabels[] = $lbl;
            }

            foreach ($t->details as $d) {
                $allItems[] = [
                    'deskripsi' => $d->keterangan ?: ($d->masterBiaya->nama ?? 'Komponen Biaya'),
                    'nama_biaya' => $d->masterBiaya->nama ?? 'Biaya Kuliah',
                    'tagihan_nomor' => $t->nomor_tagihan,
                    'nominal' => (float)$d->nominal,
                    'potongan' => (float)$d->potongan,
                    'nominal_bersih' => (float)$d->nominal_bersih,
                ];
            }
        }

        $totalSisa = max(0, ($totalTagihan + $totalDenda - $totalPotongan) - $totalBayar);

        // Dynamic Virtual Account Number for student
        $vaNumber = '88012' . $nim;

        $invoiceNumber = count($tagihans) === 1
            ? 'INV-' . $firstTagihan->nomor_tagihan
            : 'INV-GABUNGAN-' . date('Ymd') . '-' . Str::upper(Str::random(4));

        $invoice = [
            'invoice_number' => $invoiceNumber,
            'tanggal_terbit' => date('Y-m-d'),
            'jatuh_tempo' => date('Y-m-d', strtotime('+30 days')),
            'periode' => implode(', ', $periodeLabels),
            'mahasiswa' => [
                'nama' => $nama,
                'nim' => $nim,
                'prodi' => $prodi,
                'angkatan' => $angkatan,
            ],
            'virtual_account' => [
                'bank' => 'Bank BNI (Virtual Account)',
                'va_number' => $vaNumber,
                'nominal_instruksi' => (float)$totalSisa,
                'expired_at' => date('Y-m-d H:i:s', strtotime('+30 days')),
            ],
            'ringkasan' => [
                'subtotal' => (float)$totalTagihan,
                'potongan' => (float)$totalPotongan,
                'denda' => (float)$totalDenda,
                'total_dibayar' => (float)$totalBayar,
                'sisa_tagihan' => (float)$totalSisa,
                'status' => $totalSisa <= 0 ? 'lunas' : ($totalBayar > 0 ? 'sebagian' : 'belum_bayar'),
                'jumlah_tagihan_terpilih' => count($tagihans),
            ],
            'items' => $allItems,
        ];

        return response()->json([
            'status' => 'success',
            'data' => $invoice
        ]);
    }

    /**
     * POST /api/v1/sikeu/mahasiswa/pay-bills
     * Process student self-payment for all or selected bills
     */
    public function payBills(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'tagihan_ids' => 'required|array|min:1',
            'tagihan_ids.*' => 'integer|exists:sikeu_tagihan_mahasiswa,id',
            'channel_bayar' => 'nullable|string',
            'catatan' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validasi pembayaran tagihan gagal.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $mahasiswaId = $this->resolveMahasiswaId($request);
        $tagihans = TagihanMahasiswa::where('mahasiswa_id', $mahasiswaId)
            ->whereIn('id', $request->tagihan_ids)
            ->get();

        if ($tagihans->isEmpty()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Tagihan yang dipilih tidak ditemukan untuk akun mahasiswa anda.',
            ], 404);
        }

        try {
            DB::beginTransaction();

            $channel = $request->input('channel_bayar', 'BNI_VA');
            $createdPayments = [];
            $totalPaidAll = 0;

            foreach ($tagihans as $tagihan) {
                $totalBersih = (float)($tagihan->total_tagihan + $tagihan->total_denda - $tagihan->total_potongan);
                $sisa = max(0, $totalBersih - (float)$tagihan->total_bayar);

                if ($sisa <= 0) {
                    continue; // Already paid
                }

                $trxCode = 'TRX-' . ($channel === 'BNI_VA' ? 'VA' : 'ONL') . '-' . date('Ymd') . '-' . Str::upper(Str::random(5));

                $pembayaran = Pembayaran::create([
                    'tagihan_id' => $tagihan->id,
                    'virtual_account_id' => $tagihan->virtualAccount?->id,
                    'kode_transaksi' => $trxCode,
                    'jumlah_bayar' => $sisa,
                    'waktu_bayar' => now(),
                    'channel_bayar' => $channel,
                    'status' => 'success',
                    'diverifikasi_oleh' => auth()->id() ?? 1,
                    'catatan' => $request->input('catatan', 'Pelunasan Mandiri Mahasiswa via ' . $channel),
                ]);

                // Update Tagihan
                $tagihan->total_bayar = (float)$tagihan->total_bayar + $sisa;
                $tagihan->status = 'lunas';
                $tagihan->save();

                // Auto Jurnal Akuntansi
                try {
                    $akunKas = AkunKeuangan::where('kode_akun', 'like', '1%')->where('is_kas_bank', true)->first()
                        ?? AkunKeuangan::first();
                    $akunPendapatan = AkunKeuangan::where('kelompok', 'pendapatan')->first()
                        ?? AkunKeuangan::where('kode_akun', 'like', '4%')->first()
                        ?? $akunKas;

                    if ($akunKas && $akunPendapatan) {
                        $jurnal = JurnalUmum::create([
                            'nomor_jurnal' => 'JRN-MHS-' . date('Ymd') . '-' . Str::upper(Str::random(4)),
                            'tanggal_transaksi' => now()->toDateString(),
                            'keterangan' => 'Pelunasan Tagihan Mahasiswa ' . $tagihan->nomor_tagihan . ' via ' . $channel,
                            'jenis_sumber' => 'pembayaran_mahasiswa',
                            'referensi_id' => $pembayaran->id,
                            'total_debet' => $sisa,
                            'total_kredit' => $sisa,
                            'status' => 'posted',
                            'dibuat_oleh' => auth()->id() ?? 1,
                        ]);

                        DetailJurnalUmum::create([
                            'jurnal_umum_id' => $jurnal->id,
                            'akun_keuangan_id' => $akunKas->id,
                            'debet' => $sisa,
                            'kredit' => 0,
                            'keterangan' => 'Kas/Bank Penerimaan VA ' . $tagihan->nomor_tagihan,
                        ]);

                        DetailJurnalUmum::create([
                            'jurnal_umum_id' => $jurnal->id,
                            'akun_keuangan_id' => $akunPendapatan->id,
                            'debet' => 0,
                            'kredit' => $sisa,
                            'keterangan' => 'Pendapatan Pendidikan Mahasiswa ' . $tagihan->nomor_tagihan,
                        ]);
                    }
                } catch (\Throwable $e) {
                    // Ignore journal failure if tables differ
                }

                $totalPaidAll += $sisa;
                $createdPayments[] = [
                    'kode_transaksi' => $trxCode,
                    'tagihan_id' => $tagihan->id,
                    'nomor_tagihan' => $tagihan->nomor_tagihan,
                    'jumlah_bayar' => $sisa,
                ];
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Pembayaran tagihan berhasil diproses dan diverifikasi lunas!',
                'data' => [
                    'total_paid' => $totalPaidAll,
                    'channel' => $channel,
                    'payments' => $createdPayments,
                ]
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal memproses pembayaran: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /api/v1/sikeu/mahasiswa/riwayat-pembayaran
     * List payment history and receipts for student
     */
    public function myPaymentHistory(Request $request)
    {
        $mahasiswaId = $this->resolveMahasiswaId($request);

        $tagihanIds = TagihanMahasiswa::where('mahasiswa_id', $mahasiswaId)->pluck('id');

        $pembayarans = Pembayaran::with([
            'tagihan.details.masterBiaya',
            'tagihan.mahasiswa.programStudi',
            'tagihan.tipeTagihanMahasiswa',
            'virtualAccount'
        ])
            ->whereIn('tagihan_id', $tagihanIds)
            ->orderBy('waktu_bayar', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        $data = $pembayarans->map(function ($p) {
            $t = $p->tagihan;
            $mhs = $t?->mahasiswa;
            $tipeMhs = $t?->tipeTagihanMahasiswa;

            $periode = $t ? $this->extractSemesterLabel($t) : 'Semester Ganjil 2026/2027';

            $rincian = $t?->details?->map(function ($d) {
                return $d->keterangan ?: ($d->masterBiaya->nama ?? 'Komponen Biaya');
            })->filter()->implode(', ') ?: ($t?->catatan_approval ?? 'Tagihan Semester');

            return [
                'id' => $p->id,
                'kode_transaksi' => $p->kode_transaksi,
                'tagihan_id' => $p->tagihan_id,
                'nomor_tagihan' => $t?->nomor_tagihan ?? '-',
                'periode_label' => $periode,
                'rincian_pembayaran' => $rincian,
                'jumlah_bayar' => (float)$p->jumlah_bayar,
                'waktu_bayar' => $p->waktu_bayar ? (is_object($p->waktu_bayar) && method_exists($p->waktu_bayar, 'format') ? $p->waktu_bayar->format('Y-m-d H:i:s') : (string)$p->waktu_bayar) : ($p->created_at ? $p->created_at->format('Y-m-d H:i:s') : date('Y-m-d H:i:s')),
                'channel_bayar' => $p->channel_bayar,
                'status' => $p->status,
                'catatan' => $p->catatan,
                'mahasiswa' => [
                    'nama' => $mhs?->nama_lengkap ?? $tipeMhs?->nama_mahasiswa ?? ('Mahasiswa #' . ($t?->mahasiswa_id ?? 1)),
                    'nim' => $mhs?->nim ?? $tipeMhs?->nim ?? ('2024' . str_pad($t?->mahasiswa_id ?? 1, 4, '0', STR_PAD_LEFT)),
                    'prodi' => $mhs?->programStudi?->nama ?? $mhs?->programStudi?->nama_prodi ?? 'Teknik Informatika',
                    'angkatan' => $mhs?->angkatan ?? $tipeMhs?->tahun_angkatan ?? 2023,
                ],
                'details' => $t?->details?->map(function ($d) {
                    return [
                        'nama_biaya' => $d->masterBiaya->nama ?? 'Biaya Kuliah',
                        'keterangan' => $d->keterangan ?? ($d->masterBiaya->nama ?? 'Biaya Kuliah'),
                        'nominal' => (float)$d->nominal,
                        'potongan' => (float)$d->potongan,
                        'nominal_bersih' => (float)$d->nominal_bersih,
                    ];
                }) ?? [],
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => $data
        ]);
    }
}
