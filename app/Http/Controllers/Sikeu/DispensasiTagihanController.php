<?php

namespace App\Http\Controllers\Sikeu;

use App\Http\Controllers\Controller;
use App\Services\AuditLogService;
use App\Models\Sikeu\DispensasiTagihan;
use App\Models\Sikeu\TagihanMahasiswa;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class DispensasiTagihanController extends Controller
{
    /**
     * Id mahasiswa milik pemanggil (untuk scoping mandiri).
     * Cerminan logika resolve di MahasiswaTagihanController.
     */
    protected function ownMahasiswaId(Request $request): ?int
    {
        $user = $request->user();
        if (!$user) {
            return null;
        }

        $mhs = \App\Models\Siakad\Mahasiswa::where('user_id', $user->id)->first();
        if ($mhs) {
            return $mhs->id;
        }
        if (!empty($user->username)) {
            $mhsByNim = \App\Models\Siakad\Mahasiswa::where('nim', $user->username)->first();
            if ($mhsByNim) {
                return $mhsByNim->id;
            }
        }
        if (!empty($user->email)) {
            $mhsByEmail = \App\Models\Siakad\Mahasiswa::where('email', $user->email)->first();
            if ($mhsByEmail) {
                return $mhsByEmail->id;
            }
        }

        return null;
    }

    /**
     * True bila pemanggil adalah mahasiswa murni (bukan staf/admin).
     */
    protected function isStudentCaller(Request $request): bool
    {
        $user = $request->user();
        if (!$user) {
            return false;
        }
        if (method_exists($user, 'isAdmin') && ($user->isAdmin() || $user->isSuperAdmin())) {
            return false;
        }
        $slugs = $user->roles()->pluck('slug')->map(fn ($s) => strtolower((string) $s))->toArray();

        return in_array('mahasiswa', $slugs)
            && empty(array_intersect($slugs, ['superadmin', 'admin', 'operator_sikeu', 'kabag_keuangan', 'pimpinan', 'tendik', 'dosen']));
    }

    /**
     * GET /api/v1/sikeu/dispensasi
     * List all dispensation requests with search & warning flags.
     * Pemanggil mahasiswa otomatis dibatasi hanya pada datanya sendiri.
     */
    public function index(Request $request)
    {
        $query = DispensasiTagihan::with(['tagihan']);

        if ($this->isStudentCaller($request)) {
            $ownId = $this->ownMahasiswaId($request);
            if (!$ownId) {
                return response()->json([
                    'status' => 'success',
                    'data' => [],
                    'meta' => ['current_page' => 1, 'per_page' => 15, 'total' => 0, 'last_page' => 1],
                ]);
            }
            $query->where('mahasiswa_id', $ownId);
        } elseif ($request->filled('mahasiswa_id')) {
            $query->where('mahasiswa_id', $request->mahasiswa_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
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

        $query->with(['tagihan.pembayarans', 'mahasiswa.programStudi', 'tipeTagihanMahasiswa']);

        $dispensasi = $query->orderBy('created_at', 'desc')->paginate($request->input('per_page', 15));

        // Batch compute previous unpaid dispensation count to eliminate N+1 query
        $mhsIds = collect($dispensasi->items())->pluck('mahasiswa_id')->unique()->filter()->values();
        $unpaidCounts = [];
        if ($mhsIds->isNotEmpty()) {
            $unpaidCounts = DispensasiTagihan::whereIn('mahasiswa_id', $mhsIds)
                ->where('status', 'approved')
                ->whereHas('tagihan', function($q) {
                    $q->whereIn('status', ['belum_bayar', 'sebagian', 'dispensasi']);
                })
                ->selectRaw('mahasiswa_id, count(*) as total_unpaid')
                ->groupBy('mahasiswa_id')
                ->pluck('total_unpaid', 'mahasiswa_id')
                ->toArray();
        }

        // Augment with previous unpaid dispensation warning for pimpinan view
        $items = collect($dispensasi->items())->map(function ($d) use ($unpaidCounts) {
            $prevUnpaidCount = $unpaidCounts[$d->mahasiswa_id] ?? 0;
            if ($d->status === 'approved' && $d->tagihan && in_array($d->tagihan->status, ['belum_bayar', 'sebagian', 'dispensasi']) && $prevUnpaidCount > 0) {
                $prevUnpaidCount -= 1;
            }

            $mhs = $d->mahasiswa;
            $tipeMhs = $d->tipeTagihanMahasiswa;

            $dArray = $d->toArray();
            $cicilanSummary = $d->cicilanPaymentsSummary();
            $dArray['cicilan_payment_count'] = $cicilanSummary['count'];
            $dArray['cicilan_total_bayar'] = $cicilanSummary['total'];
            $dArray['has_unpaid_previous_dispensation'] = $prevUnpaidCount > 0;
            $dArray['unpaid_previous_dispensation_count'] = $prevUnpaidCount;
            $dArray['nama_mahasiswa'] = $mhs?->nama_lengkap ?? $tipeMhs?->nama_mahasiswa ?? ('Mahasiswa #' . $d->mahasiswa_id);
            $dArray['nim'] = $mhs?->nim ?? $tipeMhs?->nim ?? '';
            $dArray['prodi'] = $mhs?->programStudi?->nama ?? $tipeMhs?->program_studi?->nama ?? '';
            $dArray['allow_krs'] = (bool)($d->allow_krs ?? true);
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
            'tagihan_id' => 'required|exists:sikeu_tagihan_mahasiswa,id',
            'tipe_dispensasi' => 'required|in:penundaan_jatuh_tempo,cicilan,keringanan_khusus',
            'jatuh_tempo_baru' => 'nullable|date',
            'jumlah_cicilan' => 'nullable|integer|min:1',
            'nominal_per_cicilan' => 'nullable|numeric|min:0',
            'alasan' => 'required|string',
            'allow_krs' => 'nullable|boolean',
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
            'allow_krs' => $request->has('allow_krs') ? (bool)$request->allow_krs : true,
            'dokumen_pendukung' => $request->dokumen_pendukung,
            'status' => 'pending',
            'diajukan_oleh' => auth()->id() ?? $tagihan->mahasiswa_id,
        ]);

        // Update status tagihan menjadi dispensasi
        $tagihan->update(['status' => 'dispensasi']);

        AuditLogService::record(
            module: 'SIKEU',
            action: 'create',
            tableName: 'sikeu_dispensasi_tagihan',
            recordId: $dispensasi->id,
            newValues: [
                'tagihan_id' => $tagihan->id,
                'nomor_tagihan' => $tagihan->nomor_tagihan,
                'tipe_dispensasi' => $dispensasi->tipe_dispensasi,
                'nominal_per_cicilan' => (float) $dispensasi->nominal_per_cicilan,
                'jumlah_cicilan' => $dispensasi->jumlah_cicilan,
            ],
            request: $request,
        );

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
        $cicilanSummary = $dispensasi->cicilanPaymentsSummary();
        $data['cicilan_payment_count'] = $cicilanSummary['count'];
        $data['cicilan_total_bayar'] = $cicilanSummary['total'];
        $data['has_unpaid_previous_dispensation'] = $hasUnpaidPrev;

        $mhs = \App\Models\Siakad\Mahasiswa::with('programStudi')->find($dispensasi->mahasiswa_id);
        $data['nama_mahasiswa'] = $mhs?->nama_lengkap ?? ('Mahasiswa #' . $dispensasi->mahasiswa_id);
        $data['nim'] = $mhs?->nim ?? '';

        return response()->json([
            'status' => 'success',
            'data' => $data
        ]);
    }

    /**
     * GET /api/v1/sikeu/dispensasi/{id}/cetak-bukti
     * Official printable receipt / document for approved dispensation.
     */
    public function cetakBukti(Request $request, $id)
    {
        $dispensasi = DispensasiTagihan::with(['tagihan.details.masterBiaya'])->findOrFail($id);

        if ($this->isStudentCaller($request)) {
            $ownId = $this->ownMahasiswaId($request);
            if (!$ownId || (int) $dispensasi->mahasiswa_id !== (int) $ownId) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Dokumen ini bukan milik Anda.',
                ], 403);
            }
        }

        if ($dispensasi->status !== 'approved') {
            return response()->json([
                'status' => 'error',
                'message' => 'Hanya dispensasi yang telah disetujui pimpinan yang dapat dicetak surat buktinya.',
            ], 422);
        }

        $mhs = \App\Models\Siakad\Mahasiswa::with('programStudi')->find($dispensasi->mahasiswa_id);

        $signatureHash = $dispensasi->signature_hash
            ?: DispensasiTagihan::makeSignatureHash($dispensasi->id, $dispensasi->mahasiswa_id, $dispensasi->jatuh_tempo_baru);

        $bukti = [
            'nomor_dispensasi' => 'DISP-' . date('Y') . '-' . str_pad($dispensasi->id, 5, '0', STR_PAD_LEFT),
            'tanggal_pengajuan' => $dispensasi->created_at ? $dispensasi->created_at->format('d F Y') : date('d F Y'),
            'tanggal_persetujuan' => $dispensasi->tanggal_persetujuan ? date('d F Y', strtotime($dispensasi->tanggal_persetujuan)) : date('d F Y'),
            'status' => $dispensasi->status,
            'mahasiswa' => [
                'nama' => $mhs?->nama_lengkap ?? ('Mahasiswa #' . $dispensasi->mahasiswa_id),
                'nim' => $mhs?->nim ?? '',
                'prodi' => $mhs?->programStudi?->nama ?? '',
                'angkatan' => $mhs?->angkatan ?? null,
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
                'nama' => $dispensasi->disetujui_oleh ? $this->approverName($dispensasi->disetujui_oleh) : '',
                'jabatan' => 'Wakil Rektor II / Kabag Keuangan',
                'digital_signature_hash' => $signatureHash,
            ]
        ];

        return response()->json([
            'status' => 'success',
            'data' => $bukti
        ]);
    }

    /**
     * DELETE /api/v1/sikeu/dispensasi/{id}
     * Hapus permohonan dispensasi. Ditolak bila tagihan terkait sudah
     * memiliki pembayaran cicilan tercatat agar record tidak hilang.
     */
    public function destroy($id)
    {
        $dispensasi = DispensasiTagihan::with(['tagihan.pembayarans'])->findOrFail($id);

        $tagihan = $dispensasi->tagihan;
        // Hanya pembayaran cicilan "beneran" (tercatat setelah dispensasi
        // disetujui/diajukan) yang menghalangi hapus. Pembayaran biasa
        // sebelum skema cicilan ada tidak dihitung.
        $cicilan = $dispensasi->cicilanPaymentsSummary();
        $totalBayar = $tagihan ? (float) $tagihan->total_bayar : 0;

        if ($cicilan['count'] > 0 || $cicilan['total'] > 0) {
            return response()->json([
                'status' => 'error',
                'message' => "Dispensasi tidak dapat dihapus karena tagihan {$tagihan->nomor_tagihan} sudah memiliki {$cicilan['count']} pembayaran cicilan tercatat (" . number_format($cicilan['total'], 0, ',', '.') . " IDR) setelah skema ini disetujui. Hapus/batalkan pembayaran cicilan tersebut terlebih dahulu bila memang harus dihapus.",
                'data' => [
                    'payment_count' => $cicilan['count'],
                    'total_bayar' => $cicilan['total'],
                ],
            ], 422);
        }

        try {
            \Illuminate\Support\Facades\DB::beginTransaction();

            // Kembalikan status tagihan bila masih berstatus dispensasi
            if ($tagihan && $tagihan->status === 'dispensasi') {
                $totalBersih = (float) ($tagihan->total_tagihan + $tagihan->total_denda - $tagihan->total_potongan);
                $newStatus = $totalBayar >= $totalBersih ? 'lunas' : ($totalBayar > 0 ? 'sebagian' : 'belum_bayar');
                $tagihan->update(['status' => $newStatus]);
            }

            $nomor = 'DISP-' . date('Y') . '-' . str_pad($dispensasi->id, 5, '0', STR_PAD_LEFT);
            $dispensasi->delete();

            \Illuminate\Support\Facades\DB::commit();

            AuditLogService::record(
                module: 'SIKEU',
                action: 'delete',
                tableName: 'sikeu_dispensasi_tagihan',
                recordId: $id,
                oldValues: ['nomor_dispensasi' => $nomor, 'status' => $dispensasi->status],
            );

            return response()->json([
                'status' => 'success',
                'message' => "Dispensasi {$nomor} berhasil dihapus.",
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => 'Gagal menghapus dispensasi: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Public Dispensasi Verification Endpoint
     * Accessible by scanning QR Code on printed dispensation letter.
     */
    public function validasiDispensasiPublik(string $signature_hash)
    {
        $dispensasi = DispensasiTagihan::with(['tagihan.details.masterBiaya'])
            ->where('signature_hash', $signature_hash)
            ->first();

        if (!$dispensasi) {
            return response()->json([
                'status' => 'error',
                'message' => 'Surat dispensasi tidak ditemukan atau tidak valid.',
                'data' => null,
            ], 404);
        }

        $mhs = \App\Models\Siakad\Mahasiswa::with('programStudi')->find($dispensasi->mahasiswa_id);
        $t = $dispensasi->tagihan;

        $isValid = ($dispensasi->status === 'approved');
        $totalBersih = $t ? (float) ($t->total_tagihan + ($t->total_denda ?? 0) - $t->total_potongan) : 0;
        $sisa = $t ? max(0, $totalBersih - (float) $t->total_bayar) : 0;

        return response()->json([
            'status' => 'success',
            'message' => $isValid
                ? 'Surat dispensasi sah dan terverifikasi di sistem keuangan kampus.'
                : 'Surat ditemukan namun berstatus: ' . strtoupper($dispensasi->status),
            'data' => [
                'nomor_dispensasi' => 'DISP-' . date('Y', strtotime($dispensasi->created_at ?? 'now')) . '-' . str_pad($dispensasi->id, 5, '0', STR_PAD_LEFT),
                'status' => $dispensasi->status,
                'is_valid' => $isValid,
                'verified_at' => now()->format('Y-m-d H:i:s'),
                'tipe_dispensasi' => $dispensasi->tipe_dispensasi,
                'nominal_per_cicilan' => (float) $dispensasi->nominal_per_cicilan,
                'jumlah_cicilan' => $dispensasi->jumlah_cicilan,
                'jatuh_tempo_baru' => $dispensasi->jatuh_tempo_baru ? $dispensasi->jatuh_tempo_baru->format('Y-m-d') : null,
                'tanggal_persetujuan' => $dispensasi->tanggal_persetujuan ? $dispensasi->tanggal_persetujuan->format('Y-m-d H:i:s') : null,
                'mahasiswa' => [
                    'nama_mahasiswa' => $mhs?->nama_lengkap ?? ('Mahasiswa #' . $dispensasi->mahasiswa_id),
                    'nim' => $mhs?->nim ?? '',
                    'program_studi' => $mhs?->programStudi?->nama ?? '',
                    'tahun_angkatan' => $mhs?->angkatan ?? null,
                ],
                'tagihan' => [
                    'nomor_tagihan' => $t?->nomor_tagihan,
                    'total_tagihan' => (float) ($t?->total_tagihan ?? 0),
                    'total_bayar' => (float) ($t?->total_bayar ?? 0),
                    'sisa' => $sisa,
                    'status' => $t?->status,
                ],
                'security_hash' => $dispensasi->signature_hash,
            ],
        ]);
    }

    /**
     * Resolve real approver name from Users/Pegawai DB instead of hardcoded fake identity.
     */
    protected function approverName($userId): string
    {
        if (!$userId) {
            return '';
        }

        try {
            $user = \App\Models\User::find($userId);
            if ($user) {
                return $user->name ?? $user->username ?? ('User #' . $userId);
            }

            $pegawai = \App\Models\Simpeg\Pegawai::where('user_id', $userId)->first();
            return $pegawai?->nama_lengkap ?? ('User #' . $userId);
        } catch (\Throwable $e) {
            return 'User #' . $userId;
        }
    }
}
