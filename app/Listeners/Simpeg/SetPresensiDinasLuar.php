<?php

namespace App\Listeners\Simpeg;

use App\Events\Simpeg\SuratTugasDisetujui;
use App\Models\Simpeg\PresensiPegawai;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class SetPresensiDinasLuar
{
    /**
     * Handle the event.
     */
    public function handle(SuratTugasDisetujui $event): void
    {
        $suratTugas = $event->suratTugas;

        $pegawaiIds = collect([$suratTugas->pegawai_id]);
        $anggotaIds = $suratTugas->anggota()->pluck('pegawai_id')->toArray();
        $allPegawaiIds = $pegawaiIds->merge($anggotaIds)->unique()->filter()->values();

        $start = Carbon::parse($suratTugas->tanggal_mulai);
        $end = Carbon::parse($suratTugas->tanggal_selesai);

        $note = "Dinas Luar: {$suratTugas->nama_kegiatan} ({$suratTugas->lokasi_tujuan})";
        if (!empty($suratTugas->nomor_surat)) {
            $note .= " - No: {$suratTugas->nomor_surat}";
        }

        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $dateStr = $date->format('Y-m-d');

            foreach ($allPegawaiIds as $pegawaiId) {
                try {
                    PresensiPegawai::updateOrCreate(
                        [
                            'pegawai_id' => $pegawaiId,
                            'tanggal' => $dateStr,
                        ],
                        [
                            'status_kehadiran' => 'dinas',
                            'catatan' => $note,
                            'is_approved_by_admin' => true,
                            'status' => 'approved',
                            'approved_by' => $suratTugas->approved_by,
                            'approved_at' => now(),
                        ]
                    );
                } catch (\Throwable $e) {
                    Log::error("Gagal sinkronisasi presensi dinas luar untuk Pegawai ID {$pegawaiId} tanggal {$dateStr}: " . $e->getMessage());
                }
            }
        }
    }
}
