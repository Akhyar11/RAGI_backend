<?php

namespace App\Http\Controllers\API\Siakad;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Siakad\Kelas;
use App\Models\Siakad\Dosen;
use App\Models\Siakad\Mahasiswa;
use App\Models\Siakad\DosenPengampu;
use App\Models\Siakad\Krs;
use App\Models\Siakad\KrsDetail;
use App\Models\Siakad\NilaiMahasiswa;
use App\Models\Siakad\SkalaNilai;
use App\Models\Siakad\Khs;
use App\Models\Siakad\MataKuliah;
use App\Models\Siakad\KonversiTransferDetail;
use App\Models\Siakad\TahunAkademik;
use App\Services\Siakad\SiakadAkademikService;
use App\Services\Siakad\KrsService;
use App\Http\Requests\Siakad\StoreKelasRequest;
use App\Http\Requests\Siakad\UpdateKelasRequest;
use App\Http\Requests\Siakad\StoreAbsensiRequest;
use Illuminate\Support\Facades\DB;

class PerkuliahanController extends Controller
{
    protected SiakadAkademikService $akademikService;
    protected KrsService $krsService;

    public function __construct(SiakadAkademikService $akademikService, KrsService $krsService)
    {
        $this->akademikService = $akademikService;
        $this->krsService = $krsService;
    }

