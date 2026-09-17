<?php

namespace App\Http\Controllers\Sikeu;

use App\Http\Controllers\Controller;
use App\Models\Sikeu\JalurKelas;
use App\Models\Sikeu\TarifUkt;
use App\Models\Sikeu\Beasiswa;
use App\Models\Sikeu\MahasiswaBeasiswa;
use App\Models\Sikeu\PotonganMahasiswa;
use App\Models\Sikeu\PotonganTagihan;
use App\Models\Sikeu\TagihanMahasiswa;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SikeuExtendedMasterController extends Controller
{
    // ========================================================
    // 1. JALUR KELAS
    // ========================================================

    public function indexJalurKelas()
    {
        $data = JalurKelas::orderBy('id', 'asc')->get();
        return response()->json(['status' => 'success', 'data' => $data]);
    }

    public function storeJalurKelas(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nama_jalur' => 'required|string|max:100',
            'deskripsi' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        $item = JalurKelas::create([
            'kode' => strtoupper(substr(str_replace(' ', '_', $request->nama_jalur), 0, 20)),
            'nama_jalur' => $request->nama_jalur,
            'deskripsi' => $request->deskripsi,
            'is_active' => true,
        ]);

        return response()->json(['status' => 'success', 'message' => 'Jalur kelas berhasil disimpan', 'data' => $item], 201);
    }

    public function updateJalurKelas(Request $request, $id)
    {
        $item = JalurKelas::findOrFail($id);
        $item->update($request->only(['nama_jalur', 'deskripsi', 'is_active']));
        return response()->json(['status' => 'success', 'message' => 'Jalur kelas berhasil diperbarui', 'data' => $item]);
    }

    public function destroyJalurKelas($id)
    {
        $item = JalurKelas::findOrFail($id);
        $item->delete();
        return response()->json(['status' => 'success', 'message' => 'Jalur kelas berhasil dihapus']);
    }

    // ========================================================
    // 2. TARIF UKT KELOMPOK
    // ========================================================

    public function indexTarifUkt(Request $request)
    {
        $query = TarifUkt::with('jenisBiaya');

        if ($request->filled('tahun_angkatan')) {
            $query->where('tahun_angkatan', $request->tahun_angkatan);
        }
        if ($request->filled('jalur_kelas')) {
            $query->where('jalur_kelas', $request->jalur_kelas);
        }

        $data = $query->orderBy('tahun_angkatan', 'desc')->orderBy('kelompok_ukt', 'asc')->get();
        return response()->json(['status' => 'success', 'data' => $data]);
    }

    public function storeTarifUkt(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'jenis_biaya_id' => 'required|integer',
            'tahun_angkatan' => 'required|integer',
            'jalur_kelas' => 'required|string',
            'kelompok_ukt' => 'required|integer',
            'prodi' => 'nullable|string',
            'nama_kelompok' => 'required|string',
            'nominal' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        $item = TarifUkt::create($request->all());
        return response()->json(['status' => 'success', 'message' => 'Tarif UKT berhasil disimpan', 'data' => $item], 201);
    }

    public function updateTarifUkt(Request $request, $id)
    {
        $item = TarifUkt::findOrFail($id);
        $item->update($request->all());
        return response()->json(['status' => 'success', 'message' => 'Tarif UKT berhasil diperbarui', 'data' => $item]);
    }

    public function destroyTarifUkt($id)
    {
        $item = TarifUkt::findOrFail($id);
        $item->delete();
        return response()->json(['status' => 'success', 'message' => 'Tarif UKT berhasil dihapus']);
    }

    // ========================================================
    // 3. BEASISWA
    // ========================================================

    public function indexBeasiswa()
    {
        $data = Beasiswa::with('jenisBiaya')->orderBy('id', 'desc')->get();
        return response()->json(['status' => 'success', 'data' => $data]);
    }

    public function storeBeasiswa(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'kode' => 'required|string|unique:sikeu_beasiswa,kode',
            'nama' => 'required|string',
            'sumber' => 'required|string',
            'tipe_potongan' => 'required|in:persen,nominal',
            'nilai_potongan' => 'required|numeric|min:0',
            'jenis_biaya_ids' => 'nullable|array',
            'jenis_biaya_ids.*' => 'integer|exists:sikeu_master_biaya,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        $item = Beasiswa::create($request->only([
            'kode', 'nama', 'sumber', 'tipe_potongan', 'nilai_potongan',
            'berlaku_angkatan_mulai', 'berlaku_angkatan_sampai', 'deskripsi', 'is_active',
        ]));

        $jenisBiayaIds = $request->filled('jenis_biaya_ids') ? $request->input('jenis_biaya_ids') : [];
        $item->jenisBiaya()->sync(array_map('intval', $jenisBiayaIds));

        $item->load('jenisBiaya');
        return response()->json(['status' => 'success', 'message' => 'Program beasiswa berhasil disimpan', 'data' => $item], 201);
    }

    public function updateBeasiswa(Request $request, $id)
    {
        $item = Beasiswa::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'kode' => 'required|string|unique:sikeu_beasiswa,kode,' . $id,
            'nama' => 'required|string',
            'sumber' => 'required|string',
            'tipe_potongan' => 'required|in:persen,nominal',
            'nilai_potongan' => 'required|numeric|min:0',
            'jenis_biaya_ids' => 'nullable|array',
            'jenis_biaya_ids.*' => 'integer|exists:sikeu_master_biaya,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        $item->update($request->only([
            'kode', 'nama', 'sumber', 'tipe_potongan', 'nilai_potongan',
            'berlaku_angkatan_mulai', 'berlaku_angkatan_sampai', 'deskripsi', 'is_active',
        ]));

        $jenisBiayaIds = $request->filled('jenis_biaya_ids') ? $request->input('jenis_biaya_ids') : [];
        $item->jenisBiaya()->sync(array_map('intval', $jenisBiayaIds));

        $item->load('jenisBiaya');
        return response()->json(['status' => 'success', 'message' => 'Program beasiswa berhasil diperbarui', 'data' => $item]);
    }

    public function destroyBeasiswa($id)
    {
        $item = Beasiswa::findOrFail($id);
        $item->delete();
        return response()->json(['status' => 'success', 'message' => 'Program beasiswa berhasil dihapus']);
    }

    // ========================================================
    // 4. MAPPING BEASISWA MAHASISWA
    // ========================================================

    public function indexMahasiswaBeasiswa(Request $request)
    {
        $query = MahasiswaBeasiswa::with('beasiswa');

        if ($request->filled('q')) {
            $search = $request->q;
            $query->where(function($q) use ($search) {
                $q->where('nama_mahasiswa', 'like', "%{$search}%")
                  ->orWhere('nim', 'like', "%{$search}%");
            });
        }

        $data = $query->orderBy('id', 'desc')->get();

        $mapped = $data->map(function($item) {
            $potonganText = '-';
            if ($item->beasiswa) {
                $potonganText = $item->beasiswa->tipe_potongan === 'persen'
                    ? $item->beasiswa->nilai_potongan . '%'
                    : 'Rp ' . number_format($item->beasiswa->nilai_potongan, 0, ',', '.');
            }
            return [
                'id' => $item->id,
                'mahasiswa_id' => $item->mahasiswa_id,
                'beasiswa_id' => $item->beasiswa_id,
                'nim' => $item->nim ?? ('NIM-' . $item->mahasiswa_id),
                'nama_mahasiswa' => $item->nama_mahasiswa ?? ('Mahasiswa #' . $item->mahasiswa_id),
                'nama_beasiswa' => $item->beasiswa->nama ?? 'Beasiswa',
                'tipe_potongan' => $item->beasiswa->tipe_potongan ?? 'persen',
                'nilai_potongan' => $item->beasiswa->nilai_potongan ?? 0,
                'potongan_text' => $potonganText,
                'status' => $item->status ?? 'aktif',
                'berlaku_mulai' => $item->berlaku_mulai,
                'berlaku_sampai' => $item->berlaku_sampai,
            ];
        });

        return response()->json(['status' => 'success', 'data' => $mapped]);
    }

    public function storeMahasiswaBeasiswa(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'mahasiswa_id' => 'required|integer',
            'beasiswa_id' => 'required|integer|exists:sikeu_beasiswa,id',
            'berlaku_mulai' => 'nullable|date',
            'berlaku_sampai' => 'nullable|date|after_or_equal:berlaku_mulai',
            'status' => 'nullable|in:aktif,nonaktif,selesai',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        // Cek apakah mahasiswa sudah memiliki mapping aktif untuk skema ini
        $existing = MahasiswaBeasiswa::where('mahasiswa_id', $request->mahasiswa_id)
            ->where('beasiswa_id', $request->beasiswa_id)
            ->where('status', 'aktif')
            ->first();

        if ($existing) {
            return response()->json([
                'status' => 'error',
                'message' => 'Mahasiswa ini sudah terdaftar aktif pada program beasiswa/potongan ini.',
            ], 422);
        }

        $nim = $request->input('nim');
        $namaMahasiswa = $request->input('nama_mahasiswa');

        if (empty($nim) || empty($namaMahasiswa)) {
            $mhs = \App\Models\Siakad\Mahasiswa::find($request->mahasiswa_id);
            if ($mhs) {
                $nim = $nim ?: $mhs->nim;
                $namaMahasiswa = $namaMahasiswa ?: ($mhs->nama_lengkap ?? $mhs->nama);
            }
        }

        $item = MahasiswaBeasiswa::create([
            'mahasiswa_id' => $request->mahasiswa_id,
            'beasiswa_id' => $request->beasiswa_id,
            'nim' => $nim ?? ('NIM-' . $request->mahasiswa_id),
            'nama_mahasiswa' => $namaMahasiswa ?? ('Mahasiswa #' . $request->mahasiswa_id),
            'berlaku_mulai' => $request->berlaku_mulai ?? now()->toDateString(),
            'berlaku_sampai' => $request->berlaku_sampai ?? now()->addYears(1)->toDateString(),
            'status' => $request->status ?? 'aktif',
        ]);

        $item->load('beasiswa');

        return response()->json([
            'status' => 'success',
            'message' => 'Penerima beasiswa berhasil ditetapkan',
            'data' => $item
        ], 201);
    }

    public function updateMahasiswaBeasiswa(Request $request, $id)
    {
        $item = MahasiswaBeasiswa::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'beasiswa_id' => 'nullable|integer|exists:sikeu_beasiswa,id',
            'berlaku_mulai' => 'nullable|date',
            'berlaku_sampai' => 'nullable|date|after_or_equal:berlaku_mulai',
            'status' => 'nullable|in:aktif,nonaktif,selesai',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        $item->update($request->only([
            'beasiswa_id', 'berlaku_mulai', 'berlaku_sampai', 'status'
        ]));

        $item->load('beasiswa');

        return response()->json([
            'status' => 'success',
            'message' => 'Data penetapan potongan beasiswa berhasil diperbarui',
            'data' => $item,
        ]);
    }

    public function destroyMahasiswaBeasiswa($id)
    {
        $item = MahasiswaBeasiswa::findOrFail($id);
        $item->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Penetapan beasiswa/potongan mahasiswa berhasil dihapus',
        ]);
    }

    // ========================================================
    // 4B. POTONGAN KHUSUS MAHASISWA (DI LUAR BEASISWA)
    // ========================================================

    public function indexPotonganMahasiswa(Request $request)
    {
        $query = PotonganMahasiswa::with(['masterBiaya', 'inputter']);

        if ($request->filled('q') || $request->filled('search')) {
            $search = $request->input('q') ?: $request->input('search');
            $query->where(function($q) use ($search) {
                $q->where('nama_mahasiswa', 'like', "%{$search}%")
                  ->orWhere('nim', 'like', "%{$search}%")
                  ->orWhere('nama_potongan', 'like', "%{$search}%")
                  ->orWhere('nomor_sk', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('mahasiswa_id')) {
            $query->where('mahasiswa_id', $request->mahasiswa_id);
        }

        $perPage = min(100, $request->integer('per_page', 20));
        $paginated = $query->orderBy('id', 'desc')->paginate($perPage);

        $mapped = collect($paginated->items())->map(function($item) {
            $potonganText = $item->tipe_potongan === 'persen'
                ? $item->nilai_potongan . '%'
                : 'Rp ' . number_format($item->nilai_potongan, 0, ',', '.');

            return [
                'id' => $item->id,
                'mahasiswa_id' => $item->mahasiswa_id,
                'nim' => $item->nim ?? ('NIM-' . $item->mahasiswa_id),
                'nama_mahasiswa' => $item->nama_mahasiswa ?? ('Mahasiswa #' . $item->mahasiswa_id),
                'nama_potongan' => $item->nama_potongan,
                'tipe_potongan' => $item->tipe_potongan,
                'nilai_potongan' => (float)$item->nilai_potongan,
                'potongan_text' => $potonganText,
                'master_biaya_id' => $item->master_biaya_id,
                'komponen_biaya' => $item->masterBiaya?->nama ?? 'Semua Komponen (Total Tagihan)',
                'semester' => $item->semester,
                'tahun_akademik' => $item->tahun_akademik,
                'berlaku_mulai' => $item->berlaku_mulai ? (is_object($item->berlaku_mulai) ? $item->berlaku_mulai->format('Y-m-d') : $item->berlaku_mulai) : null,
                'berlaku_sampai' => $item->berlaku_sampai ? (is_object($item->berlaku_sampai) ? $item->berlaku_sampai->format('Y-m-d') : $item->berlaku_sampai) : null,
                'nomor_sk' => $item->nomor_sk,
                'keterangan' => $item->keterangan,
                'status' => $item->status,
                'diinput_oleh' => $item->diinput_oleh,
                'petugas_nama' => $item->inputter?->name ?? 'Admin Keuangan',
                'created_at' => $item->created_at?->format('Y-m-d H:i:s'),
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => $mapped,
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
                'last_page' => $paginated->lastPage(),
            ],
        ]);
    }

    public function storePotonganMahasiswa(Request $request)
    {
        $data = $request->all();
        if (array_key_exists('master_biaya_id', $data) && ($data['master_biaya_id'] === '' || $data['master_biaya_id'] === '0' || $data['master_biaya_id'] === 0)) {
            $data['master_biaya_id'] = null;
        }
        if (array_key_exists('semester', $data) && ($data['semester'] === '' || $data['semester'] === '0' || $data['semester'] === 0)) {
            $data['semester'] = null;
        }

        $validator = Validator::make($data, [
            'mahasiswa_id' => 'required|integer',
            'nama_potongan' => 'required|string|max:150',
            'tipe_potongan' => 'required|in:nominal,persen',
            'nilai_potongan' => 'required|numeric|min:0',
            'master_biaya_id' => 'nullable|integer|exists:sikeu_master_biaya,id',
            'semester' => 'nullable|integer|min:1|max:14',
            'tahun_akademik' => 'nullable|string|max:20',
            'berlaku_mulai' => 'nullable|date',
            'berlaku_sampai' => 'nullable|date|after_or_equal:berlaku_mulai',
            'nomor_sk' => 'nullable|string|max:100',
            'keterangan' => 'nullable|string',
            'status' => 'nullable|in:aktif,nonaktif,selesai',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        $nim = $data['nim'] ?? $request->input('nim');
        $namaMahasiswa = $data['nama_mahasiswa'] ?? $request->input('nama_mahasiswa');

        if (empty($nim) || empty($namaMahasiswa)) {
            $mhs = \App\Models\Siakad\Mahasiswa::find($data['mahasiswa_id']);
            if ($mhs) {
                $nim = $nim ?: $mhs->nim;
                $namaMahasiswa = $namaMahasiswa ?: ($mhs->nama_lengkap ?? $mhs->nama);
            }
        }

        $item = PotonganMahasiswa::create([
            'mahasiswa_id' => $data['mahasiswa_id'],
            'nim' => $nim ?? ('NIM-' . $data['mahasiswa_id']),
            'nama_mahasiswa' => $namaMahasiswa ?? ('Mahasiswa #' . $data['mahasiswa_id']),
            'nama_potongan' => $data['nama_potongan'],
            'tipe_potongan' => $data['tipe_potongan'],
            'nilai_potongan' => $data['nilai_potongan'],
            'master_biaya_id' => $data['master_biaya_id'] ?? null,
            'semester' => $data['semester'] ?? null,
            'tahun_akademik' => $data['tahun_akademik'] ?? null,
            'berlaku_mulai' => $data['berlaku_mulai'] ?? null,
            'berlaku_sampai' => $data['berlaku_sampai'] ?? null,
            'nomor_sk' => $data['nomor_sk'] ?? null,
            'keterangan' => $data['keterangan'] ?? null,
            'status' => $data['status'] ?? 'aktif',
            'diinput_oleh' => auth()->id(),
        ]);

        $item->load(['masterBiaya', 'inputter']);

        return response()->json([
            'status' => 'success',
            'message' => 'Setting potongan khusus mahasiswa berhasil disimpan',
            'data' => $item,
        ], 201);
    }

    public function updatePotonganMahasiswa(Request $request, $id)
    {
        $item = PotonganMahasiswa::findOrFail($id);

        $data = $request->all();
        if (array_key_exists('master_biaya_id', $data) && ($data['master_biaya_id'] === '' || $data['master_biaya_id'] === '0' || $data['master_biaya_id'] === 0)) {
            $data['master_biaya_id'] = null;
        }
        if (array_key_exists('semester', $data) && ($data['semester'] === '' || $data['semester'] === '0' || $data['semester'] === 0)) {
            $data['semester'] = null;
        }

        $validator = Validator::make($data, [
            'nama_potongan' => 'sometimes|required|string|max:150',
            'tipe_potongan' => 'sometimes|required|in:nominal,persen',
            'nilai_potongan' => 'sometimes|required|numeric|min:0',
            'master_biaya_id' => 'nullable|integer|exists:sikeu_master_biaya,id',
            'semester' => 'nullable|integer|min:1|max:14',
            'tahun_akademik' => 'nullable|string|max:20',
            'berlaku_mulai' => 'nullable|date',
            'berlaku_sampai' => 'nullable|date|after_or_equal:berlaku_mulai',
            'nomor_sk' => 'nullable|string|max:100',
            'keterangan' => 'nullable|string',
            'status' => 'nullable|in:aktif,nonaktif,selesai',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        $item->update([
            'nama_potongan' => $data['nama_potongan'] ?? $item->nama_potongan,
            'tipe_potongan' => $data['tipe_potongan'] ?? $item->tipe_potongan,
            'nilai_potongan' => array_key_exists('nilai_potongan', $data) ? $data['nilai_potongan'] : $item->nilai_potongan,
            'master_biaya_id' => array_key_exists('master_biaya_id', $data) ? $data['master_biaya_id'] : $item->master_biaya_id,
            'semester' => array_key_exists('semester', $data) ? $data['semester'] : $item->semester,
            'tahun_akademik' => array_key_exists('tahun_akademik', $data) ? $data['tahun_akademik'] : $item->tahun_akademik,
            'berlaku_mulai' => array_key_exists('berlaku_mulai', $data) ? $data['berlaku_mulai'] : $item->berlaku_mulai,
            'berlaku_sampai' => array_key_exists('berlaku_sampai', $data) ? $data['berlaku_sampai'] : $item->berlaku_sampai,
            'nomor_sk' => array_key_exists('nomor_sk', $data) ? $data['nomor_sk'] : $item->nomor_sk,
            'keterangan' => array_key_exists('keterangan', $data) ? $data['keterangan'] : $item->keterangan,
            'status' => $data['status'] ?? $item->status,
        ]);

        $item->load(['masterBiaya', 'inputter']);

        return response()->json([
            'status' => 'success',
            'message' => 'Data potongan khusus mahasiswa berhasil diperbarui',
            'data' => $item,
        ]);
    }

    public function destroyPotonganMahasiswa($id)
    {
        $item = PotonganMahasiswa::findOrFail($id);
        $item->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Setting potongan khusus mahasiswa berhasil dihapus',
        ]);
    }

    // ========================================================
    // 5. INDEX TAGIHAN MAHASISWA REAL
    // ========================================================

    public function indexTagihan(Request $request)
    {
        $perPage = min(100, $request->integer('per_page', 20));
        $query = TagihanMahasiswa::with([
            'details.masterBiaya',
            'potonganTagihan',
            'virtualAccount',
            'mahasiswa.programStudi',
            'tipeTagihanMahasiswa'
        ]);

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->filled('tahun_angkatan') && $request->tahun_angkatan !== 'all') {
            $angkatan = (int)$request->tahun_angkatan;
            $query->where(function ($q) use ($angkatan) {
                $q->whereHas('mahasiswa', fn($m) => $m->where('angkatan', $angkatan))
                  ->orWhereHas('tipeTagihanMahasiswa', fn($tm) => $tm->where('tahun_angkatan', $angkatan));
            });
        }

        if ($request->filled('program_studi_id') && $request->program_studi_id !== 'all') {
            $prodiId = (int)$request->program_studi_id;
            $query->whereHas('mahasiswa', fn($m) => $m->where('program_studi_id', $prodiId));
        }

        if ($request->filled('jalur_kelas') && $request->jalur_kelas !== 'all') {
            $jalur = $request->jalur_kelas;
            $query->whereHas('tipeTagihanMahasiswa', fn($tm) => $tm->where('jalur_kelas', $jalur));
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nomor_tagihan', 'like', "%{$search}%")
                  ->orWhere('mahasiswa_id', 'like', "%{$search}%")
                  ->orWhereHas('mahasiswa', fn($m) => $m->where('nim', 'like', "%{$search}%")->orWhere('nama_lengkap', 'like', "%{$search}%"))
                  ->orWhereHas('tipeTagihanMahasiswa', fn($tm) => $tm->where('nim', 'like', "%{$search}%")->orWhere('nama_mahasiswa', 'like', "%{$search}%"));
            });
        }

        $tagihans = $query->orderBy('id', 'desc')->paginate($perPage);

        $mapped = collect($tagihans->items())->map(function ($item) {
            $mhs = $item->mahasiswa;
            $tipe = $item->tipeTagihanMahasiswa;

            $totalBersih = (float)($item->total_tagihan + $item->total_denda - $item->total_potongan);
            $sisa = max(0, $totalBersih - (float)$item->total_bayar);

            return [
                'id' => $item->id,
                'nomor' => $item->nomor_tagihan,
                'nim' => $mhs?->nim ?? $tipe?->nim ?? ('2024' . str_pad($item->mahasiswa_id, 4, '0', STR_PAD_LEFT)),
                'nama' => $mhs?->nama_lengkap ?? $tipe?->nama_mahasiswa ?? ('Mahasiswa #' . $item->mahasiswa_id),
                'angkatan' => $mhs?->angkatan ?? $tipe?->tahun_angkatan ?? 2025,
                'jalur' => $tipe?->jalur_kelas ?? 'Reguler',
                'kelompok_ukt' => 'Level ' . ($tipe?->kelompok_ukt ?? 3),
                'prodi' => $mhs?->programStudi?->nama ?? $mhs?->programStudi?->nama_prodi ?? 'Teknik Informatika',
                'program_studi_id' => $mhs?->program_studi_id,
                'total' => (float)$item->total_tagihan,
                'total_potongan' => (float)$item->total_potongan,
                'total_bayar' => (float)$item->total_bayar,
                'sisa' => $sisa,
                'status' => $item->status,
                'jatuhTempo' => $item->jatuh_tempo ? $item->jatuh_tempo->format('Y-m-d') : '2026-08-31',
                'source' => $item->source_system ?? 'SIAKAD',
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => $mapped,
            'meta' => [
                'current_page' => $tagihans->currentPage(),
                'per_page' => $tagihans->perPage(),
                'total' => $tagihans->total(),
                'last_page' => $tagihans->lastPage(),
            ],
        ]);
    }

    /**
     * GET /api/v1/sikeu/tagihan/{id}
     * Return comprehensive invoice details including student info, items grouped by module,
     * payment history, dispensations, and virtual account.
     */
    public function showTagihan($id)
    {
        $tagihan = TagihanMahasiswa::with([
            'details.masterBiaya.modules',
            'potonganTagihan.inputter',
            'virtualAccounts',
            'pembayarans',
            'dispensasis',
        ])->find($id);

        if (!$tagihan) {
            return response()->json([
                'status' => 'error',
                'message' => 'Tagihan dengan ID tersebut tidak ditemukan.',
            ], 404);
        }

        $tipe = \App\Models\Sikeu\MahasiswaTipeTagihan::where('mahasiswa_id', $tagihan->mahasiswa_id)->first();
        $sisaTagihan = max(0, ((float)$tagihan->total_tagihan + (float)$tagihan->total_denda) - (float)$tagihan->total_potongan - (float)$tagihan->total_bayar);

        $groupedDetails = [];
        $itemsMapped = [];

        foreach ($tagihan->details as $d) {
            $mb = $d->masterBiaya;
            $moduleCode = $mb?->modules?->first()?->module_code ?? 'siakad';
            $kategoriKode = $mb?->tipe === 'spmb_adm' ? 'D' : ($mb?->tipe === 'spp' || $mb?->tipe === 'ukt' ? 'P' : 'L');
            $kategoriLabel = $kategoriKode === 'D' ? 'Biaya PMB / Pendaftaran (D)' : ($kategoriKode === 'P' ? 'Biaya Pendidikan Tetap (P)' : 'Biaya Insidental / Lain-Lain (L)');

            $itemRow = [
                'id' => $d->id,
                'nama_biaya' => $mb?->nama ?? 'Komponen Biaya #' . $d->master_biaya_id,
                'kode_biaya' => $mb?->kode ?? 'BIAYA-' . $d->master_biaya_id,
                'kategori_kode' => $kategoriKode,
                'kategori_label' => $kategoriLabel,
                'module_code' => $moduleCode,
                'module_label' => strtoupper($moduleCode),
                'nominal' => (float)$d->nominal,
                'potongan' => (float)$d->potongan,
                'nominal_bersih' => (float)$d->nominal_bersih,
                'keterangan' => $d->keterangan ?? $mb?->deskripsi,
            ];

            $itemsMapped[] = $itemRow;

            if (!isset($groupedDetails[$moduleCode])) {
                $groupedDetails[$moduleCode] = [
                    'module_name' => strtoupper($moduleCode),
                    'subtotal' => 0,
                    'items' => [],
                ];
            }
            $groupedDetails[$moduleCode]['subtotal'] += (float)$d->nominal;
            $groupedDetails[$moduleCode]['items'][] = $itemRow;
        }

        $latestVa = $tagihan->virtualAccounts->first();

        $mhs = $tagihan->mahasiswa;
        $tipe = $tagihan->tipeTagihanMahasiswa ?? \App\Models\Sikeu\MahasiswaTipeTagihan::where('mahasiswa_id', $tagihan->mahasiswa_id)->first();
        $nim = $mhs?->nim ?? $tipe?->nim ?? ('2024' . str_pad($tagihan->mahasiswa_id, 4, '0', STR_PAD_LEFT));
        $nama = $mhs?->nama_lengkap ?? $tipe?->nama_mahasiswa ?? ('Mahasiswa #' . $tagihan->mahasiswa_id);
        $prodi = $mhs?->programStudi?->nama ?? $mhs?->programStudi?->nama_prodi ?? 'Teknik Informatika';
        $angkatan = (int)($mhs?->angkatan ?? $tipe?->tahun_angkatan ?? 2025);
        $semester = !empty($tagihan->catatan_approval) ? str_replace('Tagihan masal ', '', $tagihan->catatan_approval) : 'Semester Ganjil 2026/2027';

        return response()->json([
            'status' => 'success',
            'data' => [
                'id' => $tagihan->id,
                'nomor_tagihan' => $tagihan->nomor_tagihan,
                'mahasiswa_id' => $tagihan->mahasiswa_id,
                'nim' => $nim,
                'nama_mahasiswa' => $nama,
                'tahun_angkatan' => $angkatan,
                'jalur_kelas' => $tipe?->jalur_kelas ?? 'Reguler',
                'program_studi' => $prodi,
                'semester' => $semester,
                'kelompok_ukt' => 'Level ' . ($tipe?->kelompok_ukt ?? 3),
                'total_tagihan' => (float)$tagihan->total_tagihan,
                'total_potongan' => (float)$tagihan->total_potongan,
                'total_denda' => (float)$tagihan->total_denda,
                'total_bayar' => (float)$tagihan->total_bayar,
                'sisa_tagihan' => $sisaTagihan,
                'status' => $tagihan->status,
                'jatuh_tempo' => $tagihan->jatuh_tempo ? (is_object($tagihan->jatuh_tempo) ? $tagihan->jatuh_tempo->format('Y-m-d') : (string)$tagihan->jatuh_tempo) : '2026-08-31',
                'source_system' => $tagihan->source_system ?? 'SIAKAD',
                'details' => $itemsMapped,
                'grouped_details' => $groupedDetails,
                'virtual_account' => $latestVa ? [
                    'bank' => $latestVa->bank_code,
                    'va_number' => $latestVa->va_number,
                    'expired_at' => $latestVa->expired_at,
                ] : [
                    'bank' => 'BTN Syariah / Mandiri',
                    'va_number' => '988' . str_pad($tagihan->mahasiswa_id, 8, '0', STR_PAD_LEFT),
                    'expired_at' => '2026-08-31 23:59:59',
                ],
                'pembayaran' => $tagihan->pembayarans,
                'dispensasi' => $tagihan->dispensasis,
                'potongan_tagihan' => $tagihan->potonganTagihan->map(function($pot) {
                    return [
                        'id' => $pot->id,
                        'tagihan_id' => $pot->tagihan_id,
                        'tipe' => $pot->tipe,
                        'nominal_potongan' => (float)$pot->nominal_potongan,
                        'keterangan' => $pot->keterangan,
                        'diinput_oleh' => $pot->diinput_oleh,
                        'petugas_nama' => $pot->inputter?->name ?? 'Admin Keuangan',
                        'created_at' => $pot->created_at ? (is_object($pot->created_at) ? $pot->created_at->format('Y-m-d H:i:s') : (string)$pot->created_at) : null,
                    ];
                }),
            ]
        ]);
    }
}
