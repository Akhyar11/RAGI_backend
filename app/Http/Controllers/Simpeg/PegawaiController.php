<?php

namespace App\Http\Controllers\Simpeg;

use App\Http\Controllers\Controller;
use App\Http\Requests\Simpeg\StorePegawaiRequest;
use App\Http\Requests\Simpeg\UpdatePegawaiRequest;
use App\Models\Simpeg\Pegawai;
use App\Services\AuditLogService;
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
        $pegawai = Pegawai::with(['unitKerja', 'jabatanFungsional', 'riwayatJabatan.jabatan', 'riwayatJabatan.jabatanFungsional', 'riwayatPendidikan', 'dosen.programStudi'])
            ->where('user_id', $user->id)
            ->first();

        if (!$pegawai) {
            $unitKerja = \App\Models\Simpeg\UnitKerja::first();
            $nama = $user->name ?: ucfirst($user->username);
            $isTendik = $user->hasRole('tendik');
            $isDosen = $user->hasRole('dosen');
            $jenis = $isTendik ? 'tendik' : ($isDosen ? 'dosen' : 'pegawai');
            $pegawai = Pegawai::create([
                'user_id' => $user->id,
                'unit_kerja_id' => $unitKerja?->id,
                'nip' => '19920815' . rand(100000, 999999),
                'nama_lengkap' => $nama,
                'jenis_kelamin' => 'P',
                'jenis_pegawai' => $jenis,
                'status_kepegawaian' => 'tetap_yayasan',
                'status' => 'aktif',
                'telepon' => null,
                'alamat' => null,
            ]);
            $pegawai->load(['unitKerja', 'jabatanFungsional', 'riwayatJabatan', 'riwayatPendidikan']);
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

    public function store(StorePegawaiRequest $request)
    {
        $pegawai = $this->pegawaiService->create($request->validated());

        return response()->json([
            'status' => 'success',
            'message' => 'Data Pegawai berhasil ditambahkan.',
            'data' => $pegawai
        ], 201);
    }

    public function show(Request $request, $id)
    {
        $pegawai = Pegawai::with([
            'user.roles',
            'unitKerja',
            'jabatanFungsional',
            'shiftTemplate',
            'officeLocation',
            'additionalOffices',
            'riwayatJabatan.jabatan',
            'riwayatJabatan.jabatanFungsional',
            'riwayatPendidikan',
            'dosen.programStudi',
            'roles',
            'asetDipegang.ruangan',
        ])->findOrFail($id);

        $user = $request->user();
        $isManager = $user->isAdmin() || $user->hasPermission('simpeg.pegawai.manage');

        if (!$isManager) {
            if ($pegawai->user_id !== $user->id) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Akses Ditolak: Anda tidak memiliki hak akses untuk melihat rincian Data Pegawai lain.'
                ], 403);
            }
        }

        return response()->json([
            'status' => 'success',
            'data' => $pegawai
        ]);
    }

    public function update(UpdatePegawaiRequest $request, $id)
    {
        $pegawai = Pegawai::findOrFail($id);

        $updated = $this->pegawaiService->update($pegawai, $request->validated());

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

    /**
     * Reset data biometrik wajah pegawai (Admin / HR)
     */
    public function resetFace(Request $request, $id)
    {
        $pegawai = Pegawai::findOrFail($id);

        if (!$request->user()->hasPermission('simpeg.pegawai.update') && !$request->user()->hasPermission('simpeg.pegawai.manage') && !$request->user()->isAdmin()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses (permission) untuk mereset biometrik pegawai ini.'
            ], 403);
        }

        $pegawai->update([
            'face_embedding' => null,
            'face_enrolled_at' => null,
        ]);

        AuditLogService::record('SIMPEG', 'reset_face', 'simpeg_pegawai', $pegawai->id, [
            'nip' => $pegawai->nip,
            'nama' => $pegawai->nama_lengkap,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Data biometrik wajah pegawai berhasil direset. Pegawai dapat mendaftarkan ulang melalui aplikasi mobile.',
            'data' => $pegawai->fresh(['unitKerja', 'officeLocation', 'shiftTemplate'])
        ]);
    }

    /**
     * Cek status clearance inventaris aset dinas dan peminjaman fasilitas pegawai.
     */
    public function clearance(Request $request, $id)
    {
        $pegawai = Pegawai::findOrFail($id);
        $user = $request->user();
        $isManager = $user->isAdmin() || $user->hasPermission('simpeg.pegawai.manage') || $user->hasPermission('simpeg.pegawai.read');

        if (!$isManager && $pegawai->user_id !== $user->id) {
            return response()->json([
                'status' => 'error',
                'message' => 'Akses Ditolak: Anda tidak memiliki hak akses untuk melihat status clearance pegawai ini.'
            ], 403);
        }

        $clearance = $this->pegawaiService->getClearanceStatus($pegawai);

        return response()->json([
            'status' => 'success',
            'data' => $clearance
        ]);
    }
}

