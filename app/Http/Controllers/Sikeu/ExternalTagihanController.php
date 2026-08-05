<?php

namespace App\Http\Controllers\Sikeu;

use App\Http\Controllers\Controller;
use App\Models\Sikeu\TagihanMahasiswa;
use App\Models\Sikeu\DetailTagihan;
use App\Models\Sikeu\PotonganTagihan;
use App\Models\Sikeu\JenisBiaya;
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
            'details.*.jenis_biaya_kode' => 'required|string',
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
                $jenisBiaya = JenisBiaya::where('kode', $item['jenis_biaya_kode'])->first();
                $jenisBiayaId = $jenisBiaya ? $jenisBiaya->id : 1;
                $nominal = (float) $item['nominal'];
                $totalNominal += $nominal;

                $detailsData[] = [
                    'jenis_biaya_id' => $jenisBiayaId,
                    'nominal' => $nominal,
                    'potongan' => 0,
                    'nominal_bersih' => $nominal,
                    'keterangan' => $item['keterangan'] ?? 'Komponen tagihan ' . $item['jenis_biaya_kode'],
                ];
            }

            // Compute deductions
            $potonganData = [];
            if ($request->has('potongan') && is_array($request->potongan)) {
                foreach ($request->potongan as $pot) {
                    $nomPot = (float) $pot['nominal_potongan'];
                    $totalPotongan += $nomPot;
                    $potonganData[] = [
                        'beasiswa_id' => null,
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
                'total_bayar' => $totalBayar,
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

            // If no approval required, generate VA automatically
            $vaData = null;
            if (!$requiresApproval) {
                $vaNumber = '888' . date('ymd') . str_pad($tagihan->id, 5, '0', STR_PAD_LEFT);
                $vaData = VirtualAccount::create([
                    'tagihan_id' => $tagihan->id,
                    'va_number' => $vaNumber,
                    'bank_kode' => 'BNI',
                    'bank_nama' => 'Bank BNI',
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
        $query = \App\Models\Sikeu\Pembayaran::with(['tagihan', 'virtualAccount']);

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
                         ->orWhere('mahasiswa_id', 'like', "%{$search}%");
                  });
            });
        }

        $pembayaran = $query->orderBy('waktu_bayar', 'desc')
            ->orderBy('id', 'desc')
            ->paginate($request->input('per_page', 15));

        return response()->json([
            'status' => 'success',
            'data' => $pembayaran
        ]);
    }
}
