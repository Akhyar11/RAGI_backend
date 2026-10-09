<?php

namespace App\Services\Sinapra;

use App\Models\PeminjamanRuangan;
use App\Models\PeminjamanAset;
use App\Models\Aset;
use App\Models\Ruangan;
use App\Models\MaintenanceLog;
use App\Models\Simpeg\TandaTanganPegawai;
use App\Services\AuditLogService;
use App\Services\Storage\FileStorageService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Exception;

class PeminjamanService
{
    public function __construct(private GedungRuanganService $gedungRuanganService) {}

    /**
     * Pengajuan Peminjaman Ruangan oleh User.
     */
    public function applyPeminjamanRuangan(array $data, int $userId): PeminjamanRuangan
    {
        return DB::transaction(function () use ($data, $userId) {
            // Cek ketersediaan jadwal ruangan
            $isAvailable = $this->gedungRuanganService->checkRuanganKetersediaan(
                ruanganId: $data['ruangan_id'],
                tanggal: $data['tanggal'],
                jamMulai: $data['jam_mulai'],
                jamSelesai: $data['jam_selesai']
            );

            if (!$isAvailable) {
                throw new Exception("Ruangan tidak tersedia pada tanggal dan jam yang dipilih (terdapat bentrok jadwal atau perkuliahan).");
            }

            $ruangan = Ruangan::findOrFail($data['ruangan_id']);
            $isLab = ($ruangan->tipe === 'lab') || $ruangan->laboran()->exists();

            $data['user_id'] = $userId;
            $data['status'] = $isLab ? 'pending_laboran' : 'pending_admin_sinapra';

            if (empty($data['kode_peminjaman'])) {
                $data['kode_peminjaman'] = 'PMR-' . date('Ymd') . '-' . strtoupper(\Illuminate\Support\Str::random(5));
            }

            if (empty($data['nomor_identitas'])) {
                $user = \App\Models\User::with(['mahasiswa', 'pegawai'])->find($userId);
                if ($user) {
                    $data['nomor_identitas'] = $user->mahasiswa?->nim 
                        ?? $user->pegawai?->nidn 
                        ?? $user->pegawai?->nuptk 
                        ?? $user->pegawai?->nip 
                        ?? $user->username;
                }
            }

            $peminjaman = PeminjamanRuangan::create($data);

            AuditLogService::record(
                module: 'SINAPRA',
                action: 'create',
                tableName: 'peminjaman_ruangan',
                recordId: $peminjaman->id,
                newValues: $peminjaman->toArray()
            );

            return $peminjaman;
        });
    }

    /**
     * Persetujuan Tahap Laboran untuk Peminjaman Ruangan Laboratorium.
     */
    public function approveLaboranRuangan(
        PeminjamanRuangan $peminjaman,
        int $laboranId,
        bool $isApproved,
        ?string $catatanLaboran = null
    ): PeminjamanRuangan {
        return DB::transaction(function () use ($peminjaman, $laboranId, $isApproved, $catatanLaboran) {
            $oldValues = $peminjaman->toArray();

            if ($isApproved) {
                $peminjaman->status = 'pending_admin_sinapra';
            } else {
                $peminjaman->status = 'ditolak_laboran';
            }

            $peminjaman->laboran_approved_by = $laboranId;
            $peminjaman->laboran_approved_at = now();
            $peminjaman->catatan_laboran = $catatanLaboran;
            $peminjaman->save();

            try {
                AuditLogService::record(
                    module: 'SIAKAD',
                    action: $isApproved ? 'approve' : 'reject',
                    tableName: 'peminjaman_ruangan',
                    recordId: $peminjaman->id,
                    oldValues: $oldValues,
                    newValues: $peminjaman->fresh()->toArray(),
                    request: request()
                );
            } catch (\Throwable $e) {
                report($e);
            }

            return $peminjaman->fresh();
        });
    }

