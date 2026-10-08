<?php

namespace App\Services\Sinapra;

use App\Models\PeminjamanRuangan;
use App\Models\PeminjamanAset;
use App\Models\Aset;
use App\Models\Ruangan;
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

                // Otomatis generate nomor surat bukti peminjaman jika belum ada
                if (empty($peminjaman->nomor_surat)) {
                    $bulan = date('m');
                    $tahun = date('Y');
                    $nomorSurat = sprintf('%03d/SINAPRA-RUANG/%s/%s', $peminjaman->id, $bulan, $tahun);
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

                // Generate nomor surat peminjaman resmi jika belum ada
                if (empty($peminjaman->nomor_surat)) {
                    $bulan = date('m');
                    $tahun = date('Y');
                    $nomorSurat = sprintf('%03d/SINAPRA-ASET/%s/%s', $peminjaman->id, $bulan, $tahun);
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
                tableName: 'peminjaman_aset',
                recordId: $peminjaman->id,
                oldValues: $oldValues,
                newValues: $peminjaman->fresh()->toArray()
            );

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
     * Proses pengembalian barang/aset yang dipinjam (Mendukung pengembalian satuan atau batch).
     */
    public function prosesPengembalianAset(
        PeminjamanAset $peminjaman,
        string $kondisiKembali,
        ?string $tanggalKembaliAktual = null,
        ?string $catatanPengembalian = null,
        bool $kembalikanSemuaDalamBatch = false
    ): PeminjamanAset {
        return DB::transaction(function () use ($peminjaman, $kondisiKembali, $tanggalKembaliAktual, $catatanPengembalian, $kembalikanSemuaDalamBatch) {
            $tanggalKembali = $tanggalKembaliAktual ?: now()->toDateString();

            // Kumpulan peminjaman yang akan diproses
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

                // Update status & kondisi aset
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

                AuditLogService::record(
                    module: 'SINAPRA',
                    action: 'update',
                    tableName: 'peminjaman_aset',
                    recordId: $item->id,
                    oldValues: $oldValues,
                    newValues: $item->fresh()->toArray()
                );
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
