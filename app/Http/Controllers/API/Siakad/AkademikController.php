<?php

namespace App\Http\Controllers\API\Siakad;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Siakad\Fakultas;
use App\Models\Siakad\ProgramStudi;
use App\Models\Siakad\Kurikulum;
use App\Models\Siakad\MataKuliah;
use App\Models\Siakad\Dosen;
use App\Models\Siakad\Mahasiswa;
use App\Models\Siakad\Kelas;
use App\Models\Spmb\MasterTahunAkademik;
use App\Models\System\MasterReferensi;
use App\Http\Requests\Siakad\StoreMataKuliahRequest;
use App\Http\Requests\Siakad\UpdateMataKuliahRequest;
use App\Http\Requests\Siakad\StorePrasyaratMkRequest;
use App\Http\Requests\Siakad\UpdateModePenilaianRequest;
use App\Http\Requests\Siakad\StoreProgramStudiRequest;
use App\Http\Requests\Siakad\UpdateProgramStudiRequest;
use Illuminate\Validation\Rule;

class AkademikController extends Controller
{
    /**
     * Opsi dropdown master akademik untuk form SIAKAD.
     * Sumber: tabel master referensi (modul siakad/global), bukan literal kode.
     * GET /akademik/referensi-options?tipe=jenjang_prodi
     */
    public function listReferensiOptions(Request $request)
    {
        $request->validate([
            'tipe' => 'required|string|max:50',
        ]);

        $data = MasterReferensi::query()
            ->where('tipe', $request->tipe)
            ->whereIn('modul', ['siakad', 'global'])
            ->where('is_active', true)
            ->orderBy('urutan')
            ->orderBy('nama')
            ->get(['kode', 'nama', 'urutan']);

        return response()->json([
            'status' => 'success',
            'message' => 'Opsi referensi akademik berhasil dimuat.',
            'data' => $data,
        ]);
    }

    public function dashboardSummary()
    {
        $taAktif = MasterTahunAkademik::where('is_active', true)->first();
        $totalMhsAktif = Mahasiswa::where('status', 'aktif')->count();
        $totalDosen = Dosen::where('is_active', true)->count();
        $totalKelas = Kelas::when($taAktif, fn($q) => $q->where('tahun_akademik_id', $taAktif->id))->count();
        $totalKurikulum = Kurikulum::where('is_active', true)->count();
        $totalMataKuliah = MataKuliah::where('is_active', true)->count();

        return response()->json([
            'status' => 'success',
            'message' => 'Ringkasan dashboard akademik berhasil dimuat',
            'data' => [
                'tahun_akademik_aktif' => $taAktif,
                'total_mahasiswa_aktif' => $totalMhsAktif,
                'total_dosen' => $totalDosen,
                'total_kelas' => $totalKelas,
                'total_kurikulum' => $totalKurikulum,
                'total_matakuliah' => $totalMataKuliah,
            ]
        ]);
    }

    public function listTahunAkademik(Request $request)
    {
        $user = $request->user();
        $query = MasterTahunAkademik::query();

        // Jika request berasal dari mahasiswa (atau menyertakan mahasiswa_id)
        $mhs = $user ? Mahasiswa::where('user_id', $user->id)->first() : null;
        if (!$mhs && $request->filled('mahasiswa_id')) {
            $mhs = Mahasiswa::find($request->mahasiswa_id);
        }

        if ($mhs && (!$user || !$user->isAdmin())) {
            $angkatan = (int) ($mhs->angkatan ?: 2026);
            $krsTaIds = \App\Models\Siakad\Krs::where('mahasiswa_id', $mhs->id)->pluck('tahun_akademik_id')->toArray();

            $query->where(function($q) use ($angkatan, $krsTaIds) {
                $q->where('tahun_mulai', '>=', $angkatan)
                  ->orWhere('kode', '>=', (string)$angkatan . '1')
                  ->orWhereIn('id', $krsTaIds);
            });
        }

        $ta = $query->orderBy('kode', 'desc')->get();
        return response()->json([
            'status' => 'success',
            'data' => $ta
        ]);
    }