    /**
     * Persetujuan Akhir Peminjaman Ruangan oleh Admin SINAPRA.
     */
    public function approvePeminjamanRuangan(
        PeminjamanRuangan $peminjaman,
        int $approverId,
        bool $isApproved,
        ?string $catatanPenolakan = null
    ): PeminjamanRuangan {
        return DB::transaction(function () use ($peminjaman, $approverId, $isApproved, $catatanPenolakan) {
            $oldValues = $peminjaman->toArray();

            if ($isApproved) {
                // Double check bentrok jadwal sebelum disetujui
                $isAvailable = $this->gedungRuanganService->checkRuanganKetersediaan(
                    ruanganId: $peminjaman->ruangan_id,
                    tanggal: $peminjaman->tanggal,
                    jamMulai: $peminjaman->jam_mulai,
                    jamSelesai: $peminjaman->jam_selesai,
                    excludePeminjamanId: $peminjaman->id
                );

                if (!$isAvailable) {
                    throw new Exception("Tidak dapat menyetujui. Ruangan sudah disetujui untuk peminjam lain pada jam yang sama.");
                }

                $peminjaman->status = 'disetujui';

                // Otomatis integrasikan penomoran surat ke modul ARSIP jika belum ada nomor surat
                if (empty($peminjaman->nomor_surat)) {
                    $nomorSurat = null;
                    try {
                        $peminjamUser = $peminjaman->user()->with(['pegawai.unitKerja', 'mahasiswa.prodi'])->first();
                        $unitKerjaKode = $peminjamUser?->pegawai?->unitKerja?->kode ?? 'BAUK-01';
                        $tanggalSurat = $peminjaman->tanggal ? $peminjaman->tanggal->toDateString() : now()->toDateString();
                        $ruanganNama = $peminjaman->ruangan?->nama ?? 'Ruangan Kampus';

                        // Buat permohonan nomor surat resmi ke modul ARSIP
                        $arsipRequestService = app(\App\Services\Arsip\RequestNomorSuratService::class);
                        $arsipReq = $arsipRequestService->createRequest([
                            'module_origin' => 'sinapra',
                            'reference_type' => \App\Models\PeminjamanRuangan::class,
                            'reference_id' => $peminjaman->id,
                            'perihal' => 'Izin Peminjaman Ruangan: ' . $ruanganNama . ' - ' . $peminjaman->keperluan,
                            'tujuan' => $peminjamUser?->name ?? 'Peminjam Ruangan',
                            'tanggal_surat' => $tanggalSurat,
                            'kode_unit' => $unitKerjaKode,
                            'kode_klasifikasi' => 'DVIII', // Surat Keterangan / Izin Pemakaian
                            'jumlah_nomor' => 1,
                            'catatan_pemohon' => 'Permohonan nomor surat bukti peminjaman ruangan SINAPRA kode ' . $peminjaman->kode_peminjaman,
                        ], $approverId);

                        // Terbitkan nomor resmi langsung via ARSIP NomorSuratService yang terdaftar di agenda ARSIP
                        $arsipNomorService = app(\App\Services\Arsip\NomorSuratService::class);
                        $nomorResmi = $arsipNomorService->generateSatuan([
                            'tanggal_surat' => $tanggalSurat,
                            'kode_unit' => $unitKerjaKode,
                            'kode_klasifikasi' => 'DVIII',
                            'perihal' => 'Izin Peminjaman Ruangan: ' . $ruanganNama . ' - ' . $peminjaman->keperluan,
                            'tujuan' => $peminjamUser?->name ?? 'Peminjam Ruangan',
                            'status' => 'terpakai',
                            'module_origin' => 'sinapra',
                            'request_id' => $arsipReq->id,
                            'reference_type' => \App\Models\PeminjamanRuangan::class,
                            'reference_id' => $peminjaman->id,
                            'catatan' => 'Peminjaman Ruangan SINAPRA #' . $peminjaman->id,
                        ], $approverId);

                        $arsipReq->update([
                            'status' => 'disetujui',
                            'verified_by' => $approverId,
                            'verified_at' => now(),
                            'catatan_verifikasi' => 'Disetujui otomatis melalui persetujuan peminjaman ruangan SINAPRA.',
                        ]);

                        $nomorSurat = $nomorResmi->nomor_surat;
                    } catch (\Throwable $e) {
                        Log::warning('Gagal generate nomor surat ARSIP untuk PeminjamanRuangan #' . $peminjaman->id . ': ' . $e->getMessage());
                        // Fallback penomoran jika layanan ARSIP terkendala
                        $bulan = date('m');
                        $tahun = date('Y');
                        $nomorSurat = sprintf('%03d/SINAPRA-RUANG/%s/%s', $peminjaman->id, $bulan, $tahun);
                    }

                    $peminjaman->nomor_surat = $nomorSurat;
                    $peminjaman->surat_generated_at = now();
                }
            } else {
                $peminjaman->status = 'ditolak_admin_sinapra';
                $peminjaman->catatan_penolakan = $catatanPenolakan;
            }

            $peminjaman->disetujui_oleh = $approverId;
            $peminjaman->admin_approved_at = now();
            $peminjaman->save();

            AuditLogService::record(
                module: 'SINAPRA',
                action: $isApproved ? 'approve' : 'reject',
                tableName: 'peminjaman_ruangan',
                recordId: $peminjaman->id,
                oldValues: $oldValues,
                newValues: $peminjaman->fresh()->toArray()
            );

            return $peminjaman->fresh();
        });
    }

