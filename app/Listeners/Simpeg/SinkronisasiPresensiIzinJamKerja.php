<?php

namespace App\Listeners\Simpeg;

use App\Events\Simpeg\IzinJamKerjaDisetujui;
use App\Models\Simpeg\PresensiPegawai;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class SinkronisasiPresensiIzinJamKerja
{
    /**
     * Handle the event.
     */
    public function handle(IzinJamKerjaDisetujui $event): void
    {
        $izin = $event->izinJamKerja;
        $dateStr = Carbon::parse($izin->tanggal)->format('Y-m-d');
        $namaIzin = $izin->jenisIzin?->nama ?? 'Izin Jam Kerja';
        $kodeIzin = $izin->jenisIzin?->kode ?? '';

        $disposisi = "[Izin Resmi: {$namaIzin} ({$izin->jam_mulai} - {$izin->jam_selesai})] Alasan: {$izin->alasan}";

        try {
            $presensi = PresensiPegawai::where('pegawai_id', $izin->pegawai_id)
                ->where('tanggal', $dateStr)
                ->first();

            if ($presensi) {
                $updatedCatatan = $presensi->catatan
                    ? $presensi->catatan . "\n" . $disposisi
                    : $disposisi;

                $updatePayload = [
                    'catatan' => $updatedCatatan,
                    'is_approved_by_admin' => true,
                    'status' => 'approved',
                    'approved_by' => $izin->approved_by,
                    'approved_at' => now(),
                ];

                // Jika izin dispensasi keterlambatan, nol-kan menit keterlambatan
                if ($kodeIzin === 'TERLAMBAT') {
                    $updatePayload['late_minutes'] = 0;
                }

                $presensi->update($updatePayload);
            } else {
                // Buat rekaman presensi dispensasi awal
                PresensiPegawai::create([
                    'pegawai_id' => $izin->pegawai_id,
                    'tanggal' => $dateStr,
                    'status_kehadiran' => 'izin',
                    'catatan' => $disposisi,
                    'is_approved_by_admin' => true,
                    'status' => 'approved',
                    'approved_by' => $izin->approved_by,
                    'approved_at' => now(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::error("Gagal sinkronisasi izin jam kerja ke presensi untuk Pegawai ID {$izin->pegawai_id}: " . $e->getMessage());
        }
    }
}
