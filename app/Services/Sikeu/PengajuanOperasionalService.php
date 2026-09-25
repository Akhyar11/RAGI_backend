<?php

namespace App\Services\Sikeu;

use App\Models\Sikeu\ApprovalHistoryPencairan;
use App\Models\Sikeu\LaporanBuktiPelaksanaan;
use App\Models\Sikeu\LpjDetail;
use App\Models\Sikeu\PengajuanItem;
use App\Models\Sikeu\PengajuanPencairanKas;
use App\Models\Sikeu\TransaksiKasUnit;
use App\Models\Sikeu\UnitKas;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PengajuanOperasionalService
{
    protected function simpanFile($file, string $folder): string
    {
        $name = Str::uuid() . '.' . $file->getClientOriginalExtension();
        return $file->storeAs($folder . '/' . date('Y/m'), $name, 'public');
    }

    protected function statusAwal(string $kategori): string
    {
        // Barang: pengaju -> sarpras dulu. Non-barang: langsung keuangan.
        return $kategori === 'pengadaan_barang' ? 'pending_sarpras' : 'pending_keuangan';
    }

    public function create(array $data, ?Request $request = null): PengajuanPencairanKas
    {
        $pengajuan = DB::transaction(function () use ($data, $request) {
            $kategori = $data['kategori_pengajuan'] ?? 'pengadaan_barang';

            $items = $data['items'] ?? [];
            $nominal = 0;
            if ($kategori === 'pengadaan_barang') {
                foreach ($items as $it) {
                    $nominal += (float) $it['qty'] * (float) $it['harga_satuan'];
                }
            } else {
                $nominal = (float) ($data['nominal_diajukan'] ?? 0);
            }

            if ($nominal <= 0) {
                throw new \InvalidArgumentException('Total pengajuan harus lebih dari nol.');
            }

            $lampiranPath = null;
            if ($request && $request->hasFile('file_lampiran')) {
                $lampiranPath = $this->simpanFile($request->file('file_lampiran'), 'sikeu/pengajuan_operasional');
            }

            $pengajuan = PengajuanPencairanKas::create([
                'nomor_pengajuan' => 'PO-' . date('Ymd') . '-' . strtoupper(Str::random(5)),
                'unit_kas_id' => $data['unit_kas_id'],
                'fakultas_id' => $data['fakultas_id'],
                'ruangan_id' => $data['ruangan_id'] ?? null,
                'pemohon_id' => auth()->id(),
                'judul_pengajuan' => $data['judul_pengajuan'],
                'deskripsi' => $data['deskripsi'],
                'nominal_diajukan' => $nominal,
                'nominal_disetujui' => 0,
                'jenis_pengajuan' => $data['jenis_pengajuan'] ?? 'operasional',
                'kategori_pengajuan' => $kategori,
                'file_lampiran' => $lampiranPath,
                'status' => $this->statusAwal($kategori),
            ]);

            if ($kategori === 'pengadaan_barang') {
                foreach ($items as $it) {
                    $qty = (float) $it['qty'];
                    $harga = (float) $it['harga_satuan'];
                    PengajuanItem::create([
                        'pengajuan_id' => $pengajuan->id,
                        'nama_barang' => $it['nama_barang'],
                        'qty' => $qty,
                        'satuan' => $it['satuan'] ?? 'pcs',
                        'harga_satuan' => $harga,
                        'subtotal' => $qty * $harga,
                        'keterangan' => $it['keterangan'] ?? null,
                    ]);
                }
            }

            return $pengajuan;
        });

        // Audit log di luar transaksi: kegagalan audit tidak boleh me-rollback pengajuan.
        if ($request) {
            try {
                AuditLogService::record(
                    module: 'SIKEU',
                    action: 'create',
                    tableName: 'sikeu_pengajuan_pencairan_kas',
                    recordId: $pengajuan->id,
                    newValues: $pengajuan->toArray(),
                    request: $request,
                );
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('AuditLog pengajuan operasional dilewati: ' . $e->getMessage());
            }
        }

        return $pengajuan->load(['items', 'fakultas', 'ruangan', 'unitKas']);
    }

    /**
     * Approval bertahap. Urutan barang: pending_sarpras -> pending_keuangan -> pending_direktur -> disetujui.
     * Non-barang: pending_keuangan -> pending_direktur -> disetujui.
     */
    public function approve(PengajuanPencairanKas $pengajuan, string $aksi, ?string $catatan, ?Request $request = null): PengajuanPencairanKas
    {
        $tahapSaatIni = $pengajuan->status;
        $roleMap = [
            'pending_sarpras' => 'sarpras',
            'pending_keuangan' => 'keuangan',
            'pending_direktur' => 'direktur',
        ];

        if (!isset($roleMap[$tahapSaatIni])) {
            throw new \RuntimeException("Pengajuan status '{$tahapSaatIni}' tidak dapat di-approval.");
        }

        $role = $roleMap[$tahapSaatIni];

        $pengajuan = DB::transaction(function () use ($pengajuan, $aksi, $catatan, $tahapSaatIni, $role) {
            $userId = auth()->id() ?? 1;

            ApprovalHistoryPencairan::create([
                'pengajuan_id' => $pengajuan->id,
                'user_id' => $userId,
                'role_approver' => $role,
                'status_action' => $aksi === 'approve' ? 'approved' : 'rejected',
                'catatan' => $catatan,
            ]);

            if ($aksi === 'reject') {
                $pengajuan->update([
                    'status' => 'ditolak',
                    'catatan_penolakan' => $catatan,
                ]);
            } else {
                if ($tahapSaatIni === 'pending_sarpras') {
                    $pengajuan->update([
                        'approved_sarpras_by' => $userId,
                        'approved_sarpras_at' => now(),
                        'status' => 'pending_keuangan',
                    ]);
                } elseif ($tahapSaatIni === 'pending_keuangan') {
                    $pengajuan->update([
                        'approved_keuangan_by' => $userId,
                        'approved_keuangan_at' => now(),
                        'status' => 'pending_direktur',
                    ]);
                } elseif ($tahapSaatIni === 'pending_direktur') {
                    $pengajuan->update([
                        'approved_direktur_by' => $userId,
                        'approved_direktur_at' => now(),
                        'nominal_disetujui' => $pengajuan->nominal_diajukan,
                        'status' => 'disetujui',
                    ]);
                }
            }

            return $pengajuan->fresh(['items', 'fakultas', 'ruangan', 'unitKas', 'historyApproval']);
        });

        // Audit log di luar transaksi agar tidak me-rollback approval.
        if ($request) {
            try {
                AuditLogService::record(
                    module: 'SIKEU',
                    action: $aksi,
                    tableName: 'sikeu_pengajuan_pencairan_kas',
                    recordId: $pengajuan->id,
                    oldValues: ['status' => $tahapSaatIni],
                    newValues: ['status' => $pengajuan->status, 'role' => $role],
                    request: $request,
                );
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('AuditLog approval pengajuan dilewati: ' . $e->getMessage());
            }
        }

        return $pengajuan->fresh(['items', 'fakultas', 'ruangan', 'unitKas', 'historyApproval']);
    }

    /**
     * Pencairan manual oleh keuangan setelah disetujui direktur.
     */
    public function cairkan(PengajuanPencairanKas $pengajuan, array $data, ?Request $request = null): PengajuanPencairanKas
    {
        $pengajuan = DB::transaction(function () use ($pengajuan, $data, $request) {
            if ($pengajuan->status !== 'disetujui') {
                throw new \RuntimeException('Pencairan hanya untuk pengajuan berstatus disetujui.');
            }

            $nominal = (float) ($data['nominal_cair'] ?? $pengajuan->nominal_disetujui);
            if ($nominal <= 0 || $nominal > (float) $pengajuan->nominal_disetujui) {
                throw new \InvalidArgumentException('Nominal cair tidak valid.');
            }

            $buktiPath = $pengajuan->bukti_pencairan_path;
            if ($request && $request->hasFile('bukti_pencairan')) {
                $buktiPath = $this->simpanFile($request->file('bukti_pencairan'), 'sikeu/pencairan_operasional');
            }

            // Keuangan boleh memilih sumber saldo berbeda dari unit kas pengajuan
            if (!empty($data['unit_kas_id']) && (int)$data['unit_kas_id'] !== (int)$pengajuan->unit_kas_id) {
                $unitKasBaru = UnitKas::find($data['unit_kas_id']);
                if (!$unitKasBaru || !$unitKasBaru->status) {
                    throw new \InvalidArgumentException('Sumber dana pencairan tidak valid / tidak aktif.');
                }
                $pengajuan->unit_kas_id = $unitKasBaru->id;
            }

            $unitKas = UnitKas::find($pengajuan->unit_kas_id);
            $saldoSebelum = $unitKas ? (float) $unitKas->saldo_saat_ini : 0;
            if ($unitKas) {
                $unitKas->increment('saldo_saat_ini', $nominal);
            }

            if ($unitKas) {
                TransaksiKasUnit::create([
                    'unit_kas_id' => $unitKas->id,
                    'pengajuan_pencairan_id' => $pengajuan->id,
                    'kode_transaksi' => 'CAIR-' . date('Ymd') . '-' . strtoupper(Str::random(4)),
                    'jenis_transaksi' => 'debet_pemasukan',
                    'nominal' => $nominal,
                    'saldo_sebelum' => $saldoSebelum,
                    'saldo_sesudah' => $saldoSebelum + $nominal,
                    'keterangan' => 'Pencairan operasional: ' . $pengajuan->nomor_pengajuan,
                    'tanggal_transaksi' => $data['tanggal_pencairan'] ?? now()->toDateString(),
                ]);
            }

            // Jurnal akrual pencairan: Dr Beban Operasional / Cr Kas kanal unit kas
            $akunKasCair = \App\Services\Sikeu\JurnalSikeuService::akunKasUnit($unitKas, '101.01');
            AutoJournalService::recordDisbursementJournal(
                'OPERASIONAL',
                $pengajuan->id,
                $nominal,
                'Pencairan operasional ' . $pengajuan->nomor_pengajuan . ' - ' . $pengajuan->judul_pengajuan,
                '502.01',
                $akunKasCair->kode_akun
            );

            $pengajuan->update([
                'status' => 'dicairkan',
                'tanggal_pencairan' => $data['tanggal_pencairan'] ?? now()->toDateString(),
                'bukti_pencairan_path' => $buktiPath,
                'kanal' => $pengajuan->kanal ?? $unitKas?->kanal,
                'referensi_eksternal' => $data['referensi_eksternal'] ?? $pengajuan->referensi_eksternal,
            ]);

            // Sinkronisasi status_pencairan ke SIMPEG jika ini adalah surat tugas
            if ($pengajuan->kanal === 'simpeg_surat_tugas') {
                \App\Models\Simpeg\SuratTugas::where('sikeu_pencairan_id', $pengajuan->id)
                    ->update(['status_pencairan' => 'dicairkan']);
            }

            return $pengajuan->fresh(['items', 'unitKas']);
        });

        if ($request) {
            try {
                AuditLogService::record(
                    module: 'SIKEU',
                    action: 'pencairan',
                    tableName: 'sikeu_pengajuan_pencairan_kas',
                    recordId: $pengajuan->id,
                    newValues: ['nominal' => (float) ($data['nominal_cair'] ?? $pengajuan->nominal_disetujui)],
                    request: $request,
                );
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('AuditLog pencairan operasional dilewati: ' . $e->getMessage());
            }
        }

        return $pengajuan;
    }

    /**
     * Keuangan menentukan unit kas dan nominal panjar untuk pengajuan surat tugas SIMPEG (Tahap 3).
     */
    public function setujuiPanjarSimpeg(PengajuanPencairanKas $pengajuan, array $data, ?Request $request = null): PengajuanPencairanKas
    {
        $pengajuan = DB::transaction(function () use ($pengajuan, $data) {
            $unitKas = UnitKas::findOrFail($data['unit_kas_id']);
            if (!$unitKas->status) {
                throw new \InvalidArgumentException('Unit kas yang dipilih tidak aktif.');
            }

            $nominal = (float) $data['nominal_disetujui'];
            if ($nominal <= 0) {
                throw new \InvalidArgumentException('Nominal panjar disetujui harus lebih dari nol.');
            }

            $userId = auth()->id();

            $pengajuan->update([
                'unit_kas_id' => $unitKas->id,
                'nominal_disetujui' => $nominal,
                'status' => 'menunggu_konfirmasi_pegawai',
                'approved_keuangan_by' => $userId,
                'approved_keuangan_at' => now(),
            ]);

            // Catat history
            ApprovalHistoryPencairan::create([
                'pengajuan_id' => $pengajuan->id,
                'user_id' => $userId,
                'tahap' => 'keuangan',
                'aksi' => 'approve',
                'catatan' => $data['catatan'] ?? 'Panjar disetujui dari kas: ' . $unitKas->nama_kas . ' sebesar Rp ' . number_format($nominal, 0, ',', '.'),
            ]);

            // Update item nominal disetujui
            PengajuanItem::where('pengajuan_id', $pengajuan->id)->update([
                'harga_satuan' => $nominal,
                'subtotal' => $nominal,
            ]);

            // Sinkronisasi ke modul SIMPEG: nominal_disetujui dan status_pencairan
            \App\Models\Simpeg\SuratTugas::where('sikeu_pencairan_id', $pengajuan->id)->update([
                'nominal_disetujui' => $nominal,
                'status_pencairan' => 'panjar_disetujui',
            ]);

            return $pengajuan->fresh(['items', 'unitKas', 'suratTugas.pegawai']);
        });

        if ($request) {
            try {
                AuditLogService::record(
                    module: 'SIKEU',
                    action: 'approve_panjar_simpeg',
                    tableName: 'sikeu_pengajuan_pencairan_kas',
                    recordId: $pengajuan->id,
                    newValues: ['nominal_disetujui' => $pengajuan->nominal_disetujui, 'unit_kas_id' => $pengajuan->unit_kas_id],
                    request: $request,
                );
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('AuditLog approve panjar dilewati: ' . $e->getMessage());
            }
        }

        return $pengajuan;
    }

    /**
     * Keuangan memverifikasi dan menutup transaksi pengajuan dinas SIMPEG setelah LPJ & selisih dana beres (Tahap 8).
     */
    public function tutupLpjSimpeg(PengajuanPencairanKas $pengajuan, array $data, ?Request $request = null): PengajuanPencairanKas
    {
        $pengajuan = DB::transaction(function () use ($pengajuan, $data) {
            $userId = auth()->id();

            $pengajuan->update([
                'status' => 'selesai',
            ]);

            ApprovalHistoryPencairan::create([
                'pengajuan_id' => $pengajuan->id,
                'user_id' => $userId,
                'tahap' => 'keuangan',
                'aksi' => 'approve',
                'catatan' => $data['catatan'] ?? 'LPJ perjalanan dinas telah diverifikasi dan kasbon ditutup (selesai).',
            ]);

            // Sinkronisasi ke SIMPEG
            \App\Models\Simpeg\SuratTugas::where('sikeu_pencairan_id', $pengajuan->id)->update([
                'status_pencairan' => 'selesai',
                'status' => 'selesai',
            ]);

            return $pengajuan->fresh(['items', 'unitKas', 'suratTugas.pegawai']);
        });

        if ($request) {
            try {
                AuditLogService::record(
                    module: 'SIKEU',
                    action: 'tutup_lpj_simpeg',
                    tableName: 'sikeu_pengajuan_pencairan_kas',
                    recordId: $pengajuan->id,
                    newValues: ['status' => 'selesai'],
                    request: $request,
                );
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('AuditLog tutup lpj dilewati: ' . $e->getMessage());
            }
        }

        return $pengajuan;
    }

    /**
     * LPJ oleh pengaju: upload nota, hitung sisa, opsi tambahan pembelian / bukti transfer kembali.
     */
    public function simpanLpj(PengajuanPencairanKas $pengajuan, array $data, ?Request $request = null): LaporanBuktiPelaksanaan
    {
        $lpj = DB::transaction(function () use ($pengajuan, $data, $request) {
            if (!in_array($pengajuan->status, ['dicairkan', 'lpj_pending'])) {
                throw new \RuntimeException('LPJ hanya untuk pengajuan yang sudah dicairkan.');
            }

            $nominalCair = (float) $pengajuan->nominal_disetujui;
            $realisasi = (float) $data['total_realisasi'];

            $tambahanTotal = 0;
            foreach (($data['tambahan'] ?? []) as $t) {
                $tambahanTotal += (float) $t['qty'] * (float) $t['harga_satuan'];
            }
            $totalPakai = $realisasi + $tambahanTotal;
            if ($totalPakai > $nominalCair) {
                throw new \InvalidArgumentException('Total realisasi + tambahan melebihi nominal yang dicairkan.');
            }
            $sisa = $nominalCair - $totalPakai;

            $metode = $data['metode_sisa'] ?? 'belum_ditentukan';
            if ($sisa > 0 && $metode === 'belum_ditentukan') {
                throw new \InvalidArgumentException('Sisa dana wajib memilih metode: kembali_transfer / pakai_lagi.');
            }
            if ($sisa == 0) {
                $metode = 'belum_ditentukan';
            }

            $notaPath = null;
            if ($request && $request->hasFile('file_nota_kuitansi')) {
                $notaPath = $this->simpanFile($request->file('file_nota_kuitansi'), 'sikeu/lpj_operasional');
            }
            $kembaliPath = null;
            if ($request && $request->hasFile('bukti_pengembalian')) {
                $kembaliPath = $this->simpanFile($request->file('bukti_pengembalian'), 'sikeu/lpj_pengembalian');
            }
            if ($metode === 'kembali_transfer' && !$kembaliPath && !$request?->input('bukti_pengembalian_path')) {
                throw new \InvalidArgumentException('Bukti transfer pengembalian wajib diunggah.');
            }

            $lpj = LaporanBuktiPelaksanaan::updateOrCreate(
                ['pengajuan_id' => $pengajuan->id],
                [
                    'sumber_tipe' => 'pengajuan_pencairan',
                    'sumber_id' => $pengajuan->id,
                    'nomor_bukti' => 'LPJ-' . date('Ymd') . '-' . strtoupper(Str::random(4)),
                    'tanggal_pelaksanaan' => $data['tanggal_pelaksanaan'],
                    'total_realisasi' => $totalPakai,
                    'nominal_dicairkan' => $nominalCair,
                    'sisa_nominal' => $sisa,
                    'metode_sisa' => $metode,
                    'file_nota_kuitansi' => $notaPath ?? LaporanBuktiPelaksanaan::where('pengajuan_id', $pengajuan->id)->value('file_nota_kuitansi'),
                    'bukti_pengembalian_path' => $kembaliPath,
                    'nomor_rekening_tujuan' => $data['nomor_rekening_tujuan'] ?? null,
                    'rincian_keterangan' => $data['rincian_keterangan'] ?? null,
                    'status_verifikasi' => 'pending',
                ]
            );

            // Sinkron rincian tambahan
            $lpj->details()->where('tipe', 'tambahan')->delete();
            $files = $request ? $request->file('tambahan_files', []) : [];
            foreach (($data['tambahan'] ?? []) as $idx => $t) {
                $buktiTambahan = null;
                if (is_array($files) && isset($files[$idx])) {
                    $buktiTambahan = $this->simpanFile($files[$idx], 'sikeu/lpj_tambahan');
                }
                LpjDetail::create([
                    'lpj_id' => $lpj->id,
                    'tipe' => 'tambahan',
                    'keterangan' => $t['keterangan'],
                    'qty' => (float) $t['qty'],
                    'satuan' => $t['satuan'] ?? 'pcs',
                    'harga_satuan' => (float) $t['harga_satuan'],
                    'subtotal' => (float) $t['qty'] * (float) $t['harga_satuan'],
                    'file_bukti_path' => $buktiTambahan,
                ]);
            }

            $pengajuan->update([
                'total_realisasi' => $totalPakai,
                'sisa_nominal' => $sisa,
                'status' => 'lpj_pending',
            ]);

            return $lpj->load('details');
        });

        if ($request) {
            try {
                AuditLogService::record(
                    module: 'SIKEU',
                    action: 'lpj',
                    tableName: 'sikeu_laporan_bukti_pelaksanaan',
                    recordId: $lpj->id,
                    newValues: ['total' => (float) $lpj->total_realisasi, 'sisa' => (float) $lpj->sisa_nominal],
                    request: $request,
                );
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('AuditLog LPJ operasional dilewati: ' . $e->getMessage());
            }
        }

        return $lpj->load('details');
    }

    /**
     * Verifikasi LPJ oleh keuangan: otomatis input jurnal realisasi.
     */
    public function verifikasiLpj(LaporanBuktiPelaksanaan $lpj, string $aksi, ?string $catatan, ?Request $request = null): LaporanBuktiPelaksanaan
    {
        $lpj = DB::transaction(function () use ($lpj, $aksi, $catatan) {
            $lpj->update([
                'status_verifikasi' => $aksi === 'approve' ? 'disetujui' : 'ditolak',
                'diverifikasi_oleh' => auth()->id(),
                'catatan_verifikasi' => $catatan,
            ]);

            $pengajuan = $lpj->pengajuan;
            if ($aksi === 'approve' && $pengajuan) {
                // Jurnal otomatis realisasi belanja
                JurnalSikeuService::jurnalRealisasiOperasional(
                    $pengajuan->id,
                    (float) $lpj->total_realisasi,
                    'Realisasi ' . $pengajuan->nomor_pengajuan . ' - ' . $pengajuan->judul_pengajuan
                );

                $pengajuan->update(['status' => 'selesai']);
            } elseif ($pengajuan) {
                $pengajuan->update(['status' => 'dicairkan']);
            }

            return $lpj->fresh(['details', 'pengajuan']);
        });

        if ($request) {
            try {
                AuditLogService::record(
                    module: 'SIKEU',
                    action: 'verifikasi_lpj',
                    tableName: 'sikeu_laporan_bukti_pelaksanaan',
                    recordId: $lpj->id,
                    newValues: ['status' => $lpj->status_verifikasi],
                    request: $request,
                );
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('AuditLog verifikasi LPJ dilewati: ' . $e->getMessage());
            }
        }

        return $lpj->fresh(['details', 'pengajuan']);
    }
}
