<?php

namespace App\Http\Controllers\Api\Siakad;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Siakad\Mahasiswa;
use App\Models\Siakad\KonversiTransfer;
use App\Models\Siakad\KonversiTransferDetail;
use Illuminate\Support\Facades\DB;

class MahasiswaController extends Controller
{
    public function index(Request $request)
    {
        $perPage = min(100, $request->integer('per_page', 15));
        $query = Mahasiswa::with(['programStudi', 'dosenWali', 'konversiTransfer']);

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
            'program_studi_id' => 'required|exists:master_program_studi,id',
            'nim' => 'required|string|unique:siakad_mahasiswa,nim',
            'nama_lengkap' => 'required|string|max:255',
            'nik' => 'nullable|string|max:20',
            'jenis_kelamin' => 'required|in:L,P',
            'angkatan' => 'required|integer',
            'dosen_wali_id' => 'nullable|exists:siakad_dosen,id',
            'status' => 'nullable|in:aktif,cuti,mangkir,dropout,lulus',
        ]);

        $mhs = Mahasiswa::create(array_merge($request->all(), [
            'status' => $request->input('status', 'aktif'),
            'tanggal_masuk' => now()->toDateString(),
        ]));

        return response()->json([
            'status' => 'success',
            'message' => 'Mahasiswa berhasil ditambahkan',
            'data' => $mhs
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $mhs = Mahasiswa::findOrFail($id);

        $request->validate([
            'nama_lengkap' => 'required|string|max:255',
            'nik' => 'nullable|string|max:20',
            'telepon' => 'nullable|string|max:30',
            'alamat' => 'nullable|string',
            'dosen_wali_id' => 'nullable|exists:siakad_dosen,id',
            'status' => 'nullable|in:aktif,cuti,mangkir,dropout,lulus',
        ]);

        $mhs->update($request->all());

        return response()->json([
            'status' => 'success',
            'message' => 'Data mahasiswa berhasil diperbarui',
            'data' => $mhs
        ]);
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
            'program_studi_id' => 'required|exists:master_program_studi,id',
            'angkatan' => 'required|integer',
            'nama_lengkap' => 'required|string',
            'jenis_kelamin' => 'required|in:L,P',
            'id' => 'nullable|exists:siakad_mahasiswa,id',
        ]);

        if ($request->filled('id')) {
            $existing = Mahasiswa::find($request->id);
            if ($existing && !empty($existing->nim)) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'Mahasiswa sudah memiliki NIM resmi.',
                    'data' => $existing
                ]);
            }
        }

        $konversiService = app(\App\Services\Spmb\SpmbKonversiService::class);
        $nim = $konversiService->generateNIM((int)$request->angkatan, (int)$request->program_studi_id);

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
            'message' => "NIM {$nim} berhasil di-generate untuk mahasiswa.",
            'data' => $mhs
        ], 201);
    }

    public function generateMissingNims(Request $request)
    {
        $unassigned = Mahasiswa::whereNull('nim')->orWhere('nim', '')->get();
        if ($unassigned->isEmpty()) {
            return response()->json([
                'status' => 'success',
                'message' => 'Semua data mahasiswa sudah memiliki NIM lengkap. Tidak ada perubahan yang dilakukan.',
                'data' => ['generated_count' => 0]
            ]);
        }

        $konversiService = app(\App\Services\Spmb\SpmbKonversiService::class);
        $count = 0;

        foreach ($unassigned as $mhs) {
            $angkatan = $mhs->angkatan ?: (int)date('Y');
            $prodiId = $mhs->program_studi_id ?: 1;
            $nim = $konversiService->generateNIM($angkatan, $prodiId);
            $mhs->update(['nim' => $nim]);
            $count++;
        }

        return response()->json([
            'status' => 'success',
            'message' => "Berhasil men-generate NIM resmi untuk {$count} mahasiswa yang sebelumnya belum memiliki NIM.",
            'data' => ['generated_count' => $count]
        ]);
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
            'details' => 'required|array|min:1',
            'details.*.mata_kuliah_diakui_id' => 'required|exists:siakad_mata_kuliah,id',
            'details.*.kode_mk_asal' => 'required|string|max:50',
            'details.*.nama_mk_asal' => 'required|string|max:255',
            'details.*.sks_asal' => 'required|integer|min:1',
            'details.*.nilai_huruf_asal' => 'required|string|max:5',
        ]);

        return DB::transaction(function () use ($request) {
            $noTransaksi = 'KNV-' . date('Y') . '-' . str_pad(KonversiTransfer::count() + 1, 3, '0', STR_PAD_LEFT);

            $konversi = KonversiTransfer::create([
                'mahasiswa_id' => $request->mahasiswa_id,
                'no_transaksi' => $noTransaksi,
                'kampus_asal' => $request->kampus_asal,
                'prodi_asal' => $request->prodi_asal,
                'diproses_oleh' => $request->user()?->id,
                'status' => 'disetujui',
                'catatan' => $request->catatan,
            ]);

            Mahasiswa::where('id', $request->mahasiswa_id)->update(['konversi_id' => $konversi->id]);

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
}
