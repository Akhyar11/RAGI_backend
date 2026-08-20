<?php

namespace App\Services\Siakad;

use App\Models\Siakad\Krs;
use App\Models\Siakad\NilaiMahasiswa;
use App\Models\Siakad\Khs;
use Illuminate\Support\Facades\DB;

class SiakadAkademikService
{
    /**
     * Hitung ulang IPK dan SKS kumulatif mahasiswa
     */
    public function hitungKhsDanIpk($mahasiswaId, $tahunAkademikId)
    {
        return DB::transaction(function () use ($mahasiswaId, $tahunAkademikId) {
            // Ambil semua KRS yang disetujui/dikunci di semester ini
            $krs = Krs::with(['krsDetails.nilaiMahasiswa', 'krsDetails.kelas.mataKuliah'])
                ->where('mahasiswa_id', $mahasiswaId)
                ->where('tahun_akademik_id', $tahunAkademikId)
                ->whereIn('status', ['disetujui', 'dikunci'])
                ->first();

            if (!$krs) return null;

            $totalSksSemester = 0;
            $totalMutuSemester = 0;

            foreach ($krs->krsDetails as $detail) {
                if ($detail->status != 'aktif') continue;
                
                $sks = $detail->kelas->mataKuliah->total_sks;
                $totalSksSemester += $sks;

                if ($detail->nilaiMahasiswa && $detail->nilaiMahasiswa->is_final) {
                    $totalMutuSemester += ($sks * $detail->nilaiMahasiswa->bobot_mutu);
                }
            }

            $ips = $totalSksSemester > 0 ? ($totalMutuSemester / $totalSksSemester) : 0;

            // Simpan atau update KHS semester ini
            $khs = Khs::updateOrCreate(
                ['mahasiswa_id' => $mahasiswaId, 'tahun_akademik_id' => $tahunAkademikId],
                ['ips' => $ips, 'total_sks_semester' => $totalSksSemester]
            );

            // Hitung IPK Keseluruhan (Sederhana: Rata-rata dari semua KRS yang memiliki nilai final)
            // Di implementasi nyata, perlu mempertimbangkan mengulang matakuliah (ambil nilai tertinggi)
            $allFinalKrs = Krs::with(['krsDetails.nilaiMahasiswa', 'krsDetails.kelas.mataKuliah'])
                ->where('mahasiswa_id', $mahasiswaId)
                ->whereIn('status', ['disetujui', 'dikunci'])
                ->get();

            $totalSksKumulatif = 0;
            $totalMutuKumulatif = 0;

            // Mapping mk_id ke nilai tertinggi jika mahasiswa mengulang
            $nilaiTertinggi = [];

            foreach ($allFinalKrs as $krsRecord) {
                foreach ($krsRecord->krsDetails as $detail) {
                    if ($detail->status != 'aktif' || !$detail->nilaiMahasiswa || !$detail->nilaiMahasiswa->is_final) continue;
                    
                    $mkId = $detail->kelas->mata_kuliah_id;
                    $sks = $detail->kelas->mataKuliah->total_sks;
                    $mutu = $detail->nilaiMahasiswa->bobot_mutu;

                    if (!isset($nilaiTertinggi[$mkId]) || $mutu > $nilaiTertinggi[$mkId]['mutu']) {
                        $nilaiTertinggi[$mkId] = ['sks' => $sks, 'mutu' => $mutu];
                    }
                }
            }

            foreach ($nilaiTertinggi as $mk) {
                $totalSksKumulatif += $mk['sks'];
                $totalMutuKumulatif += ($mk['sks'] * $mk['mutu']);
            }

            $ipk = $totalSksKumulatif > 0 ? ($totalMutuKumulatif / $totalSksKumulatif) : 0;

            $khs->sks_kumulatif = $totalSksKumulatif;
            $khs->ipk = $ipk;
            $khs->save();

            return $khs;
        });
    }
}
