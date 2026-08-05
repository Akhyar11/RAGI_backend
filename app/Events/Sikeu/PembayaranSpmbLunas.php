<?php

namespace App\Events\Sikeu;

use App\Models\Sikeu\TagihanMahasiswa;
use App\Models\Sikeu\Pembayaran;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PembayaranSpmbLunas
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly mixed $calonMahasiswaId,
        public readonly ?TagihanMahasiswa $tagihan = null,
        public readonly ?Pembayaran $pembayaran = null
    ) {}
}
