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
        return $unitKerja->delete();
    }
}
