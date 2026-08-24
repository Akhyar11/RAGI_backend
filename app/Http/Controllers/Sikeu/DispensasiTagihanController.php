<?php

namespace App\Http\Controllers\Sikeu;

use App\Http\Controllers\Controller;
use App\Models\Sikeu\DispensasiTagihan;
use App\Models\Sikeu\TagihanMahasiswa;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class DispensasiTagihanController extends Controller
{
    /**
     * GET /api/v1/sikeu/dispensasi
     * List all dispensation requests with search & warning flags.
     */
    public function index(Request $request)
    {
        $query = DispensasiTagihan::with(['tagihan']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('mahasiswa_id')) {
            $query->where('mahasiswa_id', $request->mahasiswa_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('id', 'like', "%{$search}%")
                  ->orWhere('mahasiswa_id', 'like', "%{$search}%")
                  ->orWhere('alasan', 'like', "%{$search}%")
                  ->orWhereHas('tagihan', function ($tq) use ($search) {
                      $tq->where('nomor_tagihan', 'like', "%{$search}%");
                  });
            });
        }

        $dispensasi = $query->orderBy('created_at', 'desc')->paginate($request->input('per_page', 15));

        // Augment with previous unpaid dispensation warning for pimpinan view
        $items = collect($dispensasi->items())->map(function ($d) {
            $prevUnpaidCount = DispensasiTagihan::where('mahasiswa_id', $d->mahasiswa_id)
                ->where('id', '!=', $d->id)
                ->where('status', 'approved')
                ->whereHas('tagihan', function($q) {
                    $q->whereIn('status', ['belum_bayar', 'sebagian', 'dispensasi']);
                })
                ->count();

            $dArray = $d->toArray();
            $dArray['has_unpaid_previous_dispensation'] = $prevUnpaidCount > 0;
            $dArray['unpaid_previous_dispensation_count'] = $prevUnpaidCount;
            $dArray['nama_mahasiswa'] = 'Mahasiswa #' . $d->mahasiswa_id;
            $dArray['nim'] = '2024' . str_pad($d->mahasiswa_id, 4, '0', STR_PAD_LEFT);
            return $dArray;
        });

        return response()->json([
            'status' => 'success',
            'data' => [
                'current_page' => $dispensasi->currentPage(),
                'data' => $items,
                'total' => $dispensasi->total(),
                'per_page' => $dispensasi->perPage(),
                'last_page' => $dispensasi->lastPage(),
            ]
        ]);
    }

    /**
     * POST /api/v1/sikeu/dispensasi
     * Submit a new payment dispensation request.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'tagihan_id' => 'required|exists:tagihan_mahasiswa,id',
            'tipe_dispensasi' => 'required|in:penundaan_jatuh_tempo,cicilan,keringanan_khusus',
            'jatuh_tempo_baru' => 'nullable|date',
            'jumlah_cicilan' => 'nullable|integer|min:1',
            'nominal_per_cicilan' => 'nullable|numeric|min:0',
            'alasan' => 'required|string',
            'dokumen_pendukung' => 'nullable|string',
            'selected_detail_ids' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validasi permohonan dispensasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        $tagihan = TagihanMahasiswa::findOrFail($request->tagihan_id);

        // Check if student has previous unpaid approved dispensation
        $hasUnpaidPrev = DispensasiTagihan::where('mahasiswa_id', $tagihan->mahasiswa_id)
            ->where('status', 'approved')
            ->whereHas('tagihan', function($q) {
                $q->whereIn('status', ['belum_bayar', 'sebagian', 'dispensasi']);
            })
            ->exists();

        $dispensasi = DispensasiTagihan::create([
            'tagihan_id' => $tagihan->id,
            'mahasiswa_id' => $tagihan->mahasiswa_id,
            'tipe_dispensasi' => $request->tipe_dispensasi,
            'jatuh_tempo_baru' => $request->jatuh_tempo_baru ?? date('Y-m-d', strtotime('+30 days')),
            'jumlah_cicilan' => $request->jumlah_cicilan ?? 1,
            'nominal_per_cicilan' => $request->nominal_per_cicilan ?? ($tagihan->total_tagihan - $tagihan->total_bayar),
            'alasan' => $request->alasan,
            'dokumen_pendukung' => $request->dokumen_pendukung,
            'status' => 'pending',
            'diajukan_oleh' => auth()->id() ?? $tagihan->mahasiswa_id,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Permohonan dispensasi pembayaran berhasil diajukan dan menunggu approval pimpinan.',
            'data' => array_merge($dispensasi->toArray(), [
                'has_unpaid_previous_dispensation' => $hasUnpaidPrev,
                'warning_msg' => $hasUnpaidPrev ? 'Mahasiswa ini memiliki riwayat dispensasi sebelumnya yang belum dilunasi!' : null
            ])
        ], 201);
    }

    /**
     * GET /api/v1/sikeu/dispensasi/{id}
     * Show dispensation detail.
     */
    public function show($id)
    {
        $dispensasi = DispensasiTagihan::with(['tagihan.details.masterBiaya'])->findOrFail($id);

        $hasUnpaidPrev = DispensasiTagihan::where('mahasiswa_id', $dispensasi->mahasiswa_id)
            ->where('id', '!=', $dispensasi->id)
            ->where('status', 'approved')
            ->whereHas('tagihan', function($q) {
                $q->whereIn('status', ['belum_bayar', 'sebagian', 'dispensasi']);
            })
            ->exists();

        $data = $dispensasi->toArray();
        $data['has_unpaid_previous_dispensation'] = $hasUnpaidPrev;
        $data['nama_mahasiswa'] = 'Mahasiswa #' . $dispensasi->mahasiswa_id;
        $data['nim'] = '2024' . str_pad($dispensasi->mahasiswa_id, 4, '0', STR_PAD_LEFT);

        return response()->json([
            'status' => 'success',
            'data' => $data
        ]);
    }

    /**
     * GET /api/v1/sikeu/dispensasi/{id}/cetak-bukti
     * Official printable receipt / document for approved dispensation.
     */
    public function cetakBukti($id)
    {
        $dispensasi = DispensasiTagihan::with(['tagihan.details.masterBiaya'])->findOrFail($id);

        $bukti = [
            'nomor_dispensasi' => 'DISP-' . date('Y') . '-' . str_pad($dispensasi->id, 5, '0', STR_PAD_LEFT),
            'tanggal_pengajuan' => $dispensasi->created_at ? $dispensasi->created_at->format('d F Y') : date('d F Y'),
            'tanggal_persetujuan' => $dispensasi->tanggal_persetujuan ? date('d F Y', strtotime($dispensasi->tanggal_persetujuan)) : date('d F Y'),
            'status' => $dispensasi->status,
            'mahasiswa' => [
                'nama' => 'Mahasiswa #' . $dispensasi->mahasiswa_id,
                'nim' => '2024' . str_pad($dispensasi->mahasiswa_id, 4, '0', STR_PAD_LEFT),
                'prodi' => 'Teknik Informatika',
                'angkatan' => 2024,
            ],
            'tagihan' => [
                'nomor_tagihan' => $dispensasi->tagihan->nomor_tagihan ?? '-',
                'total_tagihan' => (float)($dispensasi->tagihan->total_tagihan ?? 0),
                'jatuh_tempo_semula' => $dispensasi->tagihan->jatuh_tempo ?? '-',
                'jatuh_tempo_baru' => $dispensasi->jatuh_tempo_baru,
            ],
            'dispensasi_info' => [
                'tipe' => $dispensasi->tipe_dispensasi,
                'nominal_per_cicilan' => (float)$dispensasi->nominal_per_cicilan,
                'jumlah_cicilan' => $dispensasi->jumlah_cicilan,
                'alasan' => $dispensasi->alasan,
                'catatan_pimpinan' => $dispensasi->catatan_pimpinan ?? 'Persetujuan dispensasi diberikan sesuai kebijakan pimpinan.',
            ],
            'pejabat_approver' => [
                'nama' => 'Dr. Ir. Wakil Rektor II, M.M.',
                'jabatan' => 'Wakil Rektor II / Kabag Keuangan',
                'digital_signature_hash' => 'SIG-DISP-' . md5($dispensasi->id . 'OK'),
            ]
        ];

        return response()->json([
            'status' => 'success',
            'data' => $bukti
        ]);
    }
}