    /**
     * Pengajuan Peminjaman Aset oleh User (Mendukung 1 atau Banyak Aset Sekaligus).
     */
    public function applyPeminjamanAset(array $data, int $userId): PeminjamanAset
    {
        return DB::transaction(function () use ($data, $userId) {
            $asetIds = [];
            if (!empty($data['aset_ids']) && is_array($data['aset_ids'])) {
                $asetIds = array_values(array_unique(array_filter($data['aset_ids'])));
            } elseif (!empty($data['aset_id'])) {
                $asetIds = [(int) $data['aset_id']];
            }

            if (empty($asetIds)) {
                throw new Exception("Minimal satu barang aset wajib dipilih untuk dipinjam.");
            }

            $nomorIdentitas = $data['nomor_identitas'] ?? null;
            if (empty($nomorIdentitas)) {
                $user = \App\Models\User::with(['mahasiswa', 'pegawai'])->find($userId);
                if ($user) {
                    $nomorIdentitas = $user->mahasiswa?->nim 
                        ?? $user->pegawai?->nidn 
                        ?? $user->pegawai?->nuptk 
                        ?? $user->pegawai?->nip 
                        ?? $user->username;
                }
            }

            $kodePeminjaman = 'PMA-' . date('Ymd') . '-' . strtoupper(\Illuminate\Support\Str::random(5));
            $createdRecords = [];

            foreach ($asetIds as $asetId) {
                $aset = Aset::with('ruangan')->findOrFail($asetId);

                if (!$aset->is_borrowable) {
                    throw new Exception("Aset '{$aset->nama}' merupakan aset tetap yang tidak dapat dipinjam.");
                }

                if ($aset->status !== 'tersedia') {
                    throw new Exception("Aset '{$aset->nama}' sedang tidak tersedia untuk dipinjam (status: {$aset->status}).");
                }

                // Cek tumpang tindih rentang tanggal peminjaman aset yang aktif/berjalan
                $tglPinjam = $data['tanggal_pinjam'];
                $tglKembali = $data['tanggal_kembali_rencana'];
                $isColliding = PeminjamanAset::where('aset_id', $asetId)
                    ->whereIn('status', ['pending', 'pending_laboran', 'pending_admin_sinapra', 'disetujui', 'dipinjam'])
                    ->where(function ($q) use ($tglPinjam, $tglKembali) {
                        $q->whereBetween('tanggal_pinjam', [$tglPinjam, $tglKembali])
                          ->orWhereBetween('tanggal_kembali_rencana', [$tglPinjam, $tglKembali])
                          ->orWhere(function ($sub) use ($tglPinjam, $tglKembali) {
                              $sub->where('tanggal_pinjam', '<=', $tglPinjam)
                                  ->where('tanggal_kembali_rencana', '>=', $tglKembali);
                          });
                    })->exists();

                if ($isColliding) {
                    throw new Exception("Aset '{$aset->nama}' sudah diajukan atau sedang dipinjam oleh pihak lain pada rentang tanggal {$tglPinjam} s.d. {$tglKembali}.");
                }

                $isLab = $aset->is_lab_asset || ($aset->ruangan && $aset->ruangan->tipe === 'lab');

                $recordData = [
                    'kode_peminjaman' => $kodePeminjaman,
                    'aset_id' => $asetId,
                    'user_id' => $userId,
                    'nomor_identitas' => $nomorIdentitas,
                    'kontak_peminjam' => $data['kontak_peminjam'] ?? null,
                    'keperluan' => $data['keperluan'],
                    'tanggal_pinjam' => $data['tanggal_pinjam'],
                    'tanggal_kembali_rencana' => $data['tanggal_kembali_rencana'],
                    'status' => $isLab ? 'pending_laboran' : 'pending_admin_sinapra',
                ];

                $peminjaman = PeminjamanAset::create($recordData);

                AuditLogService::record(
                    module: 'SINAPRA',
                    action: 'create',
                    tableName: 'peminjaman_aset',
                    recordId: $peminjaman->id,
                    newValues: $peminjaman->toArray()
                );

                $createdRecords[] = $peminjaman;
            }

            $primary = $createdRecords[0];
            $itemsData = collect($createdRecords)->map(function ($item) {
                return $item->load('aset')->toArray();
            })->values()->all();
            $primary->setAttribute('items', $itemsData);
            $primary->setAttribute('items_count', count($createdRecords));

            return $primary;
        });
    }

    /**
     * Persetujuan Tahap Laboran untuk Peminjaman Aset Laboratorium.
     */
    public function approveLaboranAset(
        PeminjamanAset $peminjaman,
        int $laboranId,
        bool $isApproved,
        ?string $catatanLaboran = null
    ): PeminjamanAset {
        return DB::transaction(function () use ($peminjaman, $laboranId, $isApproved, $catatanLaboran) {
            $oldValues = $peminjaman->toArray();

            if ($isApproved) {
                $peminjaman->status = 'pending_admin_sinapra';
            } else {
                $peminjaman->status = 'ditolak_laboran';
            }

            $peminjaman->laboran_approved_by = $laboranId;
            $peminjaman->laboran_approved_at = now();
            $peminjaman->catatan_laboran = $catatanLaboran;
            $peminjaman->save();

            try {
                AuditLogService::record(
                    module: 'SIAKAD',
                    action: $isApproved ? 'approve' : 'reject',
                    tableName: 'peminjaman_aset',
                    recordId: $peminjaman->id,
                    oldValues: $oldValues,
                    newValues: $peminjaman->fresh()->toArray(),
                    request: request()
                );
            } catch (\Throwable $e) {
                report($e);
            }

            return $peminjaman->fresh();
        });
    }

