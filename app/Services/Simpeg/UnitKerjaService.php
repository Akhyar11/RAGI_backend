<?php

namespace App\Services\Simpeg;

use App\Models\Simpeg\UnitKerja;

class UnitKerjaService
{
    public function getTree()
    {
        return UnitKerja::with('children.children')->whereNull('induk_id')->get();
    }

    public function getAll()
    {
        return UnitKerja::with('parent')->get();
    }

    public function create(array $data)
    {
        return UnitKerja::create($data);
    }

    public function update(UnitKerja $unitKerja, array $data)
    {
        $unitKerja->update($data);
        return $unitKerja;
    }

    public function delete(UnitKerja $unitKerja)
    {
        if ($unitKerja->children()->exists()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'id' => ["Unit kerja '{$unitKerja->nama}' tidak dapat dihapus karena masih memiliki sub-unit kerja di bawahnya."],
            ]);
        }

        if ($unitKerja->pegawai()->exists()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'id' => ["Unit kerja '{$unitKerja->nama}' tidak dapat dihapus karena masih digunakan oleh data pegawai."],
            ]);
        }

        if ($unitKerja->jabatan()->exists()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'id' => ["Unit kerja '{$unitKerja->nama}' tidak dapat dihapus karena masih digunakan oleh data jabatan."],
            ]);
        }

        return $unitKerja->delete();
    }
}
