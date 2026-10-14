<?php

namespace App\Listeners\Spmb;

use App\Events\Siakad\MahasiswaPindahProdi;
use App\Models\Siakad\Mahasiswa;
use Illuminate\Support\Facades\Log;

/**
 * Selaraskan prodi pada data pendaftaran SPMB asal mahasiswa.
 *
 * Riwayat pendaftaran (jalur, gelombang, berkas, status seleksi) TIDAK
 * diubah — hanya `program_studi_id` tujuan yang disinkronkan agar laporan
 * dan rekap SPMB selaras dengan SIAKAD. Jejak asal tercatat di audit log
 * (observer PendaftaranCalonMhs mencatat old/new values).
 *
 * Dijalankan sinkron (single update berindeks): laporan SPMB harus benar
 * pada request yang sama, tidak tergantung worker antrian.
 * Gagal sinkron TIDAK menggagalkan apply (sudah ter-commit) — hanya di-log
 * agar BAAK bisa menindaklanjuti manual.
 */
class SinkronProdiKeSpmb
{
    public function handle(MahasiswaPindahProdi $event): void
    {
        try {
            $mhs = Mahasiswa::find($event->mahasiswaId);
            if (!$mhs) {
                return;
            }
            $pendaftaran = $mhs->pendaftaranSpmb;
            if (!$pendaftaran) {
                return;
            }
            if ((int) $pendaftaran->program_studi_id === (int) $event->newProgramStudiId) {
                return;
            }
            $pendaftaran->update(['program_studi_id' => $event->newProgramStudiId]);

            Log::info("Prodi SPMB diselaraskan dari pindah prodi SIAKAD #{$event->pindahProdiId} (pendaftaran #{$pendaftaran->id})");
        } catch (\Throwable $e) {
            Log::error("Gagal sinkron prodi ke SPMB (SIAKAD #{$event->pindahProdiId}): " . $e->getMessage());
        }
    }
}
