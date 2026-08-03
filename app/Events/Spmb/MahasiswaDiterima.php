<?php

namespace App\Events\Spmb;

use App\Models\Spmb\PendaftaranCalonMhs;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MahasiswaDiterima
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public PendaftaranCalonMhs $pendaftaran;

    /**
     * Create a new event instance.
     */
    public function __construct(PendaftaranCalonMhs $pendaftaran)
    {
        $this->pendaftaran = $pendaftaran;
    }
}
