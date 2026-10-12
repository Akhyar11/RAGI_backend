<?php

namespace App\Listeners\Sikeu;

use App\Events\Siakad\MahasiswaPindahProdi;
use App\Models\Sikeu\MahasiswaTipeTagihan;
use App\Models\Sikeu\PerubahanProdi as ProyeksiPerubahanProdi;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Proyeksi keuangan atas mutasi prodi milik SIAKAD.
 *
 * Listener ini TIDAK mengubah data akademik. Ia hanya mencatat konteks
 * penagihan efektif (tahun akademik + semester) + versioning tipe tagihan
 * agar penagihan berikutnya mengikuti prodi tujuan. Tagihan lama
 * tidak disentuh; redistribusi alokasi lewat menu Koreksi Alokasi.
 */
class CatatKonteksPenagihanPindahProdi implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'sikeu';

    public function handle(MahasiswaPindahProdi $event): void
    {
        try {
            DB::transaction(function () use ($event) {
                // Idempoten: satu baris proyeksi per pengajuan SIAKAD.
                $sudah = ProyeksiPerubahanProdi::where('referensi_surat', 'SIAKAD-PINDAH-' . $event->pindahProdiId)->first();
                if ($sudah && $sudah->status === 'applied') {
                    return;
                }

                $mhs = \App\Models\Siakad\Mahasiswa::find($event->mahasiswaId);

                ProyeksiPerubahanProdi::updateOrCreate(
                    ['referensi_surat' => 'SIAKAD-PINDAH-' . $event->pindahProdiId],
                    [
                        'mahasiswa_id' => $event->mahasiswaId,
                        'old_program_studi_id' => $event->oldProgramStudiId,
                        'new_program_studi_id' => $event->newProgramStudiId,
                        'tahun_akademik_id' => $event->tahunAkademikId,
                        'semester_efektif' => $event->semesterEfektif,
                        'jenis_pendaftaran_baru' => $event->jenisPendaftaranBaru,
                        'alasan' => 'Proyeksi otomatis dari SIAKAD (pengajuan #' . $event->pindahProdiId . ')',
                        'status' => 'applied',
                        'applied_by' => $event->appliedBy,
                        'applied_at' => now(),
                    ]
                );

                $tipe = MahasiswaTipeTagihan::where('mahasiswa_id', $event->mahasiswaId)->first();
                if ($tipe) {
                    $oldVersionListener = (int) ($tipe->version ?? 1);
                    $tipe->update([
                        'status_pendaftaran' => $event->jenisPendaftaranBaru ?? $tipe->status_pendaftaran,
                        'catatan_perubahan' => trim(($tipe->catatan_perubahan ? $tipe->catatan_perubahan . ' | ' : '') . "Pindah prodi (SIAKAD #{$event->pindahProdiId}) efektif TA {$event->tahunAkademikId}/Smt {$event->semesterEfektif}"),
                        'is_active' => true,
                        'version' => $oldVersionListener + 1,
                        'effective_tahun_akademik_id' => $event->tahunAkademikId,
                        'effective_semester' => $event->semesterEfektif,
                    ]);

                    try {
                        \App\Services\AuditLogService::record(
                            module: 'SIKEU',
                            action: 'update',
                            tableName: 'sikeu_mahasiswa_tipe_tagihan',
                            recordId: $tipe->id,
                            oldValues: ['version' => $oldVersionListener],
                            newValues: ['version' => $oldVersionListener + 1, 'sumber' => 'event_siakad_pindah_prodi'],
                        );
                    } catch (\Throwable $e) {
                        Log::warning('Gagal mencatat audit log proyeksi tipe tagihan: ' . $e->getMessage());
                    }
                }

                if ($mhs && $event->jenisPendaftaranBaru && method_exists($mhs, 'getAttribute')) {
                    // Sinkronisasi ringan: kelompok_ukt/jalur mengikuti tipe tagihan bila tersedia.
                    $tipeFresh = MahasiswaTipeTagihan::where('mahasiswa_id', $event->mahasiswaId)->first();
                    if ($tipeFresh) {
                        $update = [];
                        if (\Illuminate\Support\Facades\Schema::hasColumn('siakad_mahasiswa', 'kelompok_ukt')) {
                            $update['kelompok_ukt'] = $tipeFresh->kelompok_ukt;
                        }
                        if (!empty($update)) {
                            $mhs->update($update);
                        }
                    }
                }
            });

            Log::info("Proyeksi penagihan pindah prodi tercatat (SIAKAD #{$event->pindahProdiId})");
        } catch (\Throwable $e) {
            Log::error("Gagal mencatat proyeksi pindah prodi (SIAKAD #{$event->pindahProdiId}): " . $e->getMessage());
            $this->fail($e);
        }
    }
}
