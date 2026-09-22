<?php

namespace App\Http\Controllers\API\Siakad;

use App\Http\Controllers\Controller;
use App\Models\Siakad\Kelulusan;
use App\Models\Siakad\Mahasiswa;
use App\Models\Siakad\Krs;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class KelulusanController extends Controller
{
    public function index(Request $request)
    {
        $query = Kelulusan::with(['mahasiswa.programStudi']);
        if ($request->filled('search')) {
            $s = $request->search;
            $query->whereHas('mahasiswa', fn($q) => $q->where('nama_lengkap', 'like', "%{$s}%")->orWhere('nim', 'like', "%{$s}%"));
        }
        $perPage = min(100, $request->integer('per_page', 15));
        $data = $query->orderBy('created_at', 'desc')->paginate($perPage);
        return response()->json(['status' => 'success', 'data' => $data->items(),
            'meta' => ['current_page' => $data->currentPage(), 'per_page' => $data->perPage(), 'total' => $data->total(), 'last_page' => $data->lastPage()]]);
    }

    public function cekSyarat($mahasiswaId)
    {
        $mhs = Mahasiswa::with(['programStudi', 'khs'])->findOrFail($mahasiswaId);
        $ipk = (float) ($mhs->ipk ?? 0);
        $totalSks = (int) ($mhs->khs()->sum('sks_kumulatif') ?: 0);
        // Ambil SKS lulus best-grade via KHS terakhir bila ada
        $khsTerakhir = $mhs->khs()->latest('id')->first();
        if ($khsTerakhir) {
            $totalSks = max($totalSks, (int) $khsTerakhir->sks_kumulatif);
        }
        $syaratSks = 144;
        return response()->json(['status' => 'success', 'data' => [
            'mahasiswa' => $mhs, 'ipk' => $ipk, 'total_sks' => $totalSks,
            'syarat_sks' => $syaratSks, 'syarat_ipk' => 2.00,
            'memenuhi' => $ipk >= 2.00 && $totalSks >= $syaratSks,
        ]]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'mahasiswa_id' => 'required|exists:siakad_mahasiswa,id',
            'tahun_akademik_id' => 'required|exists:spmb_master_tahun_akademik,id',
            'tanggal_sidang' => 'nullable|date',
            'ipk_akhir' => 'required|numeric|min:0|max:4',
            'total_sks' => 'required|integer|min:100',
            'masa_studi_semester' => 'required|integer|min:1|max:28',
            'predikat' => 'nullable|in:memuaskan,sangat_memuaskan,cum_laude,dengan_pujian',
            'nomor_ijazah' => 'nullable|string|max:100|unique:siakad_kelulusan,nomor_ijazah',
            'tanggal_ijazah' => 'nullable|date',
        ]);
        return DB::transaction(function () use ($validated, $request) {
            if ((float) $validated['ipk_akhir'] < 2.00 || (int) $validated['total_sks'] < 144) {
                return response()->json(['status' => 'error', 'message' => 'Syarat yudisium belum terpenuhi (min IPK 2.00 & 144 SKS).'], 422);
            }
            $lulus = Kelulusan::create($validated);
            Mahasiswa::where('id', $validated['mahasiswa_id'])->update(['status' => 'lulus']);
            \App\Services\AuditLogService::record(module: 'SIAKAD', action: 'approve', tableName: 'siakad_kelulusan', recordId: $lulus->id, request: $request);
            return response()->json(['status' => 'success', 'message' => 'Yudisium/kelulusan berhasil ditetapkan.', 'data' => $lulus], 201);
        });
    }

    // Sensing DO/mangkir sederhana untuk dashboard BAAK
    public function sensing(Request $request)
    {
        $mangkir = Mahasiswa::where('status', 'mangkir')->count();
        $cuti = Mahasiswa::where('status', 'cuti')->count();
        $dropout = Mahasiswa::where('status', 'dropout')->count();
        // Mahasiswa aktif tanpa KRS di TA aktif = indikasi mangkir
        $taAktif = \App\Models\Spmb\MasterTahunAkademik::where('is_active', true)->first();
        $tanpaKrs = 0;
        if ($taAktif) {
            $tanpaKrs = Mahasiswa::where('status', 'aktif')
                ->whereNotExists(fn($q) => $q->select(DB::raw(1))->from('siakad_krs')->whereColumn('siakad_krs.mahasiswa_id', 'siakad_mahasiswa.id')->where('tahun_akademik_id', $taAktif->id))
                ->count();
        }
        return response()->json(['status' => 'success', 'data' => [
            'mangkir' => $mangkir, 'cuti' => $cuti, 'dropout' => $dropout,
            'aktif_tanpa_krs_TA_aktif' => $tanpaKrs, 'tahun_akademik_aktif' => $taAktif,
        ]]);
    }
}
