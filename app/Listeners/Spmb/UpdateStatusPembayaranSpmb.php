<?php

namespace App\Listeners\Spmb;

use App\Events\Sikeu\PembayaranSpmbLunas;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use App\Models\Spmb\PendaftaranCalonMhs;
use App\Services\Spmb\SpmbPendaftaranService;

class UpdateStatusPembayaranSpmb implements ShouldQueue
{
    use InteractsWithQueue;

    protected SpmbPendaftaranService $pendaftaranService;

    /**
     * Create the event listener.
     */
    public function __construct(SpmbPendaftaranService $pendaftaranService)
    {
        $this->pendaftaranService = $pendaftaranService;
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

            // Buat urutan alur pendaftaran (Progress Tracker) untuk mahasiswa ini
            $this->pendaftaranService->generateProgressAlur($pendaftaran);
        }
    }
}
