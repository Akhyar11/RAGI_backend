<?php

namespace App\Http\Controllers\API\Siakad;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Siakad\Mahasiswa;
use App\Models\Siakad\KonversiTransfer;
use App\Models\Siakad\KonversiTransferDetail;
use App\Models\User;
use App\Models\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class MahasiswaController extends Controller
{
    public function index(Request $request)
    {
        $perPage = min(100, $request->integer('per_page', 15));
        $query = Mahasiswa::with(['programStudi', 'dosenWali', 'konversiTransfer.details.mataKuliahDiakui', 'spmbKonversi.pendaftaranCalonMhs']);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nama_lengkap', 'like', "%{$search}%")
                  ->orWhere('nim', 'like', "%{$search}%")
                  ->orWhere('nik', 'like', "%{$search}%");
            });
        }

        if ($request->filled('program_studi_id')) {
            $query->where('program_studi_id', $request->program_studi_id);
        }

        if ($request->filled('angkatan')) {
            $query->where('angkatan', $request->angkatan);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('kelas_id')) {
            $query->whereHas('krs.krsDetails', function ($q) use ($request) {
                $q->where('kelas_id', $request->kelas_id);
            });
        }

        $allowedSortColumns = ['created_at', 'nim', 'nama_lengkap', 'angkatan'];
        $sortBy = in_array($request->sort_by, $allowedSortColumns) ? $request->sort_by : 'created_at';
        $sortOrder = $request->sort_order === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortBy, $sortOrder);

        $data = $query->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'message' => 'Data mahasiswa berhasil dimuat',
            'data' => $data->items(),
            'meta' => [
                'current_page' => $data->currentPage(),
                'per_page' => $data->perPage(),
                'total' => $data->total(),
                'last_page' => $data->lastPage(),
                'from' => $data->firstItem(),
                'to' => $data->lastItem(),
            ],
            'filters' => [
                'search' => $request->search,
                'program_studi_id' => $request->program_studi_id,
                'angkatan' => $request->angkatan,
                'status' => $request->status,
                'kelas_id' => $request->kelas_id,
                'sort_by' => $sortBy,
                'sort_order' => $sortOrder,
            ]
        ]);
    }

    public function show($id)
    {
        $mhs = Mahasiswa::with(['programStudi', 'dosenWali', 'konversiTransfer.details.mataKuliahDiakui', 'krs.krsDetails.kelas.mataKuliah', 'khs'])->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'message' => 'Detail mahasiswa berhasil dimuat',
            'data' => $mhs
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'program_studi_id' => 'required|exists:siakad_program_studi,id',
            'nim' => 'required|string|unique:siakad_mahasiswa,nim',
            'nama_lengkap' => 'required|string|max:255',
            'nik' => 'nullable|string|max:20',
            'jenis_kelamin' => 'required|in:L,P',
            'angkatan' => 'required|integer',
            'dosen_wali_id' => 'nullable|exists:siakad_dosen,id',
            'status' => 'nullable|in:aktif,cuti,mangkir,dropout,lulus',
        ]);

        return DB::transaction(function () use ($request) {
            $user = User::where('username', $request->nim)->first();
            if (!$user) {
                $email = $request->email ?: ($request->nim . '@campus.ac.id');
                $user = User::create([
                    'username' => $request->nim,
                    'email' => $email,
                    'password' => Hash::make('password'),
                    'is_active' => true,
                    'is_verified' => true,
                    'email_verified_at' => now(),
                ]);

                $role = Role::where('slug', 'mahasiswa')->first();
                if ($role) {
                    $user->roles()->attach($role->id);
                }
            }

            $mhs = Mahasiswa::create(array_merge($request->all(), [
                'user_id' => $user->id,
                'status' => $request->input('status', 'aktif'),
                'tanggal_masuk' => now()->toDateString(),
            ]));

            return response()->json([
                'status' => 'success',
                'message' => 'Mahasiswa & Akun SSO IAM berhasil dibuat',
                'data' => $mhs
            ], 201);
        });
    }

    public function update(Request $request, $id)
    {
        $mhs = Mahasiswa::findOrFail($id);

        $validated = $request->validate([
            'nama_lengkap' => 'required|string|max:255',
            'nim' => 'nullable|string|max:50',
            'nik' => 'nullable|string|max:20',
            'telepon' => 'nullable|string|max:30',
            'alamat' => 'nullable|string',
            'dosen_wali_id' => 'nullable|exists:siakad_dosen,id',
            'status' => 'nullable|in:aktif,cuti,mangkir,dropout,lulus',
            
            // Biodata fields
            'tempat_lahir' => 'nullable|string|max:100',
            'tanggal_lahir' => 'nullable|date',
            'jenis_kelamin' => 'nullable|in:L,P',
            'agama' => 'nullable|string|max:50',
            'nisn' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:100',
            'rt' => 'nullable|string|max:10',
            'rw' => 'nullable|string|max:10',
            'dusun' => 'nullable|string|max:100',
            'kelurahan' => 'nullable|string|max:100',
            'kecamatan' => 'nullable|string|max:100',
            'kota' => 'nullable|string|max:100',
            'provinsi' => 'nullable|string|max:100',
            'kode_pos' => 'nullable|string|max:10',
            'jenis_tinggal' => 'nullable|string|max:50',
            'alat_transportasi' => 'nullable|string|max:50',
            'nama_ibu_kandung' => 'nullable|string|max:150',
            'nik_ibu' => 'nullable|string|max:30',
            'nama_ayah' => 'nullable|string|max:150',
            'nik_ayah' => 'nullable|string|max:30',
            'nama_wali' => 'nullable|string|max:150',
        ]);

        $mhs->update($validated);

        if (!empty($validated['email']) && $mhs->user) {
            $mhs->user->update(['email' => $validated['email']]);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Data mahasiswa berhasil diperbarui',
            'data' => $mhs->fresh(['programStudi', 'dosenWali', 'konversiTransfer'])
        ]);
    }

    /**
     * Ubah status akademik mahasiswa (aktif/cuti/mangkir/dropout/lulus) + catat jejak.
     * Status 'keluar' dicatat sebagai dropout (DO/mengundurkan diri); kelulusan via Yudisium.
     */
    public function updateStatus(Request $request, $id)
    {
        $validated = $request->validate([
            'status' => 'required|in:aktif,cuti,mangkir,dropout,lulus',
            'alasan' => 'nullable|string|max:500',
        ]);

        $mhs = Mahasiswa::findOrFail($id);
        if ($mhs->status !== 'aktif' && $validated['status'] !== 'aktif' && empty($validated['alasan'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Alasan wajib diisi untuk perubahan status non-aktif.',
            ], 422);
        }

        return \Illuminate\Support\Facades\DB::transaction(function () use ($request, $mhs, $validated) {
            $lama = $mhs->status;
            $mhs->update(['status' => $validated['status']]);

            \App\Models\Siakad\StatusAkademikLog::create([
                'mahasiswa_id' => $mhs->id,
                'status_lama' => $lama,
                'status_baru' => $validated['status'],
                'alasan' => $validated['alasan'] ?? null,
                'diubah_oleh' => $request->user()?->id,
            ]);

            return response()->json([
                'status' => 'success',
                'message' => "Status mahasiswa diubah dari {$lama} menjadi {$validated['status']}.",
                'data' => $mhs->fresh()->load(['programStudi', 'dosenWali']),
            ]);
        });
    }

    /**
     * Ubah status akademik massal (checklist). Khusus status tanpa alasan personal:
     * aktif/cuti/lulus. Dropout/mangkir wajib per mahasiswa (butuh alasan individual).
     */
    public function bulkUpdateStatus(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1|max:500',
            'ids.*' => 'exists:siakad_mahasiswa,id',
            'status' => 'required|in:aktif,cuti,lulus',
            'alasan' => 'nullable|string|max:500',
        ]);

        if ($validated['status'] !== 'aktif' && empty($validated['alasan'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Alasan wajib diisi untuk perubahan status massal non-aktif.',
            ], 422);
        }

        return \Illuminate\Support\Facades\DB::transaction(function () use ($request, $validated) {
            $list = Mahasiswa::whereIn('id', $validated['ids'])->get();
            foreach ($list as $mhs) {
                if ($mhs->status === $validated['status']) continue;
                $lama = $mhs->status;
                $mhs->update(['status' => $validated['status']]);
                \App\Models\Siakad\StatusAkademikLog::create([
                    'mahasiswa_id' => $mhs->id,
                    'status_lama' => $lama,
                    'status_baru' => $validated['status'],
                    'alasan' => $validated['alasan'] ?? null,
                    'diubah_oleh' => $request->user()?->id,
                ]);
            }

            return response()->json([
                'status' => 'success',
                'message' => count($list) . " mahasiswa diubah statusnya menjadi {$validated['status']}.",
            ]);
        });
    }

    public function destroy($id)
    {
        $mhs = Mahasiswa::findOrFail($id);
        $mhs->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Mahasiswa berhasil dihapus (Soft Delete)'
        ]);
    }

    public function generateNim(Request $request)
    {
        $request->validate([
            'program_studi_id' => 'required|exists:siakad_program_studi,id',
            'angkatan' => 'required|integer',
            'nama_lengkap' => 'required|string',
            'jenis_kelamin' => 'required|in:L,P',
            'id' => 'nullable|exists:siakad_mahasiswa,id',
            'custom_nim' => 'nullable|string|max:50',
            'use_no_pendaftaran' => 'nullable|boolean',
        ]);

        if ($request->filled('id')) {
            $existing = Mahasiswa::with('spmbKonversi.pendaftaranCalonMhs')->find($request->id);
            if ($existing && !empty($existing->nim) && !$request->filled('custom_nim') && !$request->boolean('use_no_pendaftaran')) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'Mahasiswa sudah memiliki NIM resmi.',
                    'data' => $existing
                ]);
            }
        }

        $nim = null;
        if ($request->filled('custom_nim')) {
            // 1. NIM manual / format khusus dari kampus (bisa huruf, angka, kode khusus)
            $nim = trim($request->custom_nim);
        } elseif ($request->boolean('use_no_pendaftaran') && $request->filled('id')) {
            // 2. Menggunakan Nomor Pendaftaran SPMB sebagai NIM
            $mhsCheck = Mahasiswa::with('spmbKonversi.pendaftaranCalonMhs')->find($request->id);
            $noDaftar = $mhsCheck?->spmbKonversi?->pendaftaranCalonMhs?->no_pendaftaran;
            if ($noDaftar) {
                $nim = $noDaftar;
            }
        }

        // 3. Fallback jika tidak ada custom NIM atau no pendaftaran: generate standar
        if (empty($nim)) {
            $konversiService = app(\App\Services\Spmb\SpmbKonversiService::class);
            $nim = $konversiService->generateNIM((int)$request->angkatan, (int)$request->program_studi_id);
        }

        // Cek duplikasi NIM terhadap mahasiswa lain
        $duplicate = Mahasiswa::where('nim', $nim)
            ->when($request->filled('id'), fn($q) => $q->where('id', '!=', $request->id))
            ->first();
        if ($duplicate) {
            return response()->json([
                'status' => 'error',
                'message' => "NIM '{$nim}' sudah digunakan oleh mahasiswa lain ({$duplicate->nama_lengkap}). Silakan gunakan NIM unik."
            ], 422);
        }

        if ($request->filled('id')) {
            $mhs = Mahasiswa::findOrFail($request->id);
            $mhs->update(['nim' => $nim]);
        } else {
            $mhs = Mahasiswa::create([
                'program_studi_id' => $request->program_studi_id,
                'nim' => $nim,
                'nama_lengkap' => $request->nama_lengkap,
                'jenis_kelamin' => $request->jenis_kelamin,
                'angkatan' => $request->angkatan,
                'tanggal_masuk' => now()->toDateString(),
                'status' => 'aktif',
            ]);
        }

        return response()->json([
            'status' => 'success',
            'message' => "NIM {$nim} berhasil diterapkan untuk mahasiswa.",
            'data' => $mhs->fresh(['programStudi', 'dosenWali', 'spmbKonversi.pendaftaranCalonMhs'])
        ], 201);
    }

    public function generateMissingNims(Request $request)
    {
        $unassigned = Mahasiswa::with('spmbKonversi.pendaftaranCalonMhs')
            ->where(function ($q) {
                $q->whereNull('nim')->orWhere('nim', '');
            })
            ->get();

        if ($unassigned->isEmpty()) {
            return response()->json([
                'status' => 'success',
                'message' => 'Semua data mahasiswa sudah memiliki NIM lengkap. Tidak ada perubahan yang dilakukan.',
                'data' => ['generated_count' => 0]
            ]);
        }

        $scheme = $request->input('scheme', 'standard'); // standard | no_pendaftaran
        $konversiService = app(\App\Services\Spmb\SpmbKonversiService::class);
        $count = 0;

        foreach ($unassigned as $mhs) {
            $nim = null;
            if ($scheme === 'no_pendaftaran') {
                $noDaftar = $mhs->spmbKonversi?->pendaftaranCalonMhs?->no_pendaftaran;
                if (!empty($noDaftar) && !Mahasiswa::where('nim', $noDaftar)->where('id', '!=', $mhs->id)->exists()) {
                    $nim = $noDaftar;
                }
            }

            if (empty($nim)) {
                $angkatan = $mhs->angkatan ?: (int)date('Y');
                $prodiId = $mhs->program_studi_id ?: 1;
                $nim = $konversiService->generateNIM($angkatan, $prodiId);
            }

            $mhs->update(['nim' => $nim]);
            $count++;
        }

        return response()->json([
            'status' => 'success',
            'message' => "Berhasil menetapkan NIM resmi untuk {$count} mahasiswa yang sebelumnya belum memiliki NIM (" . ($scheme === 'no_pendaftaran' ? 'mengutamakan No. Pendaftaran SPMB' : 'Format Standar') . ").",
            'data' => ['generated_count' => $count]
        ]);
    }

    /**
     * Export template data mahasiswa (CSV) untuk pemetaan NIM manual / kombinasi karakter
     */
    public function exportNimData(Request $request)
    {
        $query = Mahasiswa::with(['programStudi', 'spmbKonversi.pendaftaranCalonMhs'])
            ->where('status', 'aktif');

        if ($request->filled('program_studi_id')) {
            $query->where('program_studi_id', $request->program_studi_id);
        }
        if ($request->filled('angkatan')) {
            $query->where('angkatan', $request->angkatan);
        }
        if ($request->input('status_nim') === 'unassigned') {
            $query->where(fn($q) => $q->whereNull('nim')->orWhere('nim', ''));
        }

        $list = $query->orderBy('nama_lengkap')->get();

        $rows = [];
        $rows[] = ['ID_MAHASISWA', 'NO_PENDAFTARAN_SPMB', 'NIM_SAAT_INI', 'NIM_BARU', 'NAMA_LENGKAP', 'PROGRAM_STUDI', 'ANGKATAN'];

        foreach ($list as $m) {
            $rows[] = [
                $m->id,
                $m->spmbKonversi?->pendaftaranCalonMhs?->no_pendaftaran ?? '',
                $m->nim ?? '',
                $m->nim ?? ($m->spmbKonversi?->pendaftaranCalonMhs?->no_pendaftaran ?? ''),
                $m->nama_lengkap,
                $m->programStudi?->nama ?? '',
                $m->angkatan ?? date('Y'),
            ];
        }

        $output = '';
        foreach ($rows as $row) {
            $output .= implode(',', array_map(function ($val) {
                return '"' . str_replace('"', '""', (string)$val) . '"';
            }, $row)) . "\n";
        }

        return response($output, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="template_penetapan_nim_mahasiswa.csv"',
        ]);
    }

    /**
     * Export Buku Induk — rekapan data mahasiswa per angkatan & program studi (CSV).
     */
    public function exportBukuInduk(Request $request)
    {
        $request->validate([
            'program_studi_id' => 'nullable|exists:siakad_program_studi,id',
            'angkatan' => 'nullable|integer|min:2000|max:2100',
            'status' => 'nullable|in:aktif,cuti,mangkir,dropout,lulus',
        ]);

        $query = Mahasiswa::with(['programStudi', 'dosenWali'])
            ->orderBy('angkatan')
            ->orderBy('nama_lengkap');

        if ($request->filled('program_studi_id')) {
            $query->where('program_studi_id', $request->program_studi_id);
        }
        if ($request->filled('angkatan')) {
            $query->where('angkatan', $request->angkatan);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $list = $query->get();

        $rows = [];
        $rows[] = ['NO', 'NIM', 'NAMA_LENGKAP', 'NIK', 'JENIS_KELAMIN', 'TEMPAT_LAHIR', 'TANGGAL_LAHIR', 'PROGRAM_STUDI', 'ANGKATAN', 'JALUR_MASUK', 'DOSEN_WALI', 'TELEPON', 'EMAIL', 'STATUS', 'IPK'];

        foreach ($list as $i => $m) {
            $rows[] = [
                $i + 1,
                $m->nim ?? '',
                $m->nama_lengkap,
                $m->nik ?? '',
                $m->jenis_kelamin ?? '',
                $m->tempat_lahir ?? '',
                $m->tanggal_lahir ? $m->tanggal_lahir->format('Y-m-d') : '',
                $m->programStudi?->nama ?? '',
                $m->angkatan ?? '',
                $m->jalur_masuk ?? '',
                $m->dosenWali?->nama_lengkap ?? '',
                $m->telepon ?? '',
                $m->email ?? '',
                $m->status ?? '',
                is_numeric($m->ipk) ? number_format((float) $m->ipk, 2) : '',
            ];
        }

        $output = "\xEF\xBB\xBF";
        foreach ($rows as $row) {
            $output .= implode(',', array_map(function ($val) {
                return '"' . str_replace('"', '""', (string)$val) . '"';
            }, $row)) . "\n";
        }

        $suffix = [];
        if ($request->filled('angkatan')) $suffix[] = $request->angkatan;
        if ($request->filled('status')) $suffix[] = $request->status;
        $filename = 'buku_induk_mahasiswa' . ($suffix ? '_' . implode('_', $suffix) : '') . '.csv';

        return response($output, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * Import hasil pemetaan NIM dari file CSV
     */
    public function importNimData(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:5120',
        ]);

        $file = $request->file('file');
        $handle = fopen($file->getRealPath(), 'r');
        if (!$handle) {
            return response()->json(['status' => 'error', 'message' => 'Gagal membaca file CSV.'], 422);
        }

        $header = fgetcsv($handle, 1000, ',');
        $updatedCount = 0;
        $errors = [];
        $line = 1;

        DB::beginTransaction();
        try {
            while (($data = fgetcsv($handle, 1000, ',')) !== false) {
                $line++;
                if (count($data) < 4) {
                    continue;
                }

                $mhsId = trim($data[0] ?? '');
                $nimBaru = trim($data[3] ?? '');

                if (empty($mhsId) || empty($nimBaru)) {
                    continue;
                }

                $mhs = Mahasiswa::find($mhsId);
                if (!$mhs) {
                    $errors[] = "Baris {$line}: Mahasiswa ID {$mhsId} tidak ditemukan.";
                    continue;
                }

                // Periksa apakah NIM baru sudah dipakai oleh mahasiswa lain
                $existing = Mahasiswa::where('nim', $nimBaru)->where('id', '!=', $mhsId)->first();
                if ($existing) {
                    $errors[] = "Baris {$line}: NIM '{$nimBaru}' sudah digunakan oleh {$existing->nama_lengkap}.";
                    continue;
                }

                $mhs->update(['nim' => $nimBaru]);
                $updatedCount++;
            }

            DB::commit();
            fclose($handle);

            return response()->json([
                'status' => 'success',
                'message' => "Berhasil memperbarui NIM untuk {$updatedCount} mahasiswa." . (count($errors) > 0 ? " (" . count($errors) . " baris dilewati karena duplikasi/data tidak valid)." : ""),
                'data' => [
                    'updated_count' => $updatedCount,
                    'errors' => array_slice($errors, 0, 10),
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            fclose($handle);
            return response()->json(['status' => 'error', 'message' => 'Terjadi kesalahan saat memproses data: ' . $e->getMessage()], 500);
        }
    }

    public function syncFromSpmb(Request $request)
    {
        $pendaftarList = \App\Models\Spmb\PendaftaranCalonMhs::with(['hasilSeleksi', 'programStudi'])
            ->where(function ($q) {
                $q->where('status', \App\Models\Spmb\PendaftaranCalonMhs::STATUS_MAHASISWA_BARU)
                  ->orWhere('status', 'verified')
                  ->orWhereHas('hasilSeleksi', function ($hq) {
                      $hq->where('status', 'lulus');
                  });
            })
            ->get();

        if ($pendaftarList->isEmpty()) {
            return response()->json([
                'status' => 'success',
                'message' => 'Tidak ada pendaftar SPMB yang perlu disinkronkan saat ini.',
                'data' => ['synced_count' => 0]
            ]);
        }

        $konversiService = app(\App\Services\Spmb\SpmbKonversiService::class);
        $synced = 0;

        foreach ($pendaftarList as $pendaftaran) {
            $konversiService->prosesKonversi($pendaftaran, $request->user()?->id);
            $synced++;
        }

        return response()->json([
            'status' => 'success',
            'message' => "Berhasil menyinkronkan {$synced} mahasiswa baru dari modul SPMB ke SIAKAD.",
            'data' => ['synced_count' => $synced]
        ]);
    }

    public function listKonversi(Request $request)
    {
        $konversi = KonversiTransfer::with(['mahasiswa.programStudi', 'diprosesOleh', 'details.mataKuliahDiakui'])
            ->orderBy('created_at', 'desc')
            ->paginate($request->integer('per_page', 15));

        return response()->json([
            'status' => 'success',
            'message' => 'Data konversi transfer berhasil dimuat',
            'data' => $konversi->items(),
            'meta' => [
                'current_page' => $konversi->currentPage(),
                'per_page' => $konversi->perPage(),
                'total' => $konversi->total(),
            ]
        ]);
    }

    public function storeKonversi(Request $request)
    {
        $request->validate([
            'mahasiswa_id' => 'required|exists:siakad_mahasiswa,id',
            'kampus_asal' => 'required|string|max:255',
            'prodi_asal' => 'required|string|max:255',
            'catatan' => 'nullable|string',
            'status' => 'nullable|string|in:draft,diajukan,disetujui,ditolak',
            'details' => 'required|array|min:1',
            'details.*.mata_kuliah_diakui_id' => 'required|exists:siakad_mata_kuliah,id',
            'details.*.kode_mk_asal' => 'required|string|max:50',
            'details.*.nama_mk_asal' => 'required|string|max:255',
            'details.*.sks_asal' => 'required|integer|min:1',
            'details.*.nilai_huruf_asal' => 'required|string|max:5',
        ]);

        return DB::transaction(function () use ($request) {
            $mhs = Mahasiswa::findOrFail($request->mahasiswa_id);
            
            // Determine status
            $status = $request->status;
            $user = $request->user();
            
            if ($user && ($user->hasRole('admin') || $user->hasRole('dosen'))) {
                $status = $status ?? 'disetujui';
            } else {
                // Students can only save as draft or diajukan
                if (!in_array($status, ['draft', 'diajukan'])) {
                    $status = 'draft';
                }
            }
            
            $konversi = null;
            if ($mhs->konversi_id) {
                $konversi = KonversiTransfer::find($mhs->konversi_id);
            }

            if ($konversi) {
                $konversi->update([
                    'kampus_asal' => $request->kampus_asal,
                    'prodi_asal' => $request->prodi_asal,
                    'diproses_oleh' => $user?->id,
                    'status' => $status,
                    'catatan' => $request->catatan,
                ]);
                $konversi->details()->delete();
            } else {
                $noTransaksi = 'KNV-' . date('Y') . '-' . str_pad(KonversiTransfer::count() + 1, 3, '0', STR_PAD_LEFT);

                $konversi = KonversiTransfer::create([
                    'mahasiswa_id' => $request->mahasiswa_id,
                    'no_transaksi' => $noTransaksi,
                    'kampus_asal' => $request->kampus_asal,
                    'prodi_asal' => $request->prodi_asal,
                    'diproses_oleh' => $user?->id,
                    'status' => $status,
                    'catatan' => $request->catatan,
                ]);

                $mhs->update(['konversi_id' => $konversi->id]);
            }

            foreach ($request->details as $item) {
                KonversiTransferDetail::create([
                    'konversi_id' => $konversi->id,
                    'mata_kuliah_diakui_id' => $item['mata_kuliah_diakui_id'],
                    'kode_mk_asal' => $item['kode_mk_asal'],
                    'nama_mk_asal' => $item['nama_mk_asal'],
                    'sks_asal' => $item['sks_asal'],
                    'nilai_huruf_asal' => $item['nilai_huruf_asal'],
                ]);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Konversi transfer nilai mahasiswa berhasil disimpan',
                'data' => $konversi->load('details.mataKuliahDiakui')
            ], 201);
        });
    }

    public function destroyKonversi($id)
    {
        $konversi = KonversiTransfer::findOrFail($id);
        Mahasiswa::where('konversi_id', $konversi->id)->update(['konversi_id' => null]);
        $konversi->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Konversi transfer berhasil dihapus'
        ]);
    }

    public function updateKonversiStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:draft,diajukan,disetujui,ditolak',
            'catatan' => 'nullable|string',
        ]);

        $konversi = KonversiTransfer::findOrFail($id);
        
        $konversi->update([
            'status' => $request->status,
            'diproses_oleh' => $request->user()?->id,
            'catatan' => $request->catatan ?? $konversi->catatan,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Status konversi transfer berhasil diperbarui menjadi: ' . $request->status,
            'data' => $konversi->load('details.mataKuliahDiakui')
        ]);
    }

    public function bulkAssignPa(Request $request)
    {
        $request->validate([
            'mahasiswa_ids' => 'required|array|min:1',
            'mahasiswa_ids.*' => 'exists:siakad_mahasiswa,id',
            'dosen_wali_id' => 'required|exists:siakad_dosen,id',
        ]);

        $dosen = \App\Models\Siakad\Dosen::findOrFail($request->dosen_wali_id);
        $count = Mahasiswa::whereIn('id', $request->mahasiswa_ids)
            ->update(['dosen_wali_id' => $dosen->id]);

        return response()->json([
            'status' => 'success',
            'message' => "Berhasil menetapkan {$dosen->nama_lengkap} sebagai Dosen PA untuk {$count} mahasiswa terpilih.",
            'data' => [
                'updated_count' => $count,
                'dosen_wali' => $dosen
            ]
        ]);
    }

    /**
     * Distribusi mahasiswa tanpa Dosen PA secara merata (Round-Robin) ke dosen terpilih
     */
    public function autoDistributePa(Request $request)
    {
        $request->validate([
            'dosen_ids' => 'required|array|min:1',
            'dosen_ids.*' => 'exists:siakad_dosen,id',
            'program_studi_id' => 'nullable|exists:siakad_program_studi,id',
            'angkatan' => 'nullable|integer',
        ]);

        $query = Mahasiswa::whereNull('dosen_wali_id')->where('status', 'aktif');

        if ($request->filled('program_studi_id')) {
            $query->where('program_studi_id', $request->program_studi_id);
        }
        if ($request->filled('angkatan')) {
            $query->where('angkatan', $request->angkatan);
        }

        $unassignedMahasiswas = $query->orderBy('nim')->get();
        if ($unassignedMahasiswas->isEmpty()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Tidak ditemukan mahasiswa aktif yang belum memiliki Dosen PA pada kriteria ini.'
            ], 422);
        }

        $dosenIds = $request->dosen_ids;
        $dosenCount = count($dosenIds);
        $assignedCount = 0;

        DB::transaction(function () use ($unassignedMahasiswas, $dosenIds, $dosenCount, &$assignedCount) {
            foreach ($unassignedMahasiswas as $index => $mhs) {
                $targetDosenId = $dosenIds[$index % $dosenCount];
                $mhs->update(['dosen_wali_id' => $targetDosenId]);
                $assignedCount++;
            }
        });

        return response()->json([
            'status' => 'success',
            'message' => "Berhasil mendistribusikan {$assignedCount} mahasiswa secara merata kepada {$dosenCount} Dosen PA terpilih.",
            'data' => [
                'assigned_count' => $assignedCount,
                'dosen_count' => $dosenCount,
            ]
        ]);
    }

    /**
     * Dapatkan profil & biodata lengkap mahasiswa (Self-Service)
     */
    public function getProfil(Request $request)
    {
        $user = $request->user();
        $mhs = null;

        if ($user) {
            $mhs = Mahasiswa::with(['programStudi.fakultas', 'dosenWali', 'konversiTransfer.details.mataKuliahDiakui', 'user'])
                ->where('user_id', $user->id)
                ->first();

            if (!$mhs && !empty($user->username)) {
                $mhs = Mahasiswa::with(['programStudi.fakultas', 'dosenWali', 'konversiTransfer.details.mataKuliahDiakui', 'user'])
                    ->where('nim', $user->username)
                    ->first();
            }
        }

        if (!$mhs && $request->filled('mahasiswa_id')) {
            $mhs = Mahasiswa::with(['programStudi.fakultas', 'dosenWali', 'konversiTransfer.details.mataKuliahDiakui', 'user'])
                ->find($request->mahasiswa_id);
        }

        if (!$mhs) {
            return response()->json([
                'status' => 'error',
                'message' => 'Data mahasiswa tidak ditemukan.'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $mhs
        ]);
    }

    /**
     * Update biodata mahasiswa mandiri (Self-Service)
     */
    public function updateProfil(Request $request)
    {
        $user = $request->user();
        $mhs = null;

        if ($user) {
            $mhs = Mahasiswa::where('user_id', $user->id)->first();
            if (!$mhs && !empty($user->username)) {
                $mhs = Mahasiswa::where('nim', $user->username)->first();
            }
        }

        if (!$mhs && $request->filled('mahasiswa_id')) {
            $mhs = Mahasiswa::find($request->mahasiswa_id);
        }

        if (!$mhs) {
            return response()->json([
                'status' => 'error',
                'message' => 'Data mahasiswa tidak ditemukan.'
            ], 404);
        }

        $validated = $request->validate([
            'tempat_lahir' => 'nullable|string|max:100',
            'tanggal_lahir' => 'nullable|date',
            'jenis_kelamin' => 'nullable|in:L,P',
            'agama' => 'nullable|string|max:50',
            'nik' => 'nullable|string|max:20',
            'nisn' => 'nullable|string|max:30',
            'telepon' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:100',
            'alamat' => 'nullable|string',
            'rt' => 'nullable|string|max:10',
            'rw' => 'nullable|string|max:10',
            'dusun' => 'nullable|string|max:100',
            'kelurahan' => 'nullable|string|max:100',
            'kecamatan' => 'nullable|string|max:100',
            'kota' => 'nullable|string|max:100',
            'provinsi' => 'nullable|string|max:100',
            'kode_pos' => 'nullable|string|max:10',
            'jenis_tinggal' => 'nullable|string|max:50',
            'alat_transportasi' => 'nullable|string|max:50',
            'nama_ibu_kandung' => 'nullable|string|max:150',
            'nik_ibu' => 'nullable|string|max:30',
            'nama_ayah' => 'nullable|string|max:150',
            'nik_ayah' => 'nullable|string|max:30',
            'nama_wali' => 'nullable|string|max:150',
        ]);

        $mhs->update($validated);

        if (!empty($validated['email']) && $mhs->user) {
            $mhs->user->update(['email' => $validated['email']]);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Biodata mahasiswa berhasil diperbarui.',
            'data' => $mhs->fresh(['programStudi.fakultas', 'dosenWali', 'konversiTransfer'])
        ]);
    }

    /**
     * Sinkronisasi data mahasiswa mandiri ke Neo Feeder PDDikti
     */
    public function syncProfilToFeeder(Request $request, \App\Services\Siakad\NeoFeederSyncService $syncService)
    {
        $user = $request->user();
        $mhs = null;

        if ($user) {
            $mhs = Mahasiswa::where('user_id', $user->id)->first();
            if (!$mhs && !empty($user->username)) {
                $mhs = Mahasiswa::where('nim', $user->username)->first();
            }
        }

        if (!$mhs && $request->filled('mahasiswa_id')) {
            $mhs = Mahasiswa::find($request->mahasiswa_id);
        }

        if (!$mhs) {
            return response()->json([
                'status' => 'error',
                'message' => 'Data mahasiswa tidak ditemukan.'
            ], 404);
        }

        try {
            $result = $syncService->syncSingleMahasiswa($mhs->id, $user?->id);

            return response()->json([
                'status' => 'success',
                'message' => 'Biodata & Riwayat Pendidikan berhasil disinkronkan ke Neo Feeder PDDikti.',
                'data' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal sinkronisasi ke Neo Feeder: ' . $e->getMessage()
            ], 500);
        }
    }
}
