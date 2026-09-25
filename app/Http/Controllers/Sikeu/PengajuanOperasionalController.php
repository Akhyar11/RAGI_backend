<?php

namespace App\Http\Controllers\Sikeu;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sikeu\StoreLpjOperasionalRequest;
use App\Http\Requests\Sikeu\StorePengajuanOperasionalRequest;
use App\Models\Sikeu\LaporanBuktiPelaksanaan;
use App\Models\Sikeu\PengajuanPencairanKas;
use App\Services\Sikeu\PengajuanOperasionalService;
use Illuminate\Http\Request;

class PengajuanOperasionalController extends Controller
{
    public function __construct(private PengajuanOperasionalService $service) {}

    /**
     * GET /api/v1/sikeu/pengajuan-operasional
     */
    public function index(Request $request)
    {
        $query = PengajuanPencairanKas::with([
            'items',
            'fakultas',
            'ruangan',
            'unitKas',
            'lpj',
            'suratTugas.pegawai.unitKerja',
            'pemohon',
        ]);

        if ($request->filled('tab')) {
            if ($request->tab === 'simpeg') {
                $query->where('kanal', 'simpeg_surat_tugas');
            } elseif ($request->tab === 'operasional') {
                $query->where(function ($q) {
                    $q->whereNull('kanal')->orWhere('kanal', '!=', 'simpeg_surat_tugas');
                });
            }
        }

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('nomor_pengajuan', 'like', "%{$s}%")
                    ->orWhere('judul_pengajuan', 'like', "%{$s}%")
                    ->orWhere('deskripsi', 'like', "%{$s}%");
            });
        }
        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }
        if ($request->filled('status_in')) {
            $statusList = collect(explode(',', (string) $request->status_in))
                ->map(fn ($s) => trim($s))
                ->filter()
                ->values()
                ->all();
            if (!empty($statusList)) {
                $query->whereIn('status', $statusList);
            }
        }
        if ($request->filled('kategori') && $request->kategori !== 'all') {
            $query->where('kategori_pengajuan', $request->kategori);
        }
        if ($request->filled('dari')) {
            $query->whereDate('created_at', '>=', $request->dari);
        }
        if ($request->filled('sampai')) {
            $query->whereDate('created_at', '<=', $request->sampai);
        }

        $allowed = ['created_at', 'nominal_diajukan', 'judul_pengajuan', 'status'];
        $sortBy = in_array($request->sort_by, $allowed) ? $request->sort_by : 'created_at';
        $sortOrder = $request->sort_order === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortBy, $sortOrder);

        $perPage = min(100, $request->integer('per_page', 15));
        $data = $query->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'message' => 'Data pengajuan operasional berhasil dimuat',
            'data' => $data->items(),
            'meta' => [
                'current_page' => $data->currentPage(),
                'per_page' => $data->perPage(),
                'total' => $data->total(),
                'last_page' => $data->lastPage(),
                'from' => $data->firstItem(),
                'to' => $data->lastItem(),
            ],
            'filters' => [
                'search' => $request->search,
                'sort_by' => $sortBy,
                'sort_order' => $sortOrder,
            ],
        ]);
    }

    /**
     * POST /api/v1/sikeu/pengajuan-operasional
     */
    public function store(StorePengajuanOperasionalRequest $request)
    {
        try {
            $pengajuan = $this->service->create($request->validated(), $request);

            return response()->json([
                'status' => 'success',
                'message' => 'Pengajuan operasional berhasil dibuat',
                'data' => $pengajuan,
            ], 201);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            \Log::error('Gagal membuat pengajuan operasional: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'Gagal membuat pengajuan: ' . $e->getMessage()], 500);
        }
    }

    /**
     * GET /api/v1/sikeu/pengajuan-operasional/{id}
     */
    public function show($id)
    {
        $item = PengajuanPencairanKas::with(['items', 'fakultas', 'ruangan', 'unitKas', 'historyApproval', 'lpj.details'])->findOrFail($id);

        return response()->json(['status' => 'success', 'data' => $item]);
    }

    /**
     * POST /api/v1/sikeu/pengajuan-operasional/{id}/approve
     * Body: { aksi: approve|reject, catatan?: string }
     */
    public function approve(Request $request, $id)
    {
        $request->validate([
            'aksi' => 'required|in:approve,reject',
            'catatan' => 'nullable|string|max:1000',
        ]);

        try {
            $pengajuan = PengajuanPencairanKas::findOrFail($id);
            $result = $this->service->approve($pengajuan, $request->aksi, $request->catatan, $request);

            return response()->json([
                'status' => 'success',
                'message' => $request->aksi === 'approve' ? 'Pengajuan disetujui ke tahap berikutnya' : 'Pengajuan ditolak',
                'data' => $result,
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => 'Gagal memproses approval: ' . $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/v1/sikeu/pengajuan-operasional/{id}/pencairan
     */
    public function pencairan(Request $request, $id)
    {
        $request->validate([
            'nominal_cair' => 'nullable|numeric|min:1',
            'tanggal_pencairan' => 'nullable|date',
            'bukti_pencairan' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'referensi_eksternal' => 'nullable|string|max:100',
            'unit_kas_id' => 'nullable|integer|exists:sikeu_unit_kas,id',
        ]);

        try {
            $pengajuan = PengajuanPencairanKas::findOrFail($id);
            $result = $this->service->cairkan($pengajuan, $request->only(['nominal_cair', 'tanggal_pencairan', 'referensi_eksternal', 'unit_kas_id']), $request);

            return response()->json([
                'status' => 'success',
                'message' => 'Dana berhasil dicairkan dan jurnal otomatis diterbitkan',
                'data' => $result,
            ]);
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => 'Gagal mencairkan: ' . $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/v1/sikeu/pengajuan-operasional/{id}/lpj
     */
    public function simpanLpj(StoreLpjOperasionalRequest $request, $id)
    {
        try {
            $pengajuan = PengajuanPencairanKas::findOrFail($id);
            $payload = $request->validated();
            // Normalisasi tambahan dari multipart (tambahan[0][keterangan] dst.)
            if ($request->has('tambahan')) {
                $payload['tambahan'] = array_values($request->input('tambahan', []));
            }
            $lpj = $this->service->simpanLpj($pengajuan, $payload, $request);

            return response()->json([
                'status' => 'success',
                'message' => 'LPJ berhasil disimpan, menunggu verifikasi keuangan',
                'data' => $lpj,
            ], 201);
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            \Log::error('Gagal simpan LPJ: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'Gagal menyimpan LPJ: ' . $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/v1/sikeu/lpj/{id}/verifikasi
     * Body: { aksi: approve|reject, catatan?: string }
     */
    public function verifikasiLpj(Request $request, $id)
    {
        $request->validate([
            'aksi' => 'required|in:approve,reject',
            'catatan' => 'nullable|string|max:1000',
        ]);

        try {
            $lpj = LaporanBuktiPelaksanaan::with('pengajuan')->findOrFail($id);
            $result = $this->service->verifikasiLpj($lpj, $request->aksi, $request->catatan, $request);

            return response()->json([
                'status' => 'success',
                'message' => $request->aksi === 'approve' ? 'LPJ disetujui dan jurnal realisasi otomatis diterbitkan' : 'LPJ ditolak, pengaju perlu revisi',
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => 'Gagal verifikasi LPJ: ' . $e->getMessage()], 500);
        }
    }

    /**
     * GET /api/v1/sikeu/referensi/fakultas & ruangan untuk dropdown pengajuan.
     */
    public function listFakultas()
    {
        $data = \App\Models\Siakad\Fakultas::where('is_active', true)->orderBy('nama')->get(['id', 'kode', 'nama']);

        return response()->json(['status' => 'success', 'data' => $data]);
    }

    public function listRuangan()
    {
        $data = \App\Models\Ruangan::orderBy('nama')->get(['id', 'kode', 'nama']);

        return response()->json(['status' => 'success', 'data' => $data]);
    }

    public function listKategoriPengajuan()
    {
        $data = [
            ['id' => 'pengadaan_barang', 'nama' => 'Pengadaan Barang'],
            ['id' => 'non_barang', 'nama' => 'Non-Barang'],
        ];

        return response()->json(['status' => 'success', 'data' => $data]);
    }

    /**
     * POST /api/v1/sikeu/pengajuan-operasional/{id}/setujui-panjar-simpeg
     */
    public function setujuiPanjarSimpeg(Request $request, $id)
    {
        $request->validate([
            'unit_kas_id' => 'required|integer|exists:sikeu_unit_kas,id',
            'nominal_disetujui' => 'required|numeric|min:1',
            'catatan' => 'nullable|string|max:1000',
        ]);

        try {
            $pengajuan = PengajuanPencairanKas::findOrFail($id);
            $result = $this->service->setujuiPanjarSimpeg($pengajuan, $request->only(['unit_kas_id', 'nominal_disetujui', 'catatan']), $request);

            return response()->json([
                'status' => 'success',
                'message' => 'Panjar perjalanan dinas berhasil disetujui, menunggu konfirmasi pegawai',
                'data' => $result,
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => 'Gagal menyetujui panjar: ' . $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/v1/sikeu/pengajuan-operasional/{id}/tutup-lpj-simpeg
     */
    public function tutupLpjSimpeg(Request $request, $id)
    {
        $request->validate([
            'catatan' => 'nullable|string|max:1000',
        ]);

        try {
            $pengajuan = PengajuanPencairanKas::findOrFail($id);
            $result = $this->service->tutupLpjSimpeg($pengajuan, $request->only(['catatan']), $request);

            return response()->json([
                'status' => 'success',
                'message' => 'Transaksi perjalanan dinas berhasil diverifikasi dan diselesaikan',
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => 'Gagal menutup LPJ: ' . $e->getMessage()], 500);
        }
    }
}