    public function storeTahunAkademik(Request $request)
    {
        $request->validate([
            'kode' => 'required|string|unique:spmb_master_tahun_akademik,kode',
            'nama' => 'required|string|max:255',
            'tahun_mulai' => 'nullable|integer',
            'tahun_selesai' => 'nullable|integer',
            'is_active' => 'boolean',
            'krs_mulai' => 'nullable|date',
            'krs_selesai' => 'nullable|date|after_or_equal:krs_mulai',
            'kprs_mulai' => 'nullable|date',
            'kprs_selesai' => 'nullable|date|after_or_equal:kprs_mulai',
            'perkuliahan_mulai' => 'nullable|date',
            'perkuliahan_selesai' => 'nullable|date|after_or_equal:perkuliahan_mulai',
            'input_nilai_mulai' => 'nullable|date',
            'input_nilai_selesai' => 'nullable|date|after_or_equal:input_nilai_mulai',
        ]);

        if ($request->boolean('is_active')) {
            MasterTahunAkademik::query()->update(['is_active' => false]);
        }

        $ta = MasterTahunAkademik::create($request->all());

        return response()->json([
            'status' => 'success',
            'message' => 'Periode Tahun Akademik berhasil ditambahkan',
            'data' => $ta
        ], 201);
    }

    public function updateTahunAkademik(Request $request, $id)
    {
        $ta = MasterTahunAkademik::findOrFail($id);

        $request->validate([
            'nama' => 'required|string|max:255',
            'tahun_mulai' => 'nullable|integer',
            'tahun_selesai' => 'nullable|integer',
            'is_active' => 'boolean',
            'krs_mulai' => 'nullable|date',
            'krs_selesai' => 'nullable|date',
            'kprs_mulai' => 'nullable|date',
            'kprs_selesai' => 'nullable|date',
            'perkuliahan_mulai' => 'nullable|date',
            'perkuliahan_selesai' => 'nullable|date',
            'input_nilai_mulai' => 'nullable|date',
            'input_nilai_selesai' => 'nullable|date',
        ]);

        if ($request->boolean('is_active') && !$ta->is_active) {
            MasterTahunAkademik::query()->update(['is_active' => false]);
        }

        $ta->update($request->all());

        return response()->json([
            'status' => 'success',
            'message' => 'Data dan kalender tahun akademik berhasil diperbarui',
            'data' => $ta
        ]);
    }

    public function setActiveTahunAkademik(Request $request, $id)
    {
        $target = MasterTahunAkademik::findOrFail($id);

        // Nonaktifkan semua tahun akademik lain
        MasterTahunAkademik::query()->update(['is_active' => false]);

        // Aktifkan tahun akademik terpilih
        $target->update(['is_active' => true]);

        return response()->json([
            'status' => 'success',
            'message' => "Tahun Akademik {$target->nama} ({$target->kode}) berhasil diaktifkan sebagai periode semester berjalan.",
            'data' => $target
        ]);
    }

    // --- SKALA NILAI AKADEMIK CRUD ---
    public function listSkalaNilai(Request $request)
    {
        $query = \App\Models\Siakad\SkalaNilai::with('programStudi');

        if ($request->filled('program_studi_id')) {
            $query->where('program_studi_id', $request->program_studi_id);
        }

        $data = $query->orderBy('bobot_indeks', 'desc')->get();

        return response()->json([
            'status' => 'success',
            'message' => 'Data skala nilai berhasil dimuat',
            'data' => $data,
        ]);
    }

