<?php

namespace App\Models\Spmb;

use App\Models\Siakad\TahunAkademik;

class MasterTahunAkademik extends TahunAkademik
{
    // Alias model untuk kompatibilitas modul SPMB (merujuk ke tabel siakad_tahun_akademik)
    protected $table = 'siakad_tahun_akademik';
}
