<?php

namespace App\Http\Controllers\Api\Siakad;

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

class AkademikController extends Controller
{
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

        if ($mhs && (!$user || !$user->roles()->whereIn('slug', ['superadmin', 'admin'])->exists())) {
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

    public function storeProgramStudi(Request $request)
    {
        $request->validate([
            'fakultas_id' => 'required|exists:siakad_fakultas,id',
            'kaprodi_id' => 'nullable|exists:siakad_dosen,id',
            'kode_prodi' => 'required|string|unique:master_program_studi,kode_prodi',
            'kode_prodi_dikti' => 'nullable|string|max:50',
            'nama' => 'required|string|max:255',
            'jenjang' => 'required|string|max:10',
            'akreditasi' => 'nullable|string|max:10',
        ]);

        $prodi = ProgramStudi::create($request->all());

        return response()->json([
            'status' => 'success',
            'message' => 'Program Studi berhasil ditambahkan',
            'data' => $prodi->load(['fakultas', 'kaprodi.user'])
        ], 201);
    }

    public function updateProgramStudi(Request $request, $id)
    {
        $prodi = ProgramStudi::findOrFail($id);
        $request->validate([
            'fakultas_id' => 'required|exists:siakad_fakultas,id',
            'kaprodi_id' => 'nullable|exists:siakad_dosen,id',
            'nama' => 'required|string|max:255',
            'kode_prodi_dikti' => 'nullable|string|max:50',
            'jenjang' => 'required|string|max:10',
            'akreditasi' => 'nullable|string|max:10',
        ]);

        $prodi->update($request->all());

        return response()->json([
            'status' => 'success',
            'message' => 'Program Studi berhasil diperbarui',
            'data' => $prodi->load(['fakultas', 'kaprodi.user'])
        ]);
    }

    public function destroyProgramStudi($id)
    {
        $prodi = ProgramStudi::findOrFail($id);
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
            'program_studi_id' => 'required|exists:master_program_studi,id',
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

    public function storeMataKuliah(Request $request)
    {
        $request->validate([
            'kurikulum_id' => 'required|exists:siakad_kurikulum,id',
            'kode_mk' => 'required|string|unique:siakad_mata_kuliah,kode_mk',
            'nama' => 'required|string|max:255',
            'sks_teori' => 'required|integer|min:0',
            'sks_praktik' => 'required|integer|min:0',
            'semester_anjuran' => 'required|integer|min:1|max:8',
            'tipe' => 'required|in:wajib,pilihan,wajib_prodi',
        ]);

        $totalSks = $request->sks_teori + $request->sks_praktik;
        $mk = MataKuliah::create(array_merge($request->all(), ['total_sks' => $totalSks]));

        return response()->json([
            'status' => 'success',
            'message' => 'Mata kuliah berhasil ditambahkan',
            'data' => $mk
        ], 201);
    }

    public function updateMataKuliah(Request $request, $id)
    {
        $mk = MataKuliah::findOrFail($id);
        $request->validate([
            'nama' => 'required|string|max:255',
            'sks_teori' => 'required|integer|min:0',
            'sks_praktik' => 'required|integer|min:0',
            'semester_anjuran' => 'required|integer|min:1|max:8',
            'tipe' => 'required|in:wajib,pilihan,wajib_prodi',
        ]);

        $totalSks = $request->sks_teori + $request->sks_praktik;
        $mk->update(array_merge($request->all(), ['total_sks' => $totalSks]));

        return response()->json([
            'status' => 'success',
            'message' => 'Mata kuliah berhasil diperbarui',
            'data' => $mk
        ]);
    }

    public function destroyMataKuliah($id)
    {
        $mk = MataKuliah::findOrFail($id);
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
            $query->where(fn($q) => $q->where('nama_lengkap', 'like', "%{$s}%")->orWhere('nidn', 'like', "%{$s}%"));
        }

        if ($request->filled('program_studi_id')) {
            $query->where('program_studi_id', $request->program_studi_id);
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

    public function storeDosen(Request $request)
    {
        $request->validate([
            'nama_lengkap' => 'required|string|max:255',
            'nidn' => 'nullable|string|unique:siakad_dosen,nidn',
            'nip' => 'nullable|string',
            'program_studi_id' => 'required|exists:master_program_studi,id',
            'jabatan_akademik' => 'nullable|string',
        ]);

        $dosen = Dosen::create($request->all());

        return response()->json([
            'status' => 'success',
            'message' => 'Dosen berhasil ditambahkan',
            'data' => $dosen
        ], 201);
    }

    public function updateDosen(Request $request, $id)
    {
        $dosen = Dosen::findOrFail($id);
        $request->validate([
            'nama_lengkap' => 'required|string|max:255',
            'nip' => 'nullable|string',
            'program_studi_id' => 'required|exists:master_program_studi,id',
            'jabatan_akademik' => 'nullable|string',
        ]);

        $dosen->update($request->all());

        return response()->json([
            'status' => 'success',
            'message' => 'Data dosen berhasil diperbarui',
            'data' => $dosen
        ]);
    }

    public function destroyDosen($id)
    {
        $dosen = Dosen::findOrFail($id);
        $dosen->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Dosen berhasil dihapus'
        ]);
    }

    // --- INTEGRASI SIMPEG PEGAWAI / DOSEN ---
    public function syncDosenFromSimpeg(Request $request)
    {
        $pegawais = \App\Models\Simpeg\Pegawai::where(function($q) {
            $q->where('jenis_pegawai', 'like', '%dosen%')
              ->orWhereNotNull('nip')
              ->orWhereNotNull('sinta_id');
        })->get();

        if ($pegawais->isEmpty()) {
            $pegawais = \App\Models\Simpeg\Pegawai::all();
        }

        $defaultProdi = ProgramStudi::first();
        $syncedCount = 0;

        foreach ($pegawais as $p) {
            Dosen::updateOrCreate(
                ['pegawai_id' => $p->id],
                [
                    'user_id' => $p->user_id,
                    'nip' => $p->nip,
                    'nidn' => $p->nip ?? ('04' . str_pad($p->id, 8, '0', STR_PAD_LEFT)),
                    'nama_lengkap' => $p->nama_lengkap,
                    'program_studi_id' => $defaultProdi?->id ?? 1,
                    'jabatan_akademik' => $p->jenis_pegawai ?? 'Tenaga Pendidik / Dosen',
                    'is_active' => true,
                ]
            );
            $syncedCount++;
        }

        return response()->json([
            'status' => 'success',
            'message' => "Berhasil menyinkronkan {$syncedCount} data Dosen dari modul SIMPEG.",
            'data' => [
                'total_synced' => $syncedCount,
            ]
        ]);
    }

    public function updateModePenilaian(Request $request, $id)
    {
        $request->validate([
            'mode_penilaian' => 'required|in:full_obe,semi_obe,konvensional',
        ]);
        
        $ta = \App\Models\Spmb\MasterTahunAkademik::findOrFail($id);
        $ta->update(['mode_penilaian' => $request->mode_penilaian]);
        
        return response()->json([
            'status' => 'success',
            'message' => 'Mode penilaian periode akademik berhasil diperbarui.',
        ]);
    }
}