    public function storeSkalaNilai(Request $request)
    {
        $validated = $request->validate([
            'program_studi_id' => 'nullable|exists:siakad_program_studi,id',
            'nilai_huruf' => 'required|string|max:5',
            'bobot_indeks' => 'required|numeric|min:0|max:4',
            'batas_bawah' => 'required|numeric|min:0|max:100',
            'batas_atas' => 'required|numeric|min:0|max:100|gte:batas_bawah',
            'is_lulus' => 'boolean',
            'keterangan' => 'nullable|string|max:100',
            'is_active' => 'boolean',
        ]);

        $item = \App\Models\Siakad\SkalaNilai::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Skala nilai mutu berhasil ditambahkan',
            'data' => $item->load('programStudi')
        ], 201);
    }

    public function updateSkalaNilai(Request $request, $id)
    {
        $item = \App\Models\Siakad\SkalaNilai::findOrFail($id);

        $validated = $request->validate([
            'program_studi_id' => 'nullable|exists:siakad_program_studi,id',
            'nilai_huruf' => 'required|string|max:5',
            'bobot_indeks' => 'required|numeric|min:0|max:4',
            'batas_bawah' => 'required|numeric|min:0|max:100',
            'batas_atas' => 'required|numeric|min:0|max:100|gte:batas_bawah',
            'is_lulus' => 'boolean',
            'keterangan' => 'nullable|string|max:100',
            'is_active' => 'boolean',
        ]);

        $item->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Skala nilai mutu berhasil diperbarui',
            'data' => $item->load('programStudi')
        ]);
    }

    public function destroySkalaNilai($id)
    {
        $item = \App\Models\Siakad\SkalaNilai::findOrFail($id);
        $item->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Skala nilai mutu berhasil dihapus'
        ]);
    }

    // --- FAKULTAS CRUD ---
    public function listFakultas(Request $request)
    {
        $query = Fakultas::with('programStudis');
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(fn($q) => $q->where('nama', 'like', "%{$s}%")->orWhere('kode', 'like', "%{$s}%"));
        }
        $fakultas = $query->where('is_active', true)->get();
        return response()->json([
            'status' => 'success',
            'data' => $fakultas
        ]);
    }

    public function storeFakultas(Request $request)
    {
        $request->validate([
            'kode' => 'required|string|unique:siakad_fakultas,kode',
            'nama' => 'required|string|max:255',
            'nama_singkat' => 'nullable|string|max:50',
            'telepon' => 'nullable|string',
            'email' => 'nullable|email',
        ]);

        $fakultas = Fakultas::create($request->all());

        return response()->json([
            'status' => 'success',
            'message' => 'Fakultas berhasil ditambahkan',
            'data' => $fakultas
        ], 201);
    }

    public function updateFakultas(Request $request, $id)
    {
        $fakultas = Fakultas::findOrFail($id);
        $request->validate([
            'nama' => 'required|string|max:255',
            'nama_singkat' => 'nullable|string|max:50',
            'telepon' => 'nullable|string',
            'email' => 'nullable|email',
        ]);

        $fakultas->update($request->all());

        return response()->json([
            'status' => 'success',
            'message' => 'Fakultas berhasil diperbarui',
            'data' => $fakultas
        ]);
    }

    public function destroyFakultas($id)
    {
        $fakultas = Fakultas::findOrFail($id);
        if ($fakultas->programStudis()->exists()) {
            return response()->json(['status' => 'error', 'message' => 'Fakultas tidak dapat dihapus karena masih memiliki program studi.'], 422);
        }
        $fakultas->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Fakultas berhasil dihapus'
        ]);
    }

    // --- PROGRAM STUDI CRUD ---
    public function listProgramStudi(Request $request)
    {
        $query = ProgramStudi::with(['fakultas', 'kaprodi.user']);
        if ($request->filled('fakultas_id')) {
            $query->where('fakultas_id', $request->fakultas_id);
        }
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(fn($q) => $q->where('nama', 'like', "%{$s}%")->orWhere('kode_prodi', 'like', "%{$s}%"));
        }
        $prodis = $query->where('is_active', true)->get();
        return response()->json([
            'status' => 'success',
            'data' => $prodis
        ]);
    }

    public function storeProgramStudi(StoreProgramStudiRequest $request)
    {
        $prodi = ProgramStudi::create($request->validated());

        return response()->json([
            'status' => 'success',
            'message' => 'Program Studi berhasil ditambahkan',
            'data' => $prodi->load(['fakultas', 'kaprodi.user'])
        ], 201);
    }

    public function updateProgramStudi(UpdateProgramStudiRequest $request, $id)
    {
        $prodi = ProgramStudi::findOrFail($id);

        $prodi->update($request->validated());

        return response()->json([
            'status' => 'success',
            'message' => 'Program Studi berhasil diperbarui',
            'data' => $prodi->load(['fakultas', 'kaprodi.user'])
        ]);
    }

    public function destroyProgramStudi($id)
    {
        $prodi = ProgramStudi::findOrFail($id);
        if (Kurikulum::where('program_studi_id', $prodi->id)->exists() || Mahasiswa::where('program_studi_id', $prodi->id)->exists()) {
            return response()->json(['status' => 'error', 'message' => 'Program studi tidak dapat dihapus karena masih memiliki kurikulum/mahasiswa.'], 422);
        }
        $prodi->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Program Studi berhasil dihapus'
        ]);
    }

    // --- KURIKULUM CRUD ---
    public function listKurikulum(Request $request)
    {
        $query = Kurikulum::with(['programStudi', 'mataKuliahs']);

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(fn($q) => $q->where('nama', 'like', "%{$s}%")->orWhere('kode', 'like', "%{$s}%"));
        }

        if ($request->filled('program_studi_id')) {
            $query->where('program_studi_id', $request->program_studi_id);
        }

        $data = $query->paginate($request->integer('per_page', 15));

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

    public function storeKurikulum(Request $request)
    {
        $request->validate([
            'program_studi_id' => ['required', Rule::exists(ProgramStudi::class, 'id')],
            'kode' => 'required|string|unique:siakad_kurikulum,kode',
            'nama' => 'required|string|max:255',
            'tahun_berlaku' => 'required|integer',
            'total_sks_lulus' => 'required|integer|min:100',
            'deskripsi' => 'nullable|string',
        ]);

        $kurikulum = Kurikulum::create($request->all());

        return response()->json([
            'status' => 'success',
            'message' => 'Kurikulum berhasil ditambahkan',
            'data' => $kurikulum
        ], 201);
    }

    public function updateKurikulum(Request $request, $id)
    {
        $kurikulum = Kurikulum::findOrFail($id);
        $request->validate([
            'nama' => 'required|string|max:255',
            'tahun_berlaku' => 'required|integer',
            'total_sks_lulus' => 'required|integer|min:100',
            'deskripsi' => 'nullable|string',
        ]);

        $kurikulum->update($request->all());

        return response()->json([
            'status' => 'success',
            'message' => 'Kurikulum berhasil diperbarui',
            'data' => $kurikulum
        ]);
    }

    public function destroyKurikulum($id)
    {
        $kurikulum = Kurikulum::findOrFail($id);
        if ($kurikulum->mataKuliahs()->exists()) {
            return response()->json(['status' => 'error', 'message' => 'Kurikulum tidak dapat dihapus karena masih memiliki mata kuliah.'], 422);
        }
        $kurikulum->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Kurikulum berhasil dihapus'
        ]);
    }

    // --- MATA KULIAH CRUD ---
    public function listMataKuliah(Request $request)
    {
        $query = MataKuliah::with(['kurikulum.programStudi', 'prasyarats.prasyarat']);

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(fn($q) => $q->where('nama', 'like', "%{$s}%")->orWhere('kode_mk', 'like', "%{$s}%"));
        }

        if ($request->filled('program_studi_id')) {
            $query->whereHas('kurikulum', function ($q) use ($request) {
                $q->where('program_studi_id', $request->program_studi_id);
            });
        }

        if ($request->filled('kurikulum_id')) {
            $query->where('kurikulum_id', $request->kurikulum_id);
        }

        if ($request->filled('tipe')) {
            $query->where('tipe', $request->tipe);
        }

        $data = $query->paginate($request->integer('per_page', 20));

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

    public function storeMataKuliah(StoreMataKuliahRequest $request)
    {
        $validated = $request->validated();

        $totalSks = $validated['sks_teori'] + $validated['sks_praktik'];
        $mk = MataKuliah::create(array_merge($validated, ['total_sks' => $totalSks]));

        return response()->json([
            'status' => 'success',
            'message' => 'Mata kuliah berhasil ditambahkan',
            'data' => $mk
        ], 201);
    }

    public function updateMataKuliah(UpdateMataKuliahRequest $request, $id)
    {
        $mk = MataKuliah::findOrFail($id);
        $validated = $request->validated();

        $totalSks = $validated['sks_teori'] + $validated['sks_praktik'];
        $mk->update(array_merge($validated, ['total_sks' => $totalSks]));

        return response()->json([
            'status' => 'success',
            'message' => 'Mata kuliah berhasil diperbarui',
            'data' => $mk
        ]);
    }

    public function destroyMataKuliah($id)
    {
        $mk = MataKuliah::findOrFail($id);
        if (Kelas::where('mata_kuliah_id', $mk->id)->exists()) {
            return response()->json(['status' => 'error', 'message' => 'Mata kuliah tidak dapat dihapus karena sudah dipakai di kelas perkuliahan.'], 422);
        }
        $mk->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Mata kuliah berhasil dihapus'
        ]);
    }

    // --- DOSEN CRUD ---
    public function listDosen(Request $request)
    {
        $query = Dosen::with(['programStudi']);

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(fn($q) => $q->where('nama_lengkap', 'like', "%{$s}%")
                ->orWhere('nidn', 'like', "%{$s}%")
                ->orWhere('nuptk', 'like', "%{$s}%")
                ->orWhere('nip', 'like', "%{$s}%")
                ->orWhere('nik', 'like', "%{$s}%"));
        }

        if ($request->filled('program_studi_id')) {
            $query->where('program_studi_id', $request->program_studi_id);
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Sorting whitelist
        $allowedSort = ['nama_lengkap', 'nidn', 'nuptk', 'nip', 'created_at'];
        $sortBy = in_array($request->sort_by, $allowedSort, true) ? $request->sort_by : 'nama_lengkap';
        $sortOrder = strtolower($request->sort_order ?? 'asc') === 'desc' ? 'desc' : 'asc';
        $query->orderBy($sortBy, $sortOrder);

        $perPage = min(500, max(1, $request->integer('per_page', 500)));
        $data = $query->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'data' => $data->items(),
            'meta' => [
                'current_page' => $data->currentPage(),
                'per_page' => $data->perPage(),
                'total' => $data->total(),
                'last_page' => $data->lastPage(),
            ]
        ]);
    }

    public function storeDosen(Request $request)
    {
        $validated = $request->validate([
            'nama_lengkap' => 'required|string|max:255',
            'nidn' => 'nullable|string|max:30|unique:siakad_dosen,nidn',
            'nuptk' => 'nullable|string|max:30|unique:siakad_dosen,nuptk',
            'nip' => 'nullable|string|max:30',
            'nik' => 'nullable|string|max:30',
            'program_studi_id' => ['required', Rule::exists(ProgramStudi::class, 'id')],
            'jabatan_akademik' => 'nullable|string|max:100',
            'gelar_depan' => 'nullable|string|max:50',
            'gelar_belakang' => 'nullable|string|max:50',
            'jenis_kelamin' => 'nullable|in:L,P',
            'tempat_lahir' => 'nullable|string|max:100',
            'tanggal_lahir' => 'nullable|date',
            'agama' => 'nullable|string|max:50',
            'telepon' => 'nullable|string|max:30',
            'handphone' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:100',
            'status_aktif' => 'nullable|string|max:50',
            'is_active' => 'nullable|boolean',
        ]);

        // Whitelist mass-assignment protection
        $allowedFields = [
            'nama_lengkap', 'nidn', 'nuptk', 'nip', 'nik', 'program_studi_id',
            'jabatan_akademik', 'gelar_depan', 'gelar_belakang', 'jenis_kelamin',
            'tempat_lahir', 'tanggal_lahir', 'agama', 'telepon', 'handphone',
            'email', 'status_aktif', 'is_active',
        ];
        $payload = array_intersect_key($validated, array_flip($allowedFields));

        $dosen = Dosen::create($payload);

        return response()->json([
            'status' => 'success',
            'message' => 'Dosen berhasil ditambahkan',
            'data' => $dosen->load('programStudi')
        ], 201);
    }

    public function updateDosen(Request $request, $id)
    {
        $dosen = Dosen::findOrFail($id);
        $validated = $request->validate([
            'nama_lengkap' => 'required|string|max:255',
            'nidn' => ['nullable', 'string', 'max:30', Rule::unique('siakad_dosen', 'nidn')->ignore($dosen->id)],
            'nuptk' => ['nullable', 'string', 'max:30', Rule::unique('siakad_dosen', 'nuptk')->ignore($dosen->id)],
            'nip' => 'nullable|string|max:30',
            'nik' => 'nullable|string|max:30',
            'program_studi_id' => ['required', Rule::exists(ProgramStudi::class, 'id')],
            'jabatan_akademik' => 'nullable|string|max:100',
            'gelar_depan' => 'nullable|string|max:50',
            'gelar_belakang' => 'nullable|string|max:50',
            'jenis_kelamin' => 'nullable|in:L,P',
            'tempat_lahir' => 'nullable|string|max:100',
            'tanggal_lahir' => 'nullable|date',
            'agama' => 'nullable|string|max:50',
            'telepon' => 'nullable|string|max:30',
            'handphone' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:100',
            'status_aktif' => 'nullable|string|max:50',
            'is_active' => 'nullable|boolean',
        ]);

        // Whitelist mass-assignment protection
        $allowedFields = [
            'nama_lengkap', 'nidn', 'nuptk', 'nip', 'nik', 'program_studi_id',
            'jabatan_akademik', 'gelar_depan', 'gelar_belakang', 'jenis_kelamin',
            'tempat_lahir', 'tanggal_lahir', 'agama', 'telepon', 'handphone',
            'email', 'status_aktif', 'is_active',
        ];
        $payload = array_intersect_key($validated, array_flip($allowedFields));

        $dosen->update($payload);

        return response()->json([
            'status' => 'success',
            'message' => 'Data dosen berhasil diperbarui',
            'data' => $dosen->fresh()->load('programStudi')
        ]);
    }

    public function destroyDosen($id)
    {
        $dosen = Dosen::findOrFail($id);
        if (\App\Models\Siakad\DosenPengampu::where('dosen_id', $dosen->id)->exists() || Mahasiswa::where('dosen_wali_id', $dosen->id)->exists()) {
            return response()->json(['status' => 'error', 'message' => 'Dosen tidak dapat dihapus karena masih menjadi pengampu/PA.'], 422);
        }
        $dosen->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Dosen berhasil dihapus'
        ]);
    }

    // --- PRASYARAT MATA KULIAH CRUD (BAAK) ---
    public function listPrasyaratMk(Request $request)
    {
        $query = \App\Models\Siakad\PrasyaratMk::with(['mataKuliah', 'prasyarat']);
        if ($request->filled('mata_kuliah_id')) {
            $query->where('mata_kuliah_id', $request->mata_kuliah_id);
        }
        $perPage = min(100, $request->integer('per_page', 15));
        $data = $query->paginate($perPage);
        return response()->json([
            'status' => 'success',
            'message' => 'Data prasyarat mata kuliah berhasil dimuat',
            'data' => $data->items(),
            'meta' => [
                'current_page' => $data->currentPage(),
                'per_page' => $data->perPage(),
                'total' => $data->total(),
                'last_page' => $data->lastPage(),
            ],
        ]);
    }

    public function storePrasyaratMk(StorePrasyaratMkRequest $request)
    {
        $validated = $request->validated();
        $exists = \App\Models\Siakad\PrasyaratMk::where('mata_kuliah_id', $validated['mata_kuliah_id'])
            ->where('prasyarat_id', $validated['prasyarat_id'])->exists();
        if ($exists) {
            return response()->json(['status' => 'error', 'message' => 'Prasyarat tersebut sudah terdaftar.'], 422);
        }
        $prasyarat = \App\Models\Siakad\PrasyaratMk::create($validated);
        return response()->json(['status' => 'success', 'message' => 'Prasyarat mata kuliah berhasil ditambahkan', 'data' => $prasyarat->load(['mataKuliah', 'prasyarat'])], 201);
    }

    public function destroyPrasyaratMk($id)
    {
        $prasyarat = \App\Models\Siakad\PrasyaratMk::findOrFail($id);
        $prasyarat->delete();
        return response()->json(['status' => 'success', 'message' => 'Prasyarat mata kuliah berhasil dihapus']);
    }

    public function updateModePenilaian(UpdateModePenilaianRequest $request, $id)
    {
        $validated = $request->validated();

        $ta = \App\Models\Spmb\MasterTahunAkademik::findOrFail($id);
        $ta->update(['mode_penilaian' => $validated['mode_penilaian']]);
        
        return response()->json([
            'status' => 'success',
            'message' => 'Mode penilaian periode akademik berhasil diperbarui.',
        ]);
    }
}
