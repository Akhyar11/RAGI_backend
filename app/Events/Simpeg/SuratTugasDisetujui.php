<?php

namespace App\Events\Simpeg;

use App\Models\Simpeg\SuratTugas;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SuratTugasDisetujui
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly SuratTugas $suratTugas
    ) {}
}
