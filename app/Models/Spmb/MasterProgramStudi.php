<?php

namespace App\Models\Spmb;

use App\Models\Siakad\ProgramStudi;

class MasterProgramStudi extends ProgramStudi
{
    // Alias model untuk kompatibilitas modul SPMB (merujuk ke tabel siakad_program_studi)
    protected $table = 'siakad_program_studi';

    public function pendaftaranCalonMhs()
    {
        return $this->hasMany(PendaftaranCalonMhs::class, 'program_studi_id');
    }
}
