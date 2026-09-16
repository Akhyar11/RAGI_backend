<?php

namespace App\Services\Simpeg;

use App\Events\Simpeg\IzinJamKerjaDisetujui;
use App\Models\Simpeg\IzinJamKerja;
use App\Models\Simpeg\MasterJenisIzinJamKerja;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class IzinJamKerjaService
{
    /**
     * Dapatkan master referensi dinamis jenis izin jam kerja
     */
    public function getMasters(): array
    {
        return [
            'jenis_izin' => MasterJenisIzinJamKerja::where('is_active', true)->orderBy('urutan')->get(),
        ];
    }

    /**
     * Upload berkas bukti izin jam kerja
     */
    public function handleFileUpload(?UploadedFile $file): ?string
    {
        if (!$file) {
            return null;
        }

        $extension = $file->getClientOriginalExtension();
        $safeName = Str::uuid() . '.' . $extension;
        $path = $file->storeAs('simpeg/izin_jam_kerja/' . date('Y/m'), $safeName, 'public');

        return 'storage/' . $path;
    }

    /**
     * Hapus berkas dari disk storage
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
     * List izin jam kerja dengan filter & permission scoping
     */
    public function list(array $filters, $user)
    {
        $query = IzinJamKerja::with(['pegawai.unitKerja', 'jenisIzin', 'approver']);

        $canManageAll = $user->isAdmin() || $user->hasPermission('simpeg.izin_kerja.approve');
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

        if (!empty($filters['master_jenis_izin_id'])) {
            $query->where('master_jenis_izin_id', $filters['master_jenis_izin_id']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['tanggal_mulai'])) {
            $query->whereDate('tanggal', '>=', $filters['tanggal_mulai']);
        }

        if (!empty($filters['tanggal_selesai'])) {
            $query->whereDate('tanggal', '<=', $filters['tanggal_selesai']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('alasan', 'like', "%{$search}%")
                  ->orWhereHas('pegawai', function ($qp) use ($search) {
                      $qp->where('nama_lengkap', 'like', "%{$search}%")
                         ->orWhere('nip', 'like', "%{$search}%")
                         ->orWhere('nidn', 'like', "%{$search}%");
                  });
            });
        }

        $sortBy = $filters['sort_by'] ?? 'tanggal';
        $sortDir = strtolower($filters['sort_dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortBy, $sortDir);

        $limit = max(1, min(100, (int) ($filters['limit'] ?? 15)));

        return $query->paginate($limit);
    }

    /**
     * Dapatkan detail izin jam kerja
     */
    public function getById(string $id, $user): IzinJamKerja
    {
        $izin = IzinJamKerja::with(['pegawai.unitKerja', 'jenisIzin', 'approver'])->findOrFail($id);

        $canManageAll = $user->isAdmin() || $user->hasPermission('simpeg.izin_kerja.approve');
        if (!$canManageAll && $izin->pegawai_id !== $user->pegawai?->id) {
            abort(403, 'Anda tidak memiliki akses ke data izin ini.');
        }

        return $izin;
    }

    /**
     * Buat pengajuan izin jam kerja baru
     */
    public function create(array $data, ?UploadedFile $file, $user): IzinJamKerja
    {
        $canManageAll = $user->isAdmin() || $user->hasPermission('simpeg.izin_kerja.approve');
        if (!$canManageAll) {
            $userPegawaiId = $user->pegawai?->id;
            if (!$userPegawaiId) {
                abort(403, 'Akun Anda tidak terhubung dengan data Pegawai.');
            }
            $data['pegawai_id'] = $userPegawaiId;
        }

        if ($file) {
            $data['file_bukti'] = $this->handleFileUpload($file);
        }

        $data['status'] = 'menunggu';

        return IzinJamKerja::create($data);
    }

    /**
     * Update pengajuan izin jam kerja (hanya jika menunggu)
     */
    public function update(string $id, array $data, ?UploadedFile $file, $user): IzinJamKerja
    {
        $izin = $this->getById($id, $user);

        if ($izin->status !== 'menunggu' && !$user->isAdmin()) {
            abort(422, 'Pengajuan izin yang sudah diproses approval tidak dapat diubah.');
        }

        if ($file) {
            $this->deleteFile($izin->file_bukti);
            $data['file_bukti'] = $this->handleFileUpload($file);
        }

        $izin->update($data);

        return $izin->fresh(['pegawai.unitKerja', 'jenisIzin', 'approver']);
    }

    /**
     * Proses approval izin jam kerja
     */
    public function approve(string $id, array $data, $approverUser): IzinJamKerja
    {
        $izin = IzinJamKerja::with('jenisIzin')->findOrFail($id);

        $status = $data['status'];
        $catatan = $data['catatan_approval'] ?? null;

        $izin->update([
            'status' => $status,
            'catatan_approval' => $catatan,
            'approved_by' => $approverUser->id,
            'approved_at' => now(),
        ]);

        if ($status === 'disetujui') {
            IzinJamKerjaDisetujui::dispatch($izin);
        }

        return $izin->fresh(['pegawai.unitKerja', 'jenisIzin', 'approver']);
    }

    /**
     * Hapus pengajuan izin jam kerja
     */
    public function delete(string $id, $user): bool
    {
        $izin = $this->getById($id, $user);

        if ($izin->status !== 'menunggu' && !$user->isAdmin()) {
            abort(422, 'Pengajuan izin yang sudah diproses tidak dapat dihapus.');
        }

        $this->deleteFile($izin->file_bukti);

        return $izin->delete();
    }
}
