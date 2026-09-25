<?php

namespace App\Services\Simpeg;

use App\Models\Simpeg\JabatanFungsionalAkademik;
use App\Models\Simpeg\MasterGolonganPangkat;
use Illuminate\Support\Facades\DB;

class JabatanFungsionalService
{
    public function create(array $data): JabatanFungsionalAkademik
    {
        return DB::transaction(function () use ($data) {
            if (empty($data['golongan_pangkat_id']) && !empty($data['golongan'])) {
                $data['golongan_pangkat_id'] = MasterGolonganPangkat::where('kode', $data['golongan'])->value('id');
            }

            return JabatanFungsionalAkademik::create($data);
        });
    }
}
