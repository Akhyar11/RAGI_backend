<?php

namespace App\Http\Controllers\Sikeu;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sikeu\StoreExternalTagihanRequest;
use App\Models\Sikeu\Pembayaran;
use App\Services\Sikeu\ExternalTagihanService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ExternalTagihanController extends Controller
{
    public function __construct(private ExternalTagihanService $tagihanService) {}

    /**
     * POST /api/v1/sikeu/tagihan/external
     * Generate bill from external systems (SPMB, SIAKAD, SIMPEG, SIPPM).
     */
    public function createExternalBill(StoreExternalTagihanRequest $request)
    {
        $result = $this->tagihanService->issueExternalBill($request->validated());

        return response()->json([
            'status' => 'success',
            'message' => $result['requires_approval']
                ? 'Tagihan eksternal berhasil diterbitkan dan masuk ke antrean approval pimpinan.'
                : 'Tagihan eksternal berhasil diterbitkan dan Virtual Account aktif.',
            'data' => [
                'tagihan' => $result['tagihan'],
                'virtual_account' => $result['virtual_account'],
            ],
        ], 201);
    }

    /**
     * GET /api/v1/sikeu/pembayaran
     * List payment transactions with date range, search, status, & channel filters.
     */
    public function indexPembayaran(Request $request)
    {
        $perPage = min(100, $request->integer('per_page', 15));
        $query = Pembayaran::with([
            'tagihan.details.masterBiaya',
            'tagihan.mahasiswa.programStudi',
            'tagihan.tipeTagihanMahasiswa',
            'tagihan.calonMahasiswa.programStudi',
            'virtualAccount',
            'unitKas',
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
                    ->orWhere('kode_unik', 'like', "%{$search}%")
                    ->orWhereHas('tagihan', function ($tq) use ($search) {
                        $tq->where('nomor_tagihan', 'like', "%{$search}%")
                            ->orWhere('mahasiswa_id', 'like', "%{$search}%")
                            ->orWhere('calon_mahasiswa_id', 'like', "%{$search}%")
                            ->orWhereHas('mahasiswa', fn ($m) => $m->where('nim', 'like', "%{$search}%")->orWhere('nama_lengkap', 'like', "%{$search}%")->orWhere('nik', 'like', "%{$search}%"))
                            ->orWhereHas('tipeTagihanMahasiswa', fn ($tm) => $tm->where('nim', 'like', "%{$search}%")->orWhere('nama_mahasiswa', 'like', "%{$search}%"))
                            ->orWhereHas('calonMahasiswa', fn ($cm) => $cm->where('no_pendaftaran', 'like', "%{$search}%")->orWhere('nama_lengkap', 'like', "%{$search}%")->orWhere('nik', 'like', "%{$search}%"));
                    });
            });
        }

        $allowedSort = ['waktu_bayar', 'jumlah_bayar', 'kode_transaksi', 'id', 'created_at', 'status'];
        $sortBy = in_array($request->sort_by, $allowedSort) ? $request->sort_by : 'created_at';
        $sortOrder = $request->sort_order === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortBy, $sortOrder);
        $query->orderBy('id', 'desc');

        $pembayaran = $query->paginate($perPage);

        $mappedItems = collect($pembayaran->items())->map(function ($p) {
            $t = $p->tagihan;
            $mhs = $t?->mahasiswa;
            $tipeMhs = $t?->tipeTagihanMahasiswa;
            $calon = $t?->calonMahasiswa;

            $nim = $mhs?->nim ?? $tipeMhs?->nim ?? $calon?->nim ?? ($calon?->no_pendaftaran ?: ($t?->mahasiswa_id ? (string) $t->mahasiswa_id : '-'));
            $nama = $mhs?->nama_lengkap ?? $tipeMhs?->nama_mahasiswa ?? $calon?->nama_lengkap ?? ('Mahasiswa #'.($t?->mahasiswa_id ?? $t?->calon_mahasiswa_id ?? '-'));
            $prodi = $mhs?->programStudi?->nama ?? $mhs?->programStudi?->nama_prodi ?? $calon?->programStudi?->nama ?? '-';

            $rincian = $t?->details?->map(function ($d) {
                return $d->keterangan ?: ($d->masterBiaya->nama ?? 'Komponen Biaya');
            })->filter()->implode(', ') ?: ($t?->catatan_approval ?? 'Tagihan Mahasiswa');

            return [
                'id' => $p->id,
                'kode_transaksi' => $p->kode_transaksi,
                'nim' => $nim,
                'no_pendaftaran' => $calon?->no_pendaftaran,
                'is_calon_mahasiswa' => (bool) $calon,
                'nama_mahasiswa' => $nama,
                'program_studi' => $prodi,
                'rincian_pembayaran' => $rincian,
                'tagihan_id' => $p->tagihan_id,
                'tagihan' => [
                    'id' => $t?->id,
                    'nomor_tagihan' => $t?->nomor_tagihan,
                    'mahasiswa_id' => $t?->mahasiswa_id,
                    'calon_mahasiswa_id' => $t?->calon_mahasiswa_id,
                    'total_tagihan' => (float) ($t?->total_tagihan ?? 0),
                    'total_bayar' => (float) ($t?->total_bayar ?? 0),
                    'status' => $t?->status,
                    'rincian' => $rincian,
                ],
                'virtual_account' => $p->virtualAccount ? [
                    'va_number' => $p->virtualAccount->va_number,
                    'bank_nama' => $p->virtualAccount->bank_nama,
                ] : null,
                'jumlah_bayar' => (float) $p->jumlah_bayar,
                'kode_unik' => $p->kode_unik !== null ? (int) $p->kode_unik : null,
                'nominal_transfer' => (float) $p->jumlah_bayar + (int) ($p->kode_unik ?? 0),
                'waktu_bayar' => $p->waktu_bayar ? (is_object($p->waktu_bayar) && method_exists($p->waktu_bayar, 'format') ? $p->waktu_bayar->format('Y-m-d H:i:s') : (string) $p->waktu_bayar) : null,
                'channel_bayar' => $p->channel_bayar,
                'bank_pengirim' => $p->bank_pengirim,
                'unit_kas' => $p->unitKas ? [
                    'id' => $p->unitKas->id,
                    'nama_kas' => $p->unitKas->nama_kas,
                    'kanal' => $p->unitKas->kanal,
                ] : null,
                'bukti_bayar_url' => $p->bukti_bayar_path ? asset(Storage::url($p->bukti_bayar_path)) : null,
                'status' => $p->status,
                'catatan' => $p->catatan,
            ];
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Data retrieved successfully',
            'data' => $mappedItems,
            'meta' => [
                'current_page' => $pembayaran->currentPage(),
                'per_page' => $pembayaran->perPage(),
                'total' => $pembayaran->total(),
                'last_page' => $pembayaran->lastPage(),
                'from' => $pembayaran->firstItem(),
                'to' => $pembayaran->lastItem(),
            ],
            'filters' => [
                'search' => $request->input('search'),
                'status' => $request->input('status'),
                'channel' => $request->input('channel'),
                'tgl_mulai' => $request->input('tgl_mulai'),
                'tgl_selesai' => $request->input('tgl_selesai'),
                'sort_by' => $sortBy,
                'sort_order' => $sortOrder,
            ],
        ]);
    }

    /**
     * Public Payment Receipt Verification Endpoint
     * Accessible by scanning QR Code on printed physical receipt
     */
    public function validasiPembayaranPublik(string $kode_transaksi)
    {
        $pembayaran = Pembayaran::where('kode_transaksi', $kode_transaksi)
            ->with([
                'tagihan.details.masterBiaya',
                'tagihan.mahasiswa.programStudi',
                'tagihan.tipeTagihanMahasiswa',
                'tagihan.calonMahasiswa.programStudi',
                'virtualAccount',
            ])
            ->first();

        if (! $pembayaran) {
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

        $nim = $mhs?->nim ?? $tipeMhs?->nim ?? $calon?->nim ?? ($calon?->no_pendaftaran ?: ($t?->mahasiswa_id ? (string) $t->mahasiswa_id : '-'));
        $nama = $mhs?->nama_lengkap ?? $tipeMhs?->nama_mahasiswa ?? $calon?->nama_lengkap ?? ('Mahasiswa #'.($t?->mahasiswa_id ?? $t?->calon_mahasiswa_id ?? '-'));
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
                : 'Catatan transaksi ditemukan namun berstatus: '.strtoupper($pembayaran->status),
            'data' => [
                'kode_transaksi' => $pembayaran->kode_transaksi,
                'status' => $pembayaran->status,
                'is_valid' => $isValid,
                'verified_at' => now()->format('Y-m-d H:i:s'),
                'waktu_bayar' => $pembayaran->waktu_bayar ? (is_object($pembayaran->waktu_bayar) && method_exists($pembayaran->waktu_bayar, 'format') ? $pembayaran->waktu_bayar->format('Y-m-d H:i:s') : (string) $pembayaran->waktu_bayar) : null,
                'jumlah_bayar' => (float) $pembayaran->jumlah_bayar,
                'channel_bayar' => $pembayaran->channel_bayar,
                'channel_label' => $channelName,
                'kasir' => $kasirName,
                'catatan' => $pembayaran->catatan,
                'mahasiswa' => [
                    'nama_mahasiswa' => $nama,
                    'nim' => $nim,
                    'no_pendaftaran' => $calon?->no_pendaftaran,
                    'is_calon_mahasiswa' => (bool) $calon,
                    'program_studi' => $prodi,
                    'tahun_angkatan' => $angkatan,
                ],
                'tagihan' => [
                    'id' => $t?->id,
                    'nomor_tagihan' => $t?->nomor_tagihan,
                    'uraian' => $rincian,
                    'total_tagihan' => (float) ($t?->total_tagihan ?? 0),
                    'total_bayar' => (float) ($t?->total_bayar ?? 0),
                    'sisa' => $t ? max(0, (float) $t->total_tagihan + (float) $t->total_denda - (float) $t->total_potongan - (float) $t->total_bayar) : 0,
                    'status' => $t?->status,
                ],
                'security_hash' => hash('sha256', $pembayaran->kode_transaksi.'|'.$pembayaran->jumlah_bayar.'|'.($pembayaran->waktu_bayar ?? '')),
            ],
        ]);
    }
}
