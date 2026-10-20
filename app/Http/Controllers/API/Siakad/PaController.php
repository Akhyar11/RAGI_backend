<?php

namespace App\Http\Controllers\API\Siakad;

use App\Http\Controllers\Controller;
use App\Http\Requests\Siakad\RekapPaRequest;
use App\Models\Siakad\Dosen;
use App\Models\Siakad\Mahasiswa;
use App\Models\Siakad\PaBooking;
use App\Models\Siakad\PaCatatan;
use App\Models\Siakad\PaInsiden;
use App\Models\Siakad\PaJadwal;
use App\Models\Siakad\PaLaporan;
use App\Models\Siakad\PaPkl;
use App\Rules\MasterReferensiExists;
use App\Services\AuditLogService;
use App\Services\Siakad\PaLanjutanService;
use App\Services\Siakad\PaMonitoringService;
use App\Services\Siakad\PaService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

class PaController extends Controller
{
    /**
     * BAAK/superadmin/kaprodi = istimewa. Dosen murni (walau isAdmin() true
     * karena permission update SIMPEG) tetap dibatasi ke bimbingannya.
     */
    private function isPrivileged($user): bool
    {
        return (bool) ($user && ($user->isSuperAdmin() || $user->hasRole('admin') || $user->hasRole('kaprodi') || $user->hasRole('wakil_prodi')));
    }

    private function resolveDosen(Request $request, ?int $dosenId = null): ?Dosen
    {
        if ($dosenId) {
            return Dosen::find($dosenId);
        }
        return Dosen::where('user_id', $request->user()?->id)->first();
    }

    /**
     * Resolve dosen yang menjadi cakupan (scope) bimbingan untuk request ini.
     * Kaprodi/wakil/admin/superadmin boleh memilih dosen lain via `dosen_id`;
     * dosen murni selalu di-scope ke dirinya sendiri. Kembalikan null bila tidak ada.
     */
    private function resolveScopedDosen(Request $request): ?Dosen
    {
        $request->validate(['dosen_id' => 'nullable|exists:siakad_dosen,id']);

        $user = $request->user();
        $dosen = $this->resolveDosen($request, $request->dosen_id ? (int) $request->dosen_id : null);
        if (!$this->isPrivileged($user)) {
            $dosen = Dosen::where('user_id', $user->id)->first();
        }

        return $dosen;
    }

    /**
     * Rekap bimbingan: komposisi status mahasiswa bimbingan per dosen
     * (aktif, cuti, mangkir, dropout/keluar, lulus) + butuh penanganan khusus.
     * Kaprodi/admin: seluruh dosen (filter prodi); dosen: dirinya sendiri.
     */
    public function rekap(RekapPaRequest $request, PaService $paService)
    {
        $data = $paService->getRekap($request->validated(), $request->user());

        return response()->json([
            'status' => 'success',
            'message' => 'Rekap bimbingan PA berhasil dimuat',
            'data' => $data,
        ]);
    }

    /** Daftar mahasiswa bimbingan + flag masalah (tunggakan, KRS belum disetujui, IPK rendah). */
    public function advisees(Request $request)
    {
        Gate::authorize('siakad.mahasiswa.read');

        $request->validate([
            'dosen_id' => 'nullable|exists:siakad_dosen,id',
            'search' => 'nullable|string|max:100',
        ]);

        $user = $request->user();
        $dosen = $this->resolveDosen($request, $request->dosen_id ? (int) $request->dosen_id : null);
        if (!$this->isPrivileged($user)) {
            $dosen = Dosen::where('user_id', $user->id)->first();
        }
        if (!$dosen) {
            return response()->json(['status' => 'error', 'message' => 'Data dosen tidak ditemukan untuk akun ini.'], 404);
        }

        $query = Mahasiswa::with(['programStudi', 'krs' => fn($q) => $q->latest('id')->limit(3)])
            ->where('dosen_wali_id', $dosen->id);
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(fn($q) => $q->where('nama_lengkap', 'like', "%{$s}%")->orWhere('nim', 'like', "%{$s}%"));
        }

        $perPage = min(100, $request->integer('per_page', 15));
        $allowedSorts = ['nama_lengkap', 'nim', 'angkatan', 'ipk'];
        $sortBy = in_array($request->input('sort_by'), $allowedSorts, true) ? $request->input('sort_by') : 'nama_lengkap';
        $sortOrder = $request->sort_order === 'asc' ? 'asc' : 'desc';
        $paginator = $query->orderBy($sortBy, $sortOrder)->paginate($perPage);

