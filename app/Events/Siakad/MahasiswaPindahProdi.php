<?php

namespace App\Events\Siakad;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Didispatch SIAKAD tepat setelah pengajuan pindah prodi diterapkan
 * (program_studi_id mahasiswa sudah berubah). Didengar SIKEU untuk
 * mencatat konteks penagihan efektif (queued, tidak block response).
 */
class MahasiswaPindahProdi
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly int $pindahProdiId,
        public readonly int $mahasiswaId,
        public readonly ?int $oldProgramStudiId,
        public readonly int $newProgramStudiId,
        public readonly ?int $tahunAkademikId,
        public readonly ?int $semesterEfektif,
        public readonly ?string $jenisPendaftaranBaru,
        public readonly ?string $referensiSurat,
        public readonly ?int $appliedBy,
    ) {}
}
