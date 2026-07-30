<?php

namespace App\Services\Simpeg;

use App\Models\Simpeg\Pegawai;

class PegawaiService
{
    public function getFiltered(array $filters = [])
    {
        $query = Pegawai::with(['user', 'unitKerja', 'riwayatJabatan.jabatan', 'riwayatPendidikan']);

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('nama_lengkap', 'like', "%{$search}%")
                  ->orWhere('nip', 'like', "%{$search}%")
                  ->orWhere('nik', 'like', "%{$search}%");
            });
        }

        if (!empty($filters['unit_kerja_id'])) {
            $query->where('unit_kerja_id', $filters['unit_kerja_id']);
        }

        if (!empty($filters['jenis_pegawai'])) {
            $query->where('jenis_pegawai', $filters['jenis_pegawai']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->paginate($filters['per_page'] ?? 15);
    }

    public function create(array $data)
    {
        return Pegawai::create($data);
    }

    public function update(Pegawai $pegawai, array $data)
    {
        $pegawai->update($data);
        return $pegawai;
    }

    public function delete(Pegawai $pegawai)
    {
        return $pegawai->delete();
    }
}
