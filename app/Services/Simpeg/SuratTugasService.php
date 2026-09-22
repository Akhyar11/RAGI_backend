<?php

namespace App\Services\Simpeg;

use App\Events\Simpeg\SuratTugasDisetujui;
use App\Models\Simpeg\MasterJenisTransportasi;
use App\Models\Simpeg\MasterKategoriKegiatanTugas;
use App\Models\Simpeg\SuratTugas;
use App\Models\Simpeg\SuratTugasAnggota;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SuratTugasService
{
    /**
     * Dapatkan master referensi dinamis untuk surat tugas
     */
    public function getMasters(): array
    {
        return [
            'kategori_kegiatan' => MasterKategoriKegiatanTugas::where('is_active', true)->orderBy('urutan')->orderBy('id')->get(),
            'jenis_transportasi' => MasterJenisTransportasi::where('is_active', true)->orderBy('urutan')->orderBy('id')->get(),
        ];
    }

    /**
     * Upload berkas pendukung surat tugas atau LPJ
     */
    protected function handleFileUpload(?UploadedFile $file, string $folder = 'surat_tugas'): ?string
    {
        if (!$file) {
            return null;
        }

        $extension = $file->getClientOriginalExtension();
        $safeName = Str::uuid() . '.' . $extension;
        $path = $file->storeAs("simpeg/{$folder}/" . date('Y/m'), $safeName, 'public');

        return 'storage/' . $path;
    }

    /**
     * Hapus berkas dari disk storage
     */
    protected function deleteFile(?string $filePath): void
    {
        if (!$filePath) {
            return;
        }

        $cleanPath = str_replace('storage/', '', $filePath);
        if (Storage::disk('public')->exists($cleanPath)) {
            Storage::disk('public')->delete($cleanPath);
        }
    }

    /**
     * List surat tugas dengan filter dan scoping
     */
    public function list(array $filters, $user)
    {
        $query = SuratTugas::with([
            'pegawai.unitKerja',
            'kategoriKegiatan',
            'jenisTransportasi',
            'anggota.pegawai.unitKerja',
            'approver',
            'pencairanKas',
        ]);

        $canManageAll = $user->isAdmin() || $user->hasPermission('simpeg.surat_tugas.approve');
        if (!$canManageAll) {
            $pegawaiId = $user->pegawai?->id;
            if ($pegawaiId) {
                $query->where(function ($q) use ($pegawaiId) {
                    $q->where('pegawai_id', $pegawaiId)
                      ->orWhereHas('anggota', function ($qa) use ($pegawaiId) {
                          $qa->where('pegawai_id', $pegawaiId);
                      });
                });
            } else {
                $query->whereRaw('1 = 0');
            }
        } elseif (!empty($filters['pegawai_id'])) {
            $query->where('pegawai_id', $filters['pegawai_id']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['kategori_kegiatan_id'])) {
            $query->where('kategori_kegiatan_id', $filters['kategori_kegiatan_id']);
        }

        if (!empty($filters['jenis_transportasi_id'])) {
            $query->where('jenis_transportasi_id', $filters['jenis_transportasi_id']);
        }

        if (!empty($filters['tahun'])) {
            $query->whereYear('tanggal_berangkat', $filters['tahun']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('nomor_surat', 'like', "%{$search}%")
                  ->orWhere('nama_kegiatan', 'like', "%{$search}%")
                  ->orWhere('lokasi_tujuan', 'like', "%{$search}%")
                  ->orWhere('tempat_berangkat', 'like', "%{$search}%")
                  ->orWhereHas('pegawai', function ($qp) use ($search) {
                      $qp->where('nama_lengkap', 'like', "%{$search}%")
                         ->orWhere('nip', 'like', "%{$search}%");
                  });
            });
        }

        $allowedSort = ['created_at', 'tanggal_berangkat', 'tanggal_kembali', 'status', 'nomor_surat'];
        $sortBy = in_array($filters['sort_by'] ?? '', $allowedSort) ? $filters['sort_by'] : 'created_at';
        $sortOrder = strtolower($filters['sort_order'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortBy, $sortOrder);

        $perPage = min(100, (int) ($filters['per_page'] ?? 15));
        return $query->paginate($perPage);
    }

    /**
     * Dapatkan detail surat tugas
     */
    public function getById(int $id, $user): SuratTugas
    {
        $suratTugas = SuratTugas::with([
            'pegawai.unitKerja',
            'kategoriKegiatan',
            'jenisTransportasi',
            'anggota.pegawai.unitKerja',
            'approver',
            'pencairanKas',
        ])->findOrFail($id);

        $canManageAll = $user->isAdmin() || $user->hasPermission('simpeg.surat_tugas.approve');
        if (!$canManageAll) {
            $pegawaiId = $user->pegawai?->id;
            $isKetua = $suratTugas->pegawai_id === $pegawaiId;
            $isAnggota = $suratTugas->anggota->pluck('pegawai_id')->contains($pegawaiId);

            if (!$isKetua && !$isAnggota) {
                throw new AuthorizationException('Anda tidak memiliki akses ke surat tugas ini.');
            }
        }

        return $suratTugas;
    }

    /**
     * Buat permohonan surat tugas dinas luar baru
     */
    public function create(array $data, ?UploadedFile $fileSuratTugas, ?UploadedFile $fileLpj, $user): SuratTugas
    {
        return DB::transaction(function () use ($data, $fileSuratTugas, $fileLpj, $user) {
            $fileSuratTugasPath = $this->handleFileUpload($fileSuratTugas, 'surat_tugas');
            $fileLpjPath = $this->handleFileUpload($fileLpj, 'lpj');

            // Default status: diajukan jika bukan draft eksplisit
            $status = $data['status'] ?? 'diajukan';

            $suratTugas = SuratTugas::create([
                'nomor_surat' => $data['nomor_surat'] ?? null,
                'pegawai_id' => $data['pegawai_id'],
                'kategori_kegiatan_id' => $data['kategori_kegiatan_id'],
                'jenis_transportasi_id' => $data['jenis_transportasi_id'],
                'nama_kegiatan' => $data['nama_kegiatan'],
                'tempat_berangkat' => $data['tempat_berangkat'],
                'lokasi_tujuan' => $data['lokasi_tujuan'],
                'tanggal_berangkat' => $data['tanggal_berangkat'],
                'tanggal_kembali' => $data['tanggal_kembali'],
                'tanggal_mulai' => $data['tanggal_mulai'],
                'tanggal_selesai' => $data['tanggal_selesai'],
                'maksud_tujuan' => $data['maksud_tujuan'],
                'beban_anggaran' => $data['beban_anggaran'] ?? null,
                'estimasi_biaya' => $data['estimasi_biaya'] ?? null,
                'status' => $status,
                'keterangan' => $data['keterangan'] ?? null,
                'kendaraan_dinas' => $data['kendaraan_dinas'] ?? null,
                'nama_driver' => $data['nama_driver'] ?? null,
                'kontak_driver' => $data['kontak_driver'] ?? null,
                'file_surat_tugas' => $fileSuratTugasPath,
                'file_lpj' => $fileLpjPath,
            ]);

            // Simpan anggota rombongan jika ada
            if (!empty($data['anggota']) && is_array($data['anggota'])) {
                foreach ($data['anggota'] as $anggotaItem) {
                    if (!empty($anggotaItem['pegawai_id'])) {
                        SuratTugasAnggota::create([
                            'surat_tugas_id' => $suratTugas->id,
                            'pegawai_id' => $anggotaItem['pegawai_id'],
                            'peran' => $anggotaItem['peran'] ?? 'Anggota',
                            'keterangan' => $anggotaItem['keterangan'] ?? null,
                        ]);
                    }
                }
            }

            return $this->getById($suratTugas->id, $user);
        });
    }

    /**
     * Update data surat tugas
     */
    public function update(SuratTugas $suratTugas, array $data, ?UploadedFile $fileSuratTugas, ?UploadedFile $fileLpj, $user): SuratTugas
    {
        $canManageAll = $user->isAdmin() || $user->hasPermission('simpeg.surat_tugas.approve');
        if (!$canManageAll) {
            $pegawaiId = $user->pegawai?->id;
            if ($suratTugas->pegawai_id !== $pegawaiId) {
                throw new AuthorizationException('Hanya ketua tugas atau admin yang dapat mengubah surat tugas.');
            }
            if (!in_array($suratTugas->status, ['draft', 'diajukan', 'ditolak'])) {
                throw ValidationException::withMessages([
                    'status' => ['Surat tugas yang telah disetujui/selesai tidak dapat diubah.'],
                ]);
            }
        }

        return DB::transaction(function () use ($suratTugas, $data, $fileSuratTugas, $fileLpj, $user) {
            $updatePayload = [];

            $fillableKeys = [
                'pegawai_id', 'kategori_kegiatan_id', 'jenis_transportasi_id',
                'nama_kegiatan', 'tempat_berangkat', 'lokasi_tujuan',
                'tanggal_berangkat', 'tanggal_kembali', 'tanggal_mulai', 'tanggal_selesai',
                'maksud_tujuan', 'beban_anggaran', 'estimasi_biaya', 'keterangan',
                'kendaraan_dinas', 'nama_driver', 'kontak_driver', 'status'
            ];

            foreach ($fillableKeys as $key) {
                if (array_key_exists($key, $data)) {
                    $updatePayload[$key] = $data[$key];
                }
            }

            if ($fileSuratTugas) {
                $this->deleteFile($suratTugas->file_surat_tugas);
                $updatePayload['file_surat_tugas'] = $this->handleFileUpload($fileSuratTugas, 'surat_tugas');
            }

            if ($fileLpj) {
                $this->deleteFile($suratTugas->file_lpj);
                $updatePayload['file_lpj'] = $this->handleFileUpload($fileLpj, 'lpj');
            }

            $suratTugas->update($updatePayload);

            // Sinkronkan anggota rombongan bila dikirim
            if (isset($data['anggota']) && is_array($data['anggota'])) {
                $suratTugas->anggota()->delete();
                foreach ($data['anggota'] as $anggotaItem) {
                    if (!empty($anggotaItem['pegawai_id'])) {
                        SuratTugasAnggota::create([
                            'surat_tugas_id' => $suratTugas->id,
                            'pegawai_id' => $anggotaItem['pegawai_id'],
                            'peran' => $anggotaItem['peran'] ?? 'Anggota',
                            'keterangan' => $anggotaItem['keterangan'] ?? null,
                        ]);
                    }
                }
            }

            return $this->getById($suratTugas->id, $user);
        });
    }

    /**
     * Persetujuan / Penolakan Surat Tugas dan Penomoran Resmi
     */
    public function approve(SuratTugas $suratTugas, array $data, ?UploadedFile $fileSuratTugas, $user): SuratTugas
    {
        $canApprove = $user->isAdmin() || $user->hasPermission('simpeg.surat_tugas.approve');
        if (!$canApprove) {
            throw new AuthorizationException('Anda tidak memiliki hak untuk menyetujui surat tugas.');
        }

        return DB::transaction(function () use ($suratTugas, $data, $fileSuratTugas, $user) {
            $nominalDisetujui = isset($data['nominal_disetujui']) ? (float) $data['nominal_disetujui'] : 0;

            $updatePayload = [
                'status' => $data['status'],
                'catatan_approval' => $data['catatan_approval'] ?? null,
                'approved_by' => $user->id,
                'approved_at' => now(),
            ];

            if ($data['status'] === 'disetujui') {
                $updatePayload['nomor_surat'] = $data['nomor_surat'];
                $updatePayload['nominal_disetujui'] = $nominalDisetujui;

                // Integrasi SIKEU: Jika tugas berbiaya (> 0), teruskan antrean pencairan dana ke SIKEU
                if ($nominalDisetujui > 0) {
                    $unitKas = \App\Models\Sikeu\UnitKas::where('status', true)->first() ?? \App\Models\Sikeu\UnitKas::first();
                    $unitKasId = $unitKas?->id;
                    $unitKerjaId = $suratTugas->pegawai?->unit_kerja_id;

                    $nomorPengajuan = 'CAIR-ST-' . date('Ymd') . '-' . sprintf('%04d', $suratTugas->id);

                    $attributes = [
                        'unit_kerja_id' => $unitKerjaId,
                        'unit_kas_id' => $unitKasId,
                        'pemohon_id' => $suratTugas->pegawai?->user_id ?? $user->id,
                        'judul_pengajuan' => 'Panjar Perjalanan Dinas: ' . $suratTugas->nama_kegiatan,
                        'deskripsi' => 'Pencairan panjar dana tugas dinas No. ' . $data['nomor_surat'] . ' ke ' . $suratTugas->lokasi_tujuan . ' an. ' . ($suratTugas->pegawai?->nama_lengkap ?? 'Pegawai'),
                        'nominal_diajukan' => $suratTugas->estimasi_biaya ?? $nominalDisetujui,
                        'nominal_disetujui' => $nominalDisetujui,
                        'jenis_pengajuan' => 'kegiatan',
                        'status' => 'pending_keuangan',
                        'approved_pimpinan_by' => $user->id,
                        'approved_pimpinan_at' => now(),
                    ];

                    $pengajuanKas = \App\Models\Sikeu\PengajuanPencairanKas::where('nomor_pengajuan', $nomorPengajuan)->first();
                    if ($pengajuanKas) {
                        $pengajuanKas->update($attributes);
                    } else {
                        $pengajuanKas = \App\Models\Sikeu\PengajuanPencairanKas::create(array_merge(
                            ['nomor_pengajuan' => $nomorPengajuan],
                            $attributes
                        ));
                    }

                    $updatePayload['sikeu_pencairan_id'] = $pengajuanKas->id;
                    $updatePayload['status_pencairan'] = 'belum_cair';
                } else {
                    // Non-biaya / Pelatihan daring Zoom -> Bypass SIKEU
                    $updatePayload['status_pencairan'] = 'tidak_perlu';
                }
            }

            if ($fileSuratTugas) {
                $this->deleteFile($suratTugas->file_surat_tugas);
                $updatePayload['file_surat_tugas'] = $this->handleFileUpload($fileSuratTugas, 'surat_tugas');
            }

            $suratTugas->update($updatePayload);

            // Jika disetujui, picu event untuk otomatisasi presensi dinas luar
            if ($data['status'] === 'disetujui') {
                SuratTugasDisetujui::dispatch($suratTugas->fresh());
            }

            return $this->getById($suratTugas->id, $user);
        });
    }

    /**
     * Upload berkas LPJ dan pelaporan kegiatan setelah kembali dinas
     * HANYA penanggung jawab (ketua) atau Admin/Approver yang berhak mengunggah LPJ
     */
    public function uploadLpj(SuratTugas $suratTugas, array $data, UploadedFile $fileLpj, $user): SuratTugas
    {
        $canManageAll = $user->isAdmin() || $user->hasPermission('simpeg.surat_tugas.approve');
        if (!$canManageAll) {
            $pegawaiId = $user->pegawai?->id;
            $isKetua = $suratTugas->pegawai_id === $pegawaiId;

            if (!$isKetua) {
                throw new AuthorizationException('Hanya penanggung jawab kegiatan yang berhak mengunggah berkas LPJ.');
            }
        }

        if (!in_array($suratTugas->status, ['disetujui', 'selesai'])) {
            throw ValidationException::withMessages([
                'status' => ['LPJ hanya dapat diunggah untuk surat tugas yang telah disetujui.'],
            ]);
        }

        return DB::transaction(function () use ($suratTugas, $data, $fileLpj, $user) {
            $this->deleteFile($suratTugas->file_lpj);
            $filePath = $this->handleFileUpload($fileLpj, 'lpj');

            $biayaRealisasi = array_key_exists('biaya_realisasi', $data) && $data['biaya_realisasi'] !== ''
                ? $data['biaya_realisasi']
                : ($suratTugas->nominal_disetujui > 0 ? $suratTugas->biaya_realisasi : 0);

            $suratTugas->update([
                'file_lpj' => $filePath,
                'laporan_kegiatan' => $data['laporan_kegiatan'] ?? $suratTugas->laporan_kegiatan,
                'biaya_realisasi' => $biayaRealisasi,
                'tanggal_upload_lpj' => now(),
                'status' => 'selesai',
            ]);

            // Sinkronisasi berkas LPJ dan realisasi ke transaksi pengeluaran kas SIKEU bila terhubung
            if ($suratTugas->sikeu_pencairan_id) {
                $pengeluaran = \App\Models\Sikeu\PengeluaranKampus::where('nomor_transaksi', 'like', '%-' . sprintf('%04d', $suratTugas->sikeu_pencairan_id))->first();
                if ($pengeluaran) {
                    $pengeluaranUpdate = [
                        'file_bukti_bayar' => $filePath,
                    ];
                    if ($biayaRealisasi > 0) {
                        $pengeluaranUpdate['nominal'] = $biayaRealisasi;
                        $pengeluaranUpdate['net_dibayarkan'] = $biayaRealisasi;
                    }
                    if (!str_contains($pengeluaran->keterangan, '[LPJ Terunggah]')) {
                        $pengeluaranUpdate['keterangan'] = $pengeluaran->keterangan . ' [LPJ Terunggah: Realisasi Rp ' . number_format((float)$biayaRealisasi, 0, ',', '.') . ']';
                    }
                    $pengeluaran->update($pengeluaranUpdate);
                }
            }

            return $this->getById($suratTugas->id, $user);
        });
    }

    /**
     * Hapus pengajuan surat tugas
     */
    public function delete(SuratTugas $suratTugas, $user): void
    {
        $canManageAll = $user->isAdmin() || $user->hasPermission('simpeg.surat_tugas.delete');
        if (!$canManageAll) {
            $pegawaiId = $user->pegawai?->id;
            if ($suratTugas->pegawai_id !== $pegawaiId) {
                throw new AuthorizationException('Hanya pemohon atau admin yang dapat menghapus pengajuan.');
            }
            if (!in_array($suratTugas->status, ['draft', 'diajukan', 'ditolak'])) {
                throw ValidationException::withMessages([
                    'status' => ['Surat tugas yang telah disetujui tidak dapat dihapus.'],
                ]);
            }
        }

        DB::transaction(function () use ($suratTugas) {
            $this->deleteFile($suratTugas->file_surat_tugas);
            $this->deleteFile($suratTugas->file_lpj);
            $suratTugas->delete();
        });
    }
}
