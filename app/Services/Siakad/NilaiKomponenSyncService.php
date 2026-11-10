<?php

namespace App\Services\Siakad;

use App\Models\Siakad\KetercapaianCpmkMahasiswa;
use App\Models\Siakad\KomponenPenilaian;
use App\Models\Siakad\KrsDetail;
use App\Models\Siakad\NilaiKomponenMahasiswa;
use App\Models\Siakad\NilaiMahasiswa;
use App\Models\Siakad\SkalaNilai;
use Illuminate\Support\Collection;

class NilaiKomponenSyncService
{
    /**
     * Rebuild the final OBE grade and related CPMK attainment from source scores.
     */
    public function recalculateObeGrade(KrsDetail $detail, int $actorId): NilaiMahasiswa
    {
        $detail->loadMissing(['krs', 'kelas.tahunAkademik', 'kelas.mataKuliah.kurikulum', 'kelas.mataKuliah.cpmks']);
        $kelas = $detail->kelas;
        $mataKuliah = $kelas?->mataKuliah;
        $mode = $kelas?->tahunAkademik?->mode_penilaian ?? 'semi_obe';
        $total = 0.0;

        if ($mode === 'full_obe') {
            $cpmks = $mataKuliah?->cpmks ?? collect();
            $attainmentByCpmk = KetercapaianCpmkMahasiswa::query()
                ->where('krs_detail_id', $detail->id)
                ->whereIn('cpmk_id', $cpmks->pluck('id'))
                ->get()
                ->keyBy('cpmk_id');

            foreach ($cpmks as $cpmk) {
                $score = (float) ($attainmentByCpmk->get($cpmk->id)?->skor_ketercapaian ?? 0);
                $total += $score * (float) $cpmk->bobot_persentase / 100;
            }
        } else {
            $components = KomponenPenilaian::query()
                ->where('kelas_id', $kelas->id)
                ->get();
            $scoresByComponent = NilaiKomponenMahasiswa::query()
                ->where('krs_detail_id', $detail->id)
                ->whereIn('komponen_penilaian_id', $components->pluck('id'))
                ->get()
                ->keyBy('komponen_penilaian_id');

            foreach ($components as $component) {
                $total += (float) ($scoresByComponent->get($component->id)?->nilai_angka ?? 0)
                    * (float) $component->bobot / 100;
            }

            if ($mode === 'semi_obe') {
                $this->recalculateCpmkAttainment($detail, $mataKuliah?->cpmks ?? collect(), $components, $scoresByComponent, $total);
            }
        }

        $grade = NilaiMahasiswa::query()
            ->where('krs_detail_id', $detail->id)
            ->firstOrFail();
        [$letter, $weight] = $this->convertGrade($total, $mataKuliah?->kurikulum?->program_studi_id);

        $grade->update([
            'nilai_akhir' => round($total, 2),
            'nilai_huruf' => $letter,
            'bobot_mutu' => $weight,
            'is_final' => true,
            'diinput_oleh' => $actorId,
        ]);

        return $grade->fresh();
    }

    /**
     * @return array{0:string,1:float}
     */
    public function convertGrade(float $score, ?int $programStudiId = null): array
    {
        $scale = SkalaNilai::konversiNilai($score, $programStudiId);
        if ($scale) {
            return [$scale->nilai_huruf, (float) $scale->bobot_indeks];
        }

        return match (true) {
            $score >= 85 => ['A', 4.00],
            $score >= 80 => ['A-', 3.75],
            $score >= 75 => ['B+', 3.25],
            $score >= 70 => ['B', 3.00],
            $score >= 65 => ['B-', 2.75],
            $score >= 60 => ['C+', 2.25],
            $score >= 55 => ['C', 2.00],
            $score >= 40 => ['D', 1.00],
            default => ['E', 0.00],
        };
    }

    /**
     * @param Collection<int, \App\Models\Siakad\Cpmk> $cpmks
     * @param Collection<int, KomponenPenilaian> $components
     * @param Collection<int, NilaiKomponenMahasiswa> $scoresByComponent
     */
    private function recalculateCpmkAttainment(
        KrsDetail $detail,
        Collection $cpmks,
        Collection $components,
        Collection $scoresByComponent,
        float $total
    ): void {
        foreach ($cpmks as $cpmk) {
            $related = $components->where('cpmk_id', $cpmk->id);
            $totalWeight = (float) $related->sum('bobot');
            $score = $totalWeight > 0
                ? $related->sum(fn (KomponenPenilaian $component) =>
                    (float) ($scoresByComponent->get($component->id)?->nilai_angka ?? 0)
                    * (float) $component->bobot
                ) / $totalWeight
                : $total;
            $score = round($score, 2);

            KetercapaianCpmkMahasiswa::updateOrCreate(
                ['krs_detail_id' => $detail->id, 'cpmk_id' => $cpmk->id],
                [
                    'skor_ketercapaian' => $score,
                    'status_ketercapaian' => $score >= 65 ? 'tercapai' : 'belum_tercapai',
                ]
            );
        }
    }
}