    /**
     * Persetujuan Akhir Peminjaman Aset oleh Admin SINAPRA.
     */
    public function approvePeminjamanAset(
        PeminjamanAset $peminjaman,
        int $approverId,
        bool $isApproved,
        ?string $catatanPenolakan = null
    ): PeminjamanAset {
        return DB::transaction(function () use ($peminjaman, $approverId, $isApproved, $catatanPenolakan) {
            $oldValues = $peminjaman->toArray();

            if ($isApproved) {
                $aset = Aset::findOrFail($peminjaman->aset_id);
                if ($aset->status !== 'tersedia') {
                    throw new Exception("Aset sedang tidak tersedia untuk dipinjam.");
                }

                $peminjaman->status = 'disetujui';
                $aset->update(['status' => 'dipinjam']);

                // Otomatis integrasikan penomoran surat ke modul ARSIP jika belum ada nomor surat
                if (empty($peminjaman->nomor_surat)) {
                    $nomorSurat = null;
                    try {
                        $peminjamUser = $peminjaman->user()->with(['pegawai.unitKerja', 'mahasiswa.prodi'])->first();
                        $unitKerjaKode = $peminjamUser?->pegawai?->unitKerja?->kode ?? 'BAUK-01';
                        $tanggalSurat = $peminjaman->tanggal_pinjam ? $peminjaman->tanggal_pinjam->toDateString() : now()->toDateString();
                        $asetNama = $aset->nama ?? 'Aset Kampus';

                        // Buat permohonan nomor surat resmi ke modul ARSIP
                        $arsipRequestService = app(\App\Services\Arsip\RequestNomorSuratService::class);
                        $arsipReq = $arsipRequestService->createRequest([
                            'module_origin' => 'sinapra',
                            'reference_type' => \App\Models\PeminjamanAset::class,
                            'reference_id' => $peminjaman->id,
                            'perihal' => 'Izin Peminjaman Barang/Aset: ' . $asetNama . ' - ' . $peminjaman->keperluan,
                            'tujuan' => $peminjamUser?->name ?? 'Peminjam Aset',
                            'tanggal_surat' => $tanggalSurat,
                            'kode_unit' => $unitKerjaKode,
                            'kode_klasifikasi' => 'DVIII', // Surat Keterangan / Izin Pemakaian
                            'jumlah_nomor' => 1,
                            'catatan_pemohon' => 'Permohonan nomor surat bukti peminjaman aset SINAPRA kode ' . $peminjaman->kode_peminjaman,
                        ], $approverId);

                        // Terbitkan nomor resmi langsung via ARSIP NomorSuratService yang terdaftar di agenda ARSIP
                        $arsipNomorService = app(\App\Services\Arsip\NomorSuratService::class);
                        $nomorResmi = $arsipNomorService->generateSatuan([
                            'tanggal_surat' => $tanggalSurat,
                            'kode_unit' => $unitKerjaKode,
                            'kode_klasifikasi' => 'DVIII',
                            'perihal' => 'Izin Peminjaman Barang/Aset: ' . $asetNama . ' - ' . $peminjaman->keperluan,
                            'tujuan' => $peminjamUser?->name ?? 'Peminjam Aset',
                            'status' => 'terpakai',
                            'module_origin' => 'sinapra',
                            'request_id' => $arsipReq->id,
                            'reference_type' => \App\Models\PeminjamanAset::class,
                            'reference_id' => $peminjaman->id,
                            'catatan' => 'Peminjaman Aset SINAPRA #' . $peminjaman->id,
                        ], $approverId);

                        $arsipReq->update([
                            'status' => 'disetujui',
                            'verified_by' => $approverId,
                            'verified_at' => now(),
                            'catatan_verifikasi' => 'Disetujui otomatis melalui persetujuan peminjaman aset SINAPRA.',
                        ]);

                        $nomorSurat = $nomorResmi->nomor_surat;
                    } catch (\Throwable $e) {
                        Log::warning('Gagal generate nomor surat ARSIP untuk PeminjamanAset #' . $peminjaman->id . ': ' . $e->getMessage());
                        // Fallback penomoran jika layanan ARSIP terkendala
                        $bulan = date('m');
                        $tahun = date('Y');
                        $nomorSurat = sprintf('%03d/SINAPRA-ASET/%s/%s', $peminjaman->id, $bulan, $tahun);
                    }

                    $peminjaman->nomor_surat = $nomorSurat;
                    $peminjaman->surat_generated_at = now();

                    // Jika merupakan bagian dari pengajuan batch (kode_peminjaman), seragamkan nomor surat
                    if (!empty($peminjaman->kode_peminjaman)) {
                        PeminjamanAset::where('kode_peminjaman', $peminjaman->kode_peminjaman)
                            ->whereNull('nomor_surat')
                            ->update([
                                'nomor_surat' => $nomorSurat,
                                'surat_generated_at' => now(),
                            ]);
                    }
                }

                // Jika merupakan bagian dari batch (kode_peminjaman), setujui juga item lainnya dan tandai aset fisiknya sebagai dipinjam
                if (!empty($peminjaman->kode_peminjaman)) {
                    $otherBatchItems = PeminjamanAset::where('kode_peminjaman', $peminjaman->kode_peminjaman)
                        ->where('id', '!=', $peminjaman->id)
                        ->get();

                    foreach ($otherBatchItems as $bItem) {
                        $oldBatchValues = $bItem->toArray();
                        $bItem->update([
                            'status' => 'disetujui',
                            'disetujui_oleh' => $approverId,
                            'admin_approved_at' => now(),
                            'nomor_surat' => $peminjaman->nomor_surat,
                            'surat_generated_at' => now(),
                        ]);

                        Aset::where('id', $bItem->aset_id)->update(['status' => 'dipinjam']);

                        try {
                            AuditLogService::record(
                                module: 'SINAPRA',
                                action: 'approve',
                                tableName: 'sinapra_peminjaman_aset',
                                recordId: $bItem->id,
                                oldValues: $oldBatchValues,
                                newValues: $bItem->fresh()->toArray()
                            );
                        } catch (\Throwable $e) {
                            Log::warning("Gagal mencatat audit log batch approve: " . $e->getMessage());
                        }
                    }
                }
            } else {
                $peminjaman->status = 'ditolak_admin_sinapra';
                $peminjaman->catatan_penolakan = $catatanPenolakan;

                if (!empty($peminjaman->kode_peminjaman)) {
                    $rejectedBatch = PeminjamanAset::where('kode_peminjaman', $peminjaman->kode_peminjaman)
                        ->where('id', '!=', $peminjaman->id)
                        ->get();

                    foreach ($rejectedBatch as $bItem) {
                        $oldBatchValues = $bItem->toArray();
                        $bItem->update([
                            'status' => 'ditolak_admin_sinapra',
                            'disetujui_oleh' => $approverId,
                            'admin_approved_at' => now(),
                            'catatan_penolakan' => $catatanPenolakan,
                        ]);

                        try {
                            AuditLogService::record(
                                module: 'SINAPRA',
                                action: 'reject',
                                tableName: 'sinapra_peminjaman_aset',
                                recordId: $bItem->id,
                                oldValues: $oldBatchValues,
                                newValues: $bItem->fresh()->toArray()
                            );
                        } catch (\Throwable $e) {
                            Log::warning("Gagal mencatat audit log batch reject: " . $e->getMessage());
                        }
                    }
                }
            }

            $peminjaman->disetujui_oleh = $approverId;
            $peminjaman->admin_approved_at = now();
            $peminjaman->save();

            try {
                AuditLogService::record(
                    module: 'SINAPRA',
                    action: $isApproved ? 'approve' : 'reject',
                    tableName: 'sinapra_peminjaman_aset',
                    recordId: $peminjaman->id,
                    oldValues: $oldValues,
                    newValues: $peminjaman->fresh()->toArray()
                );
            } catch (\Throwable $e) {
                Log::warning("Gagal mencatat audit log approval peminjaman aset: " . $e->getMessage());
            }

            return $peminjaman->fresh();
        });
    }

