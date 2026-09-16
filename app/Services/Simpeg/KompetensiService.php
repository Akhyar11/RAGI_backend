<?php

namespace App\Services\Simpeg;

use App\Models\Simpeg\MasterJenisPelatihan;
use App\Models\Simpeg\MasterJenisSertifikasi;
use App\Models\Simpeg\MasterJenisTes;
use App\Models\Simpeg\MasterPeranPelatihan;
use App\Models\Simpeg\MasterTingkatKegiatan;
use App\Models\Simpeg\Pegawai;
use App\Models\Simpeg\RiwayatPelatihan;
use App\Models\Simpeg\RiwayatTes;
use App\Models\Simpeg\SertifikasiDosen;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class KompetensiService
{
    /**
     * Dapatkan semua master data referensi kompetensi
     */
    public function getMasters(): array
    {
        return [
            'jenis_sertifikasi' => MasterJenisSertifikasi::where('is_active', true)->orderBy('nama')->get(),
            'jenis_tes' => MasterJenisTes::where('is_active', true)->orderBy('nama')->get(),
            'jenis_pelatihan' => MasterJenisPelatihan::where('is_active', true)->orderBy('nama')->get(),
            'peran_pelatihan' => MasterPeranPelatihan::where('is_active', true)->orderBy('nama')->get(),
            'tingkat_kegiatan' => MasterTingkatKegiatan::where('is_active', true)->orderBy('nama')->get(),
        ];
    }

    /**
     * Upload berkas sertifikat / bukti ke storage disk
     */
    protected function handleFileUpload(?UploadedFile $file, string $folder = 'kompetensi'): ?string
    {
        if (!$file) {
            return null;
        }

        $extension = $file->getClientOriginalExtension();
        $safeName = time() . '_' . Str::random(10) . '.' . $extension;
        $path = $file->storeAs("dokumen_kompetensi/{$folder}", $safeName, 'public');

        return 'storage/' . $path;
    }

    // =========================================================================
    // 1. SERTIFIKASI DOSEN
    // =========================================================================

    public function listSertifikasi(array $filters, $user)
    {
        $query = SertifikasiDosen::with(['pegawai.unitKerja', 'jenisSertifikasi']);

        // Otorisasi Scoping
        $isManager = $user->isAdmin() || $user->hasPermission('simpeg.kompetensi.manage');
        if (!$isManager) {
            $pegawaiId = $user->pegawai?->id;
            if ($pegawaiId) {
                $query->where('pegawai_id', $pegawaiId);
            } else {
                $query->whereRaw('1 = 0');
            }
        } elseif (!empty($filters['pegawai_id'])) {
            $query->where('pegawai_id', $filters['pegawai_id']);
        }

        if (!empty($filters['jenis_sertifikasi_id'])) {
            $query->where('jenis_sertifikasi_id', $filters['jenis_sertifikasi_id']);
        }

        if (!empty($filters['tahun'])) {
            $query->where('tahun_sertifikasi', $filters['tahun']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('nama_sertifikat', 'like', "%{$search}%")
                  ->orWhere('bidang_studi', 'like', "%{$search}%")
                  ->orWhere('nomor_registrasi', 'like', "%{$search}%")
                  ->orWhere('nomor_sk', 'like', "%{$search}%")
                  ->orWhere('penyelenggara', 'like', "%{$search}%")
                  ->orWhereHas('pegawai', function ($qp) use ($search) {
                      $qp->where('nama_lengkap', 'like', "%{$search}%")
                         ->orWhere('nip', 'like', "%{$search}%")
                         ->orWhere('nidn', 'like', "%{$search}%");
                  });
            });
        }

        $allowedSort = ['created_at', 'tahun_sertifikasi', 'nama_sertifikat'];
        $sortBy = in_array($filters['sort_by'] ?? '', $allowedSort) ? $filters['sort_by'] : 'created_at';
        $sortOrder = strtolower($filters['sort_order'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortBy, $sortOrder);

        $perPage = min(100, (int) ($filters['per_page'] ?? 15));
        return $query->paginate($perPage);
    }

    public function createSertifikasi(array $data, ?UploadedFile $file = null): SertifikasiDosen
    {
        if ($file) {
            $data['file_path'] = $this->handleFileUpload($file, 'sertifikasi');
        }

        return SertifikasiDosen::create($data);
    }

    public function updateSertifikasi(int $id, array $data, ?UploadedFile $file = null): SertifikasiDosen
    {
        $item = SertifikasiDosen::findOrFail($id);

        if ($file) {
            if ($item->file_path) {
                $oldPath = str_replace('storage/', '', $item->file_path);
                Storage::disk('public')->delete($oldPath);
            }
            $data['file_path'] = $this->handleFileUpload($file, 'sertifikasi');
        }

        $item->update($data);
        return $item->fresh(['pegawai', 'jenisSertifikasi']);
    }

    public function deleteSertifikasi(int $id): bool
    {
        $item = SertifikasiDosen::findOrFail($id);
        return $item->delete();
    }

    // =========================================================================
    // 2. RIWAYAT TES (TOEFL, TKDA, TPA, DLL.)
    // =========================================================================

    public function listTes(array $filters, $user)
    {
        $query = RiwayatTes::with(['pegawai.unitKerja', 'jenisTes']);

        $isManager = $user->isAdmin() || $user->hasPermission('simpeg.kompetensi.manage');
        if (!$isManager) {
            $pegawaiId = $user->pegawai?->id;
            if ($pegawaiId) {
                $query->where('pegawai_id', $pegawaiId);
            } else {
                $query->whereRaw('1 = 0');
            }
        } elseif (!empty($filters['pegawai_id'])) {
            $query->where('pegawai_id', $filters['pegawai_id']);
        }

        if (!empty($filters['jenis_tes_id'])) {
            $query->where('jenis_tes_id', $filters['jenis_tes_id']);
        }

        if (!empty($filters['tahun'])) {
            $query->where('tahun', $filters['tahun']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('nama_tes', 'like', "%{$search}%")
                  ->orWhere('penyelenggara', 'like', "%{$search}%")
                  ->orWhereHas('pegawai', function ($qp) use ($search) {
                      $qp->where('nama_lengkap', 'like', "%{$search}%")
                         ->orWhere('nip', 'like', "%{$search}%")
                         ->orWhere('nidn', 'like', "%{$search}%");
                  });
            });
        }

        $allowedSort = ['created_at', 'tahun', 'skor', 'nama_tes'];
        $sortBy = in_array($filters['sort_by'] ?? '', $allowedSort) ? $filters['sort_by'] : 'created_at';
        $sortOrder = strtolower($filters['sort_order'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortBy, $sortOrder);

        $perPage = min(100, (int) ($filters['per_page'] ?? 15));
        return $query->paginate($perPage);
    }

    public function createTes(array $data, ?UploadedFile $file = null): RiwayatTes
    {
        if ($file) {
            $data['file_path'] = $this->handleFileUpload($file, 'tes');
        }

        return RiwayatTes::create($data);
    }

    public function updateTes(int $id, array $data, ?UploadedFile $file = null): RiwayatTes
    {
        $item = RiwayatTes::findOrFail($id);

        if ($file) {
            if ($item->file_path) {
                $oldPath = str_replace('storage/', '', $item->file_path);
                Storage::disk('public')->delete($oldPath);
            }
            $data['file_path'] = $this->handleFileUpload($file, 'tes');
        }

        $item->update($data);
        return $item->fresh(['pegawai', 'jenisTes']);
    }

    public function deleteTes(int $id): bool
    {
        $item = RiwayatTes::findOrFail($id);
        return $item->delete();
    }

    // =========================================================================
    // 3. RIWAYAT PELATIHAN, DIKLAT, WORKSHOP
    // =========================================================================

    public function listPelatihan(array $filters, $user)
    {
        $query = RiwayatPelatihan::with(['pegawai.unitKerja', 'jenisPelatihan', 'peran', 'tingkat']);

        $isManager = $user->isAdmin() || $user->hasPermission('simpeg.kompetensi.manage');
        if (!$isManager) {
            $pegawaiId = $user->pegawai?->id;
            if ($pegawaiId) {
                $query->where('pegawai_id', $pegawaiId);
            } else {
                $query->whereRaw('1 = 0');
            }
        } elseif (!empty($filters['pegawai_id'])) {
            $query->where('pegawai_id', $filters['pegawai_id']);
        }

        if (!empty($filters['jenis_pelatihan_id'])) {
            $query->where('jenis_pelatihan_id', $filters['jenis_pelatihan_id']);
        }

        if (!empty($filters['peran_id'])) {
            $query->where('peran_id', $filters['peran_id']);
        }

        if (!empty($filters['tingkat_id'])) {
            $query->where('tingkat_id', $filters['tingkat_id']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('nama_kegiatan', 'like', "%{$search}%")
                  ->orWhere('penyelenggara', 'like', "%{$search}%")
                  ->orWhere('tempat', 'like', "%{$search}%")
                  ->orWhere('nomor_sertifikat', 'like', "%{$search}%")
                  ->orWhereHas('pegawai', function ($qp) use ($search) {
                      $qp->where('nama_lengkap', 'like', "%{$search}%")
                         ->orWhere('nip', 'like', "%{$search}%")
                         ->orWhere('nidn', 'like', "%{$search}%");
                  });
            });
        }

        $allowedSort = ['created_at', 'tanggal_mulai', 'nama_kegiatan'];
        $sortBy = in_array($filters['sort_by'] ?? '', $allowedSort) ? $filters['sort_by'] : 'created_at';
        $sortOrder = strtolower($filters['sort_order'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortBy, $sortOrder);

        $perPage = min(100, (int) ($filters['per_page'] ?? 15));
        return $query->paginate($perPage);
    }

    public function createPelatihan(array $data, ?UploadedFile $file = null): RiwayatPelatihan
    {
        if ($file) {
            $data['file_path'] = $this->handleFileUpload($file, 'pelatihan');
        }

        return RiwayatPelatihan::create($data);
    }

    public function updatePelatihan(int $id, array $data, ?UploadedFile $file = null): RiwayatPelatihan
    {
        $item = RiwayatPelatihan::findOrFail($id);

        if ($file) {
            if ($item->file_path) {
                $oldPath = str_replace('storage/', '', $item->file_path);
                Storage::disk('public')->delete($oldPath);
            }
            $data['file_path'] = $this->handleFileUpload($file, 'pelatihan');
        }

        $item->update($data);
        return $item->fresh(['pegawai', 'jenisPelatihan', 'peran', 'tingkat']);
    }

    public function deletePelatihan(int $id): bool
    {
        $item = RiwayatPelatihan::findOrFail($id);
        return $item->delete();
    }

    // =========================================================================
    // 4. ADMIN SEARCH & AGGREGATION UNTUK AKREDITASI & BKD
    // =========================================================================

    public function searchKompetensiAdmin(array $filters)
    {
        $kategori = $filters['kategori'] ?? 'sertifikat'; // sertifikat, tes, pelatihan

        if ($kategori === 'sertifikat') {
            $query = SertifikasiDosen::with(['pegawai.unitKerja', 'jenisSertifikasi']);
            if (!empty($filters['jenis_id'])) {
                $query->where('jenis_sertifikasi_id', $filters['jenis_id']);
            }
            if (!empty($filters['tahun'])) {
                $query->where('tahun_sertifikasi', $filters['tahun']);
            }
        } elseif ($kategori === 'tes') {
            $query = RiwayatTes::with(['pegawai.unitKerja', 'jenisTes']);
            if (!empty($filters['jenis_id'])) {
                $query->where('jenis_tes_id', $filters['jenis_id']);
            }
            if (!empty($filters['skor_min'])) {
                $query->where('skor', '>=', (float) $filters['skor_min']);
            }
            if (!empty($filters['tahun'])) {
                $query->where('tahun', $filters['tahun']);
            }
        } else {
            $query = RiwayatPelatihan::with(['pegawai.unitKerja', 'jenisPelatihan', 'peran', 'tingkat']);
            if (!empty($filters['jenis_id'])) {
                $query->where('jenis_pelatihan_id', $filters['jenis_id']);
            }
            if (!empty($filters['peran_id'])) {
                $query->where('peran_id', $filters['peran_id']);
            }
            if (!empty($filters['tingkat_id'])) {
                $query->where('tingkat_id', $filters['tingkat_id']);
            }
        }

        // Filter unit kerja / prodi
        if (!empty($filters['unit_kerja_id'])) {
            $query->whereHas('pegawai', function ($qp) use ($filters) {
                $qp->where('unit_kerja_id', $filters['unit_kerja_id']);
            });
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->whereHas('pegawai', function ($qp) use ($search) {
                $qp->where('nama_lengkap', 'like', "%{$search}%")
                   ->orWhere('nip', 'like', "%{$search}%")
                   ->orWhere('nidn', 'like', "%{$search}%");
            });
        }

        $perPage = min(100, (int) ($filters['per_page'] ?? 15));
        return $query->latest()->paginate($perPage);
    }
}
