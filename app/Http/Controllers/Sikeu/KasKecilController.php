<?php

namespace App\Http\Controllers\Sikeu;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sikeu\StoreKasKecilPengajuanRequest;
use App\Http\Requests\Sikeu\StoreKasKecilTransaksiRequest;
use App\Http\Requests\Sikeu\StoreKasKecilUnitRequest;
use App\Http\Requests\Sikeu\UpdateKasKecilUnitRequest;
use App\Models\Sikeu\KasKecilPengajuan;
use App\Models\Sikeu\UnitKas;
use App\Models\System\MasterReferensi;
use App\Services\Sikeu\KasKecilService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Kas Kecil (Petty Cash) SIKEU.
 *
 * Aksi dikendalikan permission RBAC:
 * - sikeu.kaskecil.read       : melihat unit, transaksi, pengajuan
 * - sikeu.kaskecil.transaksi  : mencatat transaksi pengeluaran kas kecil
 * - sikeu.kaskecil.pengajuan  : mengajukan kas langsung / top-up
 * - sikeu.kaskecil.approve    : menyetujui / menolak pengajuan kas langsung
 */
class KasKecilController extends Controller
{
    public function __construct(private KasKecilService $service) {}

    /**
     * GET /api/v1/sikeu/kas-kecil
     * Daftar unit kas kecil; petugas hanya melihat unit yang dipegangnya.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        if (!$user->isSuperAdmin() && !$user->isAdmin() && !$user->hasPermission('sikeu.kaskecil.read')) {
            return response()->json(['status' => 'error', 'message' => 'Anda tidak memiliki akses ke modul kas kecil.'], 403);
        }

        $filters = $request->only(['search', 'fakultas_id', 'status', 'sort_by', 'sort_dir']);
        $perPage = min(100, $request->integer('per_page', 15));

        $data = $this->service->indexUnitKas($filters, $perPage);

        return response()->json([
            'status' => 'success',
            'message' => 'Data unit kas kecil berhasil dimuat',
            'data' => $data->items(),
            'meta' => [
                'current_page' => $data->currentPage(),
                'per_page' => $data->perPage(),
                'total' => $data->total(),
                'last_page' => $data->lastPage(),
                'from' => $data->firstItem(),
                'to' => $data->lastItem(),
            ],
        ]);
    }

    /**
     * POST /api/v1/sikeu/kas-kecil
     * Buat unit kas kecil baru (role: admin keuangan dengan sikeu.kas.manage).
     */
    public function store(StoreKasKecilUnitRequest $request)
    {
        $user = $request->user();
        if (!$user->isSuperAdmin() && !$user->isAdmin() && !$user->hasPermission('sikeu.kas.manage')) {
            return response()->json(['status' => 'error', 'message' => 'Anda tidak memiliki izin membuat unit kas kecil.'], 403);
        }

        $validated = $request->validated();

        // Pastikan akun COA yang dipilih adalah kelompok aset (kas-bank 101/102).
        $akun = \App\Models\Sikeu\AkunKeuangan::find($validated['akun_keuangan_id']);
        if (!$akun || $akun->kelompok !== 'aset') {
            return response()->json([
                'status' => 'error',
                'message' => 'Akun pemetaan kas kecil harus akun kelompok aset (kas-bank 101/102).',
            ], 422);
        }

        try {
            $unitKas = UnitKas::create([
                'fakultas_id' => $validated['fakultas_id'],
                'nama_kas' => $validated['nama_kas'],
                'tipe_kas' => 'petty_cash',
                'kanal' => 'tunai',
                'akun_keuangan_id' => $validated['akun_keuangan_id'] ?? null,
                'penanggung_jawab_id' => $validated['penanggung_jawab_id'],
                'saldo_awal' => $validated['saldo_awal'],
                'saldo_saat_ini' => $validated['saldo_awal'],
                'deskripsi' => $validated['deskripsi'] ?? null,
                'status' => true,
                'is_kabag_kas' => false,
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Unit kas kecil berhasil dibuat',
                'data' => $unitKas->load([
                    'fakultas',
                    'akunKeuangan',
                    'penanggungJawab' => fn ($q) => $q->select(['id', 'username', 'email'])->with('pegawai'),
                ]),
            ], 201);
        } catch (\Exception $e) {
            Log::error('Gagal membuat unit kas kecil: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'Gagal membuat unit kas kecil.'], 500);
        }
    }

    /**
     * PUT /api/v1/sikeu/kas-kecil/{id}
     * Update data unit kas kecil (role: admin keuangan dengan sikeu.kas.manage).
     */
    public function update(UpdateKasKecilUnitRequest $request, $id)
    {
        $user = $request->user();
        if (!$user->isSuperAdmin() && !$user->isAdmin() && !$user->hasPermission('sikeu.kas.manage')) {
            return response()->json(['status' => 'error', 'message' => 'Anda tidak memiliki izin mengubah unit kas kecil.'], 403);
        }

        $unitKas = UnitKas::where('tipe_kas', 'petty_cash')->find((int) $id);
        if (!$unitKas) {
            return response()->json(['status' => 'error', 'message' => 'Unit kas kecil tidak ditemukan.'], 404);
        }

        $validated = $request->validated();

        // Pastikan akun COA yang dipilih adalah kelompok aset (kas-bank 101/102).
        $akun = \App\Models\Sikeu\AkunKeuangan::find($validated['akun_keuangan_id']);
        if (!$akun || $akun->kelompok !== 'aset') {
            return response()->json([
                'status' => 'error',
                'message' => 'Akun pemetaan kas kecil harus akun kelompok aset (kas-bank 101/102).',
            ], 422);
        }

        try {
            $updated = $this->service->updateUnitKas($unitKas, $validated, $request);

            return response()->json([
                'status' => 'success',
                'message' => 'Unit kas kecil berhasil diperbarui',
                'data' => $updated,
            ]);
        } catch (\Exception $e) {
            Log::error('Gagal memperbarui unit kas kecil: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'Gagal memperbarui unit kas kecil.'], 500);
        }
    }

    /**
     * GET /api/v1/sikeu/kas-kecil/{id}
     * Detail unit kas kecil + 10 transaksi & pengajuan terbaru.
     */
    public function show(Request $request, $id)
    {
        $user = $request->user();
        if (!$user->isSuperAdmin() && !$user->isAdmin() && !$user->hasPermission('sikeu.kaskecil.read')) {
            return response()->json(['status' => 'error', 'message' => 'Anda tidak memiliki akses ke modul kas kecil.'], 403);
        }

        $unitKas = $this->service->showUnitKas((int) $id);

        return response()->json(['status' => 'success', 'data' => $unitKas]);
    }

    /**
     * GET /api/v1/sikeu/kas-kecil/{id}/transaksi
     */
    public function transaksiIndex(Request $request, $id)
    {
        $this->pastikanBisaBaca($request);

        $filters = $request->only(['search', 'kategori_id', 'tanggal_awal', 'tanggal_akhir']);
        $perPage = min(100, $request->integer('per_page', 15));

        $data = $this->service->transaksiIndex((int) $id, $filters, $perPage);

        return response()->json([
            'status' => 'success',
            'data' => $data->items(),
            'meta' => [
                'current_page' => $data->currentPage(),
                'per_page' => $data->perPage(),
                'total' => $data->total(),
                'last_page' => $data->lastPage(),
                'from' => $data->firstItem(),
                'to' => $data->lastItem(),
            ],
            'saldo_saat_ini' => UnitKas::findOrFail((int) $id)->saldo_saat_ini,
        ]);
    }

    /**
     * POST /api/v1/sikeu/kas-kecil/{id}/transaksi
     * Pencatatan transaksi keluar kas kecil (saldo berkurang + jurnal otomatis).
     */
    public function transaksiStore(StoreKasKecilTransaksiRequest $request, $id)
    {
        $user = $request->user();
        if (!$user->isSuperAdmin() && !$user->isAdmin() && !$user->hasPermission('sikeu.kaskecil.transaksi')) {
            return response()->json(['status' => 'error', 'message' => 'Anda belum memiliki izin mencatat transaksi kas kecil.'], 403);
        }

        $data = $request->validated();
        $data['unit_kas_id'] = (int) $id;

        try {
            $transaksi = $this->service->storeTransaksi($data, $request);

            return response()->json([
                'status' => 'success',
                'message' => 'Transaksi kas kecil berhasil dicatat',
                'data' => $transaksi->load(['kategori', 'unitKas']),
            ], 201);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            Log::error('Gagal mencatat transaksi kas kecil: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'Gagal mencatat transaksi kas kecil.'], 500);
        }
    }

    /**
     * GET /api/v1/sikeu/kas-kecil/{id}/pengajuan
     */
    public function pengajuanIndex(Request $request, $id)
    {
        $this->pastikanBisaBaca($request);

        $filters = $request->only(['search', 'status']);
        $perPage = min(100, $request->integer('per_page', 15));

        $data = $this->service->pengajuanIndex((int) $id, $filters, $perPage);

        return response()->json([
            'status' => 'success',
            'data' => $data->items(),
            'meta' => [
                'current_page' => $data->currentPage(),
                'per_page' => $data->perPage(),
                'total' => $data->total(),
                'last_page' => $data->lastPage(),
                'from' => $data->firstItem(),
                'to' => $data->lastItem(),
            ],
        ]);
    }

    /**
     * POST /api/v1/sikeu/kas-kecil/{id}/pengajuan
     * Ajukan kas langsung / top-up kas kecil.
     */
    public function pengajuanStore(StoreKasKecilPengajuanRequest $request, $id)
    {
        $user = $request->user();
        if (!$user->isSuperAdmin() && !$user->isAdmin() && !$user->hasPermission('sikeu.kaskecil.pengajuan')) {
            return response()->json(['status' => 'error', 'message' => 'Anda belum memiliki izin mengajukan kas langsung.'], 403);
        }

        $data = $request->validated();
        $data['unit_kas_id'] = (int) $id;

        try {
            $pengajuan = $this->service->storePengajuan($data, $request);

            return response()->json([
                'status' => 'success',
                'message' => 'Pengajuan kas langsung berhasil dibuat dan menunggu persetujuan',
                'data' => $pengajuan->load([
                    'unitKas',
                    'pemohon' => fn ($q) => $q->select(['id', 'username', 'email'])->with('pegawai'),
                ]),
            ], 201);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            Log::error('Gagal membuat pengajuan kas kecil: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'Gagal membuat pengajuan kas langsung.'], 500);
        }
    }

    /**
     * POST /api/v1/sikeu/kas-kecil/pengajuan/{id}/approve
     * Setujui pengajuan: saldo bertambah + jurnal pengisian kas kecil.
     */
    public function pengajuanApprove(Request $request, $id)
    {
        $user = $request->user();
        if (!$user->isSuperAdmin() && !$user->isAdmin() && !$user->hasPermission('sikeu.kaskecil.approve')) {
            return response()->json(['status' => 'error', 'message' => 'Anda tidak memiliki izin menyetujui pengajuan kas kecil.'], 403);
        }

        $request->validate(['nominal_disetujui' => 'nullable|numeric|min:1']);

        try {
            $pengajuan = KasKecilPengajuan::findOrFail((int) $id);
            $result = $this->service->approvePengajuan(
                $pengajuan,
                ['nominal_disetujui' => $request->nominal_disetujui],
                $request
            );

            return response()->json([
                'status' => 'success',
                'message' => 'Pengajuan kas langsung disetujui. Saldo kas kecil bertambah & jurnal otomatis dibuat.',
                'data' => $result->load([
                    'unitKas',
                    'pemohon' => fn ($q) => $q->select(['id', 'username', 'email'])->with('pegawai'),
                    'approver' => fn ($q) => $q->select(['id', 'username', 'email'])->with('pegawai'),
                ]),
            ]);
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            Log::error('Gagal menyetujui pengajuan kas kecil: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'Gagal menyetujui pengajuan kas langsung.'], 500);
        }
    }

    /**
     * POST /api/v1/sikeu/kas-kecil/pengajuan/{id}/reject
     * Tolak pengajuan. Tidak ada mutasi saldo / jurnal.
     */
    public function pengajuanReject(Request $request, $id)
    {
        $user = $request->user();
        if (!$user->isSuperAdmin() && !$user->isAdmin() && !$user->hasPermission('sikeu.kaskecil.approve')) {
            return response()->json(['status' => 'error', 'message' => 'Anda tidak memiliki izin menolak pengajuan kas kecil.'], 403);
        }

        $request->validate(['catatan_penolakan' => 'required|string|max:1000']);

        try {
            $pengajuan = KasKecilPengajuan::findOrFail((int) $id);
            $result = $this->service->rejectPengajuan(
                $pengajuan,
                ['catatan_penolakan' => $request->catatan_penolakan],
                $request
            );

            return response()->json([
                'status' => 'success',
                'message' => 'Pengajuan kas langsung ditolak.',
                'data' => $result->load([
                    'unitKas',
                    'pemohon' => fn ($q) => $q->select(['id', 'username', 'email'])->with('pegawai'),
                    'approver' => fn ($q) => $q->select(['id', 'username', 'email'])->with('pegawai'),
                ]),
            ]);
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            Log::error('Gagal menolak pengajuan kas kecil: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'Gagal menolak pengajuan kas langsung.'], 500);
        }
    }

    /**
     * DELETE /api/v1/sikeu/kas-kecil/pengajuan/{id}
     * Hapus / batalkan pengajuan kas kecil (hanya pengajuan yang pending).
     * Dapat dilakukan oleh pemohon (petugas) atau admin/keuangan.
     */
    public function pengajuanDestroy(Request $request, $id)
    {
        $user = $request->user();
        $pengajuan = KasKecilPengajuan::findOrFail((int) $id);

        $isPemohon = ((int) $pengajuan->pemohon_id === (int) $user->id) || ((int) $pengajuan->created_by === (int) $user->id);
        $hasPermission = $user->isSuperAdmin() || $user->isAdmin() || $user->hasPermission('sikeu.kaskecil.pengajuan') || $user->hasPermission('sikeu.kas.manage') || $user->hasPermission('sikeu.kaskecil.approve');

        if (!$isPemohon && !$hasPermission) {
            return response()->json(['status' => 'error', 'message' => 'Anda tidak memiliki izin menghapus pengajuan ini.'], 403);
        }

        try {
            $this->service->deletePengajuan($pengajuan, $request);

            return response()->json([
                'status' => 'success',
                'message' => 'Pengajuan kas langsung berhasil dihapus/dibatalkan.',
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            Log::error('Gagal menghapus pengajuan kas kecil: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'Gagal menghapus pengajuan kas langsung.'], 500);
        }
    }

    /**
     * GET /api/v1/sikeu/kas-kecil/referensi/kategori
     * Kategori transaksi kas kecil dari master referensi (tipe `kategori_kas_kecil`).
     */
    public function referensiKategori(Request $request)
    {
        $user = $request->user();
        if (!$user->isSuperAdmin() && !$user->isAdmin() && !$user->hasPermission('sikeu.kaskecil.read')) {
            return response()->json(['status' => 'error', 'message' => 'Anda tidak memiliki akses ke modul kas kecil.'], 403);
        }

        $data = MasterReferensi::where('tipe', 'kategori_kas_kecil')
            ->where('is_active', true)
            ->orderBy('urutan')
            ->get(['id', 'tipe', 'kode', 'nama', 'urutan', 'is_active']);

        return response()->json(['status' => 'success', 'data' => $data]);
    }

    /**
     * GET /api/v1/sikeu/kas-kecil/referensi/petugas
     * Daftar user ber-role petugas_kas_kecil (untuk dipilih sebagai penanggung jawab unit).
     */
    public function referensiPetugas(Request $request)
    {
        $user = $request->user();
        if (!$user->isSuperAdmin() && !$user->isAdmin() && !$user->hasPermission('sikeu.kaskecil.read')) {
            return response()->json(['status' => 'error', 'message' => 'Anda tidak memiliki akses ke modul kas kecil.'], 403);
        }

        $query = \App\Models\User::query()
            ->whereHas('roles', fn ($q) => $q->where('slug', 'petugas_kas_kecil'))
            ->with(['pegawai'])
            ->orderBy('username');

        if ($request->filled('q')) {
            $query->where(function ($q) use ($request) {
                $label = '%' . trim($request->q) . '%';
                $q->where('username', 'like', $label)
                  ->orWhere('email', 'like', $label)
                  ->orWhereHas('pegawai', fn ($p) => $p->where('nama_lengkap', 'like', $label));
            });
        }

        $petugas = $query->limit(50)->get()->map(function ($u) {
            return [
                'id' => $u->id,
                'label' => trim(($u->pegawai?->nama_lengkap ?? '') . ' (' . $u->username . ')'),
                'username' => $u->username,
                'email' => $u->email,
            ];
        });

        return response()->json(['status' => 'success', 'data' => $petugas]);
    }

    protected function pastikanBisaBaca(Request $request): void
    {
        $user = $request->user();
        if (!$user->isSuperAdmin() && !$user->isAdmin() && !$user->hasPermission('sikeu.kaskecil.read')) {
            abort(403, 'Anda tidak memiliki akses ke modul kas kecil.');
        }
    }
}