    /**
     * Mengambil data lengkap Surat Peminjaman Aset beserta tanda tangan digital SIMPEG.
     */
    public function getSuratPeminjamanAset(PeminjamanAset $peminjaman): array
    {
        // Pastikan nomor surat sudah ter-generate
        if (empty($peminjaman->nomor_surat)) {
            $bulan = date('m');
            $tahun = date('Y');
            $nomorSurat = sprintf('%03d/SINAPRA-ASET/%s/%s', $peminjaman->id, $bulan, $tahun);
            $peminjaman->update([
                'nomor_surat' => $nomorSurat,
                'surat_generated_at' => now(),
            ]);
        }

        // Ambil seluruh aset dalam pengajuan yang sama jika ada kode_peminjaman
        $daftarPeminjaman = collect([$peminjaman]);
        if (!empty($peminjaman->kode_peminjaman)) {
            $daftarPeminjaman = PeminjamanAset::with(['aset.ruangan.gedung', 'aset.kategori'])
                ->where('kode_peminjaman', $peminjaman->kode_peminjaman)
                ->get();
        } else {
            $peminjaman->load(['aset.ruangan.gedung', 'aset.kategori']);
            $daftarPeminjaman = collect([$peminjaman]);
        }

        $user = $peminjaman->user()->with(['pegawai.unitKerja', 'mahasiswa'])->first();

        // 1. Tanda Tangan Approver (Admin Sarpras) dari Master SIMPEG
        $approverUser = null;
        $approverTtd = null;
        if ($peminjaman->disetujui_oleh) {
            $approverUser = \App\Models\User::with('pegawai')->find($peminjaman->disetujui_oleh);
            $approverTtd = TandaTanganPegawai::where('user_id', $peminjaman->disetujui_oleh)
                ->where('is_active', true)
                ->latest()
                ->first();
        }

        // 2. Tanda Tangan Laboran dari Master SIMPEG (jika ada)
        $laboranUser = null;
        $laboranTtd = null;
        if ($peminjaman->laboran_approved_by) {
            $laboranUser = \App\Models\User::with('pegawai')->find($peminjaman->laboran_approved_by);
            $laboranTtd = TandaTanganPegawai::where('user_id', $peminjaman->laboran_approved_by)
                ->where('is_active', true)
                ->latest()
                ->first();
        }

        // 3. Tanda Tangan Peminjam dari Master SIMPEG (jika ada)
        $peminjamTtd = TandaTanganPegawai::where('user_id', $peminjaman->user_id)
            ->where('is_active', true)
            ->latest()
            ->first();

        $daftarBarang = $daftarPeminjaman->map(function ($item, $idx) {
            return [
                'nomor' => $idx + 1,
                'peminjaman_id' => $item->id,
                'aset_id' => $item->aset_id,
                'kode_aset' => $item->aset?->kode_aset ?? '-',
                'nama_barang' => $item->aset?->nama ?? '-',
                'merk' => $item->aset?->merk ?? '-',
                'nomor_seri' => $item->aset?->nomor_seri ?? '-',
                'lokasi_ruangan' => $item->aset?->ruangan?->nama ?? '-',
                'gedung' => $item->aset?->ruangan?->gedung?->nama ?? '-',
                'kondisi_pinjam' => $item->kondisi_pinjam ?? 'baik',
                'status' => $item->status,
            ];
        });

        // Identitas peminjam
        $namaPeminjam = $user?->pegawai?->nama_lengkap ?? $user?->mahasiswa?->nama_lengkap ?? $user?->name ?? '-';
        $nomorIdentitas = $peminjaman->nomor_identitas 
            ?? $user?->mahasiswa?->nim 
            ?? $user?->pegawai?->nidn 
            ?? $user?->pegawai?->nuptk 
            ?? $user?->pegawai?->nip 
            ?? '-';

        $unitKerja = $user?->pegawai?->unitKerja?->nama ?? 'Civitas Akademika Kampus';

        return [
            'peminjaman_id' => $peminjaman->id,
            'kode_peminjaman' => $peminjaman->kode_peminjaman,
            'nomor_surat' => $peminjaman->nomor_surat,
            'surat_generated_at' => $peminjaman->surat_generated_at?->format('Y-m-d H:i:s'),
            'tanggal_pinjam' => $peminjaman->tanggal_pinjam?->format('Y-m-d'),
            'tanggal_kembali_rencana' => $peminjaman->tanggal_kembali_rencana?->format('Y-m-d'),
            'keperluan' => $peminjaman->keperluan,
            'status' => $peminjaman->status,
            'peminjam' => [
                'user_id' => $peminjaman->user_id,
                'nama' => $namaPeminjam,
                'nomor_identitas' => $nomorIdentitas,
                'unit_kerja' => $unitKerja,
                'kontak' => $peminjaman->kontak_peminjam ?? $user?->email,
                'email' => $user?->email,
                'tanda_tangan_url' => $peminjamTtd?->file_url,
                'qr_token' => $peminjamTtd?->qr_token,
            ],
            'laboran' => $laboranUser ? [
                'user_id' => $laboranUser->id,
                'nama' => $laboranUser->pegawai?->nama_lengkap ?? $laboranUser->name,
                'nip' => $laboranUser->pegawai?->nip ?? '-',
                'nidn' => $laboranUser->pegawai?->nidn ?? null,
                'verified_at' => $peminjaman->laboran_approved_at?->format('Y-m-d H:i:s'),
                'tanda_tangan_url' => $laboranTtd?->file_url,
                'qr_token' => $laboranTtd?->qr_token,
            ] : null,
            'approver' => $approverUser ? [
                'user_id' => $approverUser->id,
                'nama' => $approverUser->pegawai?->nama_lengkap ?? $approverUser->name,
                'nip' => $approverUser->pegawai?->nip ?? '-',
                'nidn' => $approverUser->pegawai?->nidn ?? null,
                'approved_at' => $peminjaman->admin_approved_at?->format('Y-m-d H:i:s'),
                'tanda_tangan_url' => $approverTtd?->file_url,
                'qr_token' => $approverTtd?->qr_token,
            ] : null,
            'daftar_barang' => $daftarBarang,
            'verifikasi_token' => hash('sha256', ($peminjaman->nomor_surat ?? '') . ($peminjaman->created_at ?? '')),
        ];
    }

