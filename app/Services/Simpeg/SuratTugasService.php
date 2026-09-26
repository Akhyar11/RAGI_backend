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

            $pegawai = \App\Models\Simpeg\Pegawai::find($data['pegawai_id']);
            $namaBank = $data['nama_bank'] ?? $pegawai?->nama_bank;
            $nomorRekening = $data['nomor_rekening'] ?? $pegawai?->nomor_rekening;
            $namaRekening = $data['nama_rekening'] ?? ($pegawai?->nama_rekening ?? $pegawai?->nama_lengkap);

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
                'nama_bank' => $namaBank,
                'nomor_rekening' => $nomorRekening,
                'nama_rekening' => $namaRekening,
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
                'maksud_tujuan', 'beban_anggaran', 'estimasi_biaya',
                'nama_bank', 'nomor_rekening', 'nama_rekening', 'keterangan',
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
            if (array_key_exists('nominal_disetujui', $data) && $data['nominal_disetujui'] !== null && $data['nominal_disetujui'] !== '') {
                $nominalDisetujui = (float) $data['nominal_disetujui'];
            } else {
                $nominalDisetujui = (float) ($suratTugas->estimasi_biaya ?? 0);
            }

            $updatePayload = [
                'status' => $data['status'],
                'catatan_approval' => $data['catatan_approval'] ?? null,
                'approved_by' => $user->id,
                'approved_at' => now(),
            ];

            if ($data['status'] === 'disetujui') {
                $updatePayload['nomor_surat'] = $data['nomor_surat'];

                $estimasiBiaya = (float) ($suratTugas->estimasi_biaya ?? 0);

                // Integrasi SIKEU: Jika tugas berbiaya (> 0), teruskan antrean pencairan dana ke SIKEU (Tahap 2)
                if ($estimasiBiaya > 0) {
                    $unitKerjaId = $suratTugas->pegawai?->unit_kerja_id;
                    $nomorPengajuan = 'CAIR-ST-' . date('Ymd') . '-' . sprintf('%04d', $suratTugas->id);

                    $attributes = [
                        'unit_kerja_id' => $unitKerjaId,
                        'unit_kas_id' => null, // Ditetapkan oleh Bagian Keuangan (SIKEU) di Tahap 3
                        'pemohon_id' => $suratTugas->pegawai?->user_id ?? $user->id,
                        'judul_pengajuan' => 'Panjar Perjalanan Dinas: ' . $suratTugas->nama_kegiatan,
                        'deskripsi' => 'Pencairan panjar dana tugas dinas No. ' . $data['nomor_surat'] . ' ke ' . $suratTugas->lokasi_tujuan . ' an. ' . ($suratTugas->pegawai?->nama_lengkap ?? 'Pegawai'),
                        'nominal_diajukan' => $estimasiBiaya,
                        'nominal_disetujui' => 0, // Menunggu persetujuan nominal oleh Admin SIKEU
                        'nama_bank_penerima' => $suratTugas->nama_bank ?? $suratTugas->pegawai?->nama_bank,
                        'nomor_rekening_penerima' => $suratTugas->nomor_rekening ?? $suratTugas->pegawai?->nomor_rekening,
                        'nama_rekening_penerima' => $suratTugas->nama_rekening ?? ($suratTugas->pegawai?->nama_rekening ?? $suratTugas->pegawai?->nama_lengkap),
                        'jenis_pengajuan' => 'kegiatan',
                        'kategori_pengajuan' => 'non_barang',
                        'status' => 'pending_keuangan',
                        'kanal' => 'simpeg_surat_tugas',
                        'referensi_eksternal' => $data['nomor_surat'],
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

                    // Buat/update rincian item pengajuan agar detail SIKEU lengkap
                    \App\Models\Sikeu\PengajuanItem::updateOrCreate(
                        [
                            'pengajuan_id' => $pengajuanKas->id,
                            'nama_barang' => 'Panjar Perjalanan Dinas: ' . $suratTugas->nama_kegiatan,
                        ],
                        [
                            'qty' => 1,
                            'satuan' => 'paket',
                            'harga_satuan' => $estimasiBiaya,
                            'subtotal' => $estimasiBiaya,
                            'keterangan' => 'Dana panjar tugas dinas luar kampus No. ' . $data['nomor_surat'],
                        ]
                    );

                    $updatePayload['sikeu_pencairan_id'] = $pengajuanKas->id;
                    $updatePayload['nominal_disetujui'] = 0;
                    $updatePayload['status_pencairan'] = 'menunggu_keuangan';
                } else {
                    // Non-biaya / Pelatihan daring Zoom / Rp 0 -> Bypass SIKEU
                    $updatePayload['sikeu_pencairan_id'] = null;
                    $updatePayload['nominal_disetujui'] = 0;
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
     * Konfirmasi penerimaan panjar oleh dosen pemohon (Tahap 4)
     */
    public function konfirmasiPanjar(SuratTugas $suratTugas, $user): SuratTugas
    {
        $canManageAll = $user->isAdmin() || $user->hasPermission('simpeg.surat_tugas.approve');
        $pegawaiId = $user->pegawai?->id;

        if (!$canManageAll && $suratTugas->pegawai_id !== $pegawaiId) {
            throw new AuthorizationException('Hanya pemohon penugasan yang dapat mengonfirmasi panjar.');
        }

        if ($suratTugas->status_pencairan !== 'panjar_disetujui') {
            throw ValidationException::withMessages([
                'status_pencairan' => ['Konfirmasi panjar hanya dapat dilakukan setelah nominal disetujui oleh Bagian Keuangan.'],
            ]);
        }

        return DB::transaction(function () use ($suratTugas, $user) {
            $suratTugas->update([
                'status_pencairan' => 'siap_cair',
            ]);

            if ($suratTugas->sikeu_pencairan_id) {
                \App\Models\Sikeu\PengajuanPencairanKas::where('id', $suratTugas->sikeu_pencairan_id)
                    ->update(['status' => 'disetujui']);
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

            $sisaNominal = (float) $suratTugas->nominal_disetujui - (float) $biayaRealisasi;

            $suratTugas->update([
                'file_lpj' => $filePath,
                'laporan_kegiatan' => $data['laporan_kegiatan'] ?? $suratTugas->laporan_kegiatan,
                'biaya_realisasi' => $biayaRealisasi,
                'tanggal_upload_lpj' => now(),
                'status_pencairan' => 'lpj_diunggah',
            ]);

            // Sinkronisasi berkas LPJ dan realisasi ke transaksi pengeluaran kas SIKEU bila terhubung
            if ($suratTugas->sikeu_pencairan_id) {
                $pengajuanKas = \App\Models\Sikeu\PengajuanPencairanKas::find($suratTugas->sikeu_pencairan_id);
                if ($pengajuanKas) {
                    $pengajuanKas->update([
                        'total_realisasi' => $biayaRealisasi,
                        'sisa_nominal' => $sisaNominal,
                        'status' => 'lpj_pending',
                    ]);
                }

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
                        $pengeluaranUpdate['keterangan'] = $pengeluaran->keterangan . ' [LPJ Terunggah: Realisasi Rp ' . number_format((float)$biayaRealisasi, 0, ',', '.') . ', Sisa Rp ' . number_format((float)$sisaNominal, 0, ',', '.') . ']';
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
