<?php

namespace App\Http\Controllers\Sikeu;

use App\Http\Controllers\Controller;
use App\Models\Sikeu\TagihanMahasiswa;
use App\Models\Sikeu\VirtualAccount;
use App\Models\Sikeu\Pembayaran;
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
    protected function resolveMahasiswaId(Request $request): ?int
    {
        $user = $request->user();
        $mahasiswaId = $request->query('mahasiswa_id', $request->input('mahasiswa_id'));
        $nimQuery = $request->query('nim', $request->input('nim'));

        if ($nimQuery) {
            $mhsByQuery = \App\Models\Siakad\Mahasiswa::where('nim', $nimQuery)->first();
            if ($mhsByQuery) return $mhsByQuery->id;
            $tipeByQuery = MahasiswaTipeTagihan::where('nim', $nimQuery)->first();
            if ($tipeByQuery) return $tipeByQuery->mahasiswa_id;
        }

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
                $mhsByEmail = \App\Models\Siakad\Mahasiswa::where('email', $user->email)->first();
                if ($mhsByEmail) return $mhsByEmail->id;
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

        return null;
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

        if (!$mahasiswaId) {
            return response()->json([
                'status' => 'success',
                'data' => [],
            ]);
        }

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
        } else if (!$request->boolean('include_lunas')) {
            // Default: hanya tampilkan tagihan berjalan yang belum lunas
            $query->whereIn('status', ['belum_bayar', 'sebagian', 'dispensasi']);
        }

        $tagihans = $query->orderBy('id', 'desc')->get();

        $data = $tagihans->map(function ($t) {
            $sisa = max(0, ($t->total_tagihan + $t->total_denda - $t->total_potongan) - $t->total_bayar);
            $periode = $this->extractSemesterLabel($t);
            $semesterNum = $this->extractSemesterNumber($t);

            $mhs = $t->mahasiswa;
            $tipeMhs = $t->tipeTagihanMahasiswa;
            $pending = $this->pendingVerifikasi($t->id);
            $rejected = $this->lastRejection($t->id);

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
                'bank_nama' => $t->virtualAccount->bank_nama ?? 'Bank BSN',
                'h2h_billing_id' => $t->h2h_billing_id,
                'h2h_id_tagihan' => $t->h2h_id_tagihan,
                'h2h_custid' => $t->h2h_custid,
                'pending_verification' => $pending ? [
                    'kode_transaksi' => $pending->kode_transaksi,
                    'channel_bayar' => $pending->channel_bayar,
                    'jumlah_bayar' => (float) $pending->jumlah_bayar,
                    'waktu_bayar' => $pending->waktu_bayar ? (string) $pending->waktu_bayar : null,
                ] : null,
                'last_rejection' => $rejected ? [
                    'kode_transaksi' => $rejected->kode_transaksi,
                    'catatan' => $rejected->catatan,
                    'waktu_bayar' => $rejected->waktu_bayar ? (string) $rejected->waktu_bayar : null,
                ] : null,
                'mahasiswa' => [
                    'nama' => $mhs?->nama_lengkap ?? $tipeMhs?->nama_mahasiswa ?? ('Mahasiswa #' . $t->mahasiswa_id),
                    'nim' => $mhs?->nim ?? $tipeMhs?->nim ?? ($t->mahasiswa_id ? (string)$t->mahasiswa_id : '-'),
                    'prodi' => $mhs?->programStudi?->nama ?? $mhs?->programStudi?->nama_prodi ?? '-',
                    'angkatan' => $mhs?->angkatan ?? $tipeMhs?->tahun_angkatan ?? null,
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

        // Filter by search keyword if requested
        if ($request->filled('search')) {
            $keyword = strtolower(trim($request->search));
            $data = $data->filter(function ($item) use ($keyword) {
                if (str_contains(strtolower($item['nomor_tagihan']), $keyword)) return true;
                if (str_contains(strtolower($item['periode_label']), $keyword)) return true;
                if (!empty($item['catatan']) && str_contains(strtolower($item['catatan']), $keyword)) return true;
                foreach ($item['details'] as $d) {
                    if (str_contains(strtolower($d['nama_biaya']), $keyword)) return true;
                    if (str_contains(strtolower($d['keterangan']), $keyword)) return true;
                }
                return false;
            })->values();
        }

        return response()->json([
            'status' => 'success',
            'data' => $data
        ]);
    }

    /**
     * GET /api/v1/sikeu/mahasiswa/payment-channels
     * List available payment channels for student from Xendit gateway
     */
    public function paymentChannels()
    {
        $channels = [
            [
                'id' => 'BSN',
                'name' => 'Bank BSN (Virtual Account H2H)',
                'code' => 'BSN',
                'type' => 'VIRTUAL_ACCOUNT',
                'category' => 'va',
                'prefix' => '90012',
                'logo_color' => 'from-teal-700 to-emerald-800',
                'badge' => 'Otomatis Realtime',
                'description' => 'VA Host-to-Host BSN (CUSTID = No. Pendaftaran), verifikasi otomatis & masuk saldo BSN',
                'fee' => 0,
                'is_active' => true,
            ],
            [
                'id' => 'MANDIRI',
                'name' => 'Bank Mandiri (Virtual Account)',
                'code' => 'MANDIRI',
                'type' => 'VIRTUAL_ACCOUNT',
                'category' => 'va',
                'prefix' => '88800',
                'logo_color' => 'from-blue-700 to-indigo-800',
                'badge' => 'Otomatis Realtime',
                'description' => 'Livin\' by Mandiri, ATM Mandiri, & Internet Banking',
                'fee' => 0,
                'is_active' => true,
            ],
            [
                'id' => 'BRI',
                'name' => 'Bank BRI (BRIVA)',
                'code' => 'BRI',
                'type' => 'VIRTUAL_ACCOUNT',
                'category' => 'va',
                'prefix' => '70012',
                'logo_color' => 'from-blue-600 to-cyan-600',
                'badge' => 'Otomatis Realtime',
                'description' => 'BRImo, ATM BRI, & Agen BRILink',
                'fee' => 0,
                'is_active' => true,
            ],
            [
                'id' => 'BCA',
                'name' => 'Bank BCA (Virtual Account)',
                'code' => 'BCA',
                'type' => 'VIRTUAL_ACCOUNT',
                'category' => 'va',
                'prefix' => '10204',
                'logo_color' => 'from-blue-800 to-blue-950',
                'badge' => 'Otomatis Realtime',
                'description' => 'myBCA, BCA mobile, KlikBCA, & ATM BCA',
                'fee' => 0,
                'is_active' => true,
            ],
            [
                'id' => 'PERMATA',
                'name' => 'Bank Permata (Virtual Account)',
                'code' => 'PERMATA',
                'type' => 'VIRTUAL_ACCOUNT',
                'category' => 'va',
                'prefix' => '85220',
                'logo_color' => 'from-emerald-700 to-teal-800',
                'badge' => 'Otomatis Realtime',
                'description' => 'PermataMobile X, PermataNet, & ATM Permata',
                'fee' => 0,
                'is_active' => true,
            ],
            [
                'id' => 'QRIS',
                'name' => 'QRIS Realtime (Semua E-Wallet / Mobile Banking)',
                'code' => 'QRIS',
                'type' => 'QR_CODE',
                'category' => 'instant',
                'prefix' => 'QRIS',
                'logo_color' => 'from-rose-600 to-red-600',
                'badge' => 'Scan & Bayar',
                'description' => 'GoPay, OVO, ShopeePay, DANA, LinkAja, BCA, Mandiri, dll.',
                'fee' => 0,
                'is_active' => true,
            ],
            [
                'id' => 'MANUAL',
                'name' => 'Transfer Manual (BNI / BSN)',
                'code' => 'MANUAL',
                'type' => 'MANUAL_TRANSFER',
                'category' => 'manual',
                'prefix' => '-',
                'logo_color' => 'from-slate-600 to-slate-800',
                'badge' => 'Upload Bukti',
                'description' => 'Transfer ke rekening BNI/BSN kampus lalu unggah bukti, diverifikasi keuangan',
                'fee' => 0,
                'is_active' => true,
            ],
        ];

        return response()->json([
            'status' => 'success',
            'data' => $channels
        ]);
    }

    /**
     * GET /api/v1/sikeu/mahasiswa/rekening-tujuan
     * Daftar rekening kampus tujuan transfer manual untuk mahasiswa.
     * Hanya bank_manual BNI/BSN yang aktif dan bernomor rekening
     * (disetting admin/keuangan via menu Unit Kas & Rekening Bank).
     * Tanpa saldo dan data kas internal lain.
     */
    public function rekeningTujuan()
    {
        $data = \App\Models\Sikeu\UnitKas::where('status', true)
            ->where('kanal', 'bank_manual')
            ->whereIn('bank_name', ['BNI', 'BSN'])
            ->whereNotNull('bank_account_number')
            ->where('bank_account_number', '!=', '')
            ->orderBy('bank_name')
            ->get(['id', 'nama_kas', 'kanal', 'bank_name', 'bank_account_number', 'bank_account_name']);

        return response()->json([
            'status' => 'success',
            'data' => $data,
        ]);
    }

    /**
     * Kode unik transfer manual (1-499, 3 digit) yang belum dipakai
     * pembayaran pending lain. Dipakai sebagai pembeda di mutasi bank:
     * nominal_transfer = jumlah_bayar + kode_unik.
     */
    protected function generateKodeUnik(): int
    {
        $terpakai = Pembayaran::where('status', 'pending')
            ->whereNotNull('kode_unik')
            ->pluck('kode_unik')
            ->map(fn ($v) => (int) $v)
            ->all();

        for ($i = 0; $i < 30; $i++) {
            $kode = random_int(1, 499);
            if (!in_array($kode, $terpakai, true)) {
                return $kode;
            }
        }
        for ($kode = 1; $kode <= 499; $kode++) {
            if (!in_array($kode, $terpakai, true)) {
                return $kode;
            }
        }

        throw new \RuntimeException('Stok kode unik habis, hubungi bagian keuangan.');
    }

    protected function rekeningRingkas($unitKas): array
    {
        return [
            'unit_kas_id' => $unitKas->id,
            'nama_kas' => $unitKas->nama_kas,
            'bank_name' => $unitKas->bank_name,
            'bank_account_number' => $unitKas->bank_account_number,
            'bank_account_name' => $unitKas->bank_account_name,
        ];
    }

    /**
     * Pembayaran manual yang sedang menunggu validasi keuangan
     * (sudah ada bukti, belum disetujui/ditolak) untuk satu tagihan.
     */
    protected function pendingVerifikasi(int $tagihanId, ?int $exceptId = null): ?Pembayaran
    {
        $q = Pembayaran::where('tagihan_id', $tagihanId)
            ->where('status', 'pending')
            ->where('channel_bayar', 'MANUAL_TRANSFER')
            ->whereNotNull('bukti_bayar_path')
            ->orderBy('id', 'desc');
        if ($exceptId) {
            $q->where('id', '!=', $exceptId);
        }

        return $q->first();
    }

    /**
     * Penolakan terakhir (beserta alasan keuangan) untuk satu tagihan.
     */
    protected function lastRejection(int $tagihanId): ?Pembayaran
    {
        return Pembayaran::where('tagihan_id', $tagihanId)
            ->where('status', 'rejected')
            ->orderBy('id', 'desc')
            ->first();
    }

    /**
     * POST /api/v1/sikeu/pembayaran/manual-init
     * Inisiasi transfer manual: kunci nominal + kode unik per tagihan sebelum
     * mahasiswa transfer ke BNI/BSN. Mengembalikan nominal_transfer yang harus
     * ditransfer persis agar mudah ditemukan di mutasi bank.
     */
    public function manualInit(\App\Http\Requests\Sikeu\StoreManualInitRequest $request)
    {
        $mahasiswaId = $this->resolveMahasiswaId($request);
        if (!$mahasiswaId) {
            return response()->json([
                'status' => 'error',
                'message' => 'Identitas mahasiswa tidak ditemukan.',
            ], 403);
        }

        $unitKas = \App\Models\Sikeu\UnitKas::find($request->unit_kas_id);
        if (!$unitKas || !$unitKas->status || $unitKas->kanal !== 'bank_manual'
            || !in_array(strtoupper((string) $unitKas->bank_name), ['BNI', 'BSN'])
            || empty($unitKas->bank_account_number)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Rekening tujuan harus bank manual BNI/BSN yang aktif dan bernomor rekening.',
            ], 422);
        }

        try {
            DB::beginTransaction();
            $inits = [];

            foreach ($request->items as $item) {
                $tagihan = TagihanMahasiswa::find($item['tagihan_id']);
                if (!$tagihan || (int) $tagihan->mahasiswa_id !== (int) $mahasiswaId) {
                    throw new \InvalidArgumentException('Tagihan #' . $item['tagihan_id'] . ' bukan milik Anda.');
                }

                $totalBersih = (float) ($tagihan->total_tagihan + $tagihan->total_denda - $tagihan->total_potongan);
                $sisa = max(0, $totalBersih - (float) $tagihan->total_bayar);
                if ($sisa <= 0) {
                    throw new \InvalidArgumentException("Tagihan {$tagihan->nomor_tagihan} sudah lunas.");
                }

                $jumlah = isset($item['jumlah_bayar']) ? (float) $item['jumlah_bayar'] : $sisa;
                if ($jumlah <= 0 || $jumlah > $sisa) {
                    throw new \InvalidArgumentException("Nominal tagihan {$tagihan->nomor_tagihan} melebihi sisa (" . number_format($sisa, 0, ',', '.') . ').');
                }

                if ($this->pendingVerifikasi($tagihan->id)) {
                    throw new \InvalidArgumentException("Tagihan {$tagihan->nomor_tagihan} sedang menunggu validasi keuangan dan terkunci untuk pembayaran lain.");
                }

                // Kode unik stabil: pakai ulang inisiasi pending tanpa bukti untuk
                // kombinasi tagihan + rekening + nominal yang sama. Ganti metode
                // (rekening) atau nominal → kode baru.
                $existing = Pembayaran::where('tagihan_id', $tagihan->id)
                    ->where('unit_kas_id', $unitKas->id)
                    ->where('status', 'pending')
                    ->where('channel_bayar', 'MANUAL_TRANSFER')
                    ->whereNull('bukti_bayar_path')
                    ->where('jumlah_bayar', $jumlah)
                    ->orderBy('id', 'desc')
                    ->first();

                if ($existing && !empty($existing->kode_unik)) {
                    $kodeUnik = (int) $existing->kode_unik;
                    $pembayaran = $existing;
                    $reused = true;
                } else {
                    $kodeUnik = $this->generateKodeUnik();

                    $pembayaran = Pembayaran::create([
                        'tagihan_id' => $tagihan->id,
                        'unit_kas_id' => $unitKas->id,
                        'kode_transaksi' => 'TRX-MANUAL-' . date('Ymd') . '-' . strtoupper(Str::random(5)),
                        'jumlah_bayar' => $jumlah,
                        'kode_unik' => $kodeUnik,
                        'waktu_bayar' => null,
                        'channel_bayar' => 'MANUAL_TRANSFER',
                        'bank_pengirim' => $unitKas->bank_name,
                        'catatan' => 'Inisiasi transfer manual, menunggu bukti.',
                        'status' => 'pending',
                    ]);
                    $reused = false;
                }

                $inits[] = [
                    'pembayaran_id' => $pembayaran->id,
                    'tagihan_id' => $tagihan->id,
                    'nomor_tagihan' => $tagihan->nomor_tagihan,
                    'jumlah_bayar' => (float) $pembayaran->jumlah_bayar,
                    'kode_unik' => $kodeUnik,
                    'kode_unik_tampil' => str_pad((string) $kodeUnik, 3, '0', STR_PAD_LEFT),
                    'nominal_transfer' => (float) $pembayaran->jumlah_bayar + $kodeUnik,
                    'reused' => $reused,
                    'rekening' => $this->rekeningRingkas($unitKas),
                ];
            }

            DB::commit();

            $allReused = count($inits) > 0 && collect($inits)->every(fn ($it) => !empty($it['reused']));

            return response()->json([
                'status' => 'success',
                'message' => $allReused
                    ? 'Kode unik sebelumnya dipakai ulang (tidak berubah). Transfer persis sebesar nominal yang tertera lalu unggah buktinya.'
                    : 'Kode unik diterbitkan. Transfer persis sebesar nominal yang tertera lalu unggah buktinya.',
                'data' => $inits,
            ], 201);
        } catch (\InvalidArgumentException $e) {
            DB::rollBack();
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Inisiasi transfer manual gagal: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'Gagal inisiasi transfer: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Helper to compute VA Number based on Bank Code and Student identifier
     */
    protected function computeVaNumber($bankCode, $identifier)
    {
        $cleanId = preg_replace('/[^0-9]/', '', (string)$identifier);
        if (empty($cleanId)) {
            $cleanId = '20240001';
        }

        switch (strtoupper($bankCode)) {
            case 'MANDIRI':
                return \App\Services\Sikeu\VaNumberService::generate($cleanId, '70012');
            case 'BSN':
                return \App\Services\Sikeu\VaNumberService::generate($cleanId, '90012');
            case 'BRI':
                return \App\Services\Sikeu\VaNumberService::generate($cleanId, '12345');
            case 'BSI':
                return \App\Services\Sikeu\VaNumberService::generate($cleanId, '70012');
            case 'BCA':
                return \App\Services\Sikeu\VaNumberService::generate($cleanId, '10204');
            case 'PERMATA':
                return \App\Services\Sikeu\VaNumberService::generate($cleanId, '85220');
            case 'QRIS':
                return 'QRIS-' . date('ymd') . '-' . str_pad($cleanId, 6, '0', STR_PAD_LEFT);
            case 'BNI':
            default:
                return \App\Services\Sikeu\VaNumberService::generate($cleanId);
        }
    }

    /**
     * GET /api/v1/sikeu/mahasiswa/invoice/{id}
     * Generate or view official invoice and Virtual Account details for a bill
     */
    public function generateInvoice(Request $request, $id)
    {
        $tagihan = TagihanMahasiswa::with(['details.masterBiaya', 'virtualAccount', 'mahasiswa.programStudi', 'tipeTagihanMahasiswa'])->findOrFail($id);

        $sisa = max(0, ($tagihan->total_tagihan + $tagihan->total_denda - $tagihan->total_potongan) - $tagihan->total_bayar);
        $bankCode = strtoupper($request->query('bank_kode', $tagihan->virtualAccount->bank_kode ?? 'BSN'));

        $mhs = $tagihan->mahasiswa;
        $tipeMhs = $tagihan->tipeTagihanMahasiswa;
        $nim = $mhs?->nim ?? $tipeMhs?->nim ?? ('2024' . str_pad($tagihan->mahasiswa_id, 4, '0', STR_PAD_LEFT));

        $expectedVaNumber = $this->computeVaNumber($bankCode, $nim);

        // Update or create Virtual Account with chosen bank
        if (!$tagihan->virtualAccount || $tagihan->virtualAccount->bank_kode !== $bankCode) {
            $va = VirtualAccount::updateOrCreate(
                ['tagihan_id' => $tagihan->id],
                [
                    'va_number' => $expectedVaNumber,
                    'bank_kode' => $bankCode,
                    'bank_nama' => $bankCode === 'QRIS' ? 'QRIS Indonesia' : ('Bank ' . $bankCode . ' (Virtual Account)'),
                    'nominal' => $sisa,
                    'expired_at' => now()->addDays(30),
                    'status' => 'aktif',
                ]
            );
            $tagihan->load('virtualAccount');
        }

        $periode = $this->extractSemesterLabel($tagihan);

        $invoice = [
            'invoice_number' => 'INV-' . $tagihan->nomor_tagihan,
            'tanggal_terbit' => $tagihan->created_at ? $tagihan->created_at->format('Y-m-d') : date('Y-m-d'),
            'jatuh_tempo' => $tagihan->jatuh_tempo ? (is_string($tagihan->jatuh_tempo) ? $tagihan->jatuh_tempo : $tagihan->jatuh_tempo->format('Y-m-d')) : date('Y-m-d', strtotime('+30 days')),
            'periode' => $periode,
            'mahasiswa' => [
                'nama' => $mhs?->nama_lengkap ?? $tipeMhs?->nama_mahasiswa ?? ('Mahasiswa #' . $tagihan->mahasiswa_id),
                'nim' => $nim,
                'prodi' => $mhs?->programStudi?->nama ?? $mhs?->programStudi?->nama_prodi ?? '-',
                'angkatan' => $mhs?->angkatan ?? $tipeMhs?->tahun_angkatan ?? null,
            ],
            'virtual_account' => [
                'bank' => $tagihan->virtualAccount->bank_nama ?? ('Bank ' . $bankCode),
                'bank_kode' => $bankCode,
                'va_number' => $tagihan->virtualAccount->va_number ?? $expectedVaNumber,
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

        if (!$mahasiswaId) {
            return response()->json([
                'status' => 'error',
                'message' => 'Akun mahasiswa tidak ditemukan.',
            ], 404);
        }

        $tagihanIds = $request->input('tagihan_ids', []);
        $bankCode = strtoupper($request->input('bank_kode', 'BSN'));

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

        $nim = $mhs?->nim ?? $tipeMhs?->nim ?? ($mahasiswaId ? (string)$mahasiswaId : '-');
        $nama = $mhs?->nama_lengkap ?? $tipeMhs?->nama_mahasiswa ?? ('Mahasiswa #' . $mahasiswaId);
        $prodi = $mhs?->programStudi?->nama ?? $mhs?->programStudi?->nama_prodi ?? '-';
        $angkatan = $mhs?->angkatan ?? $tipeMhs?->tahun_angkatan ?? null;

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

        // Dynamic Virtual Account Number for student according to selected bank
        $vaNumber = $this->computeVaNumber($bankCode, $nim);

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
                'bank' => $bankCode === 'QRIS' ? 'QRIS Indonesia' : ('Bank ' . $bankCode . ' (Virtual Account)'),
                'bank_kode' => $bankCode,
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
     * Process student self-payment initiation or simulation for all or selected bills
     */
    public function payBills(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'tagihan_ids' => 'required|array|min:1',
            'tagihan_ids.*' => 'integer|exists:sikeu_tagihan_mahasiswa,id',
            'channel_bayar' => 'nullable|string',
            'bank_kode' => 'nullable|string',
            'catatan' => 'nullable|string|max:255',
            'simulate' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validasi pembayaran tagihan gagal.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $mahasiswaId = $this->resolveMahasiswaId($request);
        if (!$mahasiswaId) {
            return response()->json([
                'status' => 'error',
                'message' => 'Tagihan yang dipilih tidak ditemukan untuk akun mahasiswa anda.',
            ], 404);
        }

        $tagihans = TagihanMahasiswa::where('mahasiswa_id', $mahasiswaId)
            ->whereIn('id', $request->tagihan_ids)
            ->get();

        if ($tagihans->isEmpty()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Tagihan yang dipilih tidak ditemukan untuk akun mahasiswa anda.',
            ], 404);
        }

        // Kunci: tagihan yang bukti transfernya sedang menunggu validasi
        // tidak boleh dibayar lagi via metode lain sampai ada keputusan.
        $locked = $tagihans->first(fn ($t) => $this->pendingVerifikasi($t->id));
        if ($locked) {
            return response()->json([
                'status' => 'error',
                'message' => "Tagihan {$locked->nomor_tagihan} sedang menunggu validasi keuangan dan terkunci untuk pembayaran lain.",
            ], 422);
        }

        try {
            DB::beginTransaction();

            $bankKode = strtoupper($request->input('bank_kode', 'BSN'));
            $channel = $request->input('channel_bayar', 'VA_' . $bankKode);
            $isSimulation = $request->boolean('simulate', false);
            $createdPayments = [];
            $virtualAccounts = [];
            $totalPaidAll = 0;

            foreach ($tagihans as $tagihan) {
                $totalBersih = (float)($tagihan->total_tagihan + $tagihan->total_denda - $tagihan->total_potongan);
                $sisa = max(0, $totalBersih - (float)$tagihan->total_bayar);

                if ($sisa <= 0) {
                    continue; // Sudah lunas
                }

                $prefixTrx = str_starts_with($channel, 'VA_') ? 'VA' : (str_starts_with($channel, 'QRIS') ? 'QRS' : 'XND');
                $trxCode = 'TRX-' . $prefixTrx . '-' . date('Ymd') . '-' . Str::upper(Str::random(5));

                // Update / create Virtual Account
                $mhsNim = $tagihan->mahasiswa?->nim ?? $tagihan->tipeTagihanMahasiswa?->nim ?? $tagihan->mahasiswa_id;
                $expectedVaNumber = $this->computeVaNumber($bankKode, $mhsNim);
                $vaStatus = $isSimulation ? 'dibayar' : 'aktif';

                $va = VirtualAccount::updateOrCreate(
                    ['tagihan_id' => $tagihan->id],
                    [
                        'va_number' => $expectedVaNumber,
                        'bank_kode' => $bankKode,
                        'bank_nama' => $bankKode === 'QRIS' ? 'QRIS Indonesia' : ('Bank ' . $bankKode),
                        'nominal' => $sisa,
                        'expired_at' => now()->addDays(30),
                        'status' => $vaStatus,
                    ]
                );
                $tagihan->load('virtualAccount');

                $virtualAccounts[] = [
                    'tagihan_id' => $tagihan->id,
                    'nomor_tagihan' => $tagihan->nomor_tagihan,
                    'va_number' => $va->va_number,
                    'bank_nama' => $va->bank_nama,
                    'bank_kode' => $bankKode,
                    'nominal' => $sisa,
                    'expired_at' => $va->expired_at ? $va->expired_at->format('Y-m-d H:i:s') : null,
                    'status' => $vaStatus,
                ];

                // Jika simulasi pembayaran langsung (sandbox mode)
                if ($isSimulation) {
                    $unitKasSim = \App\Services\Sikeu\JurnalSikeuService::resolveUnitKasUntukChannel($channel, $bankKode);
                    if ($unitKasSim) {
                        $unitKasSim->increment('saldo_saat_ini', $sisa);
                    }
                    $pembayaran = Pembayaran::create([
                        'tagihan_id' => $tagihan->id,
                        'virtual_account_id' => $va->id,
                        'unit_kas_id' => $unitKasSim?->id,
                        'kode_transaksi' => $trxCode,
                        'jumlah_bayar' => $sisa,
                        'waktu_bayar' => now(),
                        'channel_bayar' => $channel,
                        'bank_pengirim' => $bankKode,
                        'status' => 'success',
                        'diverifikasi_oleh' => auth()->id() ?? 1,
                        'catatan' => $request->input('catatan', 'Pelunasan Mandiri via ' . $channel . ' (Simulasi Sandbox)'),
                    ]);

                    $tagihan->total_bayar = (float)$tagihan->total_bayar + $sisa;
                    $tagihan->status = 'lunas';
                    $tagihan->save();

                    \App\Services\Sikeu\AutoJournalService::recordStudentPaymentJournal($tagihan, (float) $sisa, $unitKasSim);

                    $createdPayments[] = [
                        'kode_transaksi' => $trxCode,
                        'tagihan_id' => $tagihan->id,
                        'nomor_tagihan' => $tagihan->nomor_tagihan,
                        'jumlah_bayar' => $sisa,
                        'channel' => $channel,
                        'bank_kode' => $bankKode,
                    ];
                }

                $totalPaidAll += $sisa;
            }

            DB::commit();

            $message = $isSimulation
                ? 'Simulasi pembayaran via ' . $channel . ' berhasil diverifikasi lunas!'
                : 'Virtual Account ' . $bankKode . ' berhasil diterbitkan. Silakan lakukan pembayaran ke nomor VA sebelum batas tempo.';

            return response()->json([
                'status' => 'success',
                'message' => $message,
                'data' => [
                    'total_paid' => $totalPaidAll,
                    'channel' => $channel,
                    'bank_kode' => $bankKode,
                    'is_simulation' => $isSimulation,
                    'virtual_accounts' => $virtualAccounts,
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
     * POST /api/v1/sikeu/callback/va-paid
     * Webhook / Callback handler from Bank / Payment Gateway when a student pays their Virtual Account.
     */
    public function vaPaymentCallback(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'va_number' => 'required|string',
            'nominal' => 'required|numeric|min:1',
            'status' => 'required|in:paid,success,settlement',
            'order_id' => 'nullable|string',
            'bank_kode' => 'nullable|string',
            'channel' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validasi webhook callback VA gagal.',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            DB::beginTransaction();

            $vaNumber = $request->input('va_number');
            $nominal = (float)$request->input('nominal');
            $orderId = $request->input('order_id', 'TRX-VA-' . date('Ymd') . '-' . Str::upper(Str::random(5)));
            $bankKode = strtoupper($request->input('bank_kode', 'BSN'));
            $channel = $request->input('channel', 'VA_' . $bankKode);

            // Idempotency check
            $existing = Pembayaran::where('kode_transaksi', $orderId)->first();
            if ($existing) {
                DB::commit();
                return response()->json([
                    'status' => 'success',
                    'message' => 'Pembayaran sudah pernah diproses sebelumnya (idempotent).',
                    'data' => [
                        'pembayaran' => $existing,
                        'tagihan' => $existing->tagihan,
                    ]
                ]);
            }

            $va = VirtualAccount::with('tagihan')->where('va_number', $vaNumber)->first();
            if (!$va || !$va->tagihan) {
                DB::rollBack();
                return response()->json([
                    'status' => 'error',
                    'message' => "Virtual account {$vaNumber} tidak ditemukan dalam sistem.",
                ], 404);
            }

            $tagihan = $va->tagihan;
            $va->update(['status' => 'dibayar']);

            // Resolve kanal penerima (Xendit / VA bank H2H) -> saldo unit kas terkait
            $unitKasVa = \App\Services\Sikeu\JurnalSikeuService::resolveUnitKasUntukChannel($channel, $bankKode);
            if ($unitKasVa) {
                $unitKasVa->increment('saldo_saat_ini', $nominal);
            }

            $pembayaran = Pembayaran::create([
                'tagihan_id' => $tagihan->id,
                'virtual_account_id' => $va->id,
                'unit_kas_id' => $unitKasVa?->id,
                'kode_transaksi' => $orderId,
                'jumlah_bayar' => $nominal,
                'waktu_bayar' => now(),
                'channel_bayar' => $channel,
                'bank_pengirim' => $bankKode,
                'status' => 'success',
                'diverifikasi_oleh' => auth()->id() ?? 1,
                'catatan' => 'Pelunasan via Webhook Bank ' . $bankKode . ' (VA: ' . $vaNumber . ')' . ($unitKasVa ? " -> {$unitKasVa->nama_kas}" : ''),
            ]);

            // Update Tagihan
            $newTotalBayar = (float)$tagihan->total_bayar + $nominal;
            $totalBersih = (float)($tagihan->total_tagihan + $tagihan->total_denda - $tagihan->total_potongan);
            $newStatus = $newTotalBayar >= $totalBersih ? 'lunas' : ($newTotalBayar > 0 ? 'sebagian' : 'belum_bayar');

            $tagihan->update([
                'total_bayar' => $newTotalBayar,
                'status' => $newStatus,
            ]);

            // Auto Jurnal Akuntansi
            \App\Services\Sikeu\AutoJournalService::recordStudentPaymentJournal($tagihan, $nominal, $unitKasVa);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Callback pembayaran VA berhasil diproses. Tagihan dinyatakan lunas.',
                'data' => [
                    'pembayaran' => $pembayaran,
                    'tagihan' => $tagihan->fresh(),
                ]
            ]);

        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal memproses callback VA: ' . $e->getMessage(),
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

        if (!$mahasiswaId) {
            return response()->json([
                'status' => 'success',
                'data' => [],
            ]);
        }

        $tagihanIds = TagihanMahasiswa::where('mahasiswa_id', $mahasiswaId)->pluck('id');

        $pembayarans = Pembayaran::with([
            'tagihan.details.masterBiaya',
            'tagihan.mahasiswa.programStudi',
            'tagihan.tipeTagihanMahasiswa',
            'virtualAccount',
            'unitKas'
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
                'kode_unik' => $p->kode_unik !== null ? (int) $p->kode_unik : null,
                'nominal_transfer' => (float)$p->jumlah_bayar + (int)($p->kode_unik ?? 0),
                'waktu_bayar' => $p->waktu_bayar ? (is_object($p->waktu_bayar) && method_exists($p->waktu_bayar, 'format') ? $p->waktu_bayar->format('Y-m-d H:i:s') : (string)$p->waktu_bayar) : ($p->created_at ? $p->created_at->format('Y-m-d H:i:s') : date('Y-m-d H:i:s')),
                'channel_bayar' => $p->channel_bayar,
                'unit_kas_nama' => $p->unitKas?->nama_kas,
                'status' => $p->status,
                'catatan' => $p->catatan,
                'bukti_bayar_url' => $p->bukti_bayar_path ? asset(\Illuminate\Support\Facades\Storage::url($p->bukti_bayar_path)) : null,
                'mahasiswa' => [
                    'nama' => $mhs?->nama_lengkap ?? $tipeMhs?->nama_mahasiswa ?? ('Mahasiswa #' . ($t?->mahasiswa_id ?? '-')),
                    'nim' => $mhs?->nim ?? $tipeMhs?->nim ?? ($t?->mahasiswa_id ? (string)$t->mahasiswa_id : '-'),
                    'prodi' => $mhs?->programStudi?->nama ?? $mhs?->programStudi?->nama_prodi ?? '-',
                    'angkatan' => $mhs?->angkatan ?? $tipeMhs?->tahun_angkatan ?? null,
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

    /**
     * POST /api/v1/sikeu/pembayaran/manual-upload
     * Mahasiswa mengunggah bukti transfer manual ke rekening kampus.
     * Dua mode:
     *  A. pembayaran_id (hasil manual-init): lampirkan bukti ke inisiasi berkode unik.
     *  B. legacy (tagihan_id + unit_kas_id + jumlah_bayar): buat pembayaran pending + kode unik.
     * Status awal 'pending' — tagihan & jurnal baru berubah saat keuangan approve.
     */
    public function uploadBuktiManual(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'pembayaran_id' => 'nullable|integer|exists:sikeu_pembayaran,id',
            'tagihan_id' => 'required_without:pembayaran_id|integer|exists:sikeu_tagihan_mahasiswa,id',
            'unit_kas_id' => 'required_without:pembayaran_id|integer|exists:sikeu_unit_kas,id',
            'jumlah_bayar' => 'required_without:pembayaran_id|numeric|min:1',
            'tanggal_transfer' => 'required|date|before_or_equal:today',
            'bukti_transfer' => 'required|file|mimes:jpg,jpeg,png|max:5120',
            'catatan' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validasi bukti transfer gagal.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $mahasiswaId = $this->resolveMahasiswaId($request);
        if (!$mahasiswaId) {
            return response()->json([
                'status' => 'error',
                'message' => 'Identitas mahasiswa tidak ditemukan.',
            ], 403);
        }

        try {
            DB::beginTransaction();

            // Mode A: lampirkan bukti ke inisiasi yang sudah berkode unik.
            if ($request->filled('pembayaran_id')) {
                $pembayaran = Pembayaran::find($request->pembayaran_id);
                $tagihan = $pembayaran->tagihan;
                if (!$tagihan || (int) $tagihan->mahasiswa_id !== (int) $mahasiswaId) {
                    throw new \InvalidArgumentException('Data pembayaran ini bukan milik Anda.');
                }
                if ($pembayaran->status !== 'pending' || !empty($pembayaran->bukti_bayar_path)) {
                    throw new \InvalidArgumentException('Inisiasi ini sudah diproses, buat inisiasi baru bila diperlukan.');
                }
                if ($this->pendingVerifikasi($tagihan->id, (int) $pembayaran->id)) {
                    throw new \InvalidArgumentException("Tagihan {$tagihan->nomor_tagihan} sedang menunggu validasi keuangan dan terkunci untuk pembayaran lain.");
                }

                $fileName = Str::uuid() . '.' . $request->file('bukti_transfer')->getClientOriginalExtension();
                $pembayaran->bukti_bayar_path = $request->file('bukti_transfer')->storeAs(
                    'sikeu/bukti_transfer/' . date('Y/m'),
                    $fileName,
                    'public'
                );
                $pembayaran->waktu_bayar = $request->tanggal_transfer;
                if ($request->filled('catatan')) {
                    $pembayaran->catatan = $request->catatan;
                }
                $pembayaran->save();

                DB::commit();

                return response()->json([
                    'status' => 'success',
                    'message' => 'Bukti transfer terkirim. Menunggu verifikasi bagian keuangan.',
                    'data' => array_merge($pembayaran->fresh()->toArray(), [
                        'nominal_transfer' => (float) $pembayaran->jumlah_bayar + (int) $pembayaran->kode_unik,
                    ]),
                ], 200);
            }

            $tagihan = TagihanMahasiswa::find($request->tagihan_id);
            if ((int)$tagihan->mahasiswa_id !== (int)$mahasiswaId) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Tagihan ini bukan milik Anda.',
                ], 403);
            }

            if ($this->pendingVerifikasi($tagihan->id)) {
                return response()->json([
                    'status' => 'error',
                    'message' => "Tagihan {$tagihan->nomor_tagihan} sedang menunggu validasi keuangan dan terkunci untuk pembayaran lain.",
                ], 422);
            }

            $totalBersih = (float)($tagihan->total_tagihan + $tagihan->total_denda - $tagihan->total_potongan);
            $sisa = max(0, $totalBersih - (float)$tagihan->total_bayar);
            if ($sisa <= 0) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Tagihan sudah lunas.',
                ], 422);
            }
            if ((float)$request->jumlah_bayar > $sisa) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Nominal melebihi sisa tagihan (' . number_format($sisa, 0, ',', '.') . ').',
                ], 422);
            }

            $unitKas = \App\Models\Sikeu\UnitKas::find($request->unit_kas_id);
            if (!$unitKas || !$unitKas->status || !in_array($unitKas->kanal, ['tunai', 'bank_manual'])) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Rekening tujuan harus kas tunai atau bank manual yang aktif.',
                ], 422);
            }

            $fileName = Str::uuid() . '.' . $request->file('bukti_transfer')->getClientOriginalExtension();
            $buktiPath = $request->file('bukti_transfer')->storeAs(
                'sikeu/bukti_transfer/' . date('Y/m'),
                $fileName,
                'public'
            );

            $kodeUnik = $this->generateKodeUnik();
            $pembayaran = Pembayaran::create([
                'tagihan_id' => $tagihan->id,
                'unit_kas_id' => $unitKas->id,
                'kode_transaksi' => 'TRX-MANUAL-' . date('Ymd') . '-' . strtoupper(Str::random(5)),
                'jumlah_bayar' => (float)$request->jumlah_bayar,
                'kode_unik' => $kodeUnik,
                'waktu_bayar' => $request->tanggal_transfer,
                'channel_bayar' => 'MANUAL_TRANSFER',
                'bank_pengirim' => $unitKas->bank_name,
                'bukti_bayar_path' => $buktiPath,
                'catatan' => $request->catatan,
                'status' => 'pending',
            ]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Bukti transfer terkirim. Menunggu verifikasi bagian keuangan.',
                'data' => array_merge($pembayaran->toArray(), [
                    'nominal_transfer' => (float) $pembayaran->jumlah_bayar + (int) $kodeUnik,
                ]),
            ], 201);
        } catch (\InvalidArgumentException $e) {
            DB::rollBack();
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Upload bukti manual gagal: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal mengunggah bukti: ' . $e->getMessage(),
            ], 500);
        }
    }
}