        $data = collect($paginator->items())->map(function ($m) use ($dosen) {
            $krsAktif = $m->krs->first();
            $catatanKhusus = PaCatatan::where('dosen_id', $dosen->id)
                ->where('mahasiswa_id', $m->id)
                ->where('butuh_penanganan_khusus', true)
                ->where('status_tindak_lanjut', '!=', 'selesai')
                ->count();
            $kendala = [];
            if ($m->status !== 'aktif') {
                $kendala[] = 'Status: ' . $m->status;
            }
            if ($krsAktif && !in_array($krsAktif->status, ['disetujui', 'dikunci'], true)) {
                $kendala[] = 'KRS ' . ($krsAktif->status ?? 'draft') . ' (belum disetujui)';
            }
            if (is_numeric($m->ipk) && (float) $m->ipk < 2.0 && (float) $m->ipk > 0) {
                $kendala[] = 'IPK rendah (' . number_format((float) $m->ipk, 2) . ')';
            }
            if ($catatanKhusus > 0) {
                $kendala[] = $catatanKhusus . ' penanganan khusus terbuka';
            }

            $catatanList = PaCatatan::where('dosen_id', $dosen->id)
                ->where('mahasiswa_id', $m->id)
                ->orderBy('tanggal_bimbingan', 'asc')
                ->get();
            $totalBimbingan = $catatanList->count();
            $terakhirBimbingan = $catatanList->last()?->tanggal_bimbingan?->format('Y-m-d') ?? $catatanList->last()?->created_at?->format('Y-m-d');
            $sesiList = $catatanList->values()->map(function ($c, $idx) {
                return [
                    'id' => $c->id,
                    'pertemuan_ke' => $idx + 1,
                    'tanggal' => $c->tanggal_bimbingan?->format('Y-m-d') ?? $c->created_at?->format('Y-m-d'),
                    'kategori' => $c->kategori,
                    'isi' => $c->isi,
                    'kesimpulan' => $c->kesimpulan,
                    'butuh_penanganan_khusus' => (bool) $c->butuh_penanganan_khusus,
                ];
            });

            return [
                'id' => $m->id,
                'nim' => $m->nim,
                'nama_lengkap' => $m->nama_lengkap,
                'telepon' => $m->telepon,
                'email' => $m->email,
                'angkatan' => $m->angkatan,
                'status' => $m->status,
                'ipk' => $m->ipk,
                'program_studi' => $m->programStudi?->nama,
                'total_bimbingan' => $totalBimbingan,
                'terakhir_bimbingan_at' => $terakhirBimbingan,
                'sesi_list' => $sesiList,
                'krs_terakhir' => $krsAktif ? ['status' => $krsAktif->status, 'total_sks' => $krsAktif->total_sks_diambil] : null,
                'kendala' => $kendala,
            ];
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Data mahasiswa bimbingan PA berhasil diambil.',
            'data' => $data,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ]);
    }

    /**
     * Endpoint untuk mahasiswa melihat profil Dosen PA mereka,
     * status akademik, serta seluruh riwayat catatan/sesi bimbingan.
     */
    public function myPa(Request $request)
    {
        Gate::authorize('siakad.pa.read');

        $user = $request->user();
        $mhs = Mahasiswa::with(['programStudi', 'dosenWali.programStudi', 'krs' => fn($q) => $q->latest('id')->limit(5)])
            ->where('user_id', $user->id)
            ->first();

        if (!$mhs) {
            return response()->json(['status' => 'error', 'message' => 'Data mahasiswa tidak ditemukan untuk akun ini.'], 404);
        }

        $catatanList = PaCatatan::with('dosen')
            ->where('mahasiswa_id', $mhs->id)
            ->orderBy('tanggal_bimbingan', 'desc')
            ->get();

        $krsAktif = $mhs->krs->first();

        return response()->json([
            'status' => 'success',
            'message' => 'Data bimbingan PA berhasil diambil.',
            'data' => [
                'mahasiswa' => [
                    'id' => $mhs->id,
                    'nim' => $mhs->nim,
                    'nama_lengkap' => $mhs->nama_lengkap,
                    'angkatan' => $mhs->angkatan,
                    'status' => $mhs->status,
                    'ipk' => $mhs->ipk,
                    'program_studi' => $mhs->programStudi?->nama,
                ],
                'dosen_wali' => $mhs->dosenWali ? [
                    'id' => $mhs->dosenWali->id,
                    'nama_lengkap' => $mhs->dosenWali->nama_lengkap,
                    'nidn' => $mhs->dosenWali->nidn,
                    'nip' => $mhs->dosenWali->nip,
                    'email' => $mhs->dosenWali->email,
                    'telepon' => $mhs->dosenWali->telepon ?? $mhs->dosenWali->handphone,
                    'program_studi' => $mhs->dosenWali->programStudi?->nama,
                    'jabatan_akademik' => $mhs->dosenWali->jabatan_akademik,
                ] : null,
                'krs_aktif' => $krsAktif ? [
                    'id' => $krsAktif->id,
                    'status' => $krsAktif->status,
                    'total_sks' => $krsAktif->total_sks_diambil ?? $krsAktif->total_sks,
                ] : null,
                'catatan' => $catatanList,
                'total_bimbingan' => $catatanList->count(),
            ]
        ]);
    }

    public function listCatatan(Request $request)
    {
        Gate::authorize('siakad.pa.read');

        $request->validate([
            'dosen_id' => 'nullable|exists:siakad_dosen,id',
            'mahasiswa_id' => 'nullable|exists:siakad_mahasiswa,id',
            'hanya_khusus' => 'nullable|boolean',
        ]);

        $query = PaCatatan::with(['mahasiswa', 'dosen'])->orderByDesc('id');
        if ($request->filled('mahasiswa_id')) {
            $query->where('mahasiswa_id', $request->mahasiswa_id);
        }
        $user = $request->user();
        if (!$this->isPrivileged($user)) {
            $own = Dosen::where('user_id', $user->id)->first();
            if ($own) {
                $query->where('dosen_id', $own->id);
            } else {
                $mhs = Mahasiswa::where('user_id', $user->id)->first();
                if ($mhs) {
                    $query->where('mahasiswa_id', $mhs->id);
                } else {
                    $query->where('dosen_id', -1);
                }
            }
        } elseif ($request->filled('dosen_id')) {
            $query->where('dosen_id', $request->dosen_id);
        }
        if ($request->boolean('hanya_khusus')) {
            $query->where('butuh_penanganan_khusus', true);
        }

        $data = $query->paginate($request->integer('per_page', 15));

        return response()->json([
            'status' => 'success',
            'data' => $data->items(),
            'meta' => ['current_page' => $data->currentPage(), 'per_page' => $data->perPage(), 'total' => $data->total()],
        ]);
    }

    public function storeCatatan(Request $request)
    {
        Gate::authorize('siakad.pa.catatan.create');

        $validated = $request->validate([
            'mahasiswa_id' => 'nullable|exists:siakad_mahasiswa,id',
            'dosen_id' => 'nullable|exists:siakad_dosen,id',
            'kategori' => ['required', 'string', new MasterReferensiExists('pa_catatan_kategori')],
            'isi' => 'required|string',
            'kesimpulan' => 'nullable|string',
            'tanggal_bimbingan' => 'nullable|date',
            'butuh_penanganan_khusus' => 'nullable|boolean',
            'status_tindak_lanjut' => ['nullable', 'string', new MasterReferensiExists('pa_tindak_lanjut_status')],
            'tahun_akademik_id' => 'nullable|exists:siakad_tahun_akademik,id',
        ]);

        $user = $request->user();
        $dosen = Dosen::where('user_id', $user?->id)->first();
        if (!$dosen && !$this->isPrivileged($user)) {
            $mhs = Mahasiswa::where('user_id', $user?->id)->first();
            if ($mhs) {
                if (!$mhs->dosen_wali_id) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Anda belum memiliki Dosen Wali (PA) yang ditetapkan.',
                        'errors' => [
                            'dosen_wali_id' => ['Anda belum memiliki Dosen Wali (PA) yang ditetapkan.'],
                        ],
                    ], 422);
                }
                $catatan = PaCatatan::create([
                    'mahasiswa_id' => $mhs->id,
                    'dosen_id' => $mhs->dosen_wali_id,
                    'kategori' => $validated['kategori'],
                    'isi' => $validated['isi'],
                    'kesimpulan' => $validated['kesimpulan'] ?? null,
                    'tanggal_bimbingan' => $validated['tanggal_bimbingan'] ?? now()->toDateString(),
                    'butuh_penanganan_khusus' => $request->boolean('butuh_penanganan_khusus', false),
                    'status_tindak_lanjut' => 'dipantau',
                    'dibuat_oleh' => $user->id,
                    'tahun_akademik_id' => $validated['tahun_akademik_id'] ?? null,
                ]);

                try {
                    AuditLogService::record(
                        module: 'SIAKAD',
                        action: 'create',
                        tableName: 'siakad_pa_catatan',
                        recordId: $catatan->id,
                        oldValues: null,
                        newValues: $catatan->toArray()
                    );
                } catch (\Throwable $e) {
                    Log::warning('Gagal mencatat audit log catatan PA: ' . $e->getMessage());
                }

                return response()->json(['status' => 'success', 'message' => 'Catatan bimbingan berhasil diajukan ke Dosen PA.', 'data' => $catatan->load(['mahasiswa', 'dosen'])], 201);
            }
            return response()->json(['status' => 'error', 'message' => 'Akun Anda tidak terhubung ke data dosen atau mahasiswa.'], 403);
        }

        $mahasiswaId = $validated['mahasiswa_id'] ?? null;
        if (!$mahasiswaId) {
            return response()->json([
                'status' => 'error',
                'message' => 'Mahasiswa wajib dipilih.',
                'errors' => [
                    'mahasiswa_id' => ['Mahasiswa wajib dipilih.'],
                ],
            ], 422);
        }
        $mhs = Mahasiswa::findOrFail($mahasiswaId);
        // Dosen murni hanya untuk bimbingannya; istimewa boleh atas nama PA ybs.
        $dosenId = $validated['dosen_id'] ?? null;
        if (!$this->isPrivileged($user)) {
            $dosenId = $dosen?->id;
            if ((int) $mhs->dosen_wali_id !== (int) $dosenId) {
                return response()->json(['status' => 'error', 'message' => 'Mahasiswa ini bukan bimbingan Anda.'], 403);
            }
        }
        if (!$dosenId) {
            $dosenId = $mhs->dosen_wali_id;
        }
        if (!$dosenId) {
            return response()->json([
                'status' => 'error',
                'message' => 'Tentukan dosen PA untuk catatan ini.',
                'errors' => [
                    'dosen_id' => ['Tentukan dosen PA untuk catatan ini.'],
                ],
            ], 422);
        }

        $catatan = PaCatatan::create(array_merge($validated, [
            'mahasiswa_id' => $mhs->id,
            'dosen_id' => $dosenId,
            'butuh_penanganan_khusus' => $request->boolean('butuh_penanganan_khusus', false),
            'status_tindak_lanjut' => $validated['status_tindak_lanjut'] ?? 'dipantau',
            'dibuat_oleh' => $user?->id,
        ]));

        try {
            AuditLogService::record(
                module: 'SIAKAD',
                action: 'create',
                tableName: 'siakad_pa_catatan',
                recordId: $catatan->id,
                oldValues: null,
                newValues: $catatan->toArray()
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal mencatat audit log catatan PA: ' . $e->getMessage());
        }

        return response()->json(['status' => 'success', 'message' => 'Catatan bimbingan berhasil disimpan.', 'data' => $catatan->load(['mahasiswa', 'dosen'])], 201);
    }

    public function updateCatatan(Request $request, $id)
    {
        Gate::authorize('siakad.pa.update');

        $catatan = PaCatatan::findOrFail($id);
        $user = $request->user();
        $own = Dosen::where('user_id', $user?->id)->first();
        if (!$this->isPrivileged($user) && (int) $catatan->dosen_id !== (int) ($own?->id ?? -1)) {
            return response()->json(['status' => 'error', 'message' => 'Catatan ini milik PA lain.'], 403);
        }

        $validated = $request->validate([
            'kategori' => ['sometimes', 'string', new MasterReferensiExists('pa_catatan_kategori')],
            'isi' => 'sometimes|string',
            'kesimpulan' => 'nullable|string',
            'tanggal_bimbingan' => 'nullable|date',
            'butuh_penanganan_khusus' => 'nullable|boolean',
            'status_tindak_lanjut' => ['nullable', 'string', new MasterReferensiExists('pa_tindak_lanjut_status')],
        ]);
        $oldValues = $catatan->getOriginal();
        $catatan->update($validated);

        try {
            AuditLogService::record(
                module: 'SIAKAD',
                action: 'update',
                tableName: 'siakad_pa_catatan',
                recordId: $catatan->id,
                oldValues: $oldValues,
                newValues: $catatan->getChanges()
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal mencatat audit log catatan PA: ' . $e->getMessage());
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Catatan bimbingan berhasil diperbarui.',
            'data' => $catatan->fresh()->load(['mahasiswa']),
        ]);
    }

    public function destroyCatatan($id, Request $request)
    {
        Gate::authorize('siakad.pa.delete');

        $catatan = PaCatatan::findOrFail($id);
        $user = $request->user();
        $own = Dosen::where('user_id', $user?->id)->first();
        if (!$this->isPrivileged($user) && (int) $catatan->dosen_id !== (int) ($own?->id ?? -1)) {
            return response()->json(['status' => 'error', 'message' => 'Catatan ini milik PA lain.'], 403);
        }

        $oldValues = $catatan->getOriginal();
        $recordId = $catatan->id;

        $catatan->delete();

        try {
            AuditLogService::record(
                module: 'SIAKAD',
                action: 'delete',
                tableName: 'siakad_pa_catatan',
                recordId: $recordId,
                oldValues: $oldValues,
                newValues: null
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal mencatat audit log catatan PA: ' . $e->getMessage());
        }

        return response()->json(['status' => 'success', 'message' => 'Catatan bimbingan berhasil dihapus.', 'data' => null]);
    }

    public function getLaporan(Request $request)
    {
        Gate::authorize('siakad.pa.read');

        $request->validate([
            'dosen_id' => 'nullable|exists:siakad_dosen,id',
            'tahun_akademik_id' => 'nullable|exists:siakad_tahun_akademik,id',
        ]);

        $query = PaLaporan::with('dosen');
        $user = $request->user();
        if (!$this->isPrivileged($user)) {
            $own = Dosen::where('user_id', $user->id)->first();
            $query->where('dosen_id', $own?->id ?? -1);
        } elseif ($request->filled('dosen_id')) {
            $query->where('dosen_id', $request->dosen_id);
        }
        if ($request->filled('tahun_akademik_id')) {
            $query->where('tahun_akademik_id', $request->tahun_akademik_id);
        }

        return response()->json(['status' => 'success', 'data' => $query->orderByDesc('id')->get()]);
    }

    public function storeLaporan(Request $request)
    {
        Gate::authorize('siakad.pa.create');

        $validated = $request->validate([
            'dosen_id' => 'nullable|exists:siakad_dosen,id',
            'tahun_akademik_id' => 'required|exists:siakad_tahun_akademik,id',
            'kesimpulan' => 'nullable|string',
            'rekomendasi' => 'nullable|string',
            'status' => 'nullable|in:draft,final',
        ]);

        $user = $request->user();
        $own = Dosen::where('user_id', $user?->id)->first();
        $dosenId = $validated['dosen_id'] ?? $own?->id;
        if (!$dosenId) {
            return response()->json(['status' => 'error', 'message' => 'Data dosen tidak ditemukan untuk akun ini.'], 404);
        }
        if (!$this->isPrivileged($user) && (int) $dosenId !== (int) ($own?->id ?? -1)) {
            return response()->json(['status' => 'error', 'message' => 'Laporan ini milik PA lain.'], 403);
        }

        $laporan = PaLaporan::updateOrCreate(
            ['dosen_id' => $dosenId, 'tahun_akademik_id' => $validated['tahun_akademik_id']],
            [
                'kesimpulan' => $validated['kesimpulan'] ?? null,
                'rekomendasi' => $validated['rekomendasi'] ?? null,
                'status' => $validated['status'] ?? 'draft',
            ]
        );

        return response()->json(['status' => 'success', 'message' => 'Laporan aktivitas PA tersimpan', 'data' => $laporan]);
    }

    /**
     * Sesi Aktivitas Bimbingan PA per Kelas / Angkatan (Model SIMPA Indonusa: pa_aktifitas.php)
     */
    public function getLaporanAktivitas(Request $request)
    {
        Gate::authorize('siakad.pa.read');

        $request->validate([
            'dosen_id' => 'nullable|exists:siakad_dosen,id',
            'tahun_akademik_id' => 'nullable|exists:siakad_tahun_akademik,id',
            'kelas' => 'nullable|string|max:100',
        ]);

        $query = PaLaporan::with(['dosen', 'tahunAkademik'])->whereNotNull('tanggal');
        $user = $request->user();
        if (!$this->isPrivileged($user)) {
            $own = Dosen::where('user_id', $user->id)->first();
            $query->where('dosen_id', $own?->id ?? -1);
        } elseif ($request->filled('dosen_id')) {
            $query->where('dosen_id', $request->dosen_id);
        }

        if ($request->filled('tahun_akademik_id')) {
            $query->where('tahun_akademik_id', $request->tahun_akademik_id);
        }
        if ($request->filled('kelas')) {
            $query->where('kelas', $request->kelas);
        }

        $list = $query->orderBy('tanggal', 'asc')->get();

        return response()->json([
            'status' => 'success',
            'data' => $list->values()->map(function ($item, $idx) {
                return array_merge($item->toArray(), [
                    'pertemuan_ke' => $idx + 1,
                    'tanggal_id' => $item->tanggal ? $item->tanggal->format('d-m-Y') : null,
                ]);
            }),
        ]);
    }

    /**
     * Hitung komposisi kelas otomatis dari database perkuliahan BAAK (pa_aktifitas.php?aksi=aktifitas_mahasiswa)
     */
    public function hitungKomposisiKelas(Request $request)
    {
        Gate::authorize('siakad.pa.read');

        $request->validate([
            'dosen_id' => 'nullable|exists:siakad_dosen,id',
            'kelas' => 'nullable|string|max:100',
        ]);

        $user = $request->user();
        $dosen = $this->resolveDosen($request, $request->dosen_id ? (int) $request->dosen_id : null);
        if (!$this->isPrivileged($user)) {
            $dosen = Dosen::where('user_id', $user?->id)->first();
        }

        if (!$dosen) {
            return response()->json(['status' => 'error', 'message' => 'Data dosen tidak ditemukan.'], 404);
        }

        $query = Mahasiswa::where('dosen_wali_id', $dosen->id);
        if ($request->filled('kelas')) {
            $kelas = $request->kelas;
            $query->where(function ($q) use ($kelas) {
                $q->where('angkatan', $kelas)
                  ->orWhere('angkatan', (int) preg_replace('/[^0-9]/', '', $kelas));
            });
        }

        $mhsList = $query->get();

        $aktif = 0;
        $nonaktif = 0;
        $cuti = 0;
        $keluar = 0;

        foreach ($mhsList as $m) {
            if ($m->status === 'aktif') {
                $aktif++;
            } elseif ($m->status === 'cuti') {
                $cuti++;
            } elseif ($m->status === 'mangkir') {
                $nonaktif++;
            } elseif (in_array($m->status, ['dropout', 'lulus'])) {
                $keluar++;
            }
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'kelas' => $request->kelas,
                'mhs_aktif' => $aktif,
                'mhs_nonaktif' => $nonaktif,
                'mhs_cuti' => $cuti,
                'mhs_keluar' => $keluar,
                'total' => $mhsList->count(),
            ],
        ]);
    }

    /**
     * Simpan / Perbarui aktivitas bimbingan sesi kelas
     */
    public function storeLaporanAktivitas(Request $request)
    {
        Gate::authorize('siakad.pa.create');

        $validated = $request->validate([
            'id' => 'nullable|exists:siakad_pa_laporan,id',
            'dosen_id' => 'nullable|exists:siakad_dosen,id',
            'tahun_akademik_id' => 'required|exists:siakad_tahun_akademik,id',
            'tanggal' => 'required|date',
            'kelas' => 'nullable|string|max:100',
            'mhs_aktif' => 'required|integer|min:0',
            'mhs_nonaktif' => 'required|integer|min:0',
            'mhs_cuti' => 'required|integer|min:0',
            'mhs_keluar' => 'required|integer|min:0',
            'kondisi_mahasiswa' => 'required|string',
            'penanganan_mahasiswa' => 'required|string',
            'kesimpulan' => 'required|string',
            'rekomendasi' => 'nullable|string',
        ]);

        $user = $request->user();
        $own = Dosen::where('user_id', $user?->id)->first();
        $dosenId = $validated['dosen_id'] ?? $own?->id;
        if (!$dosenId) {
            return response()->json(['status' => 'error', 'message' => 'Data dosen tidak ditemukan.'], 404);
        }
        if (!$this->isPrivileged($user) && (int) $dosenId !== (int) ($own?->id ?? -1)) {
            return response()->json(['status' => 'error', 'message' => 'Aktivitas bimbingan milik PA lain.'], 403);
        }

        if (!empty($validated['id'])) {
            $laporan = PaLaporan::findOrFail($validated['id']);
            if (!$this->isPrivileged($user) && (int) $laporan->dosen_id !== (int) ($own?->id ?? -1)) {
                return response()->json(['status' => 'error', 'message' => 'Aktivitas bimbingan milik PA lain.'], 403);
            }
            $laporan->update(array_merge($validated, ['status' => 'final']));
        } else {
            $laporan = PaLaporan::create(array_merge($validated, [
                'dosen_id' => $dosenId,
                'status' => 'final',
            ]));
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Aktivitas bimbingan berhasil disimpan',
            'data' => $laporan->fresh(['dosen', 'tahunAkademik']),
        ], 201);
    }

    /**
     * Hapus aktivitas bimbingan sesi kelas
     */
    public function destroyLaporanAktivitas($id, Request $request)
    {
        Gate::authorize('siakad.pa.delete');

        $laporan = PaLaporan::findOrFail($id);
        $user = $request->user();
        $own = Dosen::where('user_id', $user?->id)->first();
        if (!$this->isPrivileged($user) && (int) $laporan->dosen_id !== (int) ($own?->id ?? -1)) {
            return response()->json(['status' => 'error', 'message' => 'Aktivitas bimbingan milik PA lain.'], 403);
        }
        $laporan->delete();

        return response()->json(['status' => 'success', 'message' => 'Aktivitas bimbingan berhasil dihapus', 'data' => null]);
    }

    /**
     * Cetak Laporan PDF / Print Preview Laporan Aktivitas Bimbingan (Model aktifitas_bimbingan_pdf.php Indonusa)
     */
    public function cetakLaporanPdf(Request $request)
    {
        Gate::authorize('siakad.pa.read');

        $request->validate([
            'dosen_id' => 'nullable|exists:siakad_dosen,id',
            'tahun_akademik_id' => 'required|exists:siakad_tahun_akademik,id',
            'kelas' => 'nullable|string|max:100',
        ]);

        $user = $request->user();
        $dosen = $this->resolveDosen($request, $request->dosen_id ? (int) $request->dosen_id : null);
        if (!$this->isPrivileged($user)) {
            $dosen = Dosen::where('user_id', $user?->id)->first();
        }

        if (!$dosen) {
            return response('Data dosen tidak ditemukan.', 404);
        }

        $ta = \App\Models\Siakad\TahunAkademik::findOrFail($request->tahun_akademik_id);
        $kelas = $request->kelas ?? 'Semua Kelas/Angkatan';

        $query = PaLaporan::where('dosen_id', $dosen->id)
            ->where('tahun_akademik_id', $ta->id)
            ->whereNotNull('tanggal');
        if ($request->filled('kelas')) {
            $query->where('kelas', $request->kelas);
        }
        $items = $query->orderBy('tanggal', 'asc')->get();

        $rowsHtml = '';
        if ($items->isEmpty()) {
            $rowsHtml = "<tr><td colspan='9' style='text-align:center; padding:15px; color:#64748b;'>Belum ada aktivitas bimbingan tercatat pada periode ini.</td></tr>";
        } else {
            foreach ($items as $idx => $d) {
                $tgl = $d->tanggal ? $d->tanggal->format('d/m/Y') : '-';
                $no = $idx + 1;
                $rowsHtml .= "
                <tr>
                    <td style='text-align:center;'>{$no}</td>
                    <td style='text-align:center; white-space:nowrap;'>{$tgl}</td>
                    <td style='text-align:center;'>{$d->mhs_aktif}</td>
                    <td style='text-align:center;'>{$d->mhs_nonaktif}</td>
                    <td style='text-align:center;'>{$d->mhs_cuti}</td>
                    <td style='text-align:center;'>{$d->mhs_keluar}</td>
                    <td style='padding:6px 8px;'>{$d->kondisi_mahasiswa}</td>
                    <td style='padding:6px 8px;'>{$d->penanganan_mahasiswa}</td>
                    <td style='padding:6px 8px;'>{$d->kesimpulan}</td>
                </tr>";
            }
        }

        $tglCetak = date('d F Y');

        $html = "<!DOCTYPE html>
<html>
<head>
    <meta charset='utf-8'>
    <title>Laporan Aktivitas Bimbingan - {$dosen->nidn}</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 11pt; color: #1e293b; margin: 20px; }
        .kop { text-align: center; border-bottom: 2px solid #0f172a; padding-bottom: 10px; margin-bottom: 15px; }
        .kop h2 { margin: 0; font-size: 14pt; letter-spacing: 0.5px; }
        .kop p { margin: 3px 0 0 0; font-size: 9pt; color: #475569; }
        .title { text-align: center; font-size: 13pt; font-weight: bold; margin: 15px 0 10px 0; text-decoration: underline; }
        table.meta { width: 100%; font-size: 10pt; margin-bottom: 15px; border-collapse: collapse; }
        table.meta td { padding: 3px 0; }
        table.data { width: 100%; border-collapse: collapse; font-size: 9.5pt; margin-top: 10px; }
        table.data th, table.data td { border: 1px solid #334155; padding: 5px 6px; }
        table.data th { background-color: #f1f5f9; font-weight: bold; text-align: center; font-size: 9pt; }
        .ttd-box { width: 100%; margin-top: 35px; border-collapse: collapse; font-size: 10.5pt; }
        @media print {
            @page { size: legal portrait; margin: 15mm; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <div class='no-print' style='background:#f8fafc; padding:10px 15px; margin-bottom:15px; border:1px solid #cbd5e1; border-radius:6px; display:flex; justify-content:space-between; align-items:center;'>
        <span><strong>Dokumen Siap Cetak</strong> (Gunakan browser print / Simpan sebagai PDF)</span>
        <button onclick='window.print()' style='background:#0284c7; color:#fff; border:none; padding:8px 16px; font-weight:bold; border-radius:4px; cursor:pointer;'>🖨️ Cetak / Unduh PDF</button>
    </div>

    <div class='kop'>
        <h2>UNIVERSITAS TERINTEGRASI</h2>
        <p>SISTEM INFORMASI AKADEMIK & PEMBIMBING AKADEMIK (SIMPA)</p>
        <p>Jl. Kampus Terpadu, Telp. (021) 1234567, Fax. (021) 1234568</p>
    </div>

    <div class='title'>LAPORAN AKTIVITAS BIMBINGAN AKADEMIK</div>

    <table class='meta'>
        <tr>
            <td width='18%'><strong>NIDN</strong></td>
            <td width='2%'>:</td>
            <td width='35%'>" . ($dosen->nidn ?: '-') . "</td>
            <td width='18%'><strong>Tahun Akademik</strong></td>
            <td width='2%'>:</td>
            <td width='25%'>{$ta->nama}</td>
        </tr>
        <tr>
            <td><strong>Nama Dosen PA</strong></td>
            <td>:</td>
            <td>{$dosen->nama_gelar}</td>
            <td><strong>Kelas / Angkatan</strong></td>
            <td>:</td>
            <td>{$kelas}</td>
        </tr>
        <tr>
            <td><strong>Program Studi</strong></td>
            <td>:</td>
            <td colspan='4'>" . ($dosen->programStudi?->nama ?: '-') . "</td>
        </tr>
    </table>

    <table class='data'>
        <thead>
            <tr>
                <th rowspan='2' width='4%'>Per</th>
                <th rowspan='2' width='11%'>Tanggal</th>
                <th colspan='4'>Komposisi Mahasiswa</th>
                <th rowspan='2' width='22%'>Kondisi Mahasiswa</th>
                <th rowspan='2' width='22%'>Penanganan Khusus</th>
                <th rowspan='2' width='22%'>Hasil & Kesimpulan</th>
            </tr>
            <tr>
                <th width='5%'>Aktif</th>
                <th width='5%'>Non</th>
                <th width='5%'>Cuti</th>
                <th width='5%'>Klr</th>
            </tr>
        </thead>
        <tbody>
            {$rowsHtml}
        </tbody>
    </table>

    <table class='ttd-box'>
        <tr>
            <td width='50%' style='text-align: center; vertical-align: top;'>
                Mengetahui,<br>
                Ketua Program Studi / Dekan<br><br><br><br><br>
                <strong>( ________________________ )</strong>
            </td>
            <td width='50%' style='text-align: center; vertical-align: top;'>
                Kampus, {$tglCetak}<br>
                Dosen Pembimbing Akademik (PA)<br><br><br><br><br>
                <strong><u>{$dosen->nama_gelar}</u></strong><br>
                NIDN. " . ($dosen->nidn ?: '-') . "
            </td>
        </tr>
    </table>
</body>
</html>";

        return response($html, 200, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    /**
     * Monitoring kehadiran bimbingan: persentase hadir, jumlah per status, dan
     * flag kehadiran kritis per mahasiswa bimbingan.
     */
    public function kehadiran(Request $request, PaMonitoringService $monitoring)
    {
        Gate::authorize('siakad.pa.read');

        $dosen = $this->resolveScopedDosen($request);
        if (!$dosen) {
            return response()->json(['status' => 'error', 'message' => 'Data dosen tidak ditemukan untuk akun ini.'], 404);
        }

        $data = $monitoring->kehadiran($dosen->id);

        return $this->paginateCollection($request, collect($data), 'Monitoring kehadiran bimbingan berhasil dimuat', ['dosen_id' => $dosen->id], ['nama_lengkap', 'nim', 'persentase_hadir', 'flag_kritis'], 'nama_lengkap');
    }

    /**
     * Monitoring prestasi: trend IPS per semester + deteksi IPS menurun/rendah.
     */
    public function prestasi(Request $request, PaMonitoringService $monitoring)
    {
        Gate::authorize('siakad.pa.read');

        $dosen = $this->resolveScopedDosen($request);
        if (!$dosen) {
            return response()->json(['status' => 'error', 'message' => 'Data dosen tidak ditemukan untuk akun ini.'], 404);
        }

        $data = $monitoring->prestasi($dosen->id);

        return $this->paginateCollection($request, collect($data), 'Monitoring prestasi bimbingan berhasil dimuat', ['dosen_id' => $dosen->id], ['nama_lengkap', 'nim'], 'nama_lengkap');
    }

    /**
     * Skor risiko "cuti/berhenti" per advisee (sinyal + band tinggi/sedang/rendah).
     */
    public function risiko(Request $request, PaMonitoringService $monitoring)
    {
        Gate::authorize('siakad.pa.read');

        $dosen = $this->resolveScopedDosen($request);
        if (!$dosen) {
            return response()->json(['status' => 'error', 'message' => 'Data dosen tidak ditemukan untuk akun ini.'], 404);
        }

        $data = $monitoring->risiko($dosen->id);

        return $this->paginateCollection($request, collect($data), 'Skor risiko bimbingan berhasil dimuat', ['dosen_id' => $dosen->id], ['nama_lengkap', 'nim', 'skor'], 'skor');
    }

    /**
     * Ringkasan "Perlu Perhatian": advisees berisiko (skor >= sedang / sinyal lain).
     */
    public function alerts(Request $request, PaMonitoringService $monitoring)
    {
        Gate::authorize('siakad.pa.read');

        $dosen = $this->resolveScopedDosen($request);
        if (!$dosen) {
            return response()->json(['status' => 'error', 'message' => 'Data dosen tidak ditemukan untuk akun ini.'], 404);
        }

        $data = $monitoring->alerts($dosen->id);

        $perPage = min(100, max(1, $request->integer('per_page', 15)));
        $page = max(1, $request->integer('page', 1));
        $items = collect($data['items']);
        $paginator = new LengthAwarePaginator(
            $items->forPage($page, $perPage)->values(),
            $items->count(),
            $perPage,
            $page,
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Ringkasan perlu perhatian berhasil dimuat',
            'data' => $paginator->items(),
            'summary' => $data['summary'],
            'meta' => [
                'dosen_id' => $dosen->id,
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ]);
    }

    // =========================================================================
    // FASE 2 — JADWAL & KONSULTASI PA
    // =========================================================================

    /** Daftar jadwal pertemuan/konsultasi PA milik dosen (scope). */
    public function listJadwal(Request $request, PaLanjutanService $service)
    {
        Gate::authorize('siakad.pa.read');

        $request->validate([
            'dosen_id' => 'nullable|exists:siakad_dosen,id',
            'dari' => 'nullable|date',
            'sampai' => 'nullable|date',
        ]);

        $dosen = $this->resolveScopedDosen($request);
        if (!$dosen) {
            return response()->json(['status' => 'error', 'message' => 'Data dosen tidak ditemukan untuk akun ini.'], 404);
        }

        $data = $service->listJadwal($dosen->id, $request->dari, $request->sampai);

        return $this->paginateCollection($request, $data, 'Jadwal bimbingan PA berhasil dimuat', ['dosen_id' => $dosen->id], ['tanggal', 'jam_mulai', 'status'], 'tanggal');
    }

    /** Buat slot jadwal pertemuan/konsultasi PA (dosen pemilik). */
    public function storeJadwal(Request $request, PaLanjutanService $service)
    {
        Gate::authorize('siakad.pa.create');

        $validated = $request->validate([
            'dosen_id' => 'nullable|exists:siakad_dosen,id',
            'tahun_akademik_id' => 'nullable|exists:siakad_tahun_akademik,id',
            'tanggal' => 'required|date',
            'jam_mulai' => 'required|date_format:H:i',
            'jam_selesai' => 'required|date_format:H:i|after:jam_mulai',
            'mode' => ['nullable', 'string', new MasterReferensiExists('pa_jadwal_mode')],
            'lokasi' => 'nullable|string|max:150',
            'kuota' => 'nullable|integer|min:1|max:100',
            'catatan' => 'nullable|string',
            'status' => ['nullable', 'string', new MasterReferensiExists('pa_jadwal_status', 'siakad')],
        ]);

        $dosen = $this->resolveScopedDosen($request);
        if (!$dosen) {
            return response()->json(['status' => 'error', 'message' => 'Data dosen tidak ditemukan untuk akun ini.'], 404);
        }

        $data = $service->createJadwal($dosen->id, $validated);

        try {
            AuditLogService::record(
                module: 'SIAKAD',
                action: 'create',
                tableName: 'siakad_pa_jadwal',
                recordId: $data->id,
                newValues: $data->toArray(),
            );
        } catch (\Throwable $e) {
            Log::warning('Audit log jadwal PA gagal: ' . $e->getMessage());
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Jadwal bimbingan PA berhasil dibuat',
            'data' => $data,
        ], 201);
    }

    /** Perbarui slot jadwal (hanya dosen pemilik / privileged). */
    public function updateJadwal(Request $request, $id, PaLanjutanService $service)
    {
        Gate::authorize('siakad.pa.update');

        $jadwal = PaJadwal::findOrFail($id);
        if (!$this->bolehKelolaJadwal($request, $jadwal->dosen_id)) {
            return response()->json(['status' => 'error', 'message' => 'Jadwal ini milik PA lain.'], 403);
        }

        $validated = $request->validate([
            'tanggal' => 'sometimes|date',
            'jam_mulai' => 'sometimes|date_format:H:i',
            'jam_selesai' => 'sometimes|date_format:H:i|after:jam_mulai',
            'mode' => ['sometimes', 'string', new MasterReferensiExists('pa_jadwal_mode')],
            'lokasi' => 'nullable|string|max:150',
            'kuota' => 'nullable|integer|min:1|max:100',
            'catatan' => 'nullable|string',
            'status' => ['sometimes', 'string', new MasterReferensiExists('pa_jadwal_status', 'siakad')],
        ]);

        $data = $service->updateJadwal($jadwal, $validated);

        try {
            AuditLogService::record(
                module: 'SIAKAD',
                action: 'update',
                tableName: 'siakad_pa_jadwal',
                recordId: $data->id,
                oldValues: $service->auditOld,
                newValues: $service->auditChanges,
            );
        } catch (\Throwable $e) {
            Log::warning('Audit log jadwal PA gagal: ' . $e->getMessage());
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Jadwal bimbingan PA berhasil diperbarui',
            'data' => $data,
        ]);
    }

    /** Hapus slot jadwal (hanya dosen pemilik / privileged). */
    public function destroyJadwal(Request $request, $id, PaLanjutanService $service)
    {
        Gate::authorize('siakad.pa.delete');

        $jadwal = PaJadwal::findOrFail($id);
        if (!$this->bolehKelolaJadwal($request, $jadwal->dosen_id)) {
            return response()->json(['status' => 'error', 'message' => 'Jadwal ini milik PA lain.'], 403);
        }

        $oldValues = $jadwal->getOriginal();
        $service->deleteJadwal($jadwal);

        try {
            AuditLogService::record(
                module: 'SIAKAD',
                action: 'delete',
                tableName: 'siakad_pa_jadwal',
                recordId: $oldValues['id'] ?? null,
                oldValues: $oldValues,
            );
        } catch (\Throwable $e) {
            Log::warning('Audit log jadwal PA gagal: ' . $e->getMessage());
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Jadwal bimbingan PA berhasil dihapus',
            'data' => null,
        ]);
    }

    /** Daftar pengajuan janji konsultasi pada jadwal dosen (scope). */
    public function listBooking(Request $request, PaLanjutanService $service)
    {
        Gate::authorize('siakad.pa.read');

        $request->validate([
            'dosen_id' => 'nullable|exists:siakad_dosen,id',
            'status' => ['nullable', 'string', new MasterReferensiExists('pa_booking_status')],
        ]);

        $dosen = $this->resolveScopedDosen($request);
        if (!$dosen) {
            return response()->json(['status' => 'error', 'message' => 'Data dosen tidak ditemukan untuk akun ini.'], 404);
        }

        $data = $service->listBookingDosen($dosen->id, $request->status);

        return $this->paginateCollection($request, $data, 'Daftar pengajuan konsultasi berhasil dimuat', ['dosen_id' => $dosen->id], ['status', 'created_at'], 'created_at');
    }

    /** Dosen memproses pengajuan konsultasi (setujui/tolak/selesai). */
    public function prosesBooking(Request $request, $id, PaLanjutanService $service)
    {
        Gate::authorize('siakad.pa.update');

        $booking = PaBooking::with('jadwal')->findOrFail($id);
        if (!$this->bolehKelolaJadwal($request, $booking->jadwal?->dosen_id)) {
            return response()->json(['status' => 'error', 'message' => 'Pengajuan ini bukan pada jadwal Anda.'], 403);
        }

        $validated = $request->validate([
            'status' => ['required', 'string', new MasterReferensiExists('pa_booking_status')],
            'catatan_dosen' => 'nullable|string',
        ]);

        $data = $service->prosesBooking($booking, $validated['status'], $validated['catatan_dosen'] ?? null);

        try {
            AuditLogService::record(
                module: 'SIAKAD',
                action: 'update',
                tableName: 'siakad_pa_booking',
                recordId: $data->id,
                oldValues: $service->auditOld,
                newValues: $service->auditChanges,
            );
        } catch (\Throwable $e) {
            Log::warning('Audit log booking PA gagal: ' . $e->getMessage());
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Pengajuan konsultasi berhasil diproses',
            'data' => $data,
        ]);
    }

    /** Mahasiswa: daftar jadwal bimbingan PA-nya + riwayat pengajuan. */
    public function myJadwalPa(Request $request, PaLanjutanService $service)
    {
        Gate::authorize('siakad.pa.read');

        $mhs = Mahasiswa::where('user_id', $request->user()?->id)->first();
        if (!$mhs) {
            return response()->json(['status' => 'error', 'message' => 'Data mahasiswa tidak ditemukan untuk akun ini.'], 404);
        }
        if (!$mhs->dosen_wali_id) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda belum memiliki Dosen Wali (PA) yang ditetapkan.',
            ], 404);
        }

        $jadwal = $service->listJadwal($mhs->dosen_wali_id);
        $riwayat = $service->listBookingMahasiswa($mhs->id);

        return response()->json([
            'status' => 'success',
            'message' => 'Jadwal konsultasi PA berhasil dimuat',
            'data' => [
                'jadwal' => $jadwal,
                'riwayat' => $riwayat,
            ],
        ]);
    }

    /** Mahasiswa: ajukan janji konsultasi pada slot jadwal PA. */
    public function ajukanBooking(Request $request, $jadwalId, PaLanjutanService $service)
    {
        Gate::authorize('siakad.pa.create');

        $validated = $request->validate([
            'topik' => 'nullable|string|max:200',
            'catatan_mahasiswa' => 'nullable|string',
        ]);

        $mhs = Mahasiswa::where('user_id', $request->user()?->id)->first();
        if (!$mhs) {
            return response()->json(['status' => 'error', 'message' => 'Data mahasiswa tidak ditemukan untuk akun ini.'], 404);
        }

        $jadwal = PaJadwal::findOrFail($jadwalId);
        if ((int) $jadwal->dosen_id !== (int) $mhs->dosen_wali_id) {
            return response()->json(['status' => 'error', 'message' => 'Jadwal ini bukan milik Dosen PA Anda.'], 403);
        }

        $hasil = $service->ajukanBooking($jadwal, $mhs->id, $validated);
        if (isset($hasil['error'])) {
            return response()->json(['status' => 'error', 'message' => $hasil['error']], 422);
        }

        try {
            AuditLogService::record(
                module: 'SIAKAD',
                action: 'create',
                tableName: 'siakad_pa_booking',
                recordId: $hasil['data']->id,
                newValues: $hasil['data']->toArray(),
            );
        } catch (\Throwable $e) {
            Log::warning('Audit log pengajuan booking PA gagal: ' . $e->getMessage());
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Pengajuan konsultasi berhasil dikirim',
            'data' => $hasil['data'],
        ], 201);
    }

    // =========================================================================
    // FASE 2 — STATUS PKL / PL / MAGANG
    // =========================================================================

    /** Daftar status PKL/PL/Magang mahasiswa bimbingan (scope). */
    public function listPkl(Request $request, PaLanjutanService $service)
    {
        Gate::authorize('siakad.pa.read');

        $request->validate(['dosen_id' => 'nullable|exists:siakad_dosen,id']);

        $dosen = $this->resolveScopedDosen($request);
        if (!$dosen) {
            return response()->json(['status' => 'error', 'message' => 'Data dosen tidak ditemukan untuk akun ini.'], 404);
        }

        $data = $service->listPklDosen($dosen->id);

        return $this->paginateCollection($request, $data, 'Status PKL/PL/Magang bimbingan berhasil dimuat', ['dosen_id' => $dosen->id], ['status', 'created_at'], 'created_at');
    }

    /** Catat/ubah status PKL/PL/Magang seorang advisee. */
    public function storePkl(Request $request, PaLanjutanService $service)
    {
        Gate::authorize('siakad.pa.create');

        $validated = $request->validate([
            'mahasiswa_id' => 'required|exists:siakad_mahasiswa,id',
            'tahun_akademik_id' => 'nullable|exists:siakad_tahun_akademik,id',
            'jenis' => ['required', 'string', new MasterReferensiExists('pa_pkl_jenis')],
            'instansi' => 'required|string|max:200',
            'lokasi' => 'nullable|string|max:200',
            'pembimbing_lapangan' => 'nullable|string|max:150',
            'tanggal_mulai' => 'nullable|date',
            'tanggal_selesai' => 'nullable|date|after_or_equal:tanggal_mulai',
            'status' => ['nullable', 'string', new MasterReferensiExists('pa_pkl_status')],
            'catatan' => 'nullable|string',
        ]);

        $dosen = $this->resolveScopedDosen($request);
        if (!$dosen) {
            return response()->json(['status' => 'error', 'message' => 'Data dosen tidak ditemukan untuk akun ini.'], 404);
        }

        if (!$this->isPrivileged($request->user())) {
            $milik = Mahasiswa::where('id', $validated['mahasiswa_id'])->where('dosen_wali_id', $dosen->id)->exists();
            if (!$milik) {
                return response()->json(['status' => 'error', 'message' => 'Mahasiswa tersebut bukan bimbingan Anda.'], 403);
            }
        }

        $data = $service->createPkl($dosen->id, $validated);

        try {
            AuditLogService::record(
                module: 'SIAKAD',
                action: 'create',
                tableName: 'siakad_pa_pkl',
                recordId: $data->id,
                newValues: $data->toArray(),
            );
        } catch (\Throwable $e) {
            Log::warning('Audit log PKL PA gagal: ' . $e->getMessage());
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Status PKL/PL/Magang berhasil dicatat',
            'data' => $data,
        ], 201);
    }

    /** Perbarui status PKL/PL/Magang. */
    public function updatePkl(Request $request, $id, PaLanjutanService $service)
    {
        Gate::authorize('siakad.pa.update');

        $pkl = PaPkl::findOrFail($id);
        if (!$this->bolehKelolaJadwal($request, $pkl->dosen_id)) {
            return response()->json(['status' => 'error', 'message' => 'Data PKL ini bukan bimbingan Anda.'], 403);
        }

        $validated = $request->validate([
            'jenis' => ['sometimes', 'string', new MasterReferensiExists('pa_pkl_jenis')],
            'instansi' => 'sometimes|string|max:200',
            'lokasi' => 'nullable|string|max:200',
            'pembimbing_lapangan' => 'nullable|string|max:150',
            'tanggal_mulai' => 'nullable|date',
            'tanggal_selesai' => 'nullable|date',
            'status' => ['sometimes', 'string', new MasterReferensiExists('pa_pkl_status')],
            'catatan' => 'nullable|string',
        ]);

        $data = $service->updatePkl($pkl, $validated);

        try {
            AuditLogService::record(
                module: 'SIAKAD',
                action: 'update',
                tableName: 'siakad_pa_pkl',
                recordId: $data->id,
                oldValues: $service->auditOld,
                newValues: $service->auditChanges,
            );
        } catch (\Throwable $e) {
            Log::warning('Audit log PKL PA gagal: ' . $e->getMessage());
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Status PKL/PL/Magang berhasil diperbarui',
            'data' => $data,
        ]);
    }

    /** Hapus catatan status PKL/PL/Magang. */
    public function destroyPkl(Request $request, $id, PaLanjutanService $service)
    {
        Gate::authorize('siakad.pa.delete');

        $pkl = PaPkl::findOrFail($id);
        if (!$this->bolehKelolaJadwal($request, $pkl->dosen_id)) {
            return response()->json(['status' => 'error', 'message' => 'Data PKL ini bukan bimbingan Anda.'], 403);
        }

        $oldValues = $pkl->getOriginal();
        $service->deletePkl($pkl);

        try {
            AuditLogService::record(
                module: 'SIAKAD',
                action: 'delete',
                tableName: 'siakad_pa_pkl',
                recordId: $oldValues['id'] ?? null,
                oldValues: $oldValues,
            );
        } catch (\Throwable $e) {
            Log::warning('Audit log PKL PA gagal: ' . $e->getMessage());
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Status PKL/PL/Magang berhasil dihapus',
            'data' => null,
        ]);
    }

    // =========================================================================
    // FASE 2 — INSIDEN (KEKERASAN/PELECEHAN) + KONSELING + ESKALASI
    // =========================================================================

    /**
     * Daftar laporan insiden (kekerasan/pelecehan). Privileged melihat semua;
     * dosen dibatasi laporan yang ia tangani. Baris anonim disamarkan.
     */
    public function listInsiden(Request $request, PaLanjutanService $service)
    {
        Gate::authorize('siakad.pa.read');

        $request->validate([
            'status' => ['nullable', 'string', new MasterReferensiExists('pa_insiden_status')],
            'jenis' => ['nullable', 'string', new MasterReferensiExists('pa_insiden_jenis')],
        ]);

        $user = $request->user();
        $privileged = $this->isPrivileged($user);
        $own = Dosen::where('user_id', $user?->id)->first();
        if (!$privileged && !$own) {
            return response()->json(['status' => 'error', 'message' => 'Hanya Dosen PA yang dapat mengakses laporan insiden.'], 403);
        }

        $data = $service->listInsiden($own?->id, $privileged, $request->status, $request->jenis);

        return $this->paginateCollection($request, $data, 'Daftar laporan insiden berhasil dimuat', [], ['status', 'created_at'], 'created_at');
    }

    /**
     * Kirim laporan insiden kekerasan/pelecehan. Mendukung pelaporan anonim
     * (identitas pelapor tidak disimpan). Dosen PA/mahasiswa dapat melapor.
     */
    public function storeInsiden(Request $request, PaLanjutanService $service)
    {
        Gate::authorize('siakad.pa.create');

        $validated = $request->validate([
            'mahasiswa_id' => 'nullable|exists:siakad_mahasiswa,id',
            'anonim' => 'nullable|boolean',
            'jenis' => ['required', 'string', new MasterReferensiExists('pa_insiden_jenis')],
            'tingkat' => 'nullable|string|max:20',
            'deskripsi' => 'required|string',
            'tanggal_kejadian' => 'nullable|date',
            'lokasi' => 'nullable|string|max:200',
        ]);

        $user = $request->user();
        $dosen = Dosen::where('user_id', $user?->id)->first();
        $anonim = (bool) ($validated['anonim'] ?? false);

        // Pelapor mahasiswa: hanya boleh melaporkan untuk dirinya sendiri.
        if (!$dosen && !$this->isPrivileged($user)) {
            $mhs = Mahasiswa::where('user_id', $user?->id)->first();
            if (!$mhs) {
                return response()->json(['status' => 'error', 'message' => 'Akun tidak dikenali sebagai mahasiswa atau dosen.'], 403);
            }
            $validated['mahasiswa_id'] = $mhs->id;
        }

        if ($anonim) {
            $validated['mahasiswa_id'] = $validated['mahasiswa_id'] ?? null;
            $validated['pelapor_user_id'] = null;
            $validated['dosen_id'] = null;
        } else {
            $validated['pelapor_user_id'] = $user?->id;
            $validated['dosen_id'] = $dosen?->id;
        }
        $validated['anonim'] = $anonim;

        $data = $service->createInsiden($validated);

        try {
            AuditLogService::record(
                module: 'SIAKAD',
                action: 'create',
                tableName: 'siakad_pa_insiden',
                recordId: $data->id,
                newValues: $anonim ? ['anonim' => true, 'jenis' => $data->jenis] : $data->toArray(),
            );
        } catch (\Throwable $e) {
            Log::warning('Audit log insiden PA gagal: ' . $e->getMessage());
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Laporan insiden berhasil dikirim',
            'data' => $service->listInsiden($dosen?->id, $this->isPrivileged($user))->firstWhere('id', $data->id),
        ], 201);
    }

    /** Eskalasi laporan insiden ke Kaprodi/Kemahasiswaan/Satgas PPKS. */
    public function eskalasiInsiden(Request $request, $id, PaLanjutanService $service)
    {
        Gate::authorize('siakad.pa.update');

        $insiden = PaInsiden::findOrFail($id);
        if (!$this->bolehKelolaJadwal($request, $insiden->dosen_id)) {
            return response()->json(['status' => 'error', 'message' => 'Laporan ini bukan dalam penanganan Anda.'], 403);
        }

        $validated = $request->validate([
            'eskalasi_tujuan' => ['required', 'string', new MasterReferensiExists('pa_eskalasi_tujuan')],
            'catatan' => 'nullable|string',
        ]);

        $data = $service->eskalasiInsiden($insiden, $validated['eskalasi_tujuan'], $validated['catatan'] ?? null);

        try {
            AuditLogService::record(
                module: 'SIAKAD',
                action: 'update',
                tableName: 'siakad_pa_insiden',
                recordId: $data->id,
                oldValues: $service->auditOld,
                newValues: $service->auditChanges,
            );
        } catch (\Throwable $e) {
            Log::warning('Audit log eskalasi insiden PA gagal: ' . $e->getMessage());
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Laporan insiden berhasil dieskalasi',
            'data' => $data,
        ]);
    }

    /** Tandai rujukan konseling untuk korban/pelapor. */
    public function rujukKonseling(Request $request, $id, PaLanjutanService $service)
    {
        Gate::authorize('siakad.pa.update');

        $insiden = PaInsiden::findOrFail($id);
        if (!$this->bolehKelolaJadwal($request, $insiden->dosen_id)) {
            return response()->json(['status' => 'error', 'message' => 'Laporan ini bukan dalam penanganan Anda.'], 403);
        }

        $validated = $request->validate([
            'konseling_ke' => 'required|string|max:150',
            'catatan' => 'nullable|string',
        ]);

        $data = $service->rujukKonseling($insiden, $validated['konseling_ke'], $validated['catatan'] ?? null);

        try {
            AuditLogService::record(
                module: 'SIAKAD',
                action: 'update',
                tableName: 'siakad_pa_insiden',
                recordId: $data->id,
                oldValues: $service->auditOld,
                newValues: $service->auditChanges,
            );
        } catch (\Throwable $e) {
            Log::warning('Audit log rujukan konseling PA gagal: ' . $e->getMessage());
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Rujukan konseling berhasil dicatat',
            'data' => $data,
        ]);
    }

    /** Perbarui status & tindak lanjut laporan insiden. */
    public function updateInsiden(Request $request, $id, PaLanjutanService $service)
    {
        Gate::authorize('siakad.pa.update');

        $insiden = PaInsiden::findOrFail($id);
        if (!$this->bolehKelolaJadwal($request, $insiden->dosen_id)) {
            return response()->json(['status' => 'error', 'message' => 'Laporan ini bukan dalam penanganan Anda.'], 403);
        }

        $validated = $request->validate([
            'status' => ['sometimes', 'string', new MasterReferensiExists('pa_insiden_status')],
            'tingkat' => 'nullable|string|max:20',
            'tindak_lanjut' => 'nullable|string',
        ]);

        $data = $service->updateInsiden($insiden, $validated);

        try {
            AuditLogService::record(
                module: 'SIAKAD',
                action: 'update',
                tableName: 'siakad_pa_insiden',
                recordId: $data->id,
                oldValues: $service->auditOld,
                newValues: $service->auditChanges,
            );
        } catch (\Throwable $e) {
            Log::warning('Audit log insiden PA gagal: ' . $e->getMessage());
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Laporan insiden berhasil diperbarui',
            'data' => $data,
        ]);
    }

    // =========================================================================
    // FASE 2 — LAPORAN MONITORING BULANAN
    // =========================================================================

    /** Ringkasan monitoring bulanan PA (komposisi risiko, kehadiran, PKL, insiden). */
    public function laporanBulanan(Request $request, PaLanjutanService $service)
    {
        Gate::authorize('siakad.pa.read');

        $validated = $request->validate([
            'dosen_id' => 'nullable|exists:siakad_dosen,id',
            'bulan' => ['required', 'regex:/^\d{4}-\d{2}$/'],
        ]);

        $dosen = $this->resolveScopedDosen($request);
        if (!$dosen) {
            return response()->json(['status' => 'error', 'message' => 'Data dosen tidak ditemukan untuk akun ini.'], 404);
        }

        $data = $service->laporanBulanan($dosen->id, $validated['bulan']);

        return response()->json([
            'status' => 'success',
            'message' => 'Laporan monitoring bulanan berhasil dimuat',
            'data' => $data,
        ]);
    }

    /**
     * Gerbang pengelolaan baris milik seorang dosen: privileged boleh semua,
     * dosen murni hanya baris dosen_id = dirinya sendiri.
     */
    private function bolehKelolaJadwal(Request $request, ?int $pemilikDosenId): bool
    {
        if ($this->isPrivileged($request->user())) {
            return true;
        }
        $own = Dosen::where('user_id', $request->user()?->id)->first();

        return $own && (int) $own->id === (int) $pemilikDosenId;
    }

    /**
     * Paginasikan koleksi hasil service sesuai standar API (per_page maks. 100)
     * dengan dukungan pengurutan dinamis `sort_by` + `sort_order` (asc/desc).
     * Mengembalikan envelope {status, message, data, meta} siap kirim.
     *
     * @param  array<int,string>  $sortable  Daftar kolom yang boleh diurutkan.
     * @param  string|null        $defaultSort  Kolom urut bawaan.
     */
    private function paginateCollection(
        Request $request,
        Collection $collection,
        string $message,
        array $meta = [],
        array $sortable = [],
        ?string $defaultSort = null
    ) {
        $sortOrder = $request->input('sort_order') === 'asc' ? 'asc' : 'desc';
        $sortBy = $request->input('sort_by');

        if ($sortBy && in_array($sortBy, $sortable, true)) {
            $collection = $sortOrder === 'asc'
                ? $collection->sortBy(fn ($row) => data_get($row, $sortBy))->values()
                : $collection->sortByDesc(fn ($row) => data_get($row, $sortBy))->values();
        } elseif ($defaultSort) {
            $collection = $sortOrder === 'asc'
                ? $collection->sortBy(fn ($row) => data_get($row, $defaultSort))->values()
                : $collection->sortByDesc(fn ($row) => data_get($row, $defaultSort))->values();
        }

        $perPage = min(100, max(1, $request->integer('per_page', 15)));
        $page = max(1, $request->integer('page', 1));

        $paginator = new LengthAwarePaginator(
            $collection->forPage($page, $perPage)->values(),
            $collection->count(),
            $perPage,
            $page,
        );

        return response()->json([
            'status' => 'success',
            'message' => $message,
            'data' => $paginator->items(),
            'meta' => array_merge($meta, [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
                'sort_by' => $sortBy && in_array($sortBy, $sortable, true) ? $sortBy : $defaultSort,
                'sort_order' => $sortOrder,
            ]),
        ]);
    }
}