    /**
     * Proses pengembalian barang/aset yang dipinjam (Mendukung pengembalian satuan, per-item, atau batch).
     */
    public function prosesPengembalianAset(
        PeminjamanAset $peminjaman,
        ?string $kondisiKembali = 'baik',
        ?string $tanggalKembaliAktual = null,
        ?string $catatanPengembalian = null,
        bool $kembalikanSemuaDalamBatch = false,
        array $items = []
    ): PeminjamanAset {
        return DB::transaction(function () use ($peminjaman, $kondisiKembali, $tanggalKembaliAktual, $catatanPengembalian, $kembalikanSemuaDalamBatch, $items) {
            $tanggalKembali = $tanggalKembaliAktual ?: now()->toDateString();

            // Skenario 1: Pemeriksaan kondisi spesifik per-item barang
            if (!empty($items)) {
                foreach ($items as $itemData) {
                    $itemId = $itemData['peminjaman_id'] ?? null;
                    if (!$itemId) {
                        continue;
                    }

                    $pItem = PeminjamanAset::find($itemId);
                    if (!$pItem || !in_array($pItem->status, ['dipinjam', 'disetujui'])) {
                        continue;
                    }

                    $itemKondisi = $itemData['kondisi_kembali'] ?? $kondisiKembali ?? 'baik';
                    $itemCatatan = $itemData['catatan'] ?? $catatanPengembalian;

                    $oldValues = $pItem->toArray();
                    $pItem->update([
                        'tanggal_kembali_aktual' => $tanggalKembali,
                        'kondisi_kembali' => $itemKondisi,
                        'catatan_pengembalian' => $itemCatatan,
                        'status' => 'kembali',
                    ]);

                    $aset = Aset::find($pItem->aset_id);
                    if ($aset) {
                        $asetStatus = match ($itemKondisi) {
                            'rusak_berat' => 'maintenance',
                            'hilang' => 'disetujui_diapkir',
                            default => 'tersedia',
                        };

                        $aset->update([
                            'status' => $asetStatus,
                            'kondisi' => ($itemKondisi === 'hilang') ? 'rusak_berat' : $itemKondisi,
                        ]);

                        // Alur otomatis pembuatan tiket perawatan jika kondisi barang rusak berat
                        if ($itemKondisi === 'rusak_berat') {
                            $mLog = MaintenanceLog::create([
                                'aset_id' => $aset->id,
                                'ruangan_id' => $aset->ruangan_id,
                                'judul' => 'Perbaikan Pengembalian Peminjaman: ' . $aset->nama,
                                'deskripsi_kerusakan' => 'Pengembalian barang dari transaksi peminjaman (' . ($pItem->kode_peminjaman ?: 'PA-' . $pItem->id) . ') dalam kondisi rusak berat. Catatan teknis: ' . ($itemCatatan ?: 'Perlu perbaikan/servis segera.'),
                                'prioritas' => 'tinggi',
                                'tanggal_lapor' => $tanggalKembali,
                                'status' => 'dilaporkan',
                            ]);

                            try {
                                AuditLogService::record(
                                    module: 'SINAPRA',
                                    action: 'create',
                                    tableName: 'sinapra_maintenance_log',
                                    recordId: $mLog->id,
                                    oldValues: null,
                                    newValues: $mLog->toArray()
                                );
                            } catch (\Throwable $e) {
                                Log::warning("Gagal mencatat audit log pembuatan tiket maintenance per-item: " . $e->getMessage());
                            }
                        }
                    }

                    try {
                        AuditLogService::record(
                            module: 'SINAPRA',
                            action: 'update',
                            tableName: 'sinapra_peminjaman_aset',
                            recordId: $pItem->id,
                            oldValues: $oldValues,
                            newValues: $pItem->fresh()->toArray()
                        );
                    } catch (\Throwable $e) {
                        Log::warning("Gagal mencatat audit log pengembalian aset per-item: " . $e->getMessage());
                    }
                }
            } else {
                // Skenario 2: Kumpulan peminjaman yang akan diproses secara seragam (single atau batch)
                $itemsToReturn = collect([$peminjaman]);
                if ($kembalikanSemuaDalamBatch && !empty($peminjaman->kode_peminjaman)) {
                    $itemsToReturn = PeminjamanAset::where('kode_peminjaman', $peminjaman->kode_peminjaman)
                        ->whereIn('status', ['dipinjam', 'disetujui'])
                        ->get();
                }

                foreach ($itemsToReturn as $item) {
                    $oldValues = $item->toArray();

                    $item->update([
                        'tanggal_kembali_aktual' => $tanggalKembali,
                        'kondisi_kembali' => $kondisiKembali,
                        'catatan_pengembalian' => $catatanPengembalian,
                        'status' => 'kembali',
                    ]);

                    // Update status & kondisi fisik aset
                    $aset = Aset::findOrFail($item->aset_id);
                    $asetStatus = match ($kondisiKembali) {
                        'rusak_berat' => 'maintenance',
                        'hilang' => 'disetujui_diapkir',
                        default => 'tersedia',
                    };

                    $aset->update([
                        'status' => $asetStatus,
                        'kondisi' => ($kondisiKembali === 'hilang') ? 'rusak_berat' : $kondisiKembali,
                    ]);

                    // Alur otomatis pembuatan tiket perawatan jika kondisi barang rusak berat
                    if ($kondisiKembali === 'rusak_berat') {
                        $mLog = MaintenanceLog::create([
                            'aset_id' => $aset->id,
                            'ruangan_id' => $aset->ruangan_id,
                            'judul' => 'Perbaikan Pengembalian Peminjaman: ' . $aset->nama,
                            'deskripsi_kerusakan' => 'Pengembalian barang dari transaksi peminjaman (' . ($item->kode_peminjaman ?: 'PA-' . $item->id) . ') dalam kondisi rusak berat. Catatan teknis: ' . ($catatanPengembalian ?: 'Perlu perbaikan/servis segera.'),
                            'prioritas' => 'tinggi',
                            'tanggal_lapor' => $tanggalKembali,
                            'status' => 'dilaporkan',
                        ]);

                        try {
                            AuditLogService::record(
                                module: 'SINAPRA',
                                action: 'create',
                                tableName: 'sinapra_maintenance_log',
                                recordId: $mLog->id,
                                oldValues: null,
                                newValues: $mLog->toArray()
                            );
                        } catch (\Throwable $e) {
                            Log::warning("Gagal mencatat audit log pembuatan tiket maintenance batch: " . $e->getMessage());
                        }
                    }

                    try {
                        AuditLogService::record(
                            module: 'SINAPRA',
                            action: 'update',
                            tableName: 'sinapra_peminjaman_aset',
                            recordId: $item->id,
                            oldValues: $oldValues,
                            newValues: $item->fresh()->toArray()
                        );
                    } catch (\Throwable $e) {
                        Log::warning("Gagal mencatat audit log pengembalian aset batch: " . $e->getMessage());
                    }
                }
            }

            return $peminjaman->fresh(['aset.ruangan', 'user']);
        });
    }

