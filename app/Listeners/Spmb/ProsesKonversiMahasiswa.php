<?php

namespace App\Listeners\Spmb;

use App\Events\Spmb\MahasiswaDiterima;
use App\Services\Spmb\SpmbKonversiService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class ProsesKonversiMahasiswa implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'spmb'; // Menjalankan konversi di antrean khusus SPMB
    protected SpmbKonversiService $konversiService;

    /**
     * Create the event listener.
     */
    public function __construct(SpmbKonversiService $konversiService)
    {
        $this->konversiService = $konversiService;
    }

    /**
     * Handle the event.
     */
    public function handle(MahasiswaDiterima $event): void
    {
        try {
            Log::info("Mulai proses konversi untuk Pendaftar ID: {$event->pendaftaran->id}");
            
            // Konversi pendaftar menjadi mahasiswa
            $this->konversiService->prosesKonversi($event->pendaftaran);
            
            Log::info("Proses konversi selesai untuk Pendaftar ID: {$event->pendaftaran->id}");
        } catch (\Exception $e) {
            Log::error("Gagal melakukan konversi mahasiswa (Pendaftar ID {$event->pendaftaran->id}): " . $e->getMessage());
            $this->fail($e); // Menandai job sebagai failed
        }
    }
}
