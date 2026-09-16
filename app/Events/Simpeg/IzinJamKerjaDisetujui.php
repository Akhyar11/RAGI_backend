<?php

namespace App\Events\Simpeg;

use App\Models\Simpeg\IzinJamKerja;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class IzinJamKerjaDisetujui
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly IzinJamKerja $izinJamKerja
    ) {}
}
