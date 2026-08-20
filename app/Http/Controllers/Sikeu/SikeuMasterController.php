<?php

namespace App\Http\Controllers\Sikeu;

use App\Http\Controllers\Controller;
use App\Models\Sikeu\TarifUkt;
use App\Models\Sikeu\TarifSpmb;
use App\Models\Sikeu\JenisBiaya;
use App\Models\Sikeu\Beasiswa;
use App\Models\Sikeu\TagihanMahasiswa;
use App\Models\Sikeu\DispensasiTagihan;
use App\Services\Sikeu\SpmbSikeuService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class SikeuMasterController extends Controller
{
    // ==========================================
    // 1. MASTER TARIF UKT & BIAYA PER ANGKATAN
    // ==========================================

    /**
     * List Tarif UKT per Tahun Angkatan, Jalur Kelas, & Prodi
     */
    public function indexTarif(Request $request)
    {
        $query = TarifUkt::with('jenisBiaya');

        if ($request->filled('tahun_angkatan')) {
            $query->where('tahun_angkatan', $request->tahun_angkatan);
        }

        if ($request->filled('jalur_kelas')) {
            $query->where('jalur_kelas', $request->jalur_kelas);
        }

        if ($request->filled('program_studi_id')) {
            $query->where('program_studi_id', $request->program_studi_id);
        }

        if ($request->filled('jenis_biaya_id')) {
            $query->where('jenis_biaya_id', $request->jenis_biaya_id);
        }

        $tarif = $query->orderBy('tahun_angkatan', 'desc')
            ->orderBy('jalur_kelas', 'asc')
            ->orderBy('kelompok_ukt', 'asc')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $tarif
        ]);
    }

    /**
     * Store new Tarif UKT for any year / class / group
     */
    public function storeTarif(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'jenis_biaya_id' => 'required|exists:jenis_biaya,id',
            'tahun_angkatan' => 'required|integer|min:2000|max:2100',
            'jalur_kelas' => 'nullable|string|max:100',
            'kelompok_ukt' => 'required|integer|min:1|max:10',
            'nama_kelompok' => 'nullable|string|max:150',
            'nominal' => 'required|numeric|min:0',
            'program_studi_id' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        $namaKelompok = $request->nama_kelompok ?? ('Kelompok ' . $request->kelompok_ukt);

        $tarif = TarifUkt::create([
            'jenis_biaya_id' => $request->jenis_biaya_id,
            'tahun_angkatan' => $request->tahun_angkatan,
            'jalur_kelas' => $request->jalur_kelas ?? 'Reguler',
            'kelompok_ukt' => $request->kelompok_ukt,
            'nama_kelompok' => $namaKelompok,
            'nominal' => $request->nominal,
            'program_studi_id' => $request->program_studi_id,
            'is_active' => true,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Tarif UKT angkatan berhasil ditambahkan.',
            'data' => $tarif->load('jenisBiaya')
        ], 201);
    }

    /**
     * Update Tarif UKT nominal & metadata
     */
    public function updateTarif(Request $request, $id)
    {
        $tarif = TarifUkt::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'nominal' => 'sometimes|numeric|min:0',
            'kelompok_ukt' => 'sometimes|integer|min:1|max:10',
            'nama_kelompok' => 'sometimes|string|max:150',
            'tahun_angkatan' => 'sometimes|integer|min:2000|max:2100',
            'jalur_kelas' => 'sometimes|string|max:100',
            'is_active' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        $tarif->update($request->only(['nominal', 'kelompok_ukt', 'nama_kelompok', 'tahun_angkatan', 'jalur_kelas', 'is_active', 'program_studi_id']));

        return response()->json([
            'status' => 'success',
            'message' => 'Tarif UKT berhasil diperbarui.',
            'data' => $tarif->load('jenisBiaya')
        ]);
    }

    /**
     * Delete Tarif UKT
     */
    public function destroyTarif($id)
    {
        $tarif = TarifUkt::findOrFail($id);
        $tarif->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Tarif UKT berhasil dihapus.'
        ]);
    }

    // ==========================================
    // 2. MASTER JALUR KELAS & TIPE MAHASISWA
    // ==========================================

    public function indexJalurKelas()
    {
        $jalur = \App\Models\Sikeu\MasterJalurKelas::orderBy('id', 'asc')->get();

        if ($jalur->isEmpty()) {
            $defaults = [
                ['kode' => 'REGULER', 'nama_jalur' => 'Reguler', 'deskripsi' => 'Jalur Kelas Reguler Tatap Muka'],
                ['kode' => 'KARYAWAN', 'nama_jalur' => 'Karyawan / Eksekutif', 'deskripsi' => 'Jalur Kelas Karyawan & Eksekutif Malam/Akhir Pekan'],
                ['kode' => 'INTERNASIONAL', 'nama_jalur' => 'Internasional', 'deskripsi' => 'Jalur Kelas Bilingual / Internasional'],
                ['kode' => 'ONLINE', 'nama_jalur' => 'Kelas Online / Blended', 'deskripsi' => 'Jalur Pembelajaran Jarak Jauh (PJJ)'],
            ];
            foreach ($defaults as $d) {
                \App\Models\Sikeu\MasterJalurKelas::create($d);
            }
            $jalur = \App\Models\Sikeu\MasterJalurKelas::orderBy('id', 'asc')->get();
        }

        return response()->json(['status' => 'success', 'data' => $jalur]);
    }

    public function storeJalurKelas(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nama_jalur' => 'required|string',
            'deskripsi' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        $kode = strtoupper(str_replace(' ', '_', $request->nama_jalur));
        $jalur = \App\Models\Sikeu\MasterJalurKelas::create([
            'kode' => $kode,
            'nama_jalur' => $request->nama_jalur,
            'deskripsi' => $request->deskripsi,
            'is_active' => true,
        ]);

        return response()->json(['status' => 'success', 'message' => 'Jalur kelas baru berhasil ditambahkan.', 'data' => $jalur], 201);
    }

    public function updateJalurKelas(Request $request, $id)
    {
        $jalur = \App\Models\Sikeu\MasterJalurKelas::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'nama_jalur' => 'sometimes|string',
            'deskripsi' => 'nullable|string',
            'is_active' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        $jalur->update($request->only(['nama_jalur', 'deskripsi', 'is_active']));

        return response()->json([
            'status' => 'success',
            'message' => 'Data Jalur / Kelas berhasil diperbarui.',
            'data' => $jalur
        ]);
    }

    public function destroyJalurKelas($id)
    {
        $jalur = \App\Models\Sikeu\MasterJalurKelas::findOrFail($id);
        $jalur->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Data Jalur / Kelas berhasil dihapus.'
        ]);
    }

    // ==========================================
    // 3. MASTER JENIS BIAYA PENDIDIKAN
    // ==========================================

    public function indexJenisBiaya(Request $request)
    {
        $query = JenisBiaya::with('moduleDelegations');
        if ($request->filled('module_code')) {
            $moduleCode = $request->module_code;
            $query->whereHas('moduleDelegations', function ($q) use ($moduleCode) {
                $q->where('module_code', $moduleCode);
            });
        }
        $biaya = $query->orderBy('id', 'asc')->get();
        return response()->json(['status' => 'success', 'data' => $biaya]);
    }

    public function storeJenisBiaya(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'kode' => 'required|string|unique:jenis_biaya,kode',
            'nama' => 'required|string',
            'tipe' => 'required|string',
            'nominal_standar' => 'nullable|numeric|min:0',
            'deskripsi' => 'nullable|string',
            'module_codes' => 'nullable|array',
            'module_codes.*' => 'string',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        $biaya = JenisBiaya::create([
            'kode' => strtoupper($request->kode),
            'nama' => $request->nama,
            'tipe' => $request->tipe,
            'nominal_standar' => $request->nominal_standar ?? 0,
            'deskripsi' => $request->deskripsi,
            'is_recurring' => true,
            'is_active' => true,
        ]);

        $moduleCodes = $request->input('module_codes', ['sikeu']);
        if (!empty($moduleCodes) && is_array($moduleCodes)) {
            foreach ($moduleCodes as $code) {
                \App\Models\Sikeu\JenisBiayaModule::create([
                    'jenis_biaya_id' => $biaya->id,
                    'module_code' => strtolower($code),
                ]);
            }
        }

        return response()->json(['status' => 'success', 'message' => 'Jenis biaya berhasil ditambahkan.', 'data' => $biaya->load('moduleDelegations')], 201);
    }

    public function updateJenisBiaya(Request $request, $id)
    {
        $biaya = JenisBiaya::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'nama' => 'sometimes|string',
            'tipe' => 'sometimes|string',
            'nominal_standar' => 'sometimes|numeric|min:0',
            'deskripsi' => 'nullable|string',
            'is_active' => 'sometimes|boolean',
            'module_codes' => 'nullable|array',
            'module_codes.*' => 'string',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        $biaya->update($request->only(['nama', 'tipe', 'nominal_standar', 'deskripsi', 'is_active', 'is_recurring']));

        if ($request->has('module_codes') && is_array($request->module_codes)) {
            \App\Models\Sikeu\JenisBiayaModule::where('jenis_biaya_id', $biaya->id)->delete();
            foreach ($request->module_codes as $code) {
                \App\Models\Sikeu\JenisBiayaModule::create([
                    'jenis_biaya_id' => $biaya->id,
                    'module_code' => strtolower($code),
                ]);
            }
        }

        return response()->json(['status' => 'success', 'message' => 'Komponen biaya & nominal standar berhasil diperbarui.', 'data' => $biaya->load('moduleDelegations')]);
    }

    // ==========================================
    // 4. MASTER BEASISWA & MAPPING MAHASISWA BEASISWA
    // ==========================================

    public function indexBeasiswa()
    {
        $beasiswa = Beasiswa::with('jenisBiaya')->orderBy('id', 'asc')->get();
        return response()->json(['status' => 'success', 'data' => $beasiswa]);
    }

    public function storeBeasiswa(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'kode' => 'required|string|unique:beasiswa,kode',
            'nama' => 'required|string',
            'sumber' => 'required|in:internal,eksternal,pemerintah',
            'tipe_potongan' => 'required|in:persen,nominal',
            'nilai_potongan' => 'required|numeric|min:0',
            'jenis_biaya_id' => 'nullable|exists:jenis_biaya,id',
            'berlaku_angkatan_mulai' => 'nullable|integer',
            'berlaku_angkatan_sampai' => 'nullable|integer',
            'deskripsi' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        $beasiswa = Beasiswa::create([
            'kode' => strtoupper($request->kode),
            'nama' => $request->nama,
            'sumber' => $request->sumber,
            'tipe_potongan' => $request->tipe_potongan,
            'nilai_potongan' => $request->nilai_potongan,
            'jenis_biaya_id' => $request->jenis_biaya_id,
            'berlaku_angkatan_mulai' => $request->berlaku_angkatan_mulai,
            'berlaku_angkatan_sampai' => $request->berlaku_angkatan_sampai,
            'deskripsi' => $request->deskripsi,
            'is_active' => true,
        ]);

        return response()->json(['status' => 'success', 'message' => 'Beasiswa berhasil ditambahkan.', 'data' => $beasiswa->load('jenisBiaya')], 201);
    }

    public function updateBeasiswa(Request $request, $id)
    {
        $beasiswa = Beasiswa::findOrFail($id);
        $beasiswa->update($request->only([
            'nama', 'sumber', 'tipe_potongan', 'nilai_potongan',
            'jenis_biaya_id', 'berlaku_angkatan_mulai', 'berlaku_angkatan_sampai',
            'deskripsi', 'is_active'
        ]));
        return response()->json(['status' => 'success', 'message' => 'Program beasiswa berhasil diperbarui.', 'data' => $beasiswa->load('jenisBiaya')]);
    }

    public function indexMahasiswaBeasiswa(Request $request)
    {
        $perPage = (int) $request->get('per_page', 10);
        $page = (int) $request->get('page', 1);

        $query = DB::table('mahasiswa_beasiswa')
            ->join('beasiswa', 'mahasiswa_beasiswa.beasiswa_id', '=', 'beasiswa.id')
            ->select('mahasiswa_beasiswa.*', 'beasiswa.nama as nama_beasiswa', 'beasiswa.kode as kode_beasiswa', 'beasiswa.nilai_potongan', 'beasiswa.tipe_potongan');

        if ($request->filled('q')) {
            $q = $request->q;
            $query->where(function($b) use ($q) {
                $b->where('mahasiswa_beasiswa.mahasiswa_id', 'like', "%{$q}%")
                  ->orWhere('beasiswa.nama', 'like', "%{$q}%")
                  ->orWhere('beasiswa.kode', 'like', "%{$q}%");
            });
        }

        $total = $query->count();
        $assignments = $query->orderBy('mahasiswa_beasiswa.id', 'desc')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get();

        $data = $assignments->map(function ($a) {
            return [
                'id' => $a->id,
                'mahasiswa_id' => $a->mahasiswa_id,
                'nama_mahasiswa' => 'Mahasiswa #' . $a->mahasiswa_id,
                'nim' => '2024' . str_pad($a->mahasiswa_id, 4, '0', STR_PAD_LEFT),
                'beasiswa_id' => $a->beasiswa_id,
                'nama_beasiswa' => $a->nama_beasiswa,
                'kode_beasiswa' => $a->kode_beasiswa,
                'potongan_text' => $a->tipe_potongan === 'persen' ? $a->nilai_potongan . '%' : 'Rp ' . number_format($a->nilai_potongan, 0, ',', '.'),
                'status' => $a->status,
                'berlaku_mulai' => $a->berlaku_mulai,
                'berlaku_sampai' => $a->berlaku_sampai,
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => $data,
            'meta' => [
                'current_page' => $page,
                'last_page' => (int) ceil($total / max(1, $perPage)),
                'per_page' => $perPage,
                'total' => $total,
            ]
        ]);
    }

    public function assignMahasiswaBeasiswa(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'mahasiswa_id' => 'required|integer',
            'beasiswa_id' => 'required|exists:beasiswa,id',
            'berlaku_mulai' => 'nullable|date',
            'berlaku_sampai' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        $id = DB::table('mahasiswa_beasiswa')->insertGetId([
            'mahasiswa_id' => $request->mahasiswa_id,
            'beasiswa_id' => $request->beasiswa_id,
            'tahun_akademik_id' => 1,
            'berlaku_mulai' => $request->berlaku_mulai ?? date('Y-m-d'),
            'berlaku_sampai' => $request->berlaku_sampai ?? date('Y-m-d', strtotime('+1 year')),
            'status' => 'aktif',
            'ditetapkan_oleh' => auth()->id() ?? 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['status' => 'success', 'message' => 'Penerima beasiswa berhasil ditetapkan.', 'data_id' => $id], 201);
    }

    // ==========================================
    // 5. PENETAPAN TIPE TAGIHAN MAHASISWA & INTEGRASI SPMB/SIAKAD
    // ==========================================

    /**
     * GET /api/v1/sikeu/master/student-billing-categories
     * API untuk SPMB/SIAKAD mengambil daftar kategori & paket tarif tagihan mahasiswa yang tersedia
     */
    public function getStudentBillingCategories()
    {
        $tarifs = TarifUkt::with('jenisBiaya')->where('is_active', true)->get();
        $beasiswas = Beasiswa::where('is_active', true)->get();

        $categories = [
          'tahun_angkatan_tersedia' => [2023, 2024, 2025, 2026, 2027],
          'jalur_kelas_tersedia' => ['Reguler', 'Karyawan', 'Internasional'],
          'kelompok_ukt_tersedia' => [1, 2, 3, 4],
          'master_beasiswa' => $beasiswas,
          'paket_tarif' => $tarifs,
        ];

        return response()->json(['status' => 'success', 'data' => $categories]);
    }

    /**
     * GET /api/v1/sikeu/master/student-billing-types
     * List penetapan tipe tagihan mahasiswa untuk pengelolaan admin SIKEU
     */
    public function indexStudentBillingTypes(Request $request)
    {
        $perPage = (int) $request->get('per_page', 10);
        $page = (int) $request->get('page', 1);

        $query = \App\Models\Sikeu\MahasiswaTipeTagihan::with('beasiswa');

        if ($request->filled('q')) {
            $q = $request->q;
            $query->where(function($b) use ($q) {
                $b->where('nama_mahasiswa', 'like', "%{$q}%")
                  ->orWhere('nim', 'like', "%{$q}%")
                  ->orWhere('mahasiswa_id', 'like', "%{$q}%");
            });
        }

        $total = $query->count();

        if ($total === 0 && !$request->filled('q')) {
            // Seed initial sample data for demonstration
            $samples = [
                ['mahasiswa_id' => 101, 'nim' => '2024010042', 'nama_mahasiswa' => 'Budi Santoso', 'tahun_angkatan' => 2024, 'jalur_kelas' => 'Reguler', 'kelompok_ukt' => 3, 'beasiswa_id' => 1, 'status_pendaftaran' => 'SIAKAD_AKTIF', 'catatan_perubahan' => 'Penetapan awal dari SPMB (Penerima KIP-Kuliah)'],
                ['mahasiswa_id' => 102, 'nim' => '2025010018', 'nama_mahasiswa' => 'Siti Rahmawati', 'tahun_angkatan' => 2025, 'jalur_kelas' => 'Reguler', 'kelompok_ukt' => 3, 'beasiswa_id' => null, 'status_pendaftaran' => 'SPMB_DITERIMA', 'catatan_perubahan' => 'Pendaftaran Jalur Mandiri SPMB'],
                ['mahasiswa_id' => 103, 'nim' => '2023010088', 'nama_mahasiswa' => 'Ahmad Fauzi', 'tahun_angkatan' => 2023, 'jalur_kelas' => 'Karyawan', 'kelompok_ukt' => 4, 'beasiswa_id' => null, 'status_pendaftaran' => 'PENGATURAN_ADMIN', 'catatan_perubahan' => 'Pindah jalur dari Reguler ke Kelas Karyawan pada Semester 3'],
            ];

            foreach ($samples as $s) {
                \App\Models\Sikeu\MahasiswaTipeTagihan::create($s);
            }

            $query = \App\Models\Sikeu\MahasiswaTipeTagihan::with('beasiswa');
            $total = $query->count();
        }

        $types = $query->orderBy('updated_at', 'desc')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $types,
            'meta' => [
                'current_page' => $page,
                'last_page' => (int) ceil($total / max(1, $perPage)),
                'per_page' => $perPage,
                'total' => $total,
            ]
        ]);
    }

    /**
     * POST /api/v1/sikeu/master/assign-student-billing-type
     * Endpoint integrasi SPMB / Admin untuk menetapkan tipe tagihan mahasiswa saat pendaftaran atau update
     */
    public function assignStudentBillingType(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'mahasiswa_id' => 'required|integer',
            'nim' => 'nullable|string',
            'nama_mahasiswa' => 'nullable|string',
            'tahun_angkatan' => 'required|integer',
            'jalur_kelas' => 'required|string',
            'kelompok_ukt' => 'required|integer',
            'beasiswa_id' => 'nullable|exists:beasiswa,id',
            'status_pendaftaran' => 'nullable|string',
            'catatan_perubahan' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        $record = \App\Models\Sikeu\MahasiswaTipeTagihan::updateOrCreate(
            ['mahasiswa_id' => $request->mahasiswa_id],
            [
                'nim' => $request->nim ?? '2024' . str_pad($request->mahasiswa_id, 4, '0', STR_PAD_LEFT),
                'nama_mahasiswa' => $request->nama_mahasiswa ?? 'Mahasiswa #' . $request->mahasiswa_id,
                'tahun_angkatan' => $request->tahun_angkatan,
                'jalur_kelas' => $request->jalur_kelas,
                'kelompok_ukt' => $request->kelompok_ukt,
                'beasiswa_id' => $request->beasiswa_id,
                'status_pendaftaran' => $request->status_pendaftaran ?? 'PENGATURAN_ADMIN',
                'catatan_perubahan' => $request->catatan_perubahan ?? 'Pembaruan tipe tagihan oleh admin',
                'updated_by' => auth()->id() ?? 1,
            ]
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Penetapan tipe tagihan mahasiswa berhasil disimpan.',
            'data' => $record->load('beasiswa')
        ], 201);
    }

    /**
     * PUT /api/v1/sikeu/master/update-student-billing-type/{id}
     * Admin update status / tipe tagihan mahasiswa (misal: pindah kelas dari Reguler ke Karyawan)
     */
    public function updateStudentBillingType(Request $request, $id)
    {
        $record = \App\Models\Sikeu\MahasiswaTipeTagihan::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'jalur_kelas' => 'sometimes|string',
            'kelompok_ukt' => 'sometimes|integer',
            'beasiswa_id' => 'nullable|exists:beasiswa,id',
            'catatan_perubahan' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        $record->update([
            'jalur_kelas' => $request->jalur_kelas ?? $record->jalur_kelas,
            'kelompok_ukt' => $request->kelompok_ukt ?? $record->kelompok_ukt,
            'beasiswa_id' => $request->has('beasiswa_id') ? $request->beasiswa_id : $record->beasiswa_id,
            'status_pendaftaran' => 'PENGATURAN_ADMIN',
            'catatan_perubahan' => $request->catatan_perubahan,
            'updated_by' => auth()->id() ?? 1,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Perubahan tipe tagihan mahasiswa berhasil disimpan.',
            'data' => $record->load('beasiswa')
        ]);
    }

    // ==========================================
    // 6. SEARCH MAHASISWA SEARCH & TAGIHAN LOOKUP
    // ==========================================

    public function searchMahasiswa(Request $request)
    {
        $search = $request->query('q', '');

        $queryBuilder = TagihanMahasiswa::with(['details.jenisBiaya', 'dispensasis']);

        if (!empty($search)) {
            $queryBuilder->where(function ($q) use ($search) {
                $q->where('nomor_tagihan', 'like', "%{$search}%")
                  ->orWhere('mahasiswa_id', 'like', "%{$search}%");
            });
        }

        $tagihans = $queryBuilder->limit(10)->get();

        $results = $tagihans->map(function ($t) {
            $hasUnpaidDispensation = DispensasiTagihan::where('mahasiswa_id', $t->mahasiswa_id)
                ->where('status', 'approved')
                ->whereHas('tagihan', function($q) {
                    $q->whereIn('status', ['belum_bayar', 'sebagian', 'dispensasi']);
                })
                ->exists();

            // Fetch assigned scholarship for this student
            $beasiswa = DB::table('mahasiswa_beasiswa')
                ->join('beasiswa', 'mahasiswa_beasiswa.beasiswa_id', '=', 'beasiswa.id')
                ->where('mahasiswa_beasiswa.mahasiswa_id', $t->mahasiswa_id)
                ->where('mahasiswa_beasiswa.status', 'aktif')
                ->select('beasiswa.nama', 'beasiswa.tipe_potongan', 'beasiswa.nilai_potongan')
                ->first();

            return [
                'tagihan_id' => $t->id,
                'nomor_tagihan' => $t->nomor_tagihan,
                'mahasiswa_id' => $t->mahasiswa_id,
                'nama_mahasiswa' => 'Mahasiswa #' . $t->mahasiswa_id . ' (NIM: 2024' . str_pad($t->mahasiswa_id, 4, '0', STR_PAD_LEFT) . ')',
                'nim' => '2024' . str_pad($t->mahasiswa_id, 4, '0', STR_PAD_LEFT),
                'prodi' => 'Teknik Informatika',
                'tahun_angkatan' => 2024,
                'jalur_kelas' => 'Reguler',
                'total_tagihan' => (float)$t->total_tagihan,
                'total_bayar' => (float)$t->total_bayar,
                'sisa_tagihan' => (float)($t->total_tagihan - $t->total_bayar),
                'status' => $t->status,
                'beasiswa_aktif' => $beasiswa ? $beasiswa->nama . ' (' . ($beasiswa->tipe_potongan === 'persen' ? $beasiswa->nilai_potongan . '%' : 'Rp ' . number_format($beasiswa->nilai_potongan, 0, ',', '.')) . ')' : null,
                'has_unpaid_previous_dispensation' => $hasUnpaidDispensation,
                'details' => $t->details->map(function ($d) {
                    return [
                        'detail_id' => $d->id,
                        'jenis_biaya' => $d->jenisBiaya->nama ?? 'Biaya Pendidikan',
                        'nominal' => (float)$d->nominal,
                        'potongan' => (float)$d->potongan,
                        'nominal_bersih' => (float)$d->nominal_bersih,
                    ];
                }),
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => $results
        ]);
    }

    // ==========================================
    // 8. MASTER TARIF SPMB & INTEGRASI GET TARIF
    // ==========================================

    /**
     * GET /api/v1/sikeu/master/tarif-spmb
     * List master tarif SPMB berdasarkan jalur & gelombang
     */
    public function indexTarifSpmb(Request $request)
    {
        $query = TarifSpmb::with('jenisBiaya');

        if ($request->filled('jalur_id')) {
            $query->where('jalur_id', $request->jalur_id);
        }

        if ($request->filled('gelombang_id')) {
            $query->where('gelombang_id', $request->gelombang_id);
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }

        $tarif = $query->orderBy('jalur_id', 'asc')
            ->orderBy('gelombang_id', 'asc')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $tarif
        ]);
    }

    /**
     * POST /api/v1/sikeu/master/tarif-spmb
     * Store new Tarif SPMB
     */
    public function storeTarifSpmb(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'jenis_biaya_id' => 'nullable|exists:jenis_biaya,id',
            'jalur_id' => 'required|string|max:50',
            'gelombang_id' => 'required|string|max:50',
            'nominal' => 'required|numeric|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        // Default jenis_biaya_id to first available JenisBiaya if null
        $jenisBiayaId = $request->jenis_biaya_id;
        if (!$jenisBiayaId) {
            $jenisBiayaDefault = JenisBiaya::where('tipe', 'spmb_adm')->first() ?? JenisBiaya::first();
            $jenisBiayaId = $jenisBiayaDefault?->id;
        }

        $tarif = TarifSpmb::create([
            'jenis_biaya_id' => $jenisBiayaId,
            'jalur_id' => $request->jalur_id,
            'gelombang_id' => $request->gelombang_id,
            'nominal' => $request->nominal,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Tarif SPMB berhasil ditambahkan.',
            'data' => $tarif->load('jenisBiaya')
        ], 201);
    }

    /**
     * PUT /api/v1/sikeu/master/tarif-spmb/{id}
     * Update Tarif SPMB
     */
    public function updateTarifSpmb(Request $request, $id)
    {
        $tarif = TarifSpmb::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'jenis_biaya_id' => 'nullable|exists:jenis_biaya,id',
            'jalur_id' => 'sometimes|required|string|max:50',
            'gelombang_id' => 'sometimes|required|string|max:50',
            'nominal' => 'sometimes|required|numeric|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        $tarif->update($request->only([
            'jenis_biaya_id',
            'jalur_id',
            'gelombang_id',
            'nominal',
            'is_active',
        ]));

        return response()->json([
            'status' => 'success',
            'message' => 'Tarif SPMB berhasil diperbarui.',
            'data' => $tarif->load('jenisBiaya')
        ]);
    }

    /**
     * DELETE /api/v1/sikeu/master/tarif-spmb/{id}
     * Delete Tarif SPMB
     */
    public function destroyTarifSpmb($id)
    {
        $tarif = TarifSpmb::findOrFail($id);
        $tarif->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Tarif SPMB berhasil dihapus.'
        ]);
    }

    /**
     * GET /api/v1/sikeu/spmb/tarif
     * Service Endpoint untuk SPMB mengambil nominal pendaftaran secara real-time
     */
    public function getTarifSpmb(Request $request, SpmbSikeuService $service)
    {
        $validator = Validator::make($request->all(), [
            'jalur_id' => 'required',
            'gelombang_id' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Parameter jalur_id dan gelombang_id wajib diisi',
                'errors' => $validator->errors()
            ], 422);
        }

        $nominal = $service->getTarifPendaftaranSpmb($request->jalur_id, $request->gelombang_id);

        return response()->json([
            'status' => 'success',
            'data' => [
                'jalur_id' => $request->jalur_id,
                'gelombang_id' => $request->gelombang_id,
                'nominal' => $nominal,
            ]
        ]);
    }
}
