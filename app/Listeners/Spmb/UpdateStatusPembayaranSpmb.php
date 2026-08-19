<?php

namespace App\Listeners\Spmb;

use App\Events\Sikeu\PembayaranSpmbLunas;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use App\Models\Spmb\PendaftaranCalonMhs;

class UpdateStatusPembayaranSpmb implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(PembayaranSpmbLunas $event): void
    {
        $calonMahasiswaId = $event->calonMahasiswaId;

        $pendaftaran = PendaftaranCalonMhs::find($calonMahasiswaId);

        if ($pendaftaran) {
            $pendaftaran->update([
                'status_pembayaran' => 'lunas',
                'status' => 'submitted' // Change status so they can proceed
            ]);
        }
    }
}
