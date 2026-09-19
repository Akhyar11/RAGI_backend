<?php

namespace App\Http\Controllers\Simpeg;

use App\Http\Controllers\Controller;
use App\Models\Simpeg\DokumenPegawai;
use App\Services\Storage\FileStorageService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DokumenController extends Controller
{
    public function __construct(private FileStorageService $files) {}
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $isPrivileged = $user->hasRole('superadmin') || $user->hasRole('admin');
        if (!$user->hasPermission('simpeg.dokumen.read') && !$user->hasPermission('simpeg.dokumen.create') && !$user->hasPermission('simpeg.dokumen.manage') && !$isPrivileged) {
            throw new AuthorizationException('Anda tidak memiliki hak akses (permission) untuk melihat Dokumen E-File.');
        }

        $query = DokumenPegawai::with(['pegawai.unitKerja', 'pegawai.dosen.programStudi']);

        if ($request->has('pegawai_id')) {
            $query->where('pegawai_id', $request->pegawai_id);
        } elseif (!$isPrivileged && !$user->hasPermission('simpeg.dokumen.manage')) {
            // Non-admin hanya bisa melihat dokumen miliknya sendiri
            $pegId = $user->pegawai?->id;
            if ($pegId) {
                $query->where('pegawai_id', $pegId);
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        if ($request->filled('jenis_dokumen')) {
            $query->where('jenis_dokumen', $request->jenis_dokumen);
        }

        if ($request->filled('search')) {
            $search = (string) $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('nama_dokumen', 'like', "%{$search}%")
                  ->orWhereHas('pegawai', function ($qp) use ($search) {
                      $qp->where('nama_lengkap', 'like', "%{$search}%")
                         ->orWhere('nip', 'like', "%{$search}%")
                         ->orWhere('nidn', 'like', "%{$search}%")
                         ->orWhere('nuptk', 'like', "%{$search}%");
                  });
            });
        }

        // Sorting
        $allowedSort = ['created_at', 'nama_dokumen', 'jenis_dokumen'];
        $sortBy = in_array($request->sort_by, $allowedSort, true) ? $request->sort_by : 'created_at';
        $sortOrder = $request->sort_order === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortBy, $sortOrder);

        $perPage = min(100, $request->integer('per_page', 15));
        $paginated = $query->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'message' => 'Data dokumen berhasil diambil',
            'data' => $paginated->items(),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
                'last_page' => $paginated->lastPage(),
                'from' => $paginated->firstItem(),
                'to' => $paginated->lastItem(),
            ],
            'filters' => [
                'search' => (string) $request->input('search', ''),
                'sort_by' => $sortBy,
                'sort_order' => $sortOrder,
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasPermission('simpeg.dokumen.create') && !$user->hasPermission('simpeg.dokumen.upload') && !$user->hasPermission('simpeg.dokumen.manage') && !$user->isAdmin()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses (permission) untuk mengunggah Dokumen E-File.'
            ], 403);
        }

        $validated = $request->validate([
            'pegawai_id' => 'required|exists:simpeg_pegawai,id',
            'nama_dokumen' => 'required|string|max:255',
            'jenis_dokumen' => 'required|in:ktp,kk,ijazah,sk,serdos,sertifikat,lainnya',
            'file' => 'nullable|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:10240',
            'file_path' => 'nullable|string',
            'file_size' => 'nullable|string',
        ]);

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $path = $this->files->store($file, 'simpeg/dokumen_pegawai', private: true);
            $validated['file_path'] = $path;
            $sizeBytes = $file->getSize();
            $validated['file_size'] = round($sizeBytes / (1024 * 1024), 2) . ' MB';
        } elseif (empty($validated['file_path'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'File fisik dokumen wajib diunggah.'
            ], 422);
        }

        unset($validated['file']);
        $dokumen = DokumenPegawai::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Dokumen pegawai berhasil diunggah dan tersimpan aman di server',
            'data' => $dokumen,
        ], 201);
    }

    public function getSecureView(Request $request, $id): JsonResponse
    {
        $user = $request->user();
        $dokumen = DokumenPegawai::with('pegawai')->findOrFail($id);

        // Hak Akses Rahasia: Hanya Admin SIMPEG, Superadmin, atau Pegawai Pemilik Dokumen
        $isAdmin = $user->isAdmin() 
            || $user->hasPermission('simpeg.dokumen.manage') 
            || $user->hasPermission('simpeg.pegawai.manage');

        $isOwner = $user->pegawai && $user->pegawai->id === $dokumen->pegawai_id;

        if (!$isAdmin && !$isOwner) {
            return response()->json([
                'status' => 'error',
                'message' => 'Akses Ditolak: Dokumen ini bersifat rahasia dan hanya dapat dibuka oleh Admin SIMPEG, Superadmin, atau pegawai pemilik dokumen tersebut.'
            ], 403);
        }

        $watermarkText = \App\Services\Simpeg\FileSecurityService::getWatermarkText($dokumen);
        $fileExists = $this->files->exists($dokumen->file_path, private: true);
        $fileUrl = $fileExists
            ? ($this->files->temporaryUrl($dokumen->file_path) ?? $this->files->url($dokumen->file_path, private: true))
            : null;

        return response()->json([
            'status' => 'success',
            'data' => [
                'dokumen_id' => $dokumen->id,
                'nama_dokumen' => $dokumen->nama_dokumen,
                'jenis_dokumen' => $dokumen->jenis_dokumen,
                'watermark_overlay' => $watermarkText,
                'file_url' => $fileUrl,
                'file_exists' => $fileExists,
                'security_status' => 'Confidential - Encrypted & Watermarked',
            ],
        ]);
    }

    public function downloadFile(Request $request, $id)
    {
        $user = $request->user();
        $dokumen = DokumenPegawai::with('pegawai')->findOrFail($id);

        $isAdmin = $user->isAdmin() 
            || $user->hasPermission('simpeg.dokumen.manage') 
            || $user->hasPermission('simpeg.pegawai.manage');

        $isOwner = $user->pegawai && $user->pegawai->id === $dokumen->pegawai_id;

        if (!$isAdmin && !$isOwner) {
            return response()->json([
                'status' => 'error',
                'message' => 'Akses Ditolak: Dokumen ini bersifat rahasia dan hanya dapat dibuka oleh Admin SIMPEG, Superadmin, atau pegawai pemilik dokumen tersebut.'
            ], 403);
        }

        if (empty($dokumen->file_path)) {
            return response()->json(['status' => 'error', 'message' => 'Berkas fisik dokumen tidak ditemukan di server.'], 404);
        }

        if (! $this->files->exists($dokumen->file_path, private: true)) {
            return response()->json(['status' => 'error', 'message' => 'File fisik tidak ditemukan pada lokasi storage server.'], 404);
        }

        $extension = pathinfo($this->files->normalizePath($dokumen->file_path), PATHINFO_EXTENSION);

        return $this->files->download(
            $dokumen->file_path,
            $dokumen->nama_dokumen . ($extension !== '' ? '.' . $extension : ''),
            private: true
        );
    }

    public function destroy(Request $request, $id): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasPermission('simpeg.dokumen.delete') && !$user->hasPermission('simpeg.dokumen.manage') && !$user->isAdmin()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses (permission) untuk menghapus Dokumen E-File.'
            ], 403);
        }

        $dokumen = DokumenPegawai::findOrFail($id);

        // Delete physical file if exists (dinamis: local / R2)
        if (!empty($dokumen->file_path)) {
            $this->files->delete($dokumen->file_path, private: true);
        }

        $dokumen->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Dokumen beserta file fisiknya berhasil dihapus',
        ]);
    }
}