    /**
     * Mengambil data lengkap Surat Bukti Peminjaman Ruangan beserta tanda tangan digital SIMPEG.
     */
    public function getSuratPeminjamanRuangan(PeminjamanRuangan $peminjaman): array
    {
        // Validasi status bisnis: surat peminjaman ruangan hanya sah diakses jika sudah disetujui / selesai
        if (!in_array($peminjaman->status, ['disetujui', 'selesai'])) {
            throw ValidationException::withMessages([
                'peminjaman' => ['Surat peminjaman ruangan hanya dapat diakses untuk peminjaman yang telah disetujui.'],
            ]);
        }

        $peminjaman->load(['ruangan.gedung', 'ruangan.tipeRuangan']);
        $user = $peminjaman->user()->with(['pegawai.unitKerja', 'mahasiswa.prodi'])->first();

        $storage = app(FileStorageService::class);

        // 1. Tanda Tangan Approver (Admin Sarpras / Approver) dari Master SIMPEG
        $approverUser = null;
        $approverTtd = null;
        if ($peminjaman->disetujui_oleh) {
            $approverUser = \App\Models\User::with('pegawai')->find($peminjaman->disetujui_oleh);
            $approverTtd = TandaTanganPegawai::where('user_id', $peminjaman->disetujui_oleh)
                ->where('is_active', true)
                ->latest()
                ->first();
        }

        // 2. Tanda Tangan Laboran dari Master SIMPEG (jika ada)
        $laboranUser = null;
        $laboranTtd = null;
        if ($peminjaman->laboran_approved_by) {
            $laboranUser = \App\Models\User::with('pegawai')->find($peminjaman->laboran_approved_by);
            $laboranTtd = TandaTanganPegawai::where('user_id', $peminjaman->laboran_approved_by)
                ->where('is_active', true)
                ->latest()
                ->first();
        }

        // 3. Tanda Tangan Peminjam dari Master SIMPEG (jika ada)
        $peminjamTtd = TandaTanganPegawai::where('user_id', $peminjaman->user_id)
            ->where('is_active', true)
            ->latest()
            ->first();

        // Identitas peminjam
        $namaPeminjam = $user?->pegawai?->nama_lengkap ?? $user?->mahasiswa?->nama_lengkap ?? $user?->name ?? '-';
        $nomorIdentitas = $peminjaman->nomor_identitas 
            ?? $user?->mahasiswa?->nim 
            ?? $user?->pegawai?->nidn 
            ?? $user?->pegawai?->nuptk 
            ?? $user?->pegawai?->nip 
            ?? '-';

        $unitKerja = $user?->pegawai?->unitKerja?->nama 
            ?? $user?->mahasiswa?->prodi?->nama 
            ?? 'Civitas Akademika Kampus';

        // Audit log akses dokumen sensitif/resmi
        try {
            AuditLogService::record(
                module: 'SINAPRA',
                action: 'export',
                tableName: 'sinapra_peminjaman_ruangan',
                recordId: $peminjaman->id,
                oldValues: null,
                newValues: ['nomor_surat' => $peminjaman->nomor_surat]
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal mencatat audit log akses surat peminjaman ruangan: ' . $e->getMessage());
        }

        return [
            'peminjaman_id' => $peminjaman->id,
            'kode_peminjaman' => $peminjaman->kode_peminjaman,
            'nomor_surat' => $peminjaman->nomor_surat,
            'surat_generated_at' => $peminjaman->surat_generated_at?->format('Y-m-d H:i:s'),
            'tanggal' => $peminjaman->tanggal?->format('Y-m-d'),
            'jam_mulai' => $peminjaman->jam_mulai,
            'jam_selesai' => $peminjaman->jam_selesai,
            'keperluan' => $peminjaman->keperluan,
            'status' => $peminjaman->status,
            'ruangan' => [
                'id' => $peminjaman->ruangan?->id,
                'nama' => $peminjaman->ruangan?->nama,
                'kode' => $peminjaman->ruangan?->kode,
                'lantai' => $peminjaman->ruangan?->lantai,
                'kapasitas' => $peminjaman->ruangan?->kapasitas,
                'gedung' => $peminjaman->ruangan?->gedung?->nama ?? '-',
                'tipe_ruangan' => $peminjaman->ruangan?->tipeRuangan?->nama ?? $peminjaman->ruangan?->tipe ?? '-',
                'ada_ac' => (bool) $peminjaman->ruangan?->ada_ac,
                'ada_proyektor' => (bool) $peminjaman->ruangan?->ada_proyektor,
                'ada_wifi' => (bool) $peminjaman->ruangan?->ada_wifi,
            ],
            'peminjam' => [
                'user_id' => $peminjaman->user_id,
                'nama' => $namaPeminjam,
                'nomor_identitas' => $nomorIdentitas,
                'unit_kerja' => $unitKerja,
                'kontak' => $peminjaman->kontak_peminjam ?? $user?->email,
                'email' => $user?->email,
                'tanda_tangan_url' => $peminjamTtd?->file_path ? $storage->url($peminjamTtd->file_path) : null,
                'qr_token' => $peminjamTtd?->qr_token,
            ],
            'laboran' => $laboranUser ? [
                'user_id' => $laboranUser->id,
                'nama' => $laboranUser->pegawai?->nama_lengkap ?? $laboranUser->name,
                'nip' => $laboranUser->pegawai?->nip ?? '-',
                'nidn' => $laboranUser->pegawai?->nidn ?? null,
                'verified_at' => $peminjaman->laboran_approved_at?->format('Y-m-d H:i:s'),
                'tanda_tangan_url' => $laboranTtd?->file_path ? $storage->url($laboranTtd->file_path) : null,
                'qr_token' => $laboranTtd?->qr_token,
            ] : null,
            'approver' => $approverUser ? [
                'user_id' => $approverUser->id,
                'nama' => $approverUser->pegawai?->nama_lengkap ?? $approverUser->name,
                'nip' => $approverUser->pegawai?->nip ?? '-',
                'nidn' => $approverUser->pegawai?->nidn ?? null,
                'approved_at' => $peminjaman->admin_approved_at?->format('Y-m-d H:i:s'),
                'tanda_tangan_url' => $approverTtd?->file_path ? $storage->url($approverTtd->file_path) : null,
                'qr_token' => $approverTtd?->qr_token,
            ] : null,
            'verifikasi_token' => hash('sha256', ($peminjaman->nomor_surat ?? '') . ($peminjaman->created_at ?? '')),
        ];
    }
}
