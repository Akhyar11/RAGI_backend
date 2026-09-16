<?php

namespace App\Services\Simpeg;

use App\Models\Simpeg\MasterKategoriSk;
use App\Models\Simpeg\SkPegawai;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SkPegawaiService
{
    /**
     * Dapatkan master kategori SK dinamis
     */
    public function getMasters(): array
    {
        return [
            'kategori_sk' => MasterKategoriSk::where('is_active', true)->orderBy('urutan')->get(),
        ];
    }

    /**
     * Upload berkas SK pegawai ber-UUID
     */
    public function handleFileUpload(?UploadedFile $file): ?string
    {
        if (!$file) {
            return null;
        }

        $extension = $file->getClientOriginalExtension();
        $safeName = Str::uuid() . '.' . $extension;
        $path = $file->storeAs('simpeg/sk_pegawai/' . date('Y/m'), $safeName, 'public');

        return 'storage/' . $path;
    }

    /**
     * Hapus berkas SK dari disk storage
     */
    public function deleteFile(?string $filePath): void
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
     * List SK pegawai dengan filter & scoping
     */
    public function list(array $filters, $user)
    {
        $query = SkPegawai::with(['pegawai.unitKerja', 'kategoriSk', 'verifier']);

        $canManageAll = $user->isAdmin() || $user->hasPermission('simpeg.sk_pegawai.verify');
        if (!$canManageAll) {
            $pegawaiId = $user->pegawai?->id;
            if ($pegawaiId) {
                $query->where('pegawai_id', $pegawaiId);
            } else {
                $query->whereRaw('1 = 0');
            }
        } elseif (!empty($filters['pegawai_id'])) {
            $query->where('pegawai_id', $filters['pegawai_id']);
        }

        if (!empty($filters['kategori_sk_id'])) {
            $query->where('kategori_sk_id', $filters['kategori_sk_id']);
        }

        if (!empty($filters['status_verifikasi'])) {
            $query->where('status_verifikasi', $filters['status_verifikasi']);
        }

        if (!empty($filters['tahun'])) {
            $query->whereYear('tanggal_sk', $filters['tahun']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('nomor_sk', 'like', "%{$search}%")
                  ->orWhere('judul_sk', 'like', "%{$search}%")
                  ->orWhere('pejabat_penetap', 'like', "%{$search}%")
                  ->orWhereHas('pegawai', function ($qp) use ($search) {
                      $qp->where('nama_lengkap', 'like', "%{$search}%")
                         ->orWhere('nip', 'like', "%{$search}%")
                         ->orWhere('nidn', 'like', "%{$search}%");
                  });
            });
        }

        $sortBy = $filters['sort_by'] ?? 'tanggal_sk';
        $sortDir = strtolower($filters['sort_dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortBy, $sortDir);

        $limit = max(1, min(100, (int) ($filters['limit'] ?? 15)));

        return $query->paginate($limit);
    }

    /**
     * Dapatkan detail SK Pegawai
     */
    public function getById(string $id, $user): SkPegawai
    {
        $sk = SkPegawai::with(['pegawai.unitKerja', 'kategoriSk', 'verifier'])->findOrFail($id);

        $canManageAll = $user->isAdmin() || $user->hasPermission('simpeg.sk_pegawai.verify');
        if (!$canManageAll && $sk->pegawai_id !== $user->pegawai?->id) {
            abort(403, 'Anda tidak memiliki akses ke data SK ini.');
        }

        return $sk;
    }

    /**
     * Buat pelaporan SK Pegawai baru
     */
    public function create(array $data, ?UploadedFile $file, $user): SkPegawai
    {
        $canManageAll = $user->isAdmin() || $user->hasPermission('simpeg.sk_pegawai.verify');
        if (!$canManageAll) {
            $userPegawaiId = $user->pegawai?->id;
            if (!$userPegawaiId) {
                abort(403, 'Akun Anda tidak terhubung dengan data Pegawai.');
            }
            $data['pegawai_id'] = $userPegawaiId;
        }

        if ($file) {
            $data['file_sk'] = $this->handleFileUpload($file);
        }

        $data['status_verifikasi'] = 'pending';

        return SkPegawai::create($data);
    }

    /**
     * Update SK Pegawai
     */
    public function update(string $id, array $data, ?UploadedFile $file, $user): SkPegawai
    {
        $sk = $this->getById($id, $user);

        if (!in_array($sk->status_verifikasi, ['pending', 'ditolak']) && !$user->isAdmin()) {
            abort(422, 'SK yang sudah terverifikasi tidak dapat diedit secara mandiri.');
        }

        if ($file) {
            $this->deleteFile($sk->file_sk);
            $data['file_sk'] = $this->handleFileUpload($file);
        }

        // Jika sebelumnya ditolak dan diedit ulang, kembalikan status ke pending
        if ($sk->status_verifikasi === 'ditolak') {
            $data['status_verifikasi'] = 'pending';
            $data['catatan_verifikasi'] = null;
        }

        $sk->update($data);

        return $sk->fresh(['pegawai.unitKerja', 'kategoriSk', 'verifier']);
    }

    /**
     * Verifikasi SK Pegawai oleh HR/SDM
     */
    public function verify(string $id, array $data, $verifierUser): SkPegawai
    {
        $sk = SkPegawai::findOrFail($id);

        $status = $data['status_verifikasi'];
        $catatan = $data['catatan_verifikasi'] ?? null;

        $sk->update([
            'status_verifikasi' => $status,
            'catatan_verifikasi' => $catatan,
            'verified_by' => $verifierUser->id,
            'verified_at' => now(),
        ]);

        return $sk->fresh(['pegawai.unitKerja', 'kategoriSk', 'verifier']);
    }

    /**
     * Hapus SK Pegawai
     */
    public function delete(string $id, $user): bool
    {
        $sk = $this->getById($id, $user);

        if (!in_array($sk->status_verifikasi, ['pending', 'ditolak']) && !$user->isAdmin()) {
            abort(422, 'SK yang sudah diverifikasi tidak dapat dihapus.');
        }

        $this->deleteFile($sk->file_sk);

        return $sk->delete();
    }
}
