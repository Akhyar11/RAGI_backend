<?php

namespace App\Services\Sikeu;

use App\Models\Siakad\Mahasiswa;
use App\Models\Sikeu\TagihanMahasiswa;
use App\Models\Spmb\PendaftaranCalonMhs;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Otomatis menautkan tagihan migrasi/yatim (mahasiswa_id / calon_mahasiswa_id NULL)
 * ketika data Mahasiswa atau Calon Mahasiswa dibuat atau diperbarui NIM/No. Pendaftarannya.
 */
class LegacyTagihanLinkerService
{
    private function updateAndAuditTagihan(TagihanMahasiswa $tagihan, array $attributes): void
    {
        $oldValues = $tagihan->getOriginal();
        $tagihan->update($attributes);

        try {
            AuditLogService::record(
                module: 'SIKEU',
                action: 'update',
                tableName: 'sikeu_tagihan_mahasiswa',
                recordId: (int) $tagihan->id,
                oldValues: $oldValues,
                newValues: $tagihan->getChanges()
            );
        } catch (\Throwable $e) {
            Log::warning("Gagal mencatat audit log reconcile tagihan #{$tagihan->id}: " . $e->getMessage());
        }
    }

    public function linkForMahasiswa(Mahasiswa $mahasiswa): int
    {
        if (!Schema::hasTable('sikeu_tagihan_mahasiswa')) {
            return 0;
        }

        $linked = 0;

        try {
            $nim = trim((string) ($mahasiswa->nim ?? ''));
            $custIds = array_filter([$nim]);

            if (Schema::hasTable('sikeu_mig_mahasiswa_mapping')) {
                $nimLama = DB::table('sikeu_mig_mahasiswa_mapping')
                    ->where('mahasiswa_id_baru', $mahasiswa->id)
                    ->pluck('nim_lama')
                    ->filter()
                    ->all();
                $custIds = array_values(array_unique(array_merge($custIds, $nimLama)));
            }

            $calonIds = [];
            if ($nim !== '' && Schema::hasTable('spmb_pendaftaran_calon_mhs')) {
                $calonIds = PendaftaranCalonMhs::where('nim', $nim)->pluck('id')->all();
            }

            if (empty($custIds) && empty($calonIds)) {
                return 0;
            }

            $tagihans = TagihanMahasiswa::whereNull('mahasiswa_id')
                ->where(function ($q) use ($custIds, $calonIds, $nim) {
                    if (!empty($custIds)) {
                        $q->whereIn('h2h_custid', $custIds);
                    }
                    if (!empty($calonIds)) {
                        $q->orWhereIn('calon_mahasiswa_id', $calonIds);
                    }
                    if ($nim !== '') {
                        $q->orWhereHas('pembayarans', function ($p) use ($nim) {
                            $p->where('catatan', 'like', "%NIM: {$nim}%")
                                ->orWhere('catatan', 'like', "%NIM:{$nim}%");
                        });
                    }
                })
                ->get();

            foreach ($tagihans as $tagihan) {
                $this->updateAndAuditTagihan($tagihan, ['mahasiswa_id' => $mahasiswa->id]);
                $linked++;
            }
        } catch (\Throwable $e) {
            Log::warning("Auto-link tagihan untuk Mahasiswa #{$mahasiswa->id} dilewati: " . $e->getMessage());
        }

        return $linked;
    }

    public function linkForCalon(PendaftaranCalonMhs $calon): int
    {
        if (!Schema::hasTable('sikeu_tagihan_mahasiswa')) {
            return 0;
        }

        $linked = 0;

        try {
            $noReg = trim((string) ($calon->no_pendaftaran ?? ''));
            if ($noReg !== '') {
                $calonTagihans = TagihanMahasiswa::whereNull('mahasiswa_id')
                    ->whereNull('calon_mahasiswa_id')
                    ->where(function ($q) use ($noReg) {
                        $q->where('h2h_custid', $noReg)
                            ->orWhereHas('pembayarans', function ($p) use ($noReg) {
                                $p->where('catatan', 'like', "%NIM: {$noReg}%")
                                    ->orWhere('catatan', 'like', "%NIM:{$noReg}%");
                            });
                    })
                    ->get();

                foreach ($calonTagihans as $tagihan) {
                    $this->updateAndAuditTagihan($tagihan, ['calon_mahasiswa_id' => $calon->id]);
                    $linked++;
                }
            }

            // Jika calon sudah mendapat NIM resmi dan sudah ada di siakad_mahasiswa, tautkan sekalian.
            $nim = trim((string) ($calon->nim ?? ''));
            if ($nim !== '' && Schema::hasTable('siakad_mahasiswa')) {
                $mhsId = Mahasiswa::where('nim', $nim)->value('id');
                if ($mhsId) {
                    $mhsTagihans = TagihanMahasiswa::where('calon_mahasiswa_id', $calon->id)
                        ->whereNull('mahasiswa_id')
                        ->get();

                    foreach ($mhsTagihans as $tagihan) {
                        $this->updateAndAuditTagihan($tagihan, ['mahasiswa_id' => $mhsId]);
                        $linked++;
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning("Auto-link tagihan untuk CalonMhs #{$calon->id} dilewati: " . $e->getMessage());
        }

        return $linked;
    }
}
