<?php

namespace App\Http\Controllers\Simpeg;

use App\Http\Controllers\Controller;
use App\Models\Simpeg\Pegawai;
use App\Services\Simpeg\PegawaiImportService;
use App\Services\Simpeg\PegawaiService;
use Illuminate\Http\Request;

class PegawaiController extends Controller
{
    protected $pegawaiService;
    protected $pegawaiImportService;

    public function __construct(PegawaiService $pegawaiService, PegawaiImportService $pegawaiImportService)
    {
        $this->pegawaiService = $pegawaiService;
        $this->pegawaiImportService = $pegawaiImportService;
    }

    public function downloadTemplate(Request $request)
    {
        if (!$request->user()->hasPermission('simpeg.pegawai.read') && !$request->user()->hasPermission('simpeg.pegawai.create') && !$request->user()->hasPermission('simpeg.pegawai.manage')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses (permission) untuk mengunduh template Data Pegawai.'
            ], 403);
        }

        $csvContent = $this->pegawaiImportService->getTemplateCsv();

        return response($csvContent, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="template_import_pegawai.csv"',
        ]);
    }

    public function import(Request $request)
    {
        if (!$request->user()->hasPermission('simpeg.pegawai.create') && !$request->user()->hasPermission('simpeg.pegawai.manage')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses (permission) untuk mengimpor Data Pegawai.'
            ], 403);
        }

        $request->validate([
            'file' => 'required|file|max:10240',
        ], [
            'file.required' => 'Berkas impor wajib diunggah.',
            'file.file' => 'Berkas yang diunggah tidak valid.',
            'file.max' => 'Ukuran berkas maksimal 10 MB.',
        ]);

        $file = $request->file('file');
        $extension = strtolower($file->getClientOriginalExtension());
        if (!in_array($extension, ['csv', 'txt', 'xlsx', 'xls'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Format berkas tidak didukung. Harap unggah berkas .csv atau .xlsx.'
            ], 422);
        }

        $result = $this->pegawaiImportService->import($file);

        return response()->json([
            'status' => 'success',
            'message' => "Proses impor selesai: {$result['success']} berhasil, {$result['failed']} gagal.",
            'data' => $result
        ]);
    }

    public function me(Request $request)
    {
        $user = $request->user();
        $pegawai = Pegawai::with(['unitKerja', 'riwayatJabatan.jabatan', 'riwayatJabatan.jabatanFungsional', 'riwayatPendidikan', 'dosen.programStudi'])
            ->where('user_id', $user->id)
            ->first();

        if (!$pegawai) {
            $unitKerja = \App\Models\Simpeg\UnitKerja::first();
            $nama = $user->username === 'admin' ? 'Dr. Wasis Utama, M.T.' : ($user->username === 'dosen' ? 'Anisa Rahmawati, M.Kom.' : ucfirst($user->username));
            $pegawai = Pegawai::create([
                'user_id' => $user->id,
                'unit_kerja_id' => $unitKerja?->id,
                'nip' => '19920815' . rand(100000, 999999),
                'nama_lengkap' => $nama,
                'jenis_kelamin' => 'P',
                'jenis_pegawai' => $user->user_type === 'dosen' ? 'dosen' : ($user->user_type === 'tendik' ? 'tendik' : 'dosen'),
                'status_kepegawaian' => 'tetap_yayasan',
                'status' => 'aktif',
                'telepon' => '081234567890',
                'alamat' => 'Jl. Merdeka No. 45, Bandung',
            ]);
            $pegawai->load(['unitKerja', 'riwayatJabatan', 'riwayatPendidikan']);
        }

        return response()->json([
            'status' => 'success',
            'data' => $pegawai
        ]);
    }

    public function index(Request $request)
    {
        if (!$request->user()->hasPermission('simpeg.pegawai.read') && !$request->user()->hasPermission('simpeg.pegawai.manage')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses (permission) untuk melihat Data Pegawai.'
            ], 403);
        }

        $filters = $request->only(['search', 'unit_kerja_id', 'jenis_pegawai', 'role_id', 'status', 'shift_template_id', 'per_page']);
        $pegawai = $this->pegawaiService->getFiltered($filters);

        return response()->json([
            'status' => 'success',
            'data' => $pegawai
        ]);
    }

    public function getRoles(Request $request)
    {
        $roles = \App\Models\Role::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'description']);

        return response()->json([
            'status' => 'success',
            'data' => $roles
        ]);
    }

    public function store(Request $request)
    {
        if (!$request->user()->hasPermission('simpeg.pegawai.create') && !$request->user()->hasPermission('simpeg.pegawai.manage')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses (permission) untuk menambah Data Pegawai.'
            ], 403);
        }

        $request->validate([
            'user_id' => 'nullable|exists:core_users,id|unique:simpeg_pegawai,user_id',
            'unit_kerja_id' => 'nullable|exists:simpeg_unit_kerja,id',
            'nip' => 'nullable|string|unique:simpeg_pegawai,nip',
            'nidn' => 'nullable|string|unique:simpeg_pegawai,nidn',
            'nuptk' => 'nullable|string|unique:simpeg_pegawai,nuptk',
            'nik' => 'nullable|string|unique:simpeg_pegawai,nik',
            'nama_lengkap' => 'required|string',
            'email' => 'nullable|email|unique:core_users,email',
            'username' => 'nullable|string|unique:core_users,username',
            'tanggal_lahir' => 'nullable|date',
            'tempat_lahir' => 'nullable|string',
            'jenis_kelamin' => 'nullable|in:L,P',
            'agama' => 'nullable|string',
            'role_ids' => 'nullable|array',
            'role_ids.*' => 'exists:core_roles,id',
            'jenis_pegawai' => 'nullable|string',
            'status_kepegawaian' => 'nullable|in:pns,non_pns,kontrak,tetap_yayasan',
            'tanggal_masuk' => 'nullable|date',
            'status' => 'nullable|in:aktif,non_aktif,pensiun,meninggal',
            'telepon' => 'nullable|string',
            'alamat' => 'nullable|string',
            'shift_template_id' => 'nullable|exists:simpeg_shift_templates,id',
            'office_location_id' => 'nullable|exists:simpeg_office_locations,id',
        ]);

        $pegawai = $this->pegawaiService->create($request->all());

        return response()->json([
            'status' => 'success',
            'message' => 'Data Pegawai berhasil ditambahkan.',
            'data' => $pegawai
        ], 201);
    }

    public function show(Request $request, $id)
    {
        if (!$request->user()->hasPermission('simpeg.pegawai.read') && !$request->user()->hasPermission('simpeg.pegawai.manage')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses (permission) untuk melihat rincian Data Pegawai.'
            ], 403);
        }

        $pegawai = Pegawai::with([
            'user.roles',
            'unitKerja',
            'shiftTemplate',
            'officeLocation',
            'riwayatJabatan.jabatan',
            'riwayatJabatan.jabatanFungsional',
            'riwayatPendidikan',
            'dosen.programStudi',
            'roles',
        ])->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => $pegawai
        ]);
    }

    public function update(Request $request, $id)
    {
        $pegawai = Pegawai::findOrFail($id);

        if (!$request->user()->hasPermission('simpeg.pegawai.update') && !$request->user()->hasPermission('simpeg.pegawai.manage')) {
            if ($pegawai->user_id !== $request->user()->id) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Anda tidak memiliki hak akses (permission) untuk memperbarui Data Pegawai.'
                ], 403);
            }
        }

        $request->validate([
            'user_id' => 'nullable|exists:core_users,id|unique:simpeg_pegawai,user_id,' . $id,
            'unit_kerja_id' => 'nullable|exists:simpeg_unit_kerja,id',
            'nip' => 'nullable|string|unique:simpeg_pegawai,nip,' . $id,
            'nidn' => 'nullable|string|unique:simpeg_pegawai,nidn,' . $id,
            'nuptk' => 'nullable|string|unique:simpeg_pegawai,nuptk,' . $id,
            'nik' => 'nullable|string|unique:simpeg_pegawai,nik,' . $id,
            'nama_lengkap' => 'sometimes|string',
            'tanggal_lahir' => 'nullable|date',
            'tempat_lahir' => 'nullable|string',
            'jenis_kelamin' => 'sometimes|in:L,P',
            'agama' => 'nullable|string',
            'role_ids' => 'sometimes|array',
            'role_ids.*' => 'exists:core_roles,id',
            'jenis_pegawai' => 'nullable|string',
            'status_kepegawaian' => 'sometimes|in:pns,non_pns,kontrak,tetap_yayasan',
            'tanggal_masuk' => 'nullable|date',
            'status' => 'sometimes|in:aktif,non_aktif,pensiun,meninggal',
            'telepon' => 'nullable|string',
            'alamat' => 'nullable|string',
            'shift_template_id' => 'nullable|exists:simpeg_shift_templates,id',
            'office_location_id' => 'nullable|exists:simpeg_office_locations,id',
        ]);

        $updated = $this->pegawaiService->update($pegawai, $request->all());

        return response()->json([
            'status' => 'success',
            'message' => 'Data Pegawai berhasil diperbarui.',
            'data' => $updated
        ]);
    }

    public function destroy(Request $request, $id)
    {
        if (!$request->user()->hasPermission('simpeg.pegawai.delete') && !$request->user()->hasPermission('simpeg.pegawai.manage')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses (permission) untuk menghapus Data Pegawai.'
            ], 403);
        }

        $pegawai = Pegawai::findOrFail($id);
        $this->pegawaiService->delete($pegawai);

        return response()->json([
            'status' => 'success',
            'message' => 'Data Pegawai berhasil dihapus.'
        ]);
    }
}
