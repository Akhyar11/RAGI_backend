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
            'kode_akun' => 'required|string|unique:sikeu_akun_keuangan,kode_akun',
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
     * Filter: search (nomor/keterangan), jenis_sumber, status_posting,
     * dari/sampai (rentang tanggal_jurnal, untuk melihat periode berjalan
     * maupun periode yang sudah ditutup). Pagination: page, per_page.
     */
    public function indexJurnal(Request $request)
    {
        $query = JurnalUmum::with(['details.akun']);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nomor_jurnal', 'like', "%{$search}%")
                    ->orWhere('keterangan', 'like', "%{$search}%");
            });
        }

        if ($request->filled('jenis_sumber')) {
            $query->where('jenis_sumber', $request->jenis_sumber);
        }

        if ($request->filled('status_posting')) {
            $query->where('status_posting', $request->status_posting);
        }

        if ($request->filled('dari')) {
            $query->whereDate('tanggal_jurnal', '>=', $request->dari);
        }

        if ($request->filled('sampai')) {
            $query->whereDate('tanggal_jurnal', '<=', $request->sampai);
        }

        $perPage = min(100, $request->integer('per_page', 15));

        $jurnal = $query->orderBy('tanggal_jurnal', 'desc')
            ->orderBy('id', 'desc')
            ->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar jurnal umum berhasil dimuat',
            'data' => $jurnal->items(),
            'meta' => [
                'current_page' => $jurnal->currentPage(),
                'per_page' => $jurnal->perPage(),
                'total' => $jurnal->total(),
                'last_page' => $jurnal->lastPage(),
                'from' => $jurnal->firstItem(),
                'to' => $jurnal->lastItem(),
            ],
        ]);
    }

    /**
     * GET /api/v1/sikeu/akuntansi/jurnal/{id}
     * Detail satu jurnal beserta rincian akun.
     */
    public function showJurnal($id)
    {
        $jurnal = JurnalUmum::with(['details.akun'])->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'message' => 'Detail jurnal berhasil dimuat',
            'data' => $jurnal,
        ]);
    }

    /**
     * PUT /api/v1/sikeu/akuntansi/jurnal/{id}
     * Edit jurnal MANUAL (post) selagi belum tutup buku. Aman karena:
     * - Jurnal otomatis sistem (referensi_id terisi: pembayaran/tagihan/periode)
     *   dan jurnal penutup (JRN-TUTUP) dikunci; koreksi lewat fitur koreksi/reversal.
     * - Tanggal lama maupun baru tidak boleh masuk periode yang sudah ditutup.
     */
    public function updateJurnal(Request $request, $id)
    {
        $jurnal = JurnalUmum::findOrFail($id);

        if ($jurnal->referensi_id !== null) {
            return response()->json([
                'status' => 'error',
                'message' => 'Jurnal otomatis sistem tidak dapat diedit langsung. Gunakan fitur koreksi/pembatalan transaksi terkait.',
            ], 422);
        }

        if ($jurnal->jenis_sumber === 'penutupan') {
            return response()->json([
                'status' => 'error',
                'message' => 'Jurnal penutup periode tidak dapat diedit.',
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'tanggal_jurnal' => 'sometimes|required|date',
            'jenis_sumber' => 'sometimes|required|in:pembayaran_mahasiswa,pemasukan_hibah,pencairan_kas,pengeluaran_manual,penyesuaian,penutupan',
            'keterangan' => 'sometimes|required|string',
            'details' => 'sometimes|required|array|min:2',
            'details.*.akun_id' => 'required|exists:sikeu_akun_keuangan,id',
            'details.*.debet' => 'required|numeric|min:0',
            'details.*.kredit' => 'required|numeric|min:0',
            'details.*.keterangan' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validasi perubahan jurnal gagal.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $tanggalBaru = $request->filled('tanggal_jurnal') ? $request->tanggal_jurnal : $jurnal->tanggal_jurnal->toDateString();

        foreach ([$jurnal->tanggal_jurnal->toDateString(), $tanggalBaru] as $tgl) {
            $tertutup = PeriodeAkuntansi::where('status', 'ditutup')
                ->where('tanggal_mulai', '<=', $tgl)
                ->where('tanggal_selesai', '>=', $tgl)
                ->exists();
            if ($tertutup) {
                return response()->json([
                    'status' => 'error',
                    'message' => "Tanggal {$tgl} berada pada periode yang sudah ditutup. Jurnal tidak dapat diubah.",
                ], 422);
            }
        }

        try {
            DB::beginTransaction();

            if ($request->filled('tanggal_jurnal')) {
                $jurnal->tanggal_jurnal = $request->tanggal_jurnal;
            }
            if ($request->filled('jenis_sumber')) {
                $jurnal->jenis_sumber = $request->jenis_sumber;
            }
            if ($request->filled('keterangan')) {
                $jurnal->keterangan = $request->keterangan;
            }

            if ($request->has('details')) {
                $totalDebet = 0;
                $totalKredit = 0;
                foreach ($request->details as $d) {
                    $totalDebet += (float) $d['debet'];
                    $totalKredit += (float) $d['kredit'];
                }

                if (abs($totalDebet - $totalKredit) > 0.01 || $totalDebet <= 0) {
                    DB::rollBack();

                    return response()->json([
                        'status' => 'error',
                        'message' => 'Total Debet dan Total Kredit tidak seimbang.',
                    ], 422);
                }

                $jurnal->details()->delete();
                foreach ($request->details as $detail) {
                    DetailJurnalUmum::create([
                        'jurnal_id' => $jurnal->id,
                        'akun_id' => $detail['akun_id'],
                        'debet' => $detail['debet'],
                        'kredit' => $detail['kredit'],
                        'keterangan' => $detail['keterangan'] ?? $jurnal->keterangan,
                    ]);
                }
                $jurnal->total_debet = $totalDebet;
                $jurnal->total_kredit = $totalKredit;
            }

            $jurnal->save();

            DB::commit();

            \App\Services\AuditLogService::record(
                module: 'SIKEU',
                action: 'update',
                tableName: 'sikeu_jurnal_umum',
                recordId: $jurnal->id,
                newValues: ['nomor_jurnal' => $jurnal->nomor_jurnal, 'tanggal_jurnal' => $tanggalBaru],
                request: $request,
            );

            return response()->json([
                'status' => 'success',
                'message' => 'Jurnal berhasil diperbarui.',
                'data' => $jurnal->load('details.akun'),
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => 'Gagal memperbarui jurnal: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * DELETE /api/v1/sikeu/akuntansi/jurnal/{id}
     * Hapus jurnal MANUAL selagi periodenya belum ditutup.
     * Jurnal otomatis & penutup terkunci (gunakan koreksi/pembatalan).
     */
    public function destroyJurnal(Request $request, $id)
    {
        $jurnal = JurnalUmum::findOrFail($id);

        if ($jurnal->referensi_id !== null) {
            return response()->json([
                'status' => 'error',
                'message' => 'Jurnal otomatis sistem tidak dapat dihapus langsung. Gunakan fitur koreksi/pembatalan transaksi terkait.',
            ], 422);
        }

        if ($jurnal->jenis_sumber === 'penutupan') {
            return response()->json([
                'status' => 'error',
                'message' => 'Jurnal penutup periode tidak dapat dihapus.',
            ], 422);
        }

        $tertutup = PeriodeAkuntansi::where('status', 'ditutup')
            ->where('tanggal_mulai', '<=', $jurnal->tanggal_jurnal->toDateString())
            ->where('tanggal_selesai', '>=', $jurnal->tanggal_jurnal->toDateString())
            ->exists();

        if ($tertutup) {
            return response()->json([
                'status' => 'error',
                'message' => 'Jurnal berada pada periode yang sudah ditutup dan tidak dapat dihapus.',
            ], 422);
        }

        try {
            DB::beginTransaction();

            $nomor = $jurnal->nomor_jurnal;
            $jurnal->details()->delete();
            $jurnal->delete();

            DB::commit();

            \App\Services\AuditLogService::record(
                module: 'SIKEU',
                action: 'delete',
                tableName: 'sikeu_jurnal_umum',
                recordId: (int) $id,
                oldValues: ['nomor_jurnal' => $nomor],
                request: $request,
            );

            return response()->json([
                'status' => 'success',
                'message' => "Jurnal manual {$nomor} berhasil dihapus.",
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => 'Gagal menghapus jurnal: ' . $e->getMessage(),
            ], 500);
        }
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
            'details.*.akun_id' => 'required|exists:sikeu_akun_keuangan,id',
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

            $nomorJurnal = \App\Services\Sikeu\JurnalSikeuService::prefix('manual') . date('Ymd') . '-' . Str::random(4);

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
            'message' => 'Buku besar berhasil dimuat',
            'data' => $items->items(),
            'meta' => [
                'current_page' => $items->currentPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
                'last_page' => $items->lastPage(),
                'from' => $items->firstItem(),
                'to' => $items->lastItem(),
            ],
        ]);
    }

    /**
     * GET /api/v1/sikeu/periode
     * Daftar periode akuntansi (untuk tutup buku).
     */
    public function indexPeriode()
    {
        $items = PeriodeAkuntansi::orderBy('tanggal_mulai', 'desc')->get();

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar periode akuntansi berhasil dimuat',
            'data' => $items,
        ]);
    }

    /**
     * POST /api/v1/sikeu/periode
     * Buat periode akuntansi baru (status terbuka).
     */
    public function storePeriode(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nama_periode' => 'required|string|max:100',
            'tanggal_mulai' => 'required|date',
            'tanggal_selesai' => 'required|date|after_or_equal:tanggal_mulai',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validasi periode gagal.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $overlap = PeriodeAkuntansi::where(function ($q) use ($request) {
            $q->whereBetween('tanggal_mulai', [$request->tanggal_mulai, $request->tanggal_selesai])
                ->orWhereBetween('tanggal_selesai', [$request->tanggal_mulai, $request->tanggal_selesai])
                ->orWhere(function ($qq) use ($request) {
                    $qq->where('tanggal_mulai', '<=', $request->tanggal_mulai)
                        ->where('tanggal_selesai', '>=', $request->tanggal_selesai);
                });
        })->exists();

        if ($overlap) {
            return response()->json([
                'status' => 'error',
                'message' => 'Rentang periode bertabrakan dengan periode yang sudah ada.',
            ], 422);
        }

        $mulai = \Carbon\Carbon::parse($request->tanggal_mulai);
        $periode = PeriodeAkuntansi::create([
            'nama_periode' => $request->nama_periode,
            'tahun' => (int) $mulai->format('Y'),
            'bulan' => (int) $mulai->format('m'),
            'tanggal_mulai' => $request->tanggal_mulai,
            'tanggal_selesai' => $request->tanggal_selesai,
            'status' => 'terbuka',
        ]);

        \App\Services\AuditLogService::record(
            module: 'SIKEU',
            action: 'create',
            tableName: 'sikeu_periode_akuntansi',
            recordId: $periode->id,
            newValues: $periode->toArray(),
            request: $request,
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Periode akuntansi berhasil dibuat.',
            'data' => $periode,
        ], 201);
    }

    /**
     * POST /api/v1/sikeu/periode/{id}/tutup
     * Tutup buku: kunci periode + jurnal penutup (pendapatan & beban -> 301.02).
     * Transaksi baru pada rentang yang ditutup otomatis ditolak kasir/koreksi.
     */
    public function tutupPeriode(Request $request, $id)
    {
        $periode = PeriodeAkuntansi::findOrFail($id);

        if ($periode->status === 'ditutup') {
            return response()->json([
                'status' => 'error',
                'message' => 'Periode ini sudah ditutup sebelumnya.',
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Saldo pendapatan & beban periode ini (posted, bukan jurnal penutup)
            $rows = DetailJurnalUmum::join('sikeu_jurnal_umum as j', 'j.id', '=', 'sikeu_detail_jurnal_umum.jurnal_id')
                ->join('sikeu_akun_keuangan as a', 'a.id', '=', 'sikeu_detail_jurnal_umum.akun_id')
                ->where('j.status_posting', 'posted')
                ->whereDate('j.tanggal_jurnal', '>=', $periode->tanggal_mulai->toDateString())
                ->whereDate('j.tanggal_jurnal', '<=', $periode->tanggal_selesai->toDateString())
                ->where('j.jenis_sumber', '!=', 'penutupan')
                ->whereIn('a.kelompok', ['pendapatan', 'beban'])
                ->selectRaw('a.id as akun_id, a.kode_akun, a.nama_akun, a.kelompok, SUM(sikeu_detail_jurnal_umum.debet) as debet, SUM(sikeu_detail_jurnal_umum.kredit) as kredit')
                ->groupBy('a.id', 'a.kode_akun', 'a.nama_akun', 'a.kelompok')
                ->get();

            $labaDitahan = AkunKeuangan::where('kode_akun', '301.02')->first()
                ?? AkunKeuangan::where('kelompok', 'ekuitas')->first();

            if (!$labaDitahan) {
                throw new \RuntimeException('Akun Laba Ditahan (301.02) belum dikonfigurasi.');
            }

            $jurnal = JurnalUmum::create([
                'nomor_jurnal' => \App\Services\Sikeu\JurnalSikeuService::prefix('penutupan') . '-' . date('Ymd') . '-' . strtoupper(Str::random(4)),
                'tanggal_jurnal' => $periode->tanggal_selesai->toDateString(),
                'periode_id' => $periode->id,
                'jenis_sumber' => 'penutupan',
                'referensi_id' => $periode->id,
                'keterangan' => "Jurnal penutup periode {$periode->nama_periode}",
                'status_posting' => 'posted',
                'total_debet' => 0,
                'total_kredit' => 0,
                'created_by' => auth()->id(),
                'posted_by' => auth()->id(),
                'posted_at' => now(),
            ]);

            $totalTutup = 0;
            foreach ($rows as $row) {
                if ($row->kelompok === 'pendapatan') {
                    $saldo = (float) $row->kredit - (float) $row->debet;
                    if ($saldo == 0) {
                        continue;
                    }
                    // Dr Pendapatan / Cr Laba Ditahan
                    DetailJurnalUmum::create(['jurnal_id' => $jurnal->id, 'akun_id' => $row->akun_id, 'debet' => $saldo, 'kredit' => 0, 'keterangan' => "Penutup {$row->kode_akun} {$periode->nama_periode}"]);
                    DetailJurnalUmum::create(['jurnal_id' => $jurnal->id, 'akun_id' => $labaDitahan->id, 'debet' => 0, 'kredit' => $saldo, 'keterangan' => "Penutup {$row->kode_akun} {$periode->nama_periode}"]);
                    $totalTutup += $saldo;
                } else {
                    $saldo = (float) $row->debet - (float) $row->kredit;
                    if ($saldo == 0) {
                        continue;
                    }
                    // Dr Laba Ditahan / Cr Beban
                    DetailJurnalUmum::create(['jurnal_id' => $jurnal->id, 'akun_id' => $labaDitahan->id, 'debet' => $saldo, 'kredit' => 0, 'keterangan' => "Penutup {$row->kode_akun} {$periode->nama_periode}"]);
                    DetailJurnalUmum::create(['jurnal_id' => $jurnal->id, 'akun_id' => $row->akun_id, 'debet' => 0, 'kredit' => $saldo, 'keterangan' => "Penutup {$row->kode_akun} {$periode->nama_periode}"]);
                    $totalTutup += $saldo;
                }
            }

            $jurnal->update(['total_debet' => $totalTutup, 'total_kredit' => $totalTutup]);

            $periode->update([
                'status' => 'ditutup',
                'ditutup_oleh' => auth()->id(),
                'ditutup_pada' => now(),
            ]);

            DB::commit();

            \App\Services\AuditLogService::record(
                module: 'SIKEU',
                action: 'approve',
                tableName: 'sikeu_periode_akuntansi',
                recordId: $periode->id,
                newValues: ['status' => 'ditutup', 'jurnal_penutup_id' => $jurnal->id, 'total_tutup' => $totalTutup],
                request: $request,
            );

            return response()->json([
                'status' => 'success',
                'message' => "Periode {$periode->nama_periode} berhasil ditutup. Jurnal penutup {$jurnal->nomor_jurnal} diterbitkan.",
                'data' => [
                    'periode' => $periode->fresh(),
                    'jurnal_penutup' => $jurnal->load('details.akun'),
                ],
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => 'Gagal menutup periode: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /api/v1/sikeu/pengaturan-jurnal
     * Daftar prefix nomor jurnal per jenis (bisa diubah mengikuti kebijakan kampus).
     */
    public function indexPrefixSetting()
    {
        return response()->json([
            'status' => 'success',
            'message' => 'Pengaturan prefix nomor jurnal berhasil dimuat',
            'data' => \App\Services\Sikeu\JurnalSikeuService::daftarPrefix(),
        ]);
    }

    /**
     * PUT /api/v1/sikeu/pengaturan-jurnal
     * Simpan prefix nomor jurnal. Format: A-Z, 0-9, strip, maks 12 karakter.
     */
    public function updatePrefixSetting(Request $request)
    {
        $defaults = \App\Services\Sikeu\JurnalSikeuService::PREFIX_DEFAULTS;

        $validator = Validator::make($request->all(), [
            'prefix' => 'required|array',
            'prefix.*' => ['nullable', 'string', 'max:12', 'regex:/^[A-Za-z0-9-]+$/'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validasi prefix gagal.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $disimpan = [];
        foreach ($request->input('prefix', []) as $jenis => $nilai) {
            if (!array_key_exists($jenis, $defaults)) {
                continue;
            }
            $nilai = strtoupper(trim((string) ($nilai ?? '')));
            if ($nilai === '') {
                $nilai = $defaults[$jenis];
            }
            \App\Models\SystemSetting::set('sikeu.jurnal_prefix_' . $jenis, $nilai);
            $disimpan[$jenis] = $nilai;
        }

        \App\Services\AuditLogService::record(
            module: 'SIKEU',
            action: 'update',
            tableName: 'system_settings',
            newValues: ['prefix' => $disimpan],
            request: $request,
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Prefix nomor jurnal berhasil disimpan.',
            'data' => \App\Services\Sikeu\JurnalSikeuService::daftarPrefix(),
        ]);
    }

    /**
     * GET /api/v1/sikeu/akuntansi/laporan
     * Generate 4 Standard Financial Statements with real database records:
     * 1. Laba Rugi / Aktivitas
     * 2. Neraca Posisi Keuangan
     * 3. Arus Kas
     * 4. Perubahan Ekuitas
     */
    /**
     * GET /api/v1/sikeu/akuntansi/laporan
     * 4 Laporan Keuangan dari BUKU (jurnal posted):
     * 1. Laba Rugi, 2. Neraca, 3. Arus Kas, 4. Perubahan Ekuitas.
     * Saldo akun dihitung neto (menang terhadap jurnal penutup),
     * akun per bucket mengikuti konvensi prefix COA (lihat SKILL akuntansi SIKEU).
     */
    public function laporanKeuangan(Request $request)
    {
        $dari = $request->filled('dari') ? $request->date('dari') : null;
        $sampai = $request->filled('sampai') ? $request->date('sampai') : null;

        // Mutasi akun: [kode => ['debet' => x, 'kredit' => y]]
        $mutasi = function (?string $sampaiBatas = null) {
            $q = DetailJurnalUmum::join('sikeu_jurnal_umum as j', 'j.id', '=', 'sikeu_detail_jurnal_umum.jurnal_id')
                ->join('sikeu_akun_keuangan as a', 'a.id', '=', 'sikeu_detail_jurnal_umum.akun_id')
                ->where('j.status_posting', 'posted')
                ->selectRaw('a.kode_akun, a.kelompok, SUM(sikeu_detail_jurnal_umum.debet) as debet, SUM(sikeu_detail_jurnal_umum.kredit) as kredit')
                ->groupBy('a.kode_akun', 'a.kelompok');

            return $q->get();
        };

        $semua = $mutasi();
        $netKredit = fn($row) => (float) $row->kredit - (float) $row->debet; // pendapatan, liabilitas, ekuitas
        $netDebet = fn($row) => (float) $row->debet - (float) $row->kredit;   // aset, beban

        // Arus (pendapatan/beban/arus kas): batasi rentang tanggal bila diminta
        $arus = $semua;
        if ($dari || $sampai) {
            $q = DetailJurnalUmum::join('sikeu_jurnal_umum as j', 'j.id', '=', 'sikeu_detail_jurnal_umum.jurnal_id')
                ->join('sikeu_akun_keuangan as a', 'a.id', '=', 'sikeu_detail_jurnal_umum.akun_id')
                ->where('j.status_posting', 'posted')
                ->selectRaw('a.kode_akun, a.kelompok, SUM(sikeu_detail_jurnal_umum.debet) as debet, SUM(sikeu_detail_jurnal_umum.kredit) as kredit')
                ->groupBy('a.kode_akun', 'a.kelompok');
            if ($dari) {
                $q->whereDate('j.tanggal_jurnal', '>=', $dari->toDateString());
            }
            if ($sampai) {
                $q->whereDate('j.tanggal_jurnal', '<=', $sampai->toDateString());
            }
            $arus = $q->get();
        }

        $mulaiDengan = fn($rows, array $prefixes) => $rows->filter(
            fn($r) => collect($prefixes)->contains(fn($p) => str_starts_with((string) $r->kode_akun, $p))
        );

        // 1. Pendapatan (neto kredit)
        $rev = $mulaiDengan($arus->where('kelompok', 'pendapatan'), ['401', '402', '403']);
        $pendapatanMahasiswa = $mulaiDengan($rev, ['401'])->sum($netKredit);
        $pendapatanHibah = $mulaiDengan($rev, ['402.01'])->sum($netKredit);
        $pendapatanEksternalLain = $mulaiDengan($rev, ['402.02', '403'])->sum($netKredit);
        $totalPendapatan = $pendapatanMahasiswa + $pendapatanHibah + $pendapatanEksternalLain;

        // 2. Beban (neto debet)
        $beban = $arus->where('kelompok', 'beban');
        $bebanOperasional = $mulaiDengan($beban, ['502.01', '505.01'])->sum($netDebet);
        $bebanPemeliharaan = $mulaiDengan($beban, ['502.02'])->sum($netDebet);
        $bebanLaboratorium = $mulaiDengan($beban, ['502.03'])->sum($netDebet);
        $bebanHonorarium = $mulaiDengan($beban, ['501'])->sum($netDebet);
        $bebanLainnya = $mulaiDengan($beban, ['503', '504'])->sum($netDebet);
        $totalBeban = $bebanOperasional + $bebanPemeliharaan + $bebanLaboratorium + $bebanHonorarium + $bebanLainnya;

        $surplusDefisit = $totalPendapatan - $totalBeban;

        // 3. Posisi (kumulatif s.d. tanggal akhir bila difilter)
        $posisi = $semua;
        if ($sampai) {
            $posisi = DetailJurnalUmum::join('sikeu_jurnal_umum as j', 'j.id', '=', 'sikeu_detail_jurnal_umum.jurnal_id')
                ->join('sikeu_akun_keuangan as a', 'a.id', '=', 'sikeu_detail_jurnal_umum.akun_id')
                ->where('j.status_posting', 'posted')
                ->whereDate('j.tanggal_jurnal', '<=', $sampai->toDateString())
                ->selectRaw('a.kode_akun, a.kelompok, SUM(sikeu_detail_jurnal_umum.debet) as debet, SUM(sikeu_detail_jurnal_umum.kredit) as kredit')
                ->groupBy('a.kode_akun', 'a.kelompok')
                ->get();
        }

        $kasBank = $mulaiDengan($posisi->where('kelompok', 'aset'), ['101', '102'])->sum($netDebet);
        $piutangMahasiswa = $mulaiDengan($posisi->where('kelompok', 'aset'), ['103'])->sum($netDebet);
        $asetTetapBuku = $mulaiDengan($posisi->where('kelompok', 'aset'), ['150'])->sum($netDebet);
        // Saldo awal aset tetap manual (belum ada jurnal mutasi aset tetap)
        $asetTetapManual = (float) (\App\Models\SystemSetting::get('sikeu.aset_tetap', 0));
        $asetTetap = $asetTetapBuku + $asetTetapManual;
        $totalAset = $kasBank + $piutangMahasiswa + $asetTetap;

        // 4. Liabilitas (utang pajak 202.x dari buku; tanpa jurnal pajak => 0)
        $utangPajak = $mulaiDengan($posisi->where('kelompok', 'liabilitas'), ['202'])->sum($netKredit);

        // 5. Ekuitas: laba ditahan buku + plug penyeimbang
        $labaDitahan = $mulaiDengan($posisi->where('kelompok', 'ekuitas'), ['301'])->sum($netKredit);
        $ekuitasAwal = $totalAset - $utangPajak - $surplusDefisit;
        $totalLiabilitasEkuitas = $utangPajak + $ekuitasAwal + $surplusDefisit;

        // 6. Arus kas dari mutasi kas/bank buku
        $kasFlow = $mulaiDengan($arus->where('kelompok', 'aset'), ['101', '102']);
        $inflowKas = $kasFlow->sum(fn($r) => (float) $r->debet);
        $outflowKas = $kasFlow->sum(fn($r) => (float) $r->kredit);
        $arusKasOperasional = $inflowKas - $outflowKas;

        $labelPeriode = ($dari || $sampai)
            ? trim(($dari ? $dari->format('d M Y') : 'Awal') . ' – ' . ($sampai ? $sampai->format('d M Y') : 'Kini'))
            : date('F Y');

        return response()->json([
            'status' => 'success',
            'message' => 'Laporan keuangan berhasil dimuat',
            'data' => [
                'periode' => $labelPeriode,
                'laba_rugi' => [
                    'pendapatan' => [
                        'pendapatan_mahasiswa' => $pendapatanMahasiswa,
                        'pendapatan_hibah' => $pendapatanHibah,
                        'pendapatan_eksternal' => $pendapatanEksternalLain,
                        'total_pendapatan' => $totalPendapatan,
                    ],
                    'beban' => [
                        'beban_operasional' => $bebanOperasional,
                        'beban_pemeliharaan' => $bebanPemeliharaan,
                        'beban_laboratorium' => $bebanLaboratorium,
                        'beban_honorarium' => $bebanHonorarium,
                        'beban_lainnya' => $bebanLainnya,
                        'total_beban' => $totalBeban,
                    ],
                    'surplus_defisit' => $surplusDefisit,
                ],
                'neraca' => [
                    'aset' => [
                        'kas_bank' => $kasBank,
                        'piutang_mahasiswa' => $piutangMahasiswa,
                        'aset_tetap' => $asetTetap,
                        'total_aset' => $totalAset,
                    ],
                    'liabilitas' => [
                        'utang_pajak' => $utangPajak,
                        'total_liabilitas' => $utangPajak,
                    ],
                    'ekuitas' => [
                        'ekuitas_awal' => $ekuitasAwal,
                        'laba_ditahan_301' => $labaDitahan,
                        'surplus_tahun_berjalan' => $surplusDefisit,
                        'total_ekuitas' => $ekuitasAwal + $surplusDefisit,
                    ],
                    'total_pasiva' => $totalLiabilitasEkuitas,
                ],
                'arus_kas' => [
                    'arus_kas_operasional' => $arusKasOperasional,
                    'arus_kas_investasi' => 0,
                    'arus_kas_pendanaan' => 0,
                    'saldo_akhir_kas' => $kasBank,
                ],
                'perubahan_ekuitas' => [
                    'saldo_awal' => $ekuitasAwal,
                    'surplus_defisit' => $surplusDefisit,
                    'saldo_akhir' => $ekuitasAwal + $surplusDefisit,
                ]
            ]
        ]);
    }
}
