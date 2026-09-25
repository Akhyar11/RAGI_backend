<?php

namespace App\Listeners\Spmb;

use App\Events\Sikeu\PembayaranSpmbLunas;
use App\Models\Spmb\PendaftaranCalonMhs;
use App\Services\Spmb\SpmbPendaftaranService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

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
            // Jangan turunkan status yang sudah lebih lanjut (verified/lulus_administrasi).
            $newStatus = $pendaftaran->status === PendaftaranCalonMhs::STATUS_DRAFT
                ? PendaftaranCalonMhs::STATUS_SUBMITTED
                : $pendaftaran->status;

            $pendaftaran->update([
                'status_pembayaran' => 'lunas',
                'status' => $newStatus,
            ]);

            // Buat urutan alur pendaftaran (Progress Tracker) untuk mahasiswa ini
            $this->pendaftaranService->generateProgressAlur($pendaftaran);
        }
    }
}
