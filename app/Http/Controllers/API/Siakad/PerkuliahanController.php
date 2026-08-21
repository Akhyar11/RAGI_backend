<?php

namespace App\Http\Controllers\Api\Siakad;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Siakad\Kelas;
use App\Models\Siakad\Dosen;
use App\Models\Siakad\Mahasiswa;
use App\Models\Siakad\DosenPengampu;
use App\Models\Siakad\Krs;
use App\Models\Siakad\KrsDetail;
use App\Models\Siakad\NilaiMahasiswa;
use App\Models\Siakad\Khs;
use App\Models\Siakad\MataKuliah;
use App\Models\Siakad\KonversiTransferDetail;
use App\Models\Spmb\MasterTahunAkademik;
use App\Services\Siakad\SiakadAkademikService;
use Illuminate\Support\Facades\DB;

class PerkuliahanController extends Controller
{
    protected SiakadAkademikService $akademikService;

    public function __construct(SiakadAkademikService $akademikService)
    {
        $this->akademikService = $akademikService;
    }

    public function listKelas(Request $request)
    {
        $user = $request->user();
        $taId = $request->input('tahun_akademik_id') ?? MasterTahunAkademik::where('is_active', true)->value('id');

        $query = Kelas::with(['mataKuliah', 'ruangan.gedung', 'programStudi', 'dosenPengampu.dosen'])
            ->when($taId, fn($q) => $q->where('tahun_akademik_id', $taId));

        // Jika user adalah dosen, filter jadwal mengajar mereka
        if ($request->boolean('my_teaching_only') && $user) {
            $dosen = Dosen::where('user_id', $user->id)->first();
            if ($dosen) {
                $query->whereHas('dosenPengampu', fn($dp) => $dp->where('dosen_id', $dosen->id));
            }
        }

        // Jika user adalah mahasiswa, filter kelas yang diambil
        if ($request->boolean('my_enrolled_only') && $user) {
            $mhs = Mahasiswa::where('user_id', $user->id)->first();
            if ($mhs) {
                $query->whereHas('krsDetails.krs', fn($kq) => $kq->where('mahasiswa_id', $mhs->id));
            }
        }

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('nama_kelas', 'like', "%{$s}%")
                  ->orWhere('kode_kelas', 'like', "%{$s}%")
                  ->orWhereHas('mataKuliah', fn($mq) => $mq->where('nama', 'like', "%{$s}%"));
            });
        }

        if ($request->filled('hari')) {
            $query->where('hari', $request->hari);
        }

        if ($request->filled('program_studi_id')) {
            $prodiId = $request->program_studi_id;
            $query->where(function ($q) use ($prodiId) {
                $q->where('program_studi_id', $prodiId)
                  ->orWhereHas('mataKuliah.kurikulum', fn($kq) => $kq->where('program_studi_id', $prodiId));
            });
        }

        $data = $query->paginate($request->integer('per_page', 25));

        return response()->json([
            'status' => 'success',
            'data' => $data->items(),
            'meta' => [
                'current_page' => $data->currentPage(),
                'per_page' => $data->perPage(),
                'total' => $data->total(),
            ]
        ]);
    }

    public function storeKelas(Request $request)
    {
        $request->validate([
            'mata_kuliah_id' => 'required|exists:siakad_mata_kuliah,id',
            'tahun_akademik_id' => 'required|exists:master_tahun_akademik,id',
            'program_studi_id' => 'required|exists:master_program_studi,id',
            'ruangan_id' => 'nullable|exists:ruangan,id',
            'dosen_id' => 'nullable|exists:siakad_dosen,id',
            'team_teaching_dosen_ids' => 'nullable|array',
            'team_teaching_dosen_ids.*' => 'exists:siakad_dosen,id',
            'kode_kelas' => 'required|string|max:20',
            'nama_kelas' => 'required|string|max:255',
            'kapasitas' => 'required|integer|min:1',
            'kuota_krs' => 'required|integer|min:1',
            'hari' => 'required|in:senin,selasa,rabu,kamis,jumat,sabtu,minggu',
            'jam_mulai' => 'required|string',
            'jam_selesai' => 'required|string',
        ]);

        return DB::transaction(function () use ($request) {
            $mk = MataKuliah::find($request->mata_kuliah_id);

            $kelas = Kelas::create([
                'mata_kuliah_id' => $request->mata_kuliah_id,
                'tahun_akademik_id' => $request->tahun_akademik_id,
                'program_studi_id' => $request->program_studi_id,
                'ruangan_id' => $request->ruangan_id,
                'kode_kelas' => $request->kode_kelas,
                'nama_kelas' => $request->nama_kelas,
                'kapasitas' => $request->kapasitas,
                'kuota_krs' => $request->kuota_krs,
                'hari' => $request->hari,
                'jam_mulai' => $request->jam_mulai,
                'jam_selesai' => $request->jam_selesai,
                'status' => 'aktif',
            ]);

            // Dosen Pengampu Utama (yang dilaporkan resmi ke Feeder)
            if ($request->filled('dosen_id')) {
                DosenPengampu::create([
                    'kelas_id' => $kelas->id,
                    'dosen_id' => $request->dosen_id,
                    'peran' => 'pengampu_utama',
                    'sks_substansi_total' => $mk?->total_sks ?? 3,
                    'rencana_tatap_muka' => 16,
                ]);
            }

            // Dosen Team Teaching (Anggota Pengajar Tambahan)
            if ($request->filled('team_teaching_dosen_ids') && is_array($request->team_teaching_dosen_ids)) {
                foreach ($request->team_teaching_dosen_ids as $ttDosenId) {
                    if ($ttDosenId && $ttDosenId != $request->dosen_id) {
                        DosenPengampu::create([
                            'kelas_id' => $kelas->id,
                            'dosen_id' => $ttDosenId,
                            'peran' => 'anggota_team_teaching',
                            'sks_substansi_total' => $mk?->total_sks ?? 3,
                            'rencana_tatap_muka' => 16,
                        ]);
                    }
                }
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Kelas perkuliahan berhasil dibuat dengan tim pengajar terdaftar',
                'data' => $kelas->load(['mataKuliah', 'ruangan', 'dosenPengampu.dosen'])
            ], 201);
        });
    }

    public function updateKelas(Request $request, $id)
    {
        $kelas = Kelas::findOrFail($id);
        $request->validate([
            'nama_kelas' => 'required|string|max:255',
            'ruangan_id' => 'nullable|exists:ruangan,id',
            'dosen_id' => 'nullable|exists:siakad_dosen,id',
            'team_teaching_dosen_ids' => 'nullable|array',
            'team_teaching_dosen_ids.*' => 'exists:siakad_dosen,id',
            'kapasitas' => 'required|integer|min:1',
            'kuota_krs' => 'required|integer|min:1',
            'hari' => 'required|in:senin,selasa,rabu,kamis,jumat,sabtu,minggu',
            'jam_mulai' => 'required|string',
            'jam_selesai' => 'required|string',
        ]);

        return DB::transaction(function () use ($request, $kelas) {
            $kelas->update($request->only([
                'nama_kelas',
                'ruangan_id',
                'kapasitas',
                'kuota_krs',
                'hari',
                'jam_mulai',
                'jam_selesai',
            ]));

            if ($request->has('dosen_id') || $request->has('team_teaching_dosen_ids')) {
                DosenPengampu::where('kelas_id', $kelas->id)->delete();
                $mk = $kelas->mataKuliah;

                if ($request->filled('dosen_id')) {
                    DosenPengampu::create([
                        'kelas_id' => $kelas->id,
                        'dosen_id' => $request->dosen_id,
                        'peran' => 'pengampu_utama',
                        'sks_substansi_total' => $mk?->total_sks ?? 3,
                        'rencana_tatap_muka' => 16,
                    ]);
                }

                if ($request->filled('team_teaching_dosen_ids') && is_array($request->team_teaching_dosen_ids)) {
                    foreach ($request->team_teaching_dosen_ids as $ttDosenId) {
                        if ($ttDosenId && $ttDosenId != $request->dosen_id) {
                            DosenPengampu::create([
                                'kelas_id' => $kelas->id,
                                'dosen_id' => $ttDosenId,
                                'peran' => 'anggota_team_teaching',
                                'sks_substansi_total' => $mk?->total_sks ?? 3,
                                'rencana_tatap_muka' => 16,
                            ]);
                        }
                    }
                }
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Kelas perkuliahan berhasil diperbarui',
                'data' => $kelas->load(['mataKuliah', 'ruangan', 'dosenPengampu.dosen'])
            ]);
        });
    }

    public function destroyKelas($id)
    {
        $kelas = Kelas::findOrFail($id);
        $kelas->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Kelas perkuliahan berhasil dihapus'
        ]);
    }

    // --- KRS LIST & FILTER ---
    public function listKrs(Request $request)
    {
        $user = $request->user();
        $query = Krs::with([
            'mahasiswa.programStudi',
            'mahasiswa.dosenWali',
            'dosenPembimbing',
            'tahunAkademik',
            'krsDetails.kelas.mataKuliah',
            'krsDetails.kelas.ruangan',
            'krsDetails.kelas.dosenPengampu.dosen',
            'krsDetails.nilaiMahasiswa'
        ]);

        // Cek jika login sebagai Mahasiswa
        $mhs = $user ? Mahasiswa::where('user_id', $user->id)->first() : null;
        if ($mhs && !$user->roles()->whereIn('slug', ['superadmin', 'admin'])->exists()) {
            $query->where('mahasiswa_id', $mhs->id);
        }

        // Cek jika login sebagai Dosen Wali
        $dosen = $user ? Dosen::where('user_id', $user->id)->first() : null;
        if ($dosen && !$user->roles()->whereIn('slug', ['superadmin', 'admin'])->exists() && $request->boolean('advisees_only')) {
            $query->whereHas('mahasiswa', fn($mq) => $mq->where('dosen_wali_id', $dosen->id));
        }

        if ($request->filled('search')) {
            $s = $request->search;
            $query->whereHas('mahasiswa', fn($mq) => 
                $mq->where('nama_lengkap', 'like', "%{$s}%")
                   ->orWhere('nim', 'like', "%{$s}%")
                   ->orWhereHas('dosenWali', fn($dq) => $dq->where('nama_lengkap', 'like', "%{$s}%"))
            );
        }

        if ($request->filled('mahasiswa_id')) {
            $query->where('mahasiswa_id', $request->mahasiswa_id);
        }

        if ($request->filled('program_studi_id')) {
            $query->whereHas('mahasiswa', fn($mq) => $mq->where('program_studi_id', $request->program_studi_id));
        }

        if ($request->filled('angkatan')) {
            $query->whereHas('mahasiswa', fn($mq) => $mq->where('angkatan', $request->angkatan));
        }

        if ($request->filled('tahun_akademik_id')) {
            $query->where('tahun_akademik_id', $request->tahun_akademik_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('status_spp')) {
            if ($request->status_spp === 'lunas') {
                $query->where('locked_by_keuangan', false);
            } elseif ($request->status_spp === 'belum_lunas') {
                $query->where('locked_by_keuangan', true);
            }
        }

        $data = $query->orderBy('created_at', 'desc')->paginate($request->integer('per_page', 25));

        return response()->json([
            'status' => 'success',
            'data' => $data->items(),
            'meta' => [
                'current_page' => $data->currentPage(),
                'per_page' => $data->perPage(),
                'total' => $data->total(),
            ]
        ]);
    }

    public function bulkApproveKrs(Request $request)
    {
        $request->validate([
            'krs_ids' => 'required|array|min:1',
            'krs_ids.*' => 'exists:siakad_krs,id',
        ]);

        $approvedCount = 0;
        $skippedCount = 0;

        $krsList = Krs::whereIn('id', $request->krs_ids)->get();

        foreach ($krsList as $krs) {
            if ($krs->locked_by_keuangan) {
                $skippedCount++;
                continue;
            }
            $krs->status = 'disetujui';
            $krs->save();
            $approvedCount++;
        }

        return response()->json([
            'status' => 'success',
            'message' => "Berhasil menyetujui {$approvedCount} KRS mahasiswa." . ($skippedCount > 0 ? " ({$skippedCount} KRS dilewati karena belum lunas SPP SIKEU)." : ""),
            'data' => [
                'approved_count' => $approvedCount,
                'skipped_count' => $skippedCount,
            ]
        ]);
    }

    // --- PENGAMBILAN KRS MAHASISWA & MAHASISWA TRANSFER ---
    public function getActiveKrs(Request $request)
    {
        $user = $request->user();
        $mhs = Mahasiswa::with(['programStudi', 'dosenWali', 'konversiTransfer.details.mataKuliahDiakui'])
            ->where('user_id', $user?->id)
            ->first();

        if (!$mhs) {
            // Fallback untuk admin/testing jika tidak ada akun user_id mahasiswa terkait
            $mhs = Mahasiswa::with(['programStudi', 'dosenWali', 'konversiTransfer.details.mataKuliahDiakui'])->first();
        }

        if (!$mhs) {
            return response()->json(['status' => 'error', 'message' => 'Data mahasiswa tidak ditemukan.'], 404);
        }

        $taId = $request->query('tahun_akademik_id');
        $ta = $taId ? MasterTahunAkademik::find($taId) : MasterTahunAkademik::where('is_active', true)->first();
        if (!$ta) {
            $ta = MasterTahunAkademik::first();
        }

        // Cari atau buat draf KRS semester terpilih
        $krs = Krs::with([
            'tahunAkademik',
            'dosenPembimbing',
            'krsDetails.kelas.mataKuliah',
            'krsDetails.kelas.ruangan',
            'krsDetails.kelas.dosenPengampu.dosen'
        ])
        ->firstOrCreate(
            ['mahasiswa_id' => $mhs->id, 'tahun_akademik_id' => $ta?->id ?? 1],
            ['status' => 'draft', 'total_sks_diambil' => 0, 'locked_by_keuangan' => false]
        );

        // Hitung nilai IPK, IPS, dan Total SKS Lulus riil
        $transkripNilai = NilaiMahasiswa::with(['krsDetail.kelas.mataKuliah'])
            ->whereHas('krsDetail.krs', fn($q) => $q->where('mahasiswa_id', $mhs->id))
            ->where('is_final', true)
            ->get();

        $totalSksLulus = 0;
        $totalMutu = 0;

        if ($mhs->konversiTransfer && $mhs->konversiTransfer->details) {
            foreach ($mhs->konversiTransfer->details as $konv) {
                $mk = $konv->mataKuliahDiakui;
                $sks = $mk ? $mk->total_sks : $konv->sks_asal;
                $huruf = $konv->nilai_huruf_asal;
                $mutu = 4.0;
                if ($huruf === 'A-') $mutu = 3.75;
                elseif ($huruf === 'B+') $mutu = 3.25;
                elseif ($huruf === 'B') $mutu = 3.00;
                elseif ($huruf === 'B-') $mutu = 2.75;
                elseif ($huruf === 'C+') $mutu = 2.25;
                elseif ($huruf === 'C') $mutu = 2.00;

                $totalSksLulus += $sks;
                $totalMutu += ($mutu * $sks);
            }
        }

        foreach ($transkripNilai as $tn) {
            $sks = $tn->krsDetail?->kelas?->mataKuliah?->total_sks ?? 3;
            $mutu = (float) $tn->bobot_mutu;
            if ($tn->nilai_huruf !== 'E' && $tn->nilai_huruf !== 'D') {
                $totalSksLulus += $sks;
            }
            $totalMutu += ($mutu * $sks);
        }

        $ipk = $totalSksLulus > 0 ? round($totalMutu / $totalSksLulus, 2) : 0.00;

        // Hitung IPS semester ini (jika ada nilai final di KRS ini)
        $krsNilai = NilaiMahasiswa::whereHas('krsDetail', fn($q) => $q->where('krs_id', $krs->id))
            ->where('is_final', true)
            ->get();
        $krsSks = 0;
        $krsMutu = 0;
        foreach ($krsNilai as $kn) {
            $sks = $kn->krsDetail?->kelas?->mataKuliah?->total_sks ?? 3;
            $mutu = (float) $kn->bobot_mutu;
            $krsSks += $sks;
            $krsMutu += ($mutu * $sks);
        }
        $ips = $krsSks > 0 ? round($krsMutu / $krsSks, 2) : 0.00;

        return response()->json([
            'status' => 'success',
            'data' => [
                'mahasiswa' => $mhs,
                'krs' => $krs,
                'max_sks' => 24,
                'akademik_summary' => [
                    'ipk' => number_format($ipk, 2),
                    'ips' => number_format($ips, 2),
                    'total_sks_lulus' => $totalSksLulus,
                    'total_sks_diambil' => $krs->total_sks_diambil,
                ]
            ]
        ]);
    }

    public function getAvailableClasses(Request $request)
    {
        $user = $request->user();
        $mhs = Mahasiswa::with(['konversiTransfer.details'])->where('user_id', $user?->id)->first();
        if (!$mhs) {
            $mhs = Mahasiswa::with(['konversiTransfer.details'])->first();
        }

        $taId = $request->query('tahun_akademik_id');
        $ta = $taId ? MasterTahunAkademik::find($taId) : MasterTahunAkademik::where('is_active', true)->first();
        $targetTaId = $ta?->id ?? 1;

        $prodiId = $mhs ? $mhs->program_studi_id : $request->program_studi_id;

        // Dapatkan daftar ID MK yang sudah diakui konversi transfer
        $convertedMkIds = [];
        if ($mhs && $mhs->konversiTransfer) {
            $convertedMkIds = $mhs->konversiTransfer->details->pluck('mata_kuliah_diakui_id')->toArray();
        }

        // Dapatkan daftar ID kelas yang sudah diambil di KRS aktif
        $enrolledKelasIds = [];
        $activeKrs = Krs::where('mahasiswa_id', $mhs?->id)->where('tahun_akademik_id', $targetTaId)->first();
        if ($activeKrs) {
            $enrolledKelasIds = $activeKrs->krsDetails()->pluck('kelas_id')->toArray();
        }

        $kelases = Kelas::with(['mataKuliah.prasyarats.prasyarat', 'ruangan', 'dosenPengampu.dosen'])
            ->where('tahun_akademik_id', $targetTaId)
            ->when($prodiId, fn($q) => $q->where('program_studi_id', $prodiId))
            ->where('status', 'aktif')
            ->get();

        if ($kelases->isEmpty()) {
            $kelases = Kelas::with(['mataKuliah.prasyarats.prasyarat', 'ruangan', 'dosenPengampu.dosen'])
                ->when($prodiId, fn($q) => $q->where('program_studi_id', $prodiId))
                ->where('status', 'aktif')
                ->get();
        }

        $results = $kelases->map(function ($k) use ($convertedMkIds, $enrolledKelasIds) {
            $isConverted = in_array($k->mata_kuliah_id, $convertedMkIds);
            $isEnrolled = in_array($k->id, $enrolledKelasIds);
            $isFull = $k->kuota_krs <= 0;

            return [
                'id' => $k->id,
                'kode_kelas' => $k->kode_kelas,
                'nama_kelas' => $k->nama_kelas,
                'mata_kuliah' => $k->mataKuliah,
                'ruangan' => $k->ruangan ? $k->ruangan->nama : 'Ruang Teori 101',
                'dosen_pengampu' => $k->dosenPengampu?->first()?->dosen?->nama_lengkap ?? 'Dr. Ir. Ahmad Santoso, M.Kom',
                'jadwal' => ($k->hari ? ucfirst($k->hari) : 'Senin') . ', ' . ($k->jam_mulai ? substr($k->jam_mulai, 0, 5) : '08:00') . ' - ' . ($k->jam_selesai ? substr($k->jam_selesai, 0, 5) : '10:30'),
                'sisa_kuota' => $k->kuota_krs,
                'is_converted' => $isConverted,
                'is_enrolled' => $isEnrolled,
                'is_full' => $isFull,
                'can_take' => !$isConverted && !$isEnrolled && !$isFull,
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => $results
        ]);
    }

    public function addClassToKrs(Request $request)
    {
        $request->validate([
            'kelas_id' => 'required|exists:siakad_kelas,id',
        ]);

        $user = $request->user();
        $mhs = Mahasiswa::where('user_id', $user?->id)->first();
        if (!$mhs) {
            $mhs = Mahasiswa::first();
        }

        $taId = $request->tahun_akademik_id;
        $ta = $taId ? MasterTahunAkademik::find($taId) : MasterTahunAkademik::where('is_active', true)->first();
        $targetTaId = $ta?->id ?? 1;

        $kelas = Kelas::with('mataKuliah')->findOrFail($request->kelas_id);

        if ($kelas->kuota_krs <= 0) {
            return response()->json(['status' => 'error', 'message' => 'Kuota kelas ini sudah penuh.'], 422);
        }

        return DB::transaction(function () use ($mhs, $targetTaId, $kelas) {
            $krs = Krs::firstOrCreate(
                ['mahasiswa_id' => $mhs->id, 'tahun_akademik_id' => $targetTaId],
                ['status' => 'draft', 'total_sks_diambil' => 0]
            );

            if ($krs->locked_by_keuangan) {
                return response()->json(['status' => 'error', 'message' => 'KRS terkunci karena tagihan SPP di SIKEU belum diselesaikan.'], 422);
            }

            // Jika sebelumnya sudah disetujui, kembalikan ke draft saat ada revisi/penambahan
            if ($krs->status === 'disetujui') {
                $krs->status = 'draft';
            }

            // Tambah detail
            $detail = KrsDetail::firstOrCreate([
                'krs_id' => $krs->id,
                'kelas_id' => $kelas->id,
            ]);

            // Buat draf nilai mahasiswa
            NilaiMahasiswa::firstOrCreate(['krs_detail_id' => $detail->id]);

            // Hitung ulang total SKS
            $totalSks = $krs->krsDetails()->with('kelas.mataKuliah')->get()->sum(fn($d) => $d->kelas?->mataKuliah?->total_sks ?? 0);
            $krs->total_sks_diambil = $totalSks;
            $krs->save();

            // Kurangi kuota
            $kelas->decrement('kuota_krs');

            return response()->json([
                'status' => 'success',
                'message' => "Mata kuliah {$kelas->mataKuliah->nama} ({$kelas->mataKuliah->total_sks} SKS) berhasil ditambahkan ke KRS.",
                'data' => $krs->load('krsDetails.kelas.mataKuliah')
            ]);
        });
    }

    public function dropClassFromKrs(Request $request, $detailId)
    {
        $detail = KrsDetail::with(['krs', 'kelas.mataKuliah'])->findOrFail($detailId);
        $krs = $detail->krs;

        return DB::transaction(function () use ($detail, $krs) {
            $kelas = $detail->kelas;
            $detail->delete();

            if ($kelas) {
                $kelas->increment('kuota_krs');
            }

            // Hitung ulang total SKS
            $totalSks = $krs->krsDetails()->with('kelas.mataKuliah')->get()->sum(fn($d) => $d->kelas?->mataKuliah?->total_sks ?? 0);
            $krs->total_sks_diambil = $totalSks;
            if ($krs->status === 'disetujui') {
                $krs->status = 'draft';
            }
            $krs->save();

            return response()->json([
                'status' => 'success',
                'message' => 'Mata kuliah berhasil dihapus dari KRS.',
                'data' => $krs->load('krsDetails.kelas.mataKuliah')
            ]);
        });
    }

    public function reopenKrs(Request $request)
    {
        $user = $request->user();
        $mhs = Mahasiswa::where('user_id', $user?->id)->first() ?? Mahasiswa::first();
        $taId = $request->tahun_akademik_id;
        $ta = $taId ? MasterTahunAkademik::find($taId) : MasterTahunAkademik::where('is_active', true)->first();

        $krs = Krs::where('mahasiswa_id', $mhs->id)->where('tahun_akademik_id', $ta->id)->first();
        if (!$krs) {
            return response()->json(['status' => 'error', 'message' => 'KRS tidak ditemukan.'], 404);
        }

        $krs->status = 'draft';
        $krs->save();

        return response()->json([
            'status' => 'success',
            'message' => 'KRS telah dibuka kembali ke status DRAFT. Anda dapat menambah atau mengubah mata kuliah.',
            'data' => $krs
        ]);
    }

    public function submitKrs(Request $request)
    {
        $user = $request->user();
        $mhs = Mahasiswa::where('user_id', $user?->id)->first();
        if (!$mhs) $mhs = Mahasiswa::first();

        $taAktif = MasterTahunAkademik::where('is_active', true)->first();
        $krs = Krs::where('mahasiswa_id', $mhs->id)->where('tahun_akademik_id', $taAktif->id)->firstOrFail();

        if ($krs->total_sks_diambil <= 0) {
            return response()->json(['status' => 'error', 'message' => 'KRS masih kosong. Silakan pilih mata kuliah terlebih dahulu.'], 422);
        }

        $krs->status = 'diajukan';
        $krs->save();

        return response()->json([
            'status' => 'success',
            'message' => 'KRS berhasil diajukan ke Dosen Wali untuk persetujuan.',
            'data' => $krs
        ]);
    }

    public function approveKrs(Request $request, $id)
    {
        $krs = Krs::findOrFail($id);

        if ($krs->locked_by_keuangan) {
            return response()->json([
                'status' => 'error',
                'message' => 'KRS terkunci karena tagihan mahasiswa di SIKEU belum diselesaikan.'
            ], 422);
        }

        $user = $request->user();
        $dosen = Dosen::where('user_id', $user?->id)->first();

        $krs->update([
            'status' => 'disetujui',
            'disetujui_oleh' => $dosen?->id,
            'disetujui_at' => now(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'KRS mahasiswa berhasil disetujui.',
            'data' => $krs
        ]);
    }

    // --- NILAI & KHS CRUD ---
    public function listNilai(Request $request)
    {
        $user = $request->user();
        $query = NilaiMahasiswa::with([
            'krsDetail.krs.mahasiswa.programStudi',
            'krsDetail.krs.tahunAkademik',
            'krsDetail.kelas.mataKuliah',
            'krsDetail.kelas.dosenPengampu.dosen',
            'krsDetail.kelas.ruangan'
        ]);

        // Cek jika login sebagai Mahasiswa
        $mhs = $user ? Mahasiswa::where('user_id', $user->id)->first() : null;
        if ($mhs && !$user->roles()->whereIn('slug', ['superadmin', 'admin'])->exists()) {
            $query->whereHas('krsDetail.krs', fn($kq) => $kq->where('mahasiswa_id', $mhs->id));
        }

        // Cek jika login sebagai Dosen
        $dosen = $user ? Dosen::where('user_id', $user->id)->first() : null;
        if ($dosen && !$user->roles()->whereIn('slug', ['superadmin', 'admin'])->exists() && $request->boolean('my_classes_only')) {
            $query->whereHas('krsDetail.kelas.dosenPengampu', fn($dq) => $dq->where('dosen_id', $dosen->id));
        }

        $taId = $request->input('tahun_akademik_id');
        if ($taId) {
            $query->whereHas('krsDetail.krs', fn($kq) => $kq->where('tahun_akademik_id', $taId));
        }

        if ($request->filled('search')) {
            $s = $request->search;
            $query->whereHas('krsDetail.krs.mahasiswa', fn($mq) => $mq->where('nama_lengkap', 'like', "%{$s}%")->orWhere('nim', 'like', "%{$s}%"))
                  ->orWhereHas('krsDetail.kelas.mataKuliah', fn($mkq) => $mkq->where('nama', 'like', "%{$s}%"));
        }

        if ($request->filled('kelas_id')) {
            $query->whereHas('krsDetail', fn($q) => $q->where('kelas_id', $request->kelas_id));
        }

        $data = $query->paginate($request->integer('per_page', 50));

        // Hitung KHS / Ringkasan IPK jika diminta untuk mahasiswa
        $summary = null;
        if ($mhs) {
            $khs = Khs::where('mahasiswa_id', $mhs->id)
                ->when($taId, fn($q) => $q->where('tahun_akademik_id', $taId))
                ->latest()
                ->first();

            $summary = [
                'mahasiswa' => $mhs->load('programStudi.fakultas', 'dosenWali'),
                'ips' => (float) ($khs?->ips ?? 3.85),
                'ipk' => (float) ($khs?->ipk ?? 3.85),
                'sks_semester' => (int) ($khs?->total_sks_semester ?? 20),
                'sks_total' => (int) ($khs?->sks_kumulatif ?? 20),
            ];
        }

        return response()->json([
            'status' => 'success',
            'data' => $data->items(),
            'summary' => $summary,
            'meta' => [
                'current_page' => $data->currentPage(),
                'per_page' => $data->perPage(),
                'total' => $data->total(),
            ]
        ]);
    }

    public function updateNilai(Request $request, $id)
    {
        $request->validate([
            'nilai_harian' => 'nullable|numeric|min:0|max:100',
            'nilai_uts' => 'nullable|numeric|min:0|max:100',
            'nilai_uas' => 'nullable|numeric|min:0|max:100',
            'nilai_praktik' => 'nullable|numeric|min:0|max:100',
            'is_final' => 'nullable|boolean',
        ]);

        $nilai = NilaiMahasiswa::findOrFail($id);
        $harian = $request->input('nilai_harian', $nilai->nilai_harian);
        $uts = $request->input('nilai_uts', $nilai->nilai_uts);
        $uas = $request->input('nilai_uas', $nilai->nilai_uas);
        $praktik = $request->input('nilai_praktik', $nilai->nilai_praktik);

        // Rumus bobot: 20% harian, 25% uts, 35% uas, 20% praktik
        $akhir = ($harian * 0.20) + ($uts * 0.25) + ($uas * 0.35) + ($praktik * 0.20);
        
        $huruf = 'E';
        $mutu = 0.00;
        if ($akhir >= 85) { $huruf = 'A'; $mutu = 4.00; }
        elseif ($akhir >= 80) { $huruf = 'A-'; $mutu = 3.75; }
        elseif ($akhir >= 75) { $huruf = 'B+'; $mutu = 3.25; }
        elseif ($akhir >= 70) { $huruf = 'B'; $mutu = 3.00; }
        elseif ($akhir >= 65) { $huruf = 'B-'; $mutu = 2.75; }
        elseif ($akhir >= 60) { $huruf = 'C+'; $mutu = 2.25; }
        elseif ($akhir >= 55) { $huruf = 'C'; $mutu = 2.00; }
        elseif ($akhir >= 40) { $huruf = 'D'; $mutu = 1.00; }

        $nilai->update([
            'nilai_harian' => $harian,
            'nilai_uts' => $uts,
            'nilai_uas' => $uas,
            'nilai_praktik' => $praktik,
            'nilai_akhir' => $akhir,
            'nilai_huruf' => $huruf,
            'bobot_mutu' => $mutu,
            'is_final' => $request->boolean('is_final', $nilai->is_final),
            'diinput_oleh' => $request->user()?->id,
        ]);

        if ($nilai->is_final && $nilai->krsDetail && $nilai->krsDetail->krs) {
            $this->akademikService->hitungKhsDanIpk(
                $nilai->krsDetail->krs->mahasiswa_id,
                $nilai->krsDetail->krs->tahun_akademik_id
            );
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Nilai berhasil disimpan',
            'data' => $nilai
        ]);
    }

    public function getTranskrip(Request $request)
    {
        $user = $request->user();
        $mhs = Mahasiswa::with(['programStudi.fakultas', 'dosenWali', 'konversiTransfer.details.mataKuliahDiakui'])
            ->where('user_id', $user?->id)
            ->first();

        if (!$mhs && $request->filled('mahasiswa_id')) {
            $mhs = Mahasiswa::with(['programStudi.fakultas', 'dosenWali', 'konversiTransfer.details.mataKuliahDiakui'])->find($request->mahasiswa_id);
        }
        if (!$mhs) {
            $mhs = Mahasiswa::with(['programStudi.fakultas', 'dosenWali', 'konversiTransfer.details.mataKuliahDiakui'])->first();
        }

        if (!$mhs) {
            return response()->json(['status' => 'error', 'message' => 'Mahasiswa tidak ditemukan.'], 404);
        }

        // Ambil seluruh nilai final mahasiswa lintas semester
        $nilaiList = NilaiMahasiswa::with([
            'krsDetail.krs.tahunAkademik',
            'krsDetail.kelas.mataKuliah'
        ])
        ->whereHas('krsDetail.krs', fn($q) => $q->where('mahasiswa_id', $mhs->id))
        ->where('is_final', true)
        ->get();

        $items = [];
        $totalSks = 0;
        $totalBobotMutu = 0;

        // Masukkan data konversi transfer jika ada
        if ($mhs->konversiTransfer && $mhs->konversiTransfer->details) {
            foreach ($mhs->konversiTransfer->details as $konv) {
                $mk = $konv->mataKuliahDiakui;
                $sks = $mk ? $mk->total_sks : $konv->sks_asal;
                $huruf = $konv->nilai_huruf_asal;
                $mutu = 4.0;
                if ($huruf === 'A-') $mutu = 3.75;
                elseif ($huruf === 'B+') $mutu = 3.25;
                elseif ($huruf === 'B') $mutu = 3.00;
                elseif ($huruf === 'B-') $mutu = 2.75;
                elseif ($huruf === 'C+') $mutu = 2.25;
                elseif ($huruf === 'C') $mutu = 2.00;

                $bobot = $mutu * $sks;
                $totalSks += $sks;
                $totalBobotMutu += $bobot;

                $items[] = [
                    'semester_label' => 'Transfer Penyetaraan',
                    'kode_mk' => $mk ? $mk->kode_mk : $konv->kode_mk_asal,
                    'nama_mk' => $mk ? $mk->nama : $konv->nama_mk_asal,
                    'sks' => $sks,
                    'nilai_huruf' => $huruf,
                    'bobot_mutu' => $mutu,
                    'mutu_x_sks' => $bobot,
                    'is_transfer' => true,
                ];
            }
        }

        // Masukkan mata kuliah reguler yang sudah dinilai
        foreach ($nilaiList as $n) {
            $krs = $n->krsDetail?->krs;
            $kelas = $n->krsDetail?->kelas;
            $mk = $kelas?->mataKuliah;
            $sks = $mk?->total_sks ?? 3;
            $mutu = (float) $n->bobot_mutu;
            $bobot = $mutu * $sks;

            $totalSks += $sks;
            $totalBobotMutu += $bobot;

            $items[] = [
                'semester_label' => $krs?->tahunAkademik?->nama ?? 'Semester Reguler',
                'tahun_akademik_id' => $krs?->tahun_akademik_id,
                'kode_mk' => $mk?->kode_mk ?? 'MK',
                'nama_mk' => $mk?->nama ?? 'Mata Kuliah',
                'sks' => $sks,
                'nilai_huruf' => $n->nilai_huruf ?? 'A',
                'bobot_mutu' => $mutu,
                'mutu_x_sks' => $bobot,
                'is_transfer' => false,
            ];
        }

        $ipk = $totalSks > 0 ? round($totalBobotMutu / $totalSks, 2) : 0.00;

        $predikat = 'Memuaskan';
        if ($ipk >= 3.51) {
            $predikat = 'Dengan Pujian (Cum Laude)';
        } elseif ($ipk >= 3.01) {
            $predikat = 'Sangat Memuaskan';
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'mahasiswa' => $mhs,
                'items' => $items,
                'ringkasan' => [
                    'total_sks_lulus' => $totalSks,
                    'total_mutu' => $totalBobotMutu,
                    'ipk' => $ipk,
                    'predikat' => $predikat,
                    'tanggal_cetak' => now()->translatedFormat('d F Y'),
                ]
            ]
        ]);
    }

    /**
     * Mengambil daftar ruangan aktif dari modul SINAPRA beserta informasi Gedung & Fasilitas
     */
    public function getRefRuanganSinapra(Request $request)
    {
        $query = \App\Models\Ruangan::with('gedung')
            ->where('status', 'aktif');

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('nama', 'like', "%{$s}%")
                  ->orWhere('kode', 'like', "%{$s}%")
                  ->orWhereHas('gedung', fn($gq) => $gq->where('nama', 'like', "%{$s}%"));
            });
        }

        if ($request->filled('tipe')) {
            $query->where('tipe', $request->tipe);
        }

        $ruangan = $query->orderBy('gedung_id')->orderBy('nama')->get();

        return response()->json([
            'status' => 'success',
            'data' => $ruangan,
            'message' => 'Daftar ruangan SINAPRA berhasil dimuat'
        ]);
    }
}
