<?php

namespace App\Services\Simpeg;

use App\Models\Simpeg\MasterKategoriSkp;
use App\Models\Simpeg\Pegawai;
use App\Models\Simpeg\PenilaianKinerja;
use App\Models\Simpeg\SkpItem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SkpService
{
    /**
     * Dapatkan master kategori SKP dan daftar pejabat penilai
     */
    public function getMasters(): array
    {
        return [
            'kategori_skp' => MasterKategoriSkp::where('is_active', true)->orderBy('urutan')->get(),
            'pejabat_penilai' => Pegawai::select('id', 'nama_lengkap', 'nip', 'jabatan_terakhir')
                ->where('status_aktif', true)
                ->orderBy('nama_lengkap')
                ->get(),
        ];
    }

    /**
     * List Penilaian Kinerja dengan filter, pencarian, dan permission scoping dinamis
     */
    public function list(array $filters, $user)
    {
        $query = PenilaianKinerja::with(['pegawai.unitKerja', 'pegawai.dosen.programStudi', 'pejabatPenilai', 'evaluator']);

        $isAdmin = $user->isAdmin() || $user->hasPermission('simpeg.kinerja.manage');
        $userPegawaiId = $user->pegawai?->id;

        if (!$isAdmin) {
            // Pegawai biasa hanya melihat miliknya sendiri atau SKP di mana dia sebagai Pejabat Penilai
            $query->where(function ($q) use ($userPegawaiId) {
                if ($userPegawaiId) {
                    $q->where('pegawai_id', $userPegawaiId)
                      ->orWhere('pejabat_penilai_id', $userPegawaiId);
                } else {
                    $q->whereRaw('1 = 0');
                }
            });
        } elseif (!empty($filters['pegawai_id'])) {
            $query->where('pegawai_id', $filters['pegawai_id']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->whereHas('pegawai', function ($q) use ($search) {
                $q->where('nama_lengkap', 'like', "%{$search}%")
                  ->orWhere('nip', 'like', "%{$search}%")
                  ->orWhere('nidn', 'like', "%{$search}%");
            });
        }

        if (!empty($filters['tahun'])) {
            $query->where('tahun', $filters['tahun']);
        }

        if (!empty($filters['semester'])) {
            $query->where('semester', $filters['semester']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['predikat'])) {
            $query->where('predikat', $filters['predikat']);
        }

        $orderBy = $filters['orderBy'] ?? 'tahun';
        $orderDir = strtolower($filters['orderDir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        if (in_array($orderBy, ['tahun', 'status', 'nilai_skp', 'created_at', 'id'])) {
            $query->orderBy($orderBy, $orderDir);
        } else {
            $query->orderBy('tahun', 'desc')->latest();
        }

        $limit = isset($filters['limit']) ? (int) $filters['limit'] : 15;
        return $query->paginate($limit);
    }

    /**
     * Dapatkan detail lengkap SKP beserta butir items
     */
    public function getDetail(int $id): PenilaianKinerja
    {
        return PenilaianKinerja::with([
            'pegawai.unitKerja',
            'pejabatPenilai',
            'evaluator',
            'items.kategori',
        ])->findOrFail($id);
    }

    /**
     * Simpan pengajuan SKP baru dengan daftar butir target
     */
    public function createSkp(array $data, $user): PenilaianKinerja
    {
        return DB::transaction(function () use ($data) {
            $skp = PenilaianKinerja::create([
                'pegawai_id' => $data['pegawai_id'],
                'tahun' => $data['tahun'],
                'semester' => $data['semester'],
                'status' => 'draft',
                'pejabat_penilai_id' => $data['pejabat_penilai_id'] ?? null,
                'nilai_skp' => 0,
                'predikat' => 'baik',
            ]);

            foreach ($data['items'] as $item) {
                $skp->items()->create([
                    'kategori_skp_id' => $item['kategori_skp_id'],
                    'uraian_tugas' => $item['uraian_tugas'],
                    'target_output' => $item['target_output'],
                    'target_mutu' => $item['target_mutu'] ?? 100.00,
                    'target_waktu' => $item['target_waktu'],
                    'target_biaya' => $item['target_biaya'] ?? null,
                ]);
            }

            return $skp->load(['pegawai', 'pejabatPenilai', 'items.kategori']);
        });
    }

    /**
     * Perbarui draf SKP beserta butir tugas
     */
    public function updateSkp(PenilaianKinerja $skp, array $data): PenilaianKinerja
    {
        return DB::transaction(function () use ($skp, $data) {
            $skp->update([
                'tahun' => $data['tahun'] ?? $skp->tahun,
                'semester' => $data['semester'] ?? $skp->semester,
                'pejabat_penilai_id' => array_key_exists('pejabat_penilai_id', $data) ? $data['pejabat_penilai_id'] : $skp->pejabat_penilai_id,
            ]);

            if (isset($data['items']) && is_array($data['items'])) {
                // Hapus butir lama dan masukkan yang baru
                $skp->items()->delete();

                foreach ($data['items'] as $item) {
                    $skp->items()->create([
                        'kategori_skp_id' => $item['kategori_skp_id'],
                        'uraian_tugas' => $item['uraian_tugas'],
                        'target_output' => $item['target_output'],
                        'target_mutu' => $item['target_mutu'] ?? 100.00,
                        'target_waktu' => $item['target_waktu'],
                        'target_biaya' => $item['target_biaya'] ?? null,
                    ]);
                }
            }

            return $skp->load(['pegawai', 'pejabatPenilai', 'items.kategori']);
        });
    }

    /**
     * Ajukan sasaran kinerja (target) kepada atasan / pejabat penilai
     */
    public function submitTarget(PenilaianKinerja $skp): PenilaianKinerja
    {
        if ($skp->items()->count() === 0) {
            throw new \InvalidArgumentException('Sasaran kinerja harus memiliki minimal 1 butir target sebelum diajukan.');
        }

        $skp->update([
            'status' => 'diajukan',
            'tanggal_pengajuan' => now(),
        ]);

        return $skp->fresh(['pegawai', 'pejabatPenilai', 'items.kategori']);
    }

    /**
     * Setujui target sasaran kerja oleh atasan / pejabat penilai
     */
    public function approveTarget(PenilaianKinerja $skp): PenilaianKinerja
    {
        $skp->update([
            'status' => 'disetujui',
            'tanggal_persetujuan' => now(),
        ]);

        return $skp->fresh(['pegawai', 'pejabatPenilai', 'items.kategori']);
    }

    /**
     * Submit realisasi capaian dan berkas bukti fisik luaran
     */
    public function submitRealisasi(PenilaianKinerja $skp, array $itemsData, array $uploadedFiles = []): PenilaianKinerja
    {
        return DB::transaction(function () use ($skp, $itemsData, $uploadedFiles) {
            foreach ($itemsData as $index => $itemData) {
                $itemId = $itemData['id'];
                $item = SkpItem::where('penilaian_kinerja_id', $skp->id)->findOrFail($itemId);

                $updatePayload = [
                    'realisasi_output' => $itemData['realisasi_output'] ?? $item->realisasi_output,
                    'realisasi_mutu' => $itemData['realisasi_mutu'] ?? $item->realisasi_mutu,
                    'realisasi_waktu' => $itemData['realisasi_waktu'] ?? $item->realisasi_waktu,
                    'realisasi_biaya' => $itemData['realisasi_biaya'] ?? $item->realisasi_biaya,
                    'keterangan' => $itemData['keterangan'] ?? $item->keterangan,
                ];

                // Cek upload berkas bukti jika ada
                $file = $uploadedFiles[$itemId] ?? ($uploadedFiles[$index] ?? null);
                if ($file instanceof UploadedFile) {
                    if ($item->berkas_bukti) {
                        $this->deleteFile($item->berkas_bukti);
                    }
                    $updatePayload['berkas_bukti'] = $this->handleFileUpload($file);
                }

                $item->update($updatePayload);
            }

            return $skp->fresh(['pegawai', 'pejabatPenilai', 'items.kategori']);
        });
    }

    /**
     * Berikan evaluasi akhir skor SKP, BKD, dan predikat kinerja
     */
    public function evaluate(PenilaianKinerja $skp, array $data, $evaluatorUser): PenilaianKinerja
    {
        return DB::transaction(function () use ($skp, $data, $evaluatorUser) {
            // Update nilai capaian per butir jika disediakan
            if (!empty($data['items']) && is_array($data['items'])) {
                foreach ($data['items'] as $itemData) {
                    if (isset($itemData['id']) && isset($itemData['nilai_capaian'])) {
                        SkpItem::where('penilaian_kinerja_id', $skp->id)
                            ->where('id', $itemData['id'])
                            ->update(['nilai_capaian' => $itemData['nilai_capaian']]);
                    }
                }
            }

            $skp->update([
                'nilai_skp' => $data['nilai_skp'],
                'nilai_bkd' => $data['nilai_bkd'] ?? $skp->nilai_bkd,
                'predikat' => $data['predikat'],
                'catatan_evaluator' => $data['catatan_evaluator'] ?? null,
                'evaluator_id' => $evaluatorUser->id,
                'evaluated_at' => now(),
                'status' => 'dinilai',
            ]);

            return $skp->fresh(['pegawai', 'pejabatPenilai', 'evaluator', 'items.kategori']);
        });
    }

    /**
     * Hapus pengajuan SKP
     */
    public function deleteSkp(PenilaianKinerja $skp): void
    {
        DB::transaction(function () use ($skp) {
            foreach ($skp->items as $item) {
                if ($item->berkas_bukti) {
                    $this->deleteFile($item->berkas_bukti);
                }
            }
            $skp->delete();
        });
    }

    /**
     * Helper simpan file bukti fisik luaran
     */
    private function handleFileUpload(UploadedFile $file): string
    {
        $extension = $file->getClientOriginalExtension();
        $safeName = Str::uuid() . '.' . $extension;
        $path = $file->storeAs('simpeg/skp_bukti/' . date('Y/m'), $safeName, 'public');

        return 'storage/' . $path;
    }

    /**
     * Helper hapus file bukti
     */
    private function deleteFile(?string $filePath): void
    {
        if (!$filePath) {
            return;
        }

        $cleanPath = str_replace('storage/', '', $filePath);
        if (Storage::disk('public')->exists($cleanPath)) {
            Storage::disk('public')->delete($cleanPath);
        }
    }
}