    public function listKelas(Request $request)
    {
        $user = $request->user();
        $taId = $request->input('tahun_akademik_id') ?? TahunAkademik::where('is_active', true)->value('id');

        $query = Kelas::with(['mataKuliah', 'ruangan.gedung', 'programStudi', 'programStudis', 'dosenPengampu.dosen'])
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
                  ->orWhereHas('mataKuliah.kurikulum', fn($kq) => $kq->where('program_studi_id', $prodiId))
                  ->orWhereHas('programStudis', fn($pq) => $pq->where('siakad_program_studi.id', $prodiId));
            });
        }

        if ($request->filled('is_gabungan')) {
            $query->where('is_gabungan', $request->boolean('is_gabungan'));
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

    public function storeKelas(StoreKelasRequest $request)
    {
        $validated = $request->validated();

        return DB::transaction(function () use ($validated) {
            $mk = MataKuliah::find($validated['mata_kuliah_id']);

            $kelas = Kelas::create([
                'mata_kuliah_id' => $validated['mata_kuliah_id'],
                'tahun_akademik_id' => $validated['tahun_akademik_id'],
                'program_studi_id' => $validated['program_studi_id'],
                'ruangan_id' => $validated['ruangan_id'] ?? null,
                'kode_kelas' => $validated['kode_kelas'],
                'nama_kelas' => $validated['nama_kelas'],
                'kapasitas' => $validated['kapasitas'],
                'kuota_krs' => $validated['kuota_krs'],
                'hari' => $validated['hari'],
                'jam_mulai' => $validated['jam_mulai'],
                'jam_selesai' => $validated['jam_selesai'],
                'status' => 'draft',
                'is_gabungan' => $validated['is_gabungan'] ?? false,
            ]);

            // Prodi peserta kelas gabungan (selalu mencakup homebase)
            $gabunganIds = collect($validated['gabungan_program_studi_ids'] ?? [])
                ->push($validated['program_studi_id'])->unique()->values()->toArray();
            if ($kelas->is_gabungan) {
                $kelas->programStudis()->sync($gabunganIds);
            }

            // Dosen Pengampu Utama (yang dilaporkan resmi ke Feeder)
            if (!empty($validated['dosen_id'])) {
                DosenPengampu::create([
                    'kelas_id' => $kelas->id,
                    'dosen_id' => $validated['dosen_id'],
                    'peran' => 'pengampu_utama',
                    'sks_substansi_total' => $mk?->total_sks ?? 3,
                    'rencana_tatap_muka' => 16,
                ]);
            }

            // Dosen Team Teaching (Anggota Pengajar Tambahan)
            if (!empty($validated['team_teaching_dosen_ids']) && is_array($validated['team_teaching_dosen_ids'])) {
                foreach ($validated['team_teaching_dosen_ids'] as $ttDosenId) {
                    if ($ttDosenId && $ttDosenId != ($validated['dosen_id'] ?? null)) {
                        DosenPengampu::create([
                            'kelas_id' => $kelas->id,
                            'dosen_id' => $ttDosenId,
                            'peran' => 'co_pengampu',
                            'sks_substansi_total' => $mk?->total_sks ?? 3,
                            'rencana_tatap_muka' => 16,
                        ]);
                    }
                }
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Kelas perkuliahan berhasil dibuat dengan tim pengajar terdaftar',
                'data' => $kelas->load(['mataKuliah', 'ruangan', 'dosenPengampu.dosen', 'programStudis'])
            ], 201);
        });
    }

    public function showKelas($id)
    {
        $kelas = Kelas::with([
            'mataKuliah.kurikulum.programStudi',
            'tahunAkademik',
            'programStudi',
            'programStudis',
            'ruangan.gedung',
            'dosenPengampu.dosen.programStudi',
        ])->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'message' => 'Detail kelas perkuliahan berhasil dimuat',
            'data' => $kelas,
        ]);
    }

    public function updateKelas(UpdateKelasRequest $request, $id)
    {
        $kelas = Kelas::findOrFail($id);
        $validated = $request->validated();

        return DB::transaction(function () use ($validated, $kelas) {
            $kelas->update([
                'nama_kelas' => $validated['nama_kelas'],
                'ruangan_id' => $validated['ruangan_id'] ?? null,
                'kapasitas' => $validated['kapasitas'],
                'kuota_krs' => $validated['kuota_krs'],
                'hari' => $validated['hari'],
                'jam_mulai' => $validated['jam_mulai'],
                'jam_selesai' => $validated['jam_selesai'],
                'is_gabungan' => $validated['is_gabungan'] ?? $kelas->is_gabungan,
            ]);

            if (array_key_exists('gabungan_program_studi_ids', $validated)) {
                if ($kelas->is_gabungan) {
                    $ids = collect($validated['gabungan_program_studi_ids'] ?? [])
                        ->push($kelas->program_studi_id)->unique()->values()->toArray();
                    $kelas->programStudis()->sync($ids);
                } else {
                    $kelas->programStudis()->detach();
                }
            }

            if (array_key_exists('dosen_id', $validated) || array_key_exists('team_teaching_dosen_ids', $validated)) {
                DosenPengampu::where('kelas_id', $kelas->id)->delete();
                $mk = $kelas->mataKuliah;

                if (!empty($validated['dosen_id'])) {
                    DosenPengampu::create([
                        'kelas_id' => $kelas->id,
                        'dosen_id' => $validated['dosen_id'],
                        'peran' => 'pengampu_utama',
                        'sks_substansi_total' => $mk?->total_sks ?? 3,
                        'rencana_tatap_muka' => 16,
                    ]);
                }

                if (!empty($validated['team_teaching_dosen_ids']) && is_array($validated['team_teaching_dosen_ids'])) {
                    foreach ($validated['team_teaching_dosen_ids'] as $ttDosenId) {
                        if ($ttDosenId && $ttDosenId != ($validated['dosen_id'] ?? null)) {
                            DosenPengampu::create([
                                'kelas_id' => $kelas->id,
                                'dosen_id' => $ttDosenId,
                                'peran' => 'co_pengampu',
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
        if ($mhs && !$user->isAdmin()) {
            $query->where('mahasiswa_id', $mhs->id);
        }

        // Cek jika login sebagai Dosen Wali
        $dosen = $user ? Dosen::where('user_id', $user->id)->first() : null;
        if ($dosen && !$user->isAdmin() && $request->boolean('advisees_only')) {
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

        // Filter khusus mahasiswa konversi / transfer yang memerlukan review DPA
        if ($request->boolean('only_konversi')) {
            $query->whereHas('mahasiswa', function ($mq) {
                $mq->whereNotNull('konversi_id')
                   ->orWhereHas('konversiTransfer');
            });
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

        $user = $request->user();
        $dosen = Dosen::where('user_id', $user?->id)->first();
        $isPrivileged = $user && ($user->isAdmin() || $user->isSuperAdmin() || $user->hasRole('kaprodi') || $user->hasRole('wakil_prodi'));

        $approvedCount = 0;
        $skippedCount = 0;

        $krsList = Krs::with('mahasiswa')->whereIn('id', $request->krs_ids)->get();

        foreach ($krsList as $krs) {
            if ($krs->locked_by_keuangan || $krs->status !== 'diajukan') {
                $skippedCount++;
                continue;
            }
            if (!$isPrivileged) {
                if (!$dosen || (int) $krs->mahasiswa?->dosen_wali_id !== (int) $dosen->id) {
                    $skippedCount++;
                    continue;
                }
            }
            $krs->status = 'disetujui';
            $krs->disetujui_oleh = $dosen?->id ?? $krs->disetujui_oleh;
            $krs->disetujui_at = now();
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

    /**
     * Monitoring & Rekapitulasi Progres Pengisian KRS BAAK per Program Studi
     */
    public function monitoringKrsProdi(Request $request)
    {
        $taId = $request->input('tahun_akademik_id') ?? MasterTahunAkademik::where('is_active', true)->value('id');
        $tahunAkademik = MasterTahunAkademik::find($taId);

        $prodiQuery = \App\Models\Spmb\MasterProgramStudi::with('fakultas')
            ->where('is_active', true);

        if ($request->filled('program_studi_id')) {
            $prodiQuery->where('id', $request->program_studi_id);
        }

        $prodis = $prodiQuery->orderBy('nama')->get();

        $rekapProdi = [];
        $totalSemuaMahasiswa = 0;
        $totalSemuaSudahKrs = 0;
        $totalSemuaDisetujui = 0;
        $totalSemuaDiajukan = 0;
        $totalSemuaDraft = 0;
        $totalSemuaBelumKrs = 0;
        $totalSemuaTerkunciKeuangan = 0;

        foreach ($prodis as $prodi) {
            $mhsAktifCount = Mahasiswa::where('program_studi_id', $prodi->id)
                ->where('status', 'aktif')
                ->count();

            $krsRecords = Krs::whereHas('mahasiswa', function ($q) use ($prodi) {
                $q->where('program_studi_id', $prodi->id)->where('status', 'aktif');
            })
            ->where('tahun_akademik_id', $taId)
            ->get();

            $draftCount = $krsRecords->where('status', 'draft')->count();
            $diajukanCount = $krsRecords->where('status', 'diajukan')->count();
            $disetujuiCount = $krsRecords->where('status', 'disetujui')->count();
            $terkunciKeuanganCount = $krsRecords->where('locked_by_keuangan', true)->count();
            $sudahKrsCount = $krsRecords->count();
            $belumKrsCount = max(0, $mhsAktifCount - $sudahKrsCount);

            $persentaseKrs = $mhsAktifCount > 0 ? round(($sudahKrsCount / $mhsAktifCount) * 100, 1) : 0;
            $persentaseDisetujui = $mhsAktifCount > 0 ? round(($disetujuiCount / $mhsAktifCount) * 100, 1) : 0;

            $rekapProdi[] = [
                'prodi_id' => $prodi->id,
                'kode_prodi' => $prodi->kode_prodi,
                'nama_prodi' => $prodi->nama,
                'jenjang' => $prodi->jenjang,
                'fakultas' => $prodi->fakultas?->nama ?? '-',
                'total_mahasiswa_aktif' => $mhsAktifCount,
                'sudah_krs' => $sudahKrsCount,
                'draft' => $draftCount,
                'diajukan' => $diajukanCount,
                'disetujui' => $disetujuiCount,
                'belum_krs' => $belumKrsCount,
                'terkunci_keuangan' => $terkunciKeuanganCount,
                'persentase_krs' => $persentaseKrs,
                'persentase_disetujui' => $persentaseDisetujui,
            ];

            $totalSemuaMahasiswa += $mhsAktifCount;
            $totalSemuaSudahKrs += $sudahKrsCount;
            $totalSemuaDisetujui += $disetujuiCount;
            $totalSemuaDiajukan += $diajukanCount;
            $totalSemuaDraft += $draftCount;
            $totalSemuaBelumKrs += $belumKrsCount;
            $totalSemuaTerkunciKeuangan += $terkunciKeuanganCount;
        }

        $overallPersentaseKrs = $totalSemuaMahasiswa > 0 ? round(($totalSemuaSudahKrs / $totalSemuaMahasiswa) * 100, 1) : 0;
        $overallPersentaseDisetujui = $totalSemuaMahasiswa > 0 ? round(($totalSemuaDisetujui / $totalSemuaMahasiswa) * 100, 1) : 0;

        return response()->json([
            'status' => 'success',
            'message' => 'Data monitoring KRS berhasil dimuat',
            'data' => [
                'tahun_akademik' => $tahunAkademik,
                'summary' => [
                    'total_mahasiswa_aktif' => $totalSemuaMahasiswa,
                    'total_sudah_krs' => $totalSemuaSudahKrs,
                    'total_disetujui' => $totalSemuaDisetujui,
                    'total_diajukan' => $totalSemuaDiajukan,
                    'total_draft' => $totalSemuaDraft,
                    'total_belum_krs' => $totalSemuaBelumKrs,
                    'total_terkunci_keuangan' => $totalSemuaTerkunciKeuangan,
                    'persentase_krs' => $overallPersentaseKrs,
                    'persentase_disetujui' => $overallPersentaseDisetujui,
                ],
                'prodi' => $rekapProdi,
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

        if (!$mhs && $request->filled('mahasiswa_id')) {
            $mhs = Mahasiswa::with(['programStudi', 'dosenWali', 'konversiTransfer.details.mataKuliahDiakui'])
                ->find($request->mahasiswa_id);
        }

        if (!$mhs) {
            return response()->json(['status' => 'error', 'message' => 'Data mahasiswa tidak ditemukan.'], 404);
        }

        $taId = $request->query('tahun_akademik_id');
        $ta = $taId ? TahunAkademik::find($taId) : TahunAkademik::where('is_active', true)->first();
        if (!$ta) {
            return response()->json(['status' => 'error', 'message' => 'Tahun akademik aktif belum ditetapkan.'], 422);
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

        // Evaluasi sinkronisasi penguncian keuangan riil vs dispensasi
        $hasUnpaidBills = \App\Models\Sikeu\TagihanMahasiswa::where('mahasiswa_id', $mhs->id)
            ->whereIn('status', ['belum_bayar', 'sebagian'])
            ->exists();

        $hasApprovedKrsDispensasi = false;
        if ($hasUnpaidBills) {
            $hasApprovedKrsDispensasi = \App\Models\Sikeu\DispensasiTagihan::where('mahasiswa_id', $mhs->id)
                ->where('status', 'approved')
                ->where('allow_krs', true)
                ->whereHas('tagihan', fn($q) => $q->whereIn('status', ['belum_bayar', 'sebagian', 'dispensasi']))
                ->exists();
        }

        $shouldLock = $hasUnpaidBills && !$hasApprovedKrsDispensasi;
        if ((bool)$krs->locked_by_keuangan !== $shouldLock) {
            $krs->locked_by_keuangan = $shouldLock;
            $krs->save();
        }

        // Hitung nilai IPK, IPS, dan Total SKS Lulus riil
        $transkripNilai = NilaiMahasiswa::with(['krsDetail.kelas.mataKuliah'])
            ->whereHas('krsDetail.krs', fn($q) => $q->where('mahasiswa_id', $mhs->id))
            ->where('is_final', true)
            ->get();

        $totalSksLulus = 0;
        $totalMutu = 0;

        if ($mhs->konversiTransfer && $mhs->konversiTransfer->status === 'disetujui' && $mhs->konversiTransfer->details) {
            foreach ($mhs->konversiTransfer->details as $konv) {
                $mk = $konv->mataKuliahDiakui;
                $sks = $mk ? $mk->total_sks : $konv->sks_asal;
                $huruf = strtoupper(trim((string) $konv->nilai_huruf_asal));
                $mutu = match ($huruf) {
                    'A' => 4.00,
                    'A-' => 3.75,
                    'B+' => 3.25,
                    'B' => 3.00,
                    'B-' => 2.75,
                    'C+' => 2.25,
                    'C' => 2.00,
                    'D' => 1.00,
                    'E' => 0.00,
                    default => 0.00,
                };

                // Hanya MK lulus (bukan D/E) yang menambah SKS lulus, tapi mutu tetap dihitung proporsional
                if ($huruf !== 'D' && $huruf !== 'E' && $huruf !== '') {
                    $totalSksLulus += $sks;
                    $totalMutu += ($mutu * $sks);
                }
            }
        }

        foreach ($transkripNilai as $tn) {
            $sks = $tn->krsDetail?->kelas?->mataKuliah?->total_sks ?? 3;
            $mutu = (float) $tn->bobot_mutu;
            if ($tn->nilai_huruf !== 'E' && $tn->nilai_huruf !== 'D') {
                $totalSksLulus += $sks;
                $totalMutu += ($mutu * $sks);
            }
        }

        $ipk = $totalSksLulus > 0 ? round($totalMutu / $totalSksLulus, 2) : 0.00;
        $maxSks = $ipk >= 3.00 ? 24 : ($ipk >= 2.50 ? 22 : ($ipk >= 2.00 ? 20 : 18));

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
                'max_sks' => $maxSks,
                'keuangan_status' => [
                    'has_unpaid_bills' => $hasUnpaidBills,
                    'has_approved_dispensasi' => $hasApprovedKrsDispensasi,
                    'is_locked' => $shouldLock,
                ],
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
        if (!$mhs && $request->filled('mahasiswa_id')) {
            $mhs = Mahasiswa::with(['konversiTransfer.details'])->find($request->mahasiswa_id);
        }
        if (!$mhs && $request->filled('program_studi_id')) {
            $mhs = null;
        }
        if (!$mhs && !$request->filled('program_studi_id')) {
            return response()->json(['status' => 'error', 'message' => 'Data mahasiswa tidak ditemukan.'], 404);
        }

        $taId = $request->query('tahun_akademik_id');
        $ta = $taId ? TahunAkademik::find($taId) : TahunAkademik::where('is_active', true)->first();
        if (!$ta) {
            return response()->json(['status' => 'error', 'message' => 'Tahun akademik aktif belum ditetapkan.'], 422);
        }
        $targetTaId = $ta->id;

        $prodiId = $mhs ? $mhs->program_studi_id : $request->program_studi_id;

        // Dapatkan daftar ID MK yang sudah diakui konversi transfer
        $convertedMkIds = [];
        if ($mhs && $mhs->konversiTransfer && $mhs->konversiTransfer->status === 'disetujui') {
            $convertedMkIds = $mhs->konversiTransfer->details->pluck('mata_kuliah_diakui_id')->toArray();
        }

        // Dapatkan daftar ID kelas yang sudah diambil di KRS aktif
        $enrolledKelasIds = [];
        $activeKrs = Krs::where('mahasiswa_id', $mhs?->id)->where('tahun_akademik_id', $targetTaId)->first();
        if ($activeKrs) {
            $enrolledKelasIds = $activeKrs->krsDetails()->pluck('kelas_id')->toArray();
        }

        $kelases = Kelas::with(['mataKuliah.prasyarats.prasyarat', 'ruangan', 'dosenPengampu.dosen', 'programStudis'])
            ->where('tahun_akademik_id', $targetTaId)
            ->when($prodiId, fn($q) => $q->where(function ($qq) use ($prodiId) {
                $qq->where('program_studi_id', $prodiId)
                   ->orWhereHas('programStudis', fn($pq) => $pq->where('siakad_program_studi.id', $prodiId));
            }))
            ->where('status', 'aktif')
            ->get();

        $results = $kelases->map(function ($k) use ($convertedMkIds, $enrolledKelasIds) {
            $isConverted = in_array($k->mata_kuliah_id, $convertedMkIds);
            $isEnrolled = in_array($k->id, $enrolledKelasIds);
            $isFull = $k->kuota_krs <= 0;

            return [
                'id' => $k->id,
                'kode_kelas' => $k->kode_kelas,
                'nama_kelas' => $k->nama_kelas,
                'mata_kuliah' => $k->mataKuliah,
                'ruangan' => $k->ruangan ? $k->ruangan->nama : null,
                'dosen_pengampu' => $k->dosenPengampu?->first()?->dosen?->nama_lengkap,
                'jadwal' => ($k->hari ? ucfirst($k->hari) : null) . ($k->jam_mulai && $k->jam_selesai ? ', ' . substr($k->jam_mulai, 0, 5) . ' - ' . substr($k->jam_selesai, 0, 5) : ''),
                'sisa_kuota' => $k->kuota_krs,
                'is_gabungan' => (bool) $k->is_gabungan,
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
            'mahasiswa_id' => 'nullable|exists:siakad_mahasiswa,id',
            'tahun_akademik_id' => 'nullable|exists:siakad_tahun_akademik,id',
        ]);

        $user = $request->user();
        $mhs = $this->krsService->resolveMahasiswa($user?->id, $request->input('mahasiswa_id'));

        $taId = $request->tahun_akademik_id;
        $ta = $taId ? TahunAkademik::find($taId) : TahunAkademik::where('is_active', true)->first();
        if (!$ta) {
            return response()->json(['status' => 'error', 'message' => 'Tahun akademik aktif belum ditetapkan.'], 422);
        }

        $kelas = Kelas::with('mataKuliah')->findOrFail($request->kelas_id);

        if ($kelas->kuota_krs <= 0) {
            return response()->json(['status' => 'error', 'message' => 'Kuota kelas ini sudah penuh.'], 422);
        }

        // Gate keuangan SIKEU (tetap sebelum validasi akademik agar pesan tagihan jelas)
        $krsAwal = Krs::firstOrCreate(
            ['mahasiswa_id' => $mhs->id, 'tahun_akademik_id' => $ta->id],
            ['status' => 'draft', 'total_sks_diambil' => 0, 'locked_by_keuangan' => false]
        );
        $hasUnpaidBills = \App\Models\Sikeu\TagihanMahasiswa::where('mahasiswa_id', $mhs->id)
            ->whereIn('status', ['belum_bayar', 'sebagian'])
            ->exists();
        if ($hasUnpaidBills) {
            $hasApprovedKrsDispensasi = \App\Models\Sikeu\DispensasiTagihan::where('mahasiswa_id', $mhs->id)
                ->where('status', 'approved')
                ->where('allow_krs', true)
                ->whereHas('tagihan', fn($q) => $q->whereIn('status', ['belum_bayar', 'sebagian', 'dispensasi']))
                ->exists();
            if (!$hasApprovedKrsDispensasi) {
                $krsAwal->locked_by_keuangan = true;
                $krsAwal->save();
                return response()->json([
                    'status' => 'error',
                    'message' => 'KRS terkunci karena Anda memiliki tagihan pembayaran SPP/UKT di SIKEU yang belum diselesaikan. Silakan lunasi tagihan atau ajukan dispensasi keuangan dengan izin bypass KRS.'
                ], 422);
            }
            $krsAwal->locked_by_keuangan = false;
            $krsAwal->save();
        } elseif ($krsAwal->locked_by_keuangan) {
            $krsAwal->locked_by_keuangan = false;
            $krsAwal->save();
        }

        [$krs, $kelas] = $this->krsService->addClass($mhs, (int) $ta->id, (int) $request->kelas_id, (float) ($mhs->ipk ?? 0));

        return response()->json([
            'status' => 'success',
            'message' => "Mata kuliah {$kelas->mataKuliah->nama} ({$kelas->mataKuliah->total_sks} SKS) berhasil ditambahkan ke KRS.",
            'data' => $krs->load('krsDetails.kelas.mataKuliah')
        ]);
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
        $mhs = Mahasiswa::where('user_id', $user?->id)->first();
        if (!$mhs && $request->filled('mahasiswa_id')) {
            $mhs = Mahasiswa::find($request->mahasiswa_id);
        }
        if (!$mhs) {
            return response()->json(['status' => 'error', 'message' => 'Data mahasiswa tidak ditemukan.'], 404);
        }
        $taId = $request->tahun_akademik_id;
        $ta = $taId ? TahunAkademik::find($taId) : TahunAkademik::where('is_active', true)->first();
        if (!$ta) {
            return response()->json(['status' => 'error', 'message' => 'Tahun akademik aktif belum ditetapkan.'], 422);
        }

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
        if (!$mhs && $request->filled('mahasiswa_id')) {
            $mhs = Mahasiswa::find($request->mahasiswa_id);
        }
        if (!$mhs) {
            return response()->json(['status' => 'error', 'message' => 'Data mahasiswa tidak ditemukan.'], 404);
        }

        $taAktif = TahunAkademik::where('is_active', true)->first();
        if (!$taAktif) {
            return response()->json(['status' => 'error', 'message' => 'Tahun akademik aktif belum ditetapkan.'], 422);
        }
        $krs = Krs::where('mahasiswa_id', $mhs->id)->where('tahun_akademik_id', $taAktif->id)->firstOrFail();

        if ($krs->total_sks_diambil <= 0) {
            return response()->json(['status' => 'error', 'message' => 'KRS masih kosong. Silakan pilih mata kuliah terlebih dahulu.'], 422);
        }

        // Cek apakah mahasiswa merupakan mahasiswa transfer / memiliki usulan konversi
        $hasKonversi = !empty($mhs->konversi_id) || \App\Models\Siakad\KonversiTransfer::where('mahasiswa_id', $mhs->id)->exists();

        if (!$hasKonversi) {
            // Mahasiswa Reguler: Otomatis Disetujui Sistem jika tidak ada tunggakan keuangan
            if ($krs->locked_by_keuangan) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'KRS tidak dapat diajukan/disetujui karena masih terkunci oleh tagihan keuangan (SIKEU).'
                ], 422);
            }

            $krs->status = 'disetujui';
            $krs->disetujui_oleh = $mhs->dosen_wali_id;
            $krs->disetujui_at = now();
            $krs->save();

            return response()->json([
                'status' => 'success',
                'message' => 'KRS mahasiswa reguler berhasil diverifikasi dan disetujui secara otomatis oleh sistem.',
                'data' => $krs
            ]);
        }

        // Mahasiswa Konversi / Transfer: Memerlukan validasi & persetujuan Dosen PA
        $krs->status = 'diajukan';
        $krs->save();

        return response()->json([
            'status' => 'success',
            'message' => 'KRS mahasiswa transfer berhasil diajukan ke Dosen Pembimbing Akademik (DPA) untuk peninjauan konversi nilai.',
            'data' => $krs
        ]);
    }

    public function approveKrs(Request $request, $id)
    {
        $krs = Krs::with('mahasiswa')->findOrFail($id);

        if ($krs->locked_by_keuangan) {
            return response()->json([
                'status' => 'error',
                'message' => 'KRS terkunci karena tagihan mahasiswa di SIKEU belum diselesaikan.'
            ], 422);
        }
        if ($krs->status !== 'diajukan') {
            return response()->json([
                'status' => 'error',
                'message' => 'Hanya KRS berstatus diajukan yang dapat disetujui.',
            ], 422);
        }

        $user = $request->user();
        $dosen = Dosen::where('user_id', $user?->id)->first();
        $isPrivileged = $user && ($user->isAdmin() || $user->isSuperAdmin() || $user->hasRole('kaprodi') || $user->hasRole('wakil_prodi'));

        if (!$isPrivileged) {
            if (!$dosen) {
                return response()->json(['status' => 'error', 'message' => 'Hanya Dosen PA / Kaprodi yang dapat menyetujui KRS.'], 403);
            }
            if ((int) $krs->mahasiswa?->dosen_wali_id !== (int) $dosen->id) {
                return response()->json(['status' => 'error', 'message' => 'Anda bukan Dosen PA mahasiswa ini. Persetujuan ditolak.'], 403);
            }
        }

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
        if ($mhs && !$user->isAdmin()) {
            $query->whereHas('krsDetail.krs', fn($kq) => $kq->where('mahasiswa_id', $mhs->id));
        }

        // Cek jika login sebagai Dosen
        $dosen = $user ? Dosen::where('user_id', $user->id)->first() : null;
        if ($dosen && !$user->isAdmin() && $request->boolean('my_classes_only')) {
            $query->whereHas('krsDetail.kelas.dosenPengampu', fn($dq) => $dq->where('dosen_id', $dosen->id));
        }

        $taId = $request->input('tahun_akademik_id');
        if ($taId) {
            $query->whereHas('krsDetail.krs', fn($kq) => $kq->where('tahun_akademik_id', $taId));
        }

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($wq) use ($s) {
                $wq->whereHas('krsDetail.krs.mahasiswa', fn($mq) => $mq->where('nama_lengkap', 'like', "%{$s}%")->orWhere('nim', 'like', "%{$s}%"))
                   ->orWhereHas('krsDetail.kelas.mataKuliah', fn($mkq) => $mkq->where('nama', 'like', "%{$s}%")->orWhere('kode_mk', 'like', "%{$s}%"));
            });
        }

        if ($request->filled('kelas_id')) {
            $query->whereHas('krsDetail', fn($q) => $q->where('kelas_id', $request->kelas_id));
        }

        if ($request->filled('mahasiswa_id')) {
            $query->whereHas('krsDetail.krs', fn($q) => $q->where('mahasiswa_id', $request->mahasiswa_id));
        }

        $data = $query->paginate($request->integer('per_page', 50));

        // Hitung KHS / Ringkasan IPK jika diminta untuk mahasiswa
        $summary = null;
        $summaryMhs = $mhs;
        if (!$summaryMhs && $request->filled('mahasiswa_id')) {
            $summaryMhs = Mahasiswa::find($request->mahasiswa_id);
        }
        if ($summaryMhs) {
            $khs = Khs::where('mahasiswa_id', $summaryMhs->id)
                ->when($taId, fn($q) => $q->where('tahun_akademik_id', $taId))
                ->latest()
                ->first();

            $totalSksTransfer = 0;
            if (!$khs && $summaryMhs->konversiTransfer && $summaryMhs->konversiTransfer->status === 'disetujui' && $summaryMhs->konversiTransfer->details) {
                foreach ($summaryMhs->konversiTransfer->details as $konv) {
                    $mk = $konv->mataKuliahDiakui;
                    $totalSksTransfer += $mk ? $mk->total_sks : $konv->sks_asal;
                }
            }

            $summary = [
                'mahasiswa' => $summaryMhs->load('programStudi.fakultas', 'dosenWali'),
                'ips' => (float) ($khs?->ips ?? 0.00),
                'ipk' => (float) ($khs?->ipk ?? $summaryMhs->ipk),
                'sks_semester' => (int) ($khs?->total_sks_semester ?? 0),
                'sks_total' => (int) ($khs?->sks_kumulatif ?? $totalSksTransfer),
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

        $nilai = NilaiMahasiswa::with('krsDetail.kelas.tahunAkademik', 'krsDetail.kelas.mataKuliah.kurikulum')->findOrFail($id);
        $mode = $nilai->krsDetail?->kelas?->tahunAkademik?->mode_penilaian ?? 'konvensional';
        if ($mode !== 'konvensional') {
            return response()->json([
                'status' => 'error',
                'message' => 'Nilai kelas ini dikelola via asesmen OBE (mode: ' . $mode . '). Gunakan menu Penilaian & KHS Kelas (OBE) agar sinkron dengan komponen dan CPMK.'
            ], 422);
        }
        $harian = $request->input('nilai_harian', $nilai->nilai_harian);
        $uts = $request->input('nilai_uts', $nilai->nilai_uts);
        $uas = $request->input('nilai_uas', $nilai->nilai_uas);
        $praktik = $request->input('nilai_praktik', $nilai->nilai_praktik);

        // Rumus bobot konvensional: 20% harian, 25% uts, 35% uas, 20% praktik
        $akhir = ($harian * 0.20) + ($uts * 0.25) + ($uas * 0.35) + ($praktik * 0.20);

        $prodiId = $nilai->krsDetail?->kelas?->mataKuliah?->kurikulum?->program_studi_id;
        $skala = SkalaNilai::konversiNilai((float) $akhir, $prodiId);
        if ($skala) {
            $huruf = $skala->nilai_huruf;
            $mutu = (float) $skala->bobot_indeks;
        } else {
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
        }

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
        $totalSksDiambil = 0;
        $totalSksLulus = 0;
        $totalBobotMutu = 0;

        // Masukkan data konversi transfer jika ada — mutu via master skala nilai
        if ($mhs->konversiTransfer && $mhs->konversiTransfer->status === 'disetujui' && $mhs->konversiTransfer->details) {
            foreach ($mhs->konversiTransfer->details as $konv) {
                $mk = $konv->mataKuliahDiakui;
                $sks = $mk ? $mk->total_sks : $konv->sks_asal;
                $huruf = strtoupper(trim((string) $konv->nilai_huruf_asal));
                $skalaTransfer = SkalaNilai::where('is_active', true)
                    ->where('nilai_huruf', $huruf)
                    ->orderByRaw('program_studi_id IS NULL')
                    ->first();
                $mutu = $skalaTransfer ? (float) $skalaTransfer->bobot_indeks : match ($huruf) {
                    'A' => 4.00,
                    'A-' => 3.75,
                    'B+' => 3.25,
                    'B' => 3.00,
                    'B-' => 2.75,
                    'C+' => 2.25,
                    'C' => 2.00,
                    'D' => 1.00,
                    'E' => 0.00,
                    default => 0.00,
                };

                if ($huruf === 'D' || $huruf === 'E' || $huruf === '') {
                    continue;
                }
                $bobot = $mutu * $sks;
                $totalSksDiambil += $sks;
                $totalSksLulus += $sks;
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

        // Masukkan mata kuliah reguler — ambil nilai terbaik per MK (mengulang)
        $bestByMk = [];
        foreach ($nilaiList as $n) {
            $kelas = $n->krsDetail?->kelas;
            $mk = $kelas?->mataKuliah;
            $mkKey = $mk?->id ?? ('krs-' . $n->krs_detail_id);
            if (!isset($bestByMk[$mkKey]) || (float) $n->bobot_mutu > (float) $bestByMk[$mkKey]->bobot_mutu) {
                $bestByMk[$mkKey] = $n;
            }
        }
        foreach ($bestByMk as $n) {
            $krs = $n->krsDetail?->krs;
            $kelas = $n->krsDetail?->kelas;
            $mk = $kelas?->mataKuliah;
            $sks = $mk?->total_sks ?? 3;
            $mutu = (float) $n->bobot_mutu;
            $bobot = $mutu * $sks;

            $totalSksDiambil += $sks;
            if (($n->nilai_huruf ?? 'E') !== 'E') {
                $totalSksLulus += $sks;
            }
            $totalBobotMutu += $bobot;

            $items[] = [
                'semester_label' => $krs?->tahunAkademik?->nama ?? 'Semester Reguler',
                'tahun_akademik_id' => $krs?->tahun_akademik_id,
                'kode_mk' => $mk?->kode_mk ?? 'MK',
                'nama_mk' => $mk?->nama ?? 'Mata Kuliah',
                'sks' => $sks,
                'nilai_huruf' => $n->nilai_huruf ?? 'E',
                'bobot_mutu' => $mutu,
                'mutu_x_sks' => $bobot,
                'is_transfer' => false,
            ];
        }

        $ipk = $totalSksDiambil > 0 ? round($totalBobotMutu / $totalSksDiambil, 2) : 0.00;

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
                    'total_sks' => $totalSksDiambil,
                    'total_sks_lulus' => $totalSksLulus,
                    'total_mutu' => round($totalBobotMutu, 2),
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

    private function checkRpsApproved(Kelas $kelas)
    {
        $hasApprovedRps = \App\Models\Siakad\Rps::where('mata_kuliah_id', $kelas->mata_kuliah_id)
            ->where('status', 'disetujui')
            ->exists();

        if (!$hasApprovedRps) {
            abort(response()->json([
                'status' => 'error',
                'message' => 'Aksi ditolak. Rencana Pembelajaran Semester (RPS) mata kuliah ini belum disetujui oleh Kaprodi/Wakil Kaprodi.'
            ], 403));
        }
    }

    public function listPertemuan(Request $request, $kelasId)
    {
        $kelas = Kelas::findOrFail($kelasId);
        $pertemuans = \App\Models\Siakad\Pertemuan::where('kelas_id', $kelasId)
            ->orderBy('pertemuan_ke')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $pertemuans
        ]);
    }

    public function storePertemuan(Request $request, $kelasId)
    {
        $kelas = Kelas::findOrFail($kelasId);
        $this->checkRpsApproved($kelas);

        $request->validate([
            'pertemuan_ke' => 'required|integer|min:1|max:16',
            'tanggal' => 'required|date',
            'materi' => 'nullable|string|max:255',
            'jam_mulai' => 'nullable|string',
            'jam_selesai' => 'nullable|string',
        ]);

        $pertemuan = \App\Models\Siakad\Pertemuan::updateOrCreate(
            ['kelas_id' => $kelasId, 'pertemuan_ke' => $request->pertemuan_ke],
            $request->only(['tanggal', 'materi', 'jam_mulai', 'jam_selesai'])
        );

        // Auto create attendance logs for all enrolled students (via KRS -> mahasiswa_id)
        $enrolledStudents = \App\Models\Siakad\KrsDetail::where('kelas_id', $kelasId)
            ->whereHas('krs', fn($q) => $q->whereIn('status', ['disetujui', 'diajukan']))
            ->with('krs')
            ->get()
            ->map(fn($d) => $d->krs?->mahasiswa_id)
            ->filter()
            ->unique()
            ->values();

        foreach ($enrolledStudents as $mhsId) {
            \App\Models\Siakad\AbsensiMahasiswa::firstOrCreate(
                ['pertemuan_id' => $pertemuan->id, 'mahasiswa_id' => $mhsId],
                ['status' => 'hadir']
            );
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Pertemuan berhasil disimpan dan daftar absensi mahasiswa telah digenerate.',
            'data' => $pertemuan
        ]);
    }

    public function listAbsensi(Request $request, $pertemuanId)
    {
        $pertemuan = \App\Models\Siakad\Pertemuan::with('kelas.mataKuliah')->findOrFail($pertemuanId);
        $absensi = \App\Models\Siakad\AbsensiMahasiswa::with('mahasiswa')
            ->where('pertemuan_id', $pertemuanId)
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => [
                'pertemuan' => $pertemuan,
                'absensi' => $absensi
            ]
        ]);
    }

    public function storeAbsensi(StoreAbsensiRequest $request, $pertemuanId)
    {
        $pertemuan = \App\Models\Siakad\Pertemuan::findOrFail($pertemuanId);
        $kelas = Kelas::findOrFail($pertemuan->kelas_id);
        $this->checkRpsApproved($kelas);

        $validated = $request->validated();

        foreach ($validated['absensi'] as $item) {
            \App\Models\Siakad\AbsensiMahasiswa::updateOrCreate(
                ['pertemuan_id' => $pertemuanId, 'mahasiswa_id' => $item['mahasiswa_id']],
                ['status' => $item['status'], 'catatan' => $item['catatan'] ?? null]
            );
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Data absensi mahasiswa berhasil disimpan.'
        ]);
    }
}
