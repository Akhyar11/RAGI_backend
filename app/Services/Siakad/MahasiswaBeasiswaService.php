<?php

namespace App\Services\Siakad;

use App\Models\Sikeu\MahasiswaBeasiswa;
use App\Models\Sikeu\Beasiswa;
use App\Models\Siakad\Mahasiswa;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

class MahasiswaBeasiswaService
{
    /**
     * Dapatkan daftar penetapan beasiswa mahasiswa dengan filter & pagination.
     */
    public function getPaginatedList(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = MahasiswaBeasiswa::with(['beasiswa', 'mahasiswa.programStudi']);

        // Filter Pencarian Teks
        $search = $filters['search'] ?? $filters['q'] ?? null;
        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('nama_mahasiswa', 'like', "%{$search}%")
                  ->orWhere('nim', 'like', "%{$search}%")
                  ->orWhereHas('beasiswa', function ($b) use ($search) {
                      $b->where('nama', 'like', "%{$search}%")
                        ->orWhere('kode', 'like', "%{$search}%");
                  });
            });
        }

        // Filter Status
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Filter Beasiswa ID
        if (!empty($filters['beasiswa_id'])) {
            $query->where('beasiswa_id', $filters['beasiswa_id']);
        }

        // Sorting
        $allowedSort = ['nama_mahasiswa', 'nim', 'status', 'created_at', 'berlaku_mulai', 'id'];
        $sortBy = in_array($filters['sort_by'] ?? '', $allowedSort) ? $filters['sort_by'] : 'id';
        $sortOrder = strtolower($filters['sort_order'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortBy, $sortOrder);

        $paginator = $query->paginate($perPage);

        $paginator->getCollection()->transform(function ($item) {
            $potonganText = '-';
            if ($item->beasiswa) {
                $potonganText = $item->beasiswa->tipe_potongan === 'persen'
                    ? rtrim(rtrim(number_format((float)$item->beasiswa->nilai_potongan, 2), '0'), '.') . '%'
                    : 'Rp ' . number_format((float)$item->beasiswa->nilai_potongan, 0, ',', '.');
            }

            return [
                'id' => $item->id,
                'mahasiswa_id' => $item->mahasiswa_id,
                'nim' => $item->nim ?? $item->mahasiswa?->nim ?? ('NIM-' . $item->mahasiswa_id),
                'nama_mahasiswa' => $item->nama_mahasiswa ?? $item->mahasiswa?->nama_lengkap ?? ('Mahasiswa #' . $item->mahasiswa_id),
                'prodi' => $item->mahasiswa?->programStudi?->nama ?? $item->mahasiswa?->programStudi?->nama_prodi ?? '-',
                'angkatan' => $item->mahasiswa?->angkatan ?? null,
                'beasiswa_id' => $item->beasiswa_id,
                'kode_beasiswa' => $item->beasiswa?->kode ?? '-',
                'nama_beasiswa' => $item->beasiswa?->nama ?? 'Beasiswa',
                'sumber_beasiswa' => $item->beasiswa?->sumber ?? 'internal',
                'tipe_potongan' => $item->beasiswa?->tipe_potongan ?? 'persen',
                'nilai_potongan' => (float)($item->beasiswa?->nilai_potongan ?? 0),
                'potongan_text' => $potonganText,
                'berlaku_mulai' => $item->berlaku_mulai ? (is_object($item->berlaku_mulai) ? $item->berlaku_mulai->format('Y-m-d') : substr((string)$item->berlaku_mulai, 0, 10)) : null,
                'berlaku_sampai' => $item->berlaku_sampai ? (is_object($item->berlaku_sampai) ? $item->berlaku_sampai->format('Y-m-d') : substr((string)$item->berlaku_sampai, 0, 10)) : null,
                'status' => $item->status ?? 'aktif',
                'created_at' => $item->created_at?->format('Y-m-d H:i:s'),
            ];
        });

        return $paginator;
    }

    /**
     * Tetapkan mahasiswa penerima beasiswa baru (oleh BAAK).
     */
    public function assignBeasiswa(array $data): MahasiswaBeasiswa
    {
        $mahasiswaId = (int)$data['mahasiswa_id'];
        $beasiswaId = (int)$data['beasiswa_id'];
        $status = $data['status'] ?? 'aktif';

        if ($status === 'aktif') {
            $existing = MahasiswaBeasiswa::where('mahasiswa_id', $mahasiswaId)
                ->where('beasiswa_id', $beasiswaId)
                ->where('status', 'aktif')
                ->first();

            if ($existing) {
                throw ValidationException::withMessages([
                    'beasiswa_id' => ['Mahasiswa ini sudah terdaftar aktif pada program beasiswa yang dipilih.'],
                ]);
            }
        }

        $mhs = Mahasiswa::find($mahasiswaId);
        $nim = $mhs?->nim ?? ($data['nim'] ?? 'NIM-' . $mahasiswaId);
        $nama = $mhs?->nama_lengkap ?? ($data['nama_mahasiswa'] ?? 'Mahasiswa #' . $mahasiswaId);

        $berlakuMulai = !empty($data['berlaku_mulai']) ? $data['berlaku_mulai'] : now()->toDateString();
        $berlakuSampai = !empty($data['berlaku_sampai']) ? $data['berlaku_sampai'] : now()->addYear()->toDateString();

        $item = MahasiswaBeasiswa::create([
            'mahasiswa_id' => $mahasiswaId,
            'beasiswa_id' => $beasiswaId,
            'nim' => $nim,
            'nama_mahasiswa' => $nama,
            'berlaku_mulai' => $berlakuMulai,
            'berlaku_sampai' => $berlakuSampai,
            'status' => $status,
        ]);

        return $item->load(['beasiswa', 'mahasiswa.programStudi']);
    }

    /**
     * Perbarui penetapan beasiswa mahasiswa.
     */
    public function updateBeasiswa(int $id, array $data): MahasiswaBeasiswa
    {
        $item = MahasiswaBeasiswa::findOrFail($id);

        if (isset($data['beasiswa_id']) && (int)$data['beasiswa_id'] !== (int)$item->beasiswa_id) {
            $statusCheck = $data['status'] ?? $item->status;
            if ($statusCheck === 'aktif') {
                $existing = MahasiswaBeasiswa::where('mahasiswa_id', $item->mahasiswa_id)
                    ->where('beasiswa_id', $data['beasiswa_id'])
                    ->where('status', 'aktif')
                    ->where('id', '!=', $item->id)
                    ->first();

                if ($existing) {
                    throw ValidationException::withMessages([
                        'beasiswa_id' => ['Mahasiswa ini sudah terdaftar aktif pada program beasiswa yang dipilih.'],
                    ]);
                }
            }
        }

        $updateData = [];
        if (isset($data['beasiswa_id'])) {
            $updateData['beasiswa_id'] = $data['beasiswa_id'];
        }
        if (array_key_exists('berlaku_mulai', $data)) {
            $updateData['berlaku_mulai'] = $data['berlaku_mulai'];
        }
        if (array_key_exists('berlaku_sampai', $data)) {
            $updateData['berlaku_sampai'] = $data['berlaku_sampai'];
        }
        if (isset($data['status'])) {
            $updateData['status'] = $data['status'];
        }

        $item->update($updateData);

        return $item->load(['beasiswa', 'mahasiswa.programStudi']);
    }

    /**
     * Hapus penetapan beasiswa mahasiswa.
     */
    public function deleteBeasiswa(int $id): bool
    {
        $item = MahasiswaBeasiswa::findOrFail($id);
        return (bool)$item->delete();
    }

    /**
     * Ambil daftar opsi program beasiswa yang aktif (dikelola oleh Keuangan/SIKEU).
     */
    public function getBeasiswaOptions(): array
    {
        $items = Beasiswa::where('is_active', true)->orderBy('nama', 'asc')->get();

        return $items->map(function ($b) {
            $potonganText = $b->tipe_potongan === 'persen'
                ? rtrim(rtrim(number_format((float)$b->nilai_potongan, 2), '0'), '.') . '%'
                : 'Rp ' . number_format((float)$b->nilai_potongan, 0, ',', '.');

            return [
                'id' => $b->id,
                'kode' => $b->kode,
                'nama' => $b->nama,
                'sumber' => $b->sumber,
                'tipe_potongan' => $b->tipe_potongan,
                'nilai_potongan' => (float)$b->nilai_potongan,
                'potongan_text' => $potonganText,
                'deskripsi' => $b->deskripsi,
            ];
        })->toArray();
    }
}
