<?php

namespace App\Services\Siakad;

use App\Models\Siakad\Dosen;
use App\Models\Siakad\Mahasiswa;
use App\Models\Siakad\PaCatatan;
use App\Models\Siakad\PaLaporan;
use App\Models\User;

class PaService
{
    /**
     * Hitung rekap bimbingan PA per dosen beserta komposisi status mahasiswa
     * dan agregasi catatan bimbingan dalam rentang tanggal.
     */
    public function getRekap(array $filters, ?User $user = null): array
    {
        $dosenId = $filters['dosen_id'] ?? null;
        $prodiId = $filters['program_studi_id'] ?? null;
        $dariTanggal = $filters['dari_tanggal'] ?? null;
        $sampaiTanggal = $filters['sampai_tanggal'] ?? null;

        $isPrivileged = $user && ($user->isSuperAdmin() || $user->hasRole('admin') || $user->hasRole('kaprodi') || $user->hasRole('wakil_prodi'));
        $ownDosen = Dosen::where('user_id', $user?->id)->first();

        $dosenQuery = Dosen::with('programStudi');
        if (!$isPrivileged) {
            $dosenQuery->where('id', $ownDosen?->id ?? -1);
        } elseif (!empty($dosenId)) {
            $dosenQuery->where('id', $dosenId);
        }

        if (!empty($prodiId)) {
            $dosenQuery->where('program_studi_id', $prodiId);
        }

        $dosens = $dosenQuery->orderBy('nama_lengkap')->get();

        $catatanDalamRentang = function ($query) use ($dariTanggal, $sampaiTanggal) {
            if (!$dariTanggal && !$sampaiTanggal) {
                return $query;
            }
            return $query->where(function ($w) use ($dariTanggal, $sampaiTanggal) {
                $w->where(function ($x) use ($dariTanggal, $sampaiTanggal) {
                    $x->whereNotNull('tanggal_bimbingan');
                    if ($dariTanggal) {
                        $x->whereDate('tanggal_bimbingan', '>=', $dariTanggal);
                    }
                    if ($sampaiTanggal) {
                        $x->whereDate('tanggal_bimbingan', '<=', $sampaiTanggal);
                    }
                })->orWhere(function ($x) use ($dariTanggal, $sampaiTanggal) {
                    $x->whereNull('tanggal_bimbingan');
                    if ($dariTanggal) {
                        $x->whereDate('created_at', '>=', $dariTanggal);
                    }
                    if ($sampaiTanggal) {
                        $x->whereDate('created_at', '<=', $sampaiTanggal);
                    }
                });
            });
        };

        return $dosens->map(function ($d) use ($catatanDalamRentang) {
            $base = Mahasiswa::where('dosen_wali_id', $d->id);
            $counts = [
                'aktif' => (clone $base)->where('status', 'aktif')->count(),
                'cuti' => (clone $base)->where('status', 'cuti')->count(),
                'mangkir' => (clone $base)->where('status', 'mangkir')->count(),
                'keluar' => (clone $base)->whereIn('status', ['dropout'])->count(),
                'lulus' => (clone $base)->where('status', 'lulus')->count(),
            ];
            $counts['total'] = array_sum($counts);

            $khusus = $catatanDalamRentang(
                PaCatatan::where('dosen_id', $d->id)
                    ->where('butuh_penanganan_khusus', true)
                    ->where('status_tindak_lanjut', '!=', 'selesai')
            )->count();
            $totalCatatan = $catatanDalamRentang(PaCatatan::where('dosen_id', $d->id))->count();
            $terakhir = $catatanDalamRentang(PaCatatan::where('dosen_id', $d->id))->latest('id')->first();
            $laporan = PaLaporan::where('dosen_id', $d->id)->latest('id')->first();

            return [
                'dosen_id' => $d->id,
                'nama_lengkap' => $d->nama_lengkap,
                'nidn' => $d->nidn,
                'program_studi' => $d->programStudi?->nama,
                'komposisi' => $counts,
                'butuh_khusus_aktif' => $khusus,
                'total_bimbingan' => $totalCatatan,
                'terakhir_bimbingan_at' => $terakhir?->tanggal_bimbingan?->format('Y-m-d H:i:s') ?? ($terakhir?->created_at?->format('Y-m-d H:i:s') ?? ($terakhir?->tanggal_bimbingan ?? $terakhir?->created_at)),
                'belum_bimbingan' => $totalCatatan === 0 && $counts['total'] > 0,
                'laporan_terakhir' => $laporan ? [
                    'id' => $laporan->id,
                    'tahun_akademik_id' => $laporan->tahun_akademik_id,
                    'status' => $laporan->status,
                    'updated_at' => $laporan->updated_at,
                ] : null,
            ];
        })->values()->toArray();
    }
}
