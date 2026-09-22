<?php

namespace App\Services\Sikeu;

use App\Models\Sikeu\KasKecilPengajuan;
use App\Models\Sikeu\KasKecilTransaksi;
use App\Models\Sikeu\TransaksiKasUnit;
use App\Models\Sikeu\UnitKas;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Service Layer Kas Kecil (Petty Cash) SIKEU:
 * - Transaksi pengeluaran kas kecil oleh petugas (saldo berkurang + jurnal beban).
 * - Pengajuan kas langsung / top-up, disetujui oleh Admin Keuangan
 *   (saldo bertambah + jurnal pengisian kas kecil).
 *
 * Seluruh proses mutasi saldo dijalankan dalam satu transaksi database agar
 * rekam TransaksiKasUnit dan jurnal selalu konsisten dengan saldo UnitKas.
 */
class KasKecilService
{
    /**
     * Cakupan data unit kas kecil tergantung role:
     * - Petugas Kas Kecil    : hanya unit di mana user adalah penanggung jawab.
     * - Tim Keuangan (operator/kabag/admin) : semua unit tipe_kas='petty_cash'.
     */
    public function indexUnitKas(array $filters = [], int $perPage = 15)
    {
        $query = UnitKas::query()
            ->with([
                'fakultas',
                'akunKeuangan',
                // Hanya field publik penanggung jawab; hindari bocornya data sensitif user (token 2FA, dsb.).
                'penanggungJawab' => fn ($q) => $q->select(['id', 'username', 'email'])->with('pegawai'),
            ])
            ->where('tipe_kas', 'petty_cash');

        $this->scopeUnitKasByRole($query);

        if (!empty($filters['search'])) {
            $search = trim($filters['search']);
            $query->where(function ($q) use ($search) {
                $q->where('nama_kas', 'like', "%{$search}%")
                  ->orWhere('penanggung_jawab', 'like', "%{$search}%");
            });
        }

        if (!empty($filters['fakultas_id'])) {
            $query->where('fakultas_id', $filters['fakultas_id']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', filter_var($filters['status'], FILTER_VALIDATE_BOOLEAN));
        }

        $sortBy = $filters['sort_by'] ?? 'nama_kas';
        $sortDir = !empty($filters['sort_dir']) && strtolower($filters['sort_dir']) === 'desc' ? 'desc' : 'asc';
        $query->orderBy($sortBy, $sortDir);

        return $query->paginate($perPage);
    }

    public function showUnitKas(int $unitKasId): UnitKas
    {
        $unitKas = UnitKas::with([
            'fakultas',
            'akunKeuangan',
            'penanggungJawab' => fn ($q) => $q->select(['id', 'username', 'email'])->with('pegawai'),
            'kasKecilTransaksis' => fn ($q) => $q->with('kategori')->latest('tanggal_transaksi')->limit(10),
            'kasKecilPengajuans' => fn ($q) => $q->latest()->limit(10),
        ])->findOrFail($unitKasId);

        if ($this->dilarangAksesUnit($unitKas)) {
            throw new AuthorizationException('Anda hanya dapat mengakses unit kas kecil yang Anda pegang.');
        }

        return $unitKas;
    }

    public function updateUnitKas(UnitKas $unitKas, array $data, ?Request $request = null): UnitKas
    {
        $oldValues = $unitKas->only([
            'nama_kas', 'fakultas_id', 'penanggung_jawab_id', 'akun_keuangan_id', 'deskripsi', 'status'
        ]);

        $unitKas->update([
            'nama_kas' => $data['nama_kas'],
            'fakultas_id' => $data['fakultas_id'],
            'penanggung_jawab_id' => $data['penanggung_jawab_id'],
            'akun_keuangan_id' => $data['akun_keuangan_id'],
            'deskripsi' => $data['deskripsi'] ?? null,
            'status' => (bool) $data['status'],
        ]);

        $unitKas->load([
            'fakultas',
            'akunKeuangan',
            'penanggungJawab' => fn ($q) => $q->select(['id', 'username', 'email'])->with('pegawai'),
        ]);

        $newValues = $unitKas->only([
            'nama_kas', 'fakultas_id', 'penanggung_jawab_id', 'akun_keuangan_id', 'deskripsi', 'status'
        ]);

        AuditLogService::record(
            'SIKEU',
            'update',
            'sikeu_unit_kas',
            $unitKas->id,
            $oldValues,
            $newValues,
            $request
        );

        return $unitKas;
    }

    /**
     * Petugas Kas Kecil hanya mengelola unit tempat dia menjadi penanggung jawab.
     */
    protected function scopeUnitKasByRole($query): void
    {
        $user = auth()->user();
        if (!$user) {
            return;
        }

        $isAdminKeuangan = $user->hasRole('operator_sikeu')
            || $user->hasRole('kabag_keuangan')
            || $user->hasRole('admin_keuangan_akuntansi');

        if (!$user->isSuperAdmin() && !$user->isAdmin() && !$isAdminKeuangan) {
            $query->where('penanggung_jawab_id', $user->id);
        }
    }

    protected function dilarangAksesUnit(UnitKas $unitKas): bool
    {
        $user = auth()->user();
        if (!$user || $user->isSuperAdmin() || $user->isAdmin()) {
            return false;
        }

        $isAdminKeuangan = $user->hasRole('operator_sikeu')
            || $user->hasRole('kabag_keuangan')
            || $user->hasRole('admin_keuangan_akuntansi');

        if ($isAdminKeuangan) {
            return false;
        }

        return (int) $unitKas->penanggung_jawab_id !== (int) $user->id;
    }

    public function transaksiIndex(int $unitKasId, array $filters = [], int $perPage = 15)
    {
        $unitKas = $this->showUnitKas($unitKasId);

        $query = KasKecilTransaksi::with([
            'kategori',
            'dibuatOleh' => fn ($q) => $q->select(['id', 'username', 'email'])->with('pegawai'),
        ])
            ->where('unit_kas_id', $unitKas->id);

        if (!empty($filters['search'])) {
            $search = trim($filters['search']);
            $query->where(fn ($q) => $q
                ->where('nomor_transaksi', 'like', "%{$search}%")
                ->orWhere('uraian', 'like', "%{$search}%")
                ->orWhere('penerima', 'like', "%{$search}%"));
        }

        if (!empty($filters['kategori_id'])) {
            $query->where('referensi_kategori_id', $filters['kategori_id']);
        }

        if (!empty($filters['tanggal_awal'])) {
            $query->whereDate('tanggal_transaksi', '>=', $filters['tanggal_awal']);
        }
        if (!empty($filters['tanggal_akhir'])) {
            $query->whereDate('tanggal_transaksi', '<=', $filters['tanggal_akhir']);
        }

        $query->orderByDesc('tanggal_transaksi')->orderByDesc('id');

        return $query->paginate($perPage);
    }

    public function pengajuanIndex(int $unitKasId, array $filters = [], int $perPage = 15)
    {
        $unitKas = $this->showUnitKas($unitKasId);

        $query = KasKecilPengajuan::with([
            'pemohon' => fn ($q) => $q->select(['id', 'username', 'email'])->with('pegawai'),
            'approver' => fn ($q) => $q->select(['id', 'username', 'email'])->with('pegawai'),
        ])
            ->where('unit_kas_id', $unitKas->id);

        if (!empty($filters['status']) && in_array($filters['status'], ['pending_keuangan', 'disetujui', 'ditolak'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['search'])) {
            $search = trim($filters['search']);
            $query->where(fn ($q) => $q
                ->where('nomor_pengajuan', 'like', "%{$search}%")
                ->orWhere('judul_pengajuan', 'like', "%{$search}%"));
        }

        $query->orderByDesc('id');

        return $query->paginate($perPage);
    }

    /**
     * Catat transaksi pengeluaran kas kecil.
     * Saldo unit kas dikurangi, rekam di TransaksiKasUnit (kredit_pengeluaran),
     * lalu dibuatkan jurnal otomatis Dr Beban / Cr Kas Unit.
     */
    public function storeTransaksi(array $data, ?Request $request = null): KasKecilTransaksi
    {
        $transaksi = DB::transaction(function () use ($data, $request) {
            $unitKas = $this->showUnitKas((int) $data['unit_kas_id']);

            $nominal = (float) $data['nominal'];
            if ($nominal <= 0) {
                throw new \InvalidArgumentException('Nominal transaksi harus lebih dari nol.');
            }

            $saldoSaatIni = (float) $unitKas->saldo_saat_ini;
            if ($nominal > $saldoSaatIni) {
                throw new \InvalidArgumentException('Saldo kas kecil tidak mencukupi untuk transaksi ini.');
            }

            $filePath = $unitKas?->id && $request && $request->hasFile('file_bukti')
                ? $this->simpanFile($request->file('file_bukti'), 'sikeu/kas_kecil')
                : null;

            // 1. Rekam mutasi kas unit (kredit / pengeluaran)
            $saldoSesudah = $saldoSaatIni - $nominal;
            $transaksiKasUnit = TransaksiKasUnit::create([
                'unit_kas_id' => $unitKas->id,
                'kode_transaksi' => 'KK-' . date('Ymd') . '-' . strtoupper(Str::random(5)),
                'jenis_transaksi' => 'kredit_pengeluaran',
                'nominal' => $nominal,
                'saldo_sebelum' => $saldoSaatIni,
                'saldo_sesudah' => $saldoSesudah,
                'keterangan' => 'Transaksi keluar kas kecil: ' . trim($data['uraian']),
                'tanggal_transaksi' => $data['tanggal_transaksi'],
                'created_by' => auth()->id(),
            ]);

            // 2. Update saldo unit kas
            $unitKas->saldo_saat_ini = $saldoSesudah;
            $unitKas->save();

            // 3. Simpan transaksi kas kecil
            $transaksi = KasKecilTransaksi::create([
                'unit_kas_id' => $unitKas->id,
                'transaksi_kas_unit_id' => $transaksiKasUnit->id,
                'nomor_transaksi' => 'KK-TRX-' . date('YmdHis') . '-' . strtoupper(Str::random(4)),
                'referensi_kategori_id' => $data['referensi_kategori_id'] ?? null,
                'uraian' => trim($data['uraian']),
                'penerima' => $data['penerima'] ?? null,
                'nominal' => $nominal,
                'tanggal_transaksi' => $data['tanggal_transaksi'],
                'file_bukti_path' => $filePath,
                'keterangan' => $data['keterangan'] ?? null,
                'created_by' => auth()->id(),
            ]);

            // 4. Jurnal otomatis: Dr Beban / Cr Kas Unit
            JurnalSikeuService::jurnalKeluarKasKecil(
                $transaksi->id,
                $nominal,
                $transaksi->nomor_transaksi . ' - ' . $transaksi->uraian,
                $unitKas
            );

            AuditLogService::record(
                'Sikeu',
                'keluar_kas_kecil',
                'sikeu_kas_kecil_transaksi',
                $transaksi->id,
                null,
                $transaksi->toArray(),
                $request
            );

            return $transaksi;
        });

        return $transaksi;
    }

    /**
     * Ajukan kas langsung / top-up kas kecil oleh petugas.
     * Status awal: pending_keuangan, menunggu persetujuan Admin Keuangan.
     */
    public function storePengajuan(array $data, ?Request $request = null): KasKecilPengajuan
    {
        $pengajuan = DB::transaction(function () use ($data, $request) {
            $unitKas = $this->showUnitKas((int) $data['unit_kas_id']);

            $nominal = (float) $data['nominal_diajukan'];
            if ($nominal <= 0) {
                throw new \InvalidArgumentException('Nominal pengajuan harus lebih dari nol.');
            }

            $pengajuan = KasKecilPengajuan::create([
                'unit_kas_id' => $unitKas->id,
                'nomor_pengajuan' => 'KK-PGJ-' . date('YmdHis') . '-' . strtoupper(Str::random(4)),
                'judul_pengajuan' => trim($data['judul_pengajuan']),
                'keperluan' => $data['keperluan'] ?? null,
                'nominal_diajukan' => $nominal,
                'nominal_disetujui' => 0,
                'status' => 'pending_keuangan',
                'pemohon_id' => auth()->id(),
                'created_by' => auth()->id(),
            ]);

            AuditLogService::record(
                'Sikeu',
                'ajukan_kas_kecil',
                'sikeu_kas_kecil_pengajuan',
                $pengajuan->id,
                null,
                $pengajuan->toArray(),
                $request
            );

            return $pengajuan;
        });

        return $pengajuan;
    }

    /**
     * Setujui pengajuan kas langsung. Saldo unit kas bertambah,
     * direkam di TransaksiKasUnit (debet_pemasukan) dan dibuatkan
     * jurnal pengisian kas kecil: Dr Kas Unit / Cr Kas Utama.
     */
    public function approvePengajuan(KasKecilPengajuan $pengajuan, array $data, ?Request $request = null): KasKecilPengajuan
    {
        $pengajuan = DB::transaction(function () use ($pengajuan, $data, $request) {
            if ($pengajuan->status === 'disetujui') {
                throw new \RuntimeException('Pengajuan kas kecil ini sudah disetujui.');
            }
            if ($pengajuan->status === 'ditolak') {
                throw new \RuntimeException('Pengajuan yang ditolak tidak dapat disetujui kembali. Buat pengajuan baru.');
            }

            $unitKas = UnitKas::find($pengajuan->unit_kas_id);
            if (!$unitKas || !$unitKas->status) {
                throw new \InvalidArgumentException('Unit kas kecil tidak valid / tidak aktif.');
            }

            $nominalSetujui = (float) ($data['nominal_disetujui'] ?? $pengajuan->nominal_diajukan);
            if ($nominalSetujui <= 0 || $nominalSetujui > (float) $pengajuan->nominal_diajukan) {
                throw new \InvalidArgumentException('Nominal disetujui tidak valid.');
            }

            // 1. Update status pengajuan
            $pengajuan->status = 'disetujui';
            $pengajuan->nominal_disetujui = $nominalSetujui;
            $pengajuan->approved_by = auth()->id();
            $pengajuan->approved_at = now();
            $pengajuan->save();

            // 2. Mutasi kas unit (debet / pemasukan) -> saldo bertambah
            $saldoSebelum = (float) $unitKas->saldo_saat_ini;
            $saldoSesudah = $saldoSebelum + $nominalSetujui;
            $unitKas->saldo_saat_ini = $saldoSesudah;
            $unitKas->save();

            $transaksiKasUnit = TransaksiKasUnit::create([
                'unit_kas_id' => $unitKas->id,
                'kode_transaksi' => 'KK-TOPUP-' . date('Ymd') . '-' . strtoupper(Str::random(5)),
                'jenis_transaksi' => 'debet_pemasukan',
                'nominal' => $nominalSetujui,
                'saldo_sebelum' => $saldoSebelum,
                'saldo_sesudah' => $saldoSesudah,
                'keterangan' => 'Pengisian kas kecil: ' . $pengajuan->nomor_pengajuan,
                'tanggal_transaksi' => now()->toDateString(),
                'created_by' => auth()->id(),
            ]);

            $pengajuan->transaksi_kas_unit_id = $transaksiKasUnit->id;
            $pengajuan->save();

            // 3. Jurnal pengisian kas kecil: Dr Kas Unit / Cr Kas Utama
            JurnalSikeuService::jurnalPengisianKasKecil(
                $pengajuan->id,
                $nominalSetujui,
                $pengajuan->nomor_pengajuan . ' - ' . $pengajuan->judul_pengajuan,
                $unitKas
            );

            AuditLogService::record(
                'Sikeu',
                'setujui_kas_kecil',
                'sikeu_kas_kecil_pengajuan',
                $pengajuan->id,
                ['status' => 'pending_keuangan'],
                $pengajuan->toArray(),
                $request
            );

            return $pengajuan;
        });

        return $pengajuan;
    }

    /**
     * Tolak pengajuan kas langsung oleh Admin Keuangan.
     * Tidak ada mutasi saldo / jurnal.
     */
    public function rejectPengajuan(KasKecilPengajuan $pengajuan, array $data, ?Request $request = null): KasKecilPengajuan
    {
        $pengajuan = DB::transaction(function () use ($pengajuan, $data, $request) {
            if ($pengajuan->status !== 'pending_keuangan') {
                throw new \RuntimeException('Hanya pengajuan berstatus pending_keuangan yang dapat ditolak.');
            }

            $catatan = trim($data['catatan_penolakan'] ?? '');
            if ($catatan === '') {
                throw new \InvalidArgumentException('Catatan penolakan wajib diisi.');
            }

            $old = $pengajuan->only(['status']);
            $pengajuan->status = 'ditolak';
            $pengajuan->catatan_penolakan = $catatan;
            $pengajuan->approved_by = auth()->id();
            $pengajuan->approved_at = now();
            $pengajuan->save();

            AuditLogService::record(
                'Sikeu',
                'tolak_kas_kecil',
                'sikeu_kas_kecil_pengajuan',
                $pengajuan->id,
                $old,
                $pengajuan->only(['status', 'catatan_penolakan', 'approved_by', 'approved_at']),
                $request
            );

            return $pengajuan;
        });

        return $pengajuan;
    }

    /**
     * Hapus / Batalkan pengajuan kas langsung (hanya untuk status pending_keuangan).
     */
    public function deletePengajuan(KasKecilPengajuan $pengajuan, ?Request $request = null): bool
    {
        return DB::transaction(function () use ($pengajuan, $request) {
            if ($pengajuan->status !== 'pending_keuangan') {
                throw new \RuntimeException('Hanya pengajuan dengan status menunggu persetujuan (pending) yang dapat dihapus/dibatalkan.');
            }

            $oldData = $pengajuan->toArray();
            $pengajuan->delete();

            AuditLogService::record(
                'Sikeu',
                'hapus_pengajuan_kas_kecil',
                'sikeu_kas_kecil_pengajuan',
                $oldData['id'] ?? null,
                $oldData,
                null,
                $request
            );

            return true;
        });
    }

    protected function simpanFile($file, string $folder): string
    {
        $name = Str::uuid() . '.' . $file->getClientOriginalExtension();
        return $file->storeAs($folder . '/' . date('Y/m'), $name, 'public');
    }

    public function fileUrl(?string $path): ?string
    {
        if (empty($path)) {
            return null;
        }
        return Storage::disk('public')->url($path);
    }
}