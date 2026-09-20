<?php

namespace App\Http\Controllers\Sikeu;

use App\Http\Controllers\Controller;
use App\Models\Sikeu\PotonganMahasiswa;
use App\Models\Sikeu\PotonganTagihan;
use App\Models\Sikeu\TagihanMahasiswa;
use App\Models\Siakad\Mahasiswa;
use App\Models\Spmb\PendaftaranCalonMhs;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PembayaranMahasiswaPotonganController extends Controller
{
    /**
     * GET /api/v1/sikeu/pembayaran-mahasiswa/potongan
     * List potongan mahasiswa dengan pagination, search, filter status, dan summary.
     */
    public function index(Request $request)
    {
        $perPage = min(100, $request->integer('per_page', 15));
        $query = PotonganMahasiswa::with([
            'mahasiswa.programStudi',
            'calonMahasiswa.programStudi',
            'potonganTagihan.tagihan',
            'inputter',
        ]);

        // Filter Pencarian (Nama Potongan, Nama Mahasiswa, NIM, No SK)
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nama_potongan', 'like', "%{$search}%")
                  ->orWhere('nama_mahasiswa', 'like', "%{$search}%")
                  ->orWhere('nim', 'like', "%{$search}%")
                  ->orWhere('nomor_sk', 'like', "%{$search}%")
                  ->orWhere('keterangan', 'like', "%{$search}%");
            });
        }

        // Filter Status
        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        // Filter Tipe Referensi (SIAKAD vs SPMB)
        if ($request->filled('tipe_referensi') && $request->tipe_referensi !== 'all') {
            $query->where('tipe_referensi', $request->tipe_referensi);
        }

        // Sorting
        $allowedSort = ['id', 'created_at', 'nilai_potongan', 'nama_mahasiswa', 'nama_potongan'];
        $sortBy = in_array($request->sort_by, $allowedSort) ? $request->sort_by : 'id';
        $sortOrder = strtolower($request->sort_order) === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortBy, $sortOrder);

        $paginated = $query->paginate($perPage);

        $items = collect($paginated->items())->map(function ($item) {
            $isCalon = $item->tipe_referensi === 'calon_mahasiswa' || (bool)$item->calon_mahasiswa_id;
            $mhs = $isCalon ? $item->calonMahasiswa : $item->mahasiswa;
            $prodi = $mhs?->programStudi?->nama ?? '-';

            // Hitung total potongan real yang tersinkron ke tagihan
            $totalApplied = $item->potonganTagihan->sum('nominal_potongan');

            return [
                'id' => $item->id,
                'mahasiswa_id' => $item->mahasiswa_id,
                'calon_mahasiswa_id' => $item->calon_mahasiswa_id,
                'is_calon_mahasiswa' => $isCalon,
                'tipe_referensi' => $item->tipe_referensi ?? ($isCalon ? 'calon_mahasiswa' : 'mahasiswa'),
                'nim' => $item->nim ?? '-',
                'nama_mahasiswa' => $item->nama_mahasiswa ?? ($mhs?->nama_lengkap ?? 'Mahasiswa #' . $item->id),
                'prodi' => $prodi,
                'nama_potongan' => $item->nama_potongan,
                'tipe_potongan' => $item->tipe_potongan,
                'nilai_potongan' => (float)$item->nilai_potongan,
                'total_terpotong_tagihan' => (float)($totalApplied > 0 ? $totalApplied : $item->nilai_potongan),
                'jumlah_tagihan_dipotong' => $item->potonganTagihan->count(),
                'nomor_sk' => $item->nomor_sk,
                'keterangan' => $item->keterangan,
                'status' => $item->status,
                'diinput_oleh_nama' => $item->inputter?->name ?? 'Admin Keuangan',
                'created_at' => $item->created_at ? $item->created_at->format('Y-m-d H:i') : null,
                'tagihan_list' => $item->potonganTagihan->map(function ($pt) {
                    return [
                        'tagihan_id' => $pt->tagihan_id,
                        'nomor_tagihan' => $pt->tagihan?->nomor_tagihan ?? '-',
                        'nominal_potongan' => (float)$pt->nominal_potongan,
                        'status_tagihan' => $pt->tagihan?->status ?? '-',
                    ];
                }),
            ];
        });

        // Summary KPI counts
        $totalPotongan = PotonganMahasiswa::count();
        $totalAktif = PotonganMahasiswa::where('status', 'aktif')->count();
        $totalNominal = (float)PotonganTagihan::sum('nominal_potongan');
        $totalMhsUnik = PotonganMahasiswa::distinct('mahasiswa_id')->count('mahasiswa_id')
            + PotonganMahasiswa::whereNull('mahasiswa_id')->distinct('calon_mahasiswa_id')->count('calon_mahasiswa_id');

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar potongan mahasiswa berhasil dimuat',
            'data' => $items,
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
                'last_page' => $paginated->lastPage(),
                'from' => $paginated->firstItem(),
                'to' => $paginated->lastItem(),
            ],
            'summary' => [
                'total_potongan' => $totalPotongan,
                'total_aktif' => $totalAktif,
                'total_nominal_terpotong' => $totalNominal,
                'total_mahasiswa' => $totalMhsUnik,
            ],
            'filters' => [
                'search' => $request->search,
                'status' => $request->status,
                'tipe_referensi' => $request->tipe_referensi,
                'sort_by' => $sortBy,
                'sort_order' => $sortOrder,
            ],
        ]);
    }

    /**
     * GET /api/v1/sikeu/pembayaran-mahasiswa/potongan/{id}
     * Menampilkan detail potongan beserta rincian tagihan yang dipotong.
     */
    public function show($id)
    {
        $item = PotonganMahasiswa::with([
            'mahasiswa.programStudi',
            'calonMahasiswa.programStudi',
            'potonganTagihan.tagihan.details.masterBiaya',
            'inputter',
        ])->findOrFail($id);

        $isCalon = $item->tipe_referensi === 'calon_mahasiswa' || (bool)$item->calon_mahasiswa_id;
        $mhs = $isCalon ? $item->calonMahasiswa : $item->mahasiswa;

        return response()->json([
            'status' => 'success',
            'message' => 'Detail potongan mahasiswa berhasil dimuat',
            'data' => [
                'id' => $item->id,
                'mahasiswa_id' => $item->mahasiswa_id,
                'calon_mahasiswa_id' => $item->calon_mahasiswa_id,
                'is_calon_mahasiswa' => $isCalon,
                'tipe_referensi' => $item->tipe_referensi ?? ($isCalon ? 'calon_mahasiswa' : 'mahasiswa'),
                'nim' => $item->nim ?? '-',
                'nama_mahasiswa' => $item->nama_mahasiswa ?? ($mhs?->nama_lengkap ?? '-'),
                'prodi' => $mhs?->programStudi?->nama ?? '-',
                'nama_potongan' => $item->nama_potongan,
                'tipe_potongan' => $item->tipe_potongan,
                'nilai_potongan' => (float)$item->nilai_potongan,
                'nomor_sk' => $item->nomor_sk,
                'keterangan' => $item->keterangan,
                'status' => $item->status,
                'diinput_oleh_nama' => $item->inputter?->name ?? 'Admin Keuangan',
                'created_at' => $item->created_at ? $item->created_at->format('Y-m-d H:i:s') : null,
                'tagihan_list' => $item->potonganTagihan->map(function ($pt) {
                    return [
                        'id' => $pt->id,
                        'tagihan_id' => $pt->tagihan_id,
                        'nomor_tagihan' => $pt->tagihan?->nomor_tagihan ?? '-',
                        'nominal_potongan' => (float)$pt->nominal_potongan,
                        'total_tagihan' => (float)($pt->tagihan?->total_tagihan ?? 0),
                        'total_bayar' => (float)($pt->tagihan?->total_bayar ?? 0),
                        'status_tagihan' => $pt->tagihan?->status ?? '-',
                        'created_at' => $pt->created_at ? $pt->created_at->format('Y-m-d H:i') : null,
                    ];
                }),
            ],
        ]);
    }

    /**
     * POST /api/v1/sikeu/pembayaran-mahasiswa/potongan
     * Menambahkan potongan baru dan mengaplikasikannya langsung ke tagihan terpilih.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'mahasiswa_id' => 'nullable|integer|required_without:calon_mahasiswa_id',
            'calon_mahasiswa_id' => 'nullable|integer|required_without:mahasiswa_id',
            'is_calon_mahasiswa' => 'nullable|boolean',
            'tipe_referensi' => 'nullable|string|in:mahasiswa,calon_mahasiswa',
            'nama_potongan' => 'required|string|max:150',
            'nomor_sk' => 'nullable|string|max:100',
            'keterangan' => 'nullable|string|max:500',
            'status' => 'nullable|in:aktif,nonaktif',
            'target_bills' => 'required|array|min:1',
            'target_bills.*.tagihan_id' => 'required|integer|exists:sikeu_tagihan_mahasiswa,id',
            'target_bills.*.nominal_potongan' => 'required|numeric|min:1',
            'target_bills.*.mode_potongan' => 'nullable|string|in:seluruhnya,nominal',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validasi data potongan mahasiswa gagal.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $isCalon = $request->boolean('is_calon_mahasiswa') || $request->input('tipe_referensi') === 'calon_mahasiswa' || $request->filled('calon_mahasiswa_id');
        $mhsId = $request->input('mahasiswa_id');
        $calonId = $request->input('calon_mahasiswa_id');

        if ($isCalon && !$calonId) {
            $calonId = $mhsId;
            $mhsId = null;
        }

        // Ambil identitas mahasiswa
        $nim = '-';
        $namaMahasiswa = 'Mahasiswa';
        if ($isCalon && $calonId) {
            $calon = PendaftaranCalonMhs::find($calonId);
            if (!$calon) {
                return response()->json(['status' => 'error', 'message' => 'Calon mahasiswa SPMB tidak ditemukan.'], 404);
            }
            $nim = $calon->nim ?: ($calon->no_pendaftaran ?: ('SPMB-' . $calonId));
            $namaMahasiswa = $calon->nama_lengkap ?? ('Calon Mahasiswa #' . $calonId);
        } elseif ($mhsId) {
            $mhs = Mahasiswa::find($mhsId);
            if (!$mhs) {
                return response()->json(['status' => 'error', 'message' => 'Mahasiswa SIAKAD tidak ditemukan.'], 404);
            }
            $nim = $mhs->nim ?: ('MHS-' . $mhsId);
            $namaMahasiswa = $mhs->nama_lengkap ?? ('Mahasiswa #' . $mhsId);
        }

        $targetBillsInput = $request->input('target_bills', []);

        // Validasi kepemilikan tagihan dan batasan sisa tagihan
        $validatedBills = [];
        $totalNominalSemuaPotongan = 0;

        foreach ($targetBillsInput as $billInput) {
            $tagihan = TagihanMahasiswa::with(['virtualAccount'])->find($billInput['tagihan_id']);
            if (!$tagihan) {
                return response()->json([
                    'status' => 'error',
                    'message' => "Tagihan dengan ID {$billInput['tagihan_id']} tidak ditemukan.",
                ], 404);
            }

            // Pastikan tagihan adalah milik mahasiswa yang dipilih
            if ($isCalon) {
                if ($tagihan->calon_mahasiswa_id != $calonId && $tagihan->mahasiswa_id != $calonId) {
                    return response()->json([
                        'status' => 'error',
                        'message' => "Tagihan #{$tagihan->nomor_tagihan} bukan milik calon mahasiswa yang dipilih.",
                    ], 422);
                }
            } else {
                if ($tagihan->mahasiswa_id != $mhsId) {
                    return response()->json([
                        'status' => 'error',
                        'message' => "Tagihan #{$tagihan->nomor_tagihan} bukan milik mahasiswa yang dipilih.",
                    ], 422);
                }
            }

            $totalBersih = (float)($tagihan->total_tagihan + $tagihan->total_denda - $tagihan->total_potongan);
            $sisaTagihan = max(0, $totalBersih - (float)$tagihan->total_bayar);

            if ($sisaTagihan <= 0) {
                return response()->json([
                    'status' => 'error',
                    'message' => "Tagihan #{$tagihan->nomor_tagihan} sudah lunas dan tidak dapat diberikan potongan lagi.",
                ], 422);
            }

            $mode = $billInput['mode_potongan'] ?? 'nominal';
            $nominalPotongan = ($mode === 'seluruhnya') ? $sisaTagihan : (float)$billInput['nominal_potongan'];

            if ($nominalPotongan > $sisaTagihan) {
                return response()->json([
                    'status' => 'error',
                    'message' => "Nominal potongan (Rp " . number_format($nominalPotongan, 0, ',', '.') . ") pada tagihan #{$tagihan->nomor_tagihan} melebihi sisa tagihan (Rp " . number_format($sisaTagihan, 0, ',', '.') . ").",
                ], 422);
            }

            $validatedBills[] = [
                'tagihan' => $tagihan,
                'nominal_potongan' => $nominalPotongan,
                'sisa_awal' => $sisaTagihan,
            ];

            $totalNominalSemuaPotongan += $nominalPotongan;
        }

        try {
            DB::beginTransaction();

            // 1. Simpan Master Record Potongan Mahasiswa
            $potongan = PotonganMahasiswa::create([
                'mahasiswa_id' => $isCalon ? null : $mhsId,
                'calon_mahasiswa_id' => $isCalon ? $calonId : null,
                'tipe_referensi' => $isCalon ? 'calon_mahasiswa' : 'mahasiswa',
                'nim' => $nim,
                'nama_mahasiswa' => $namaMahasiswa,
                'nama_potongan' => $request->nama_potongan,
                'tipe_potongan' => 'nominal',
                'nilai_potongan' => $totalNominalSemuaPotongan,
                'nomor_sk' => $request->nomor_sk,
                'keterangan' => $request->keterangan,
                'status' => $request->input('status', 'aktif'),
                'diinput_oleh' => auth()->id(),
            ]);

            // 2. Terapkan Potongan pada Masing-Masing Tagihan Terpilih
            $impactedBills = [];

            foreach ($validatedBills as $vb) {
                $tagihan = $vb['tagihan'];
                $nomPotongan = $vb['nominal_potongan'];

                // Buat item potongan tagihan
                $pt = PotonganTagihan::create([
                    'tagihan_id' => $tagihan->id,
                    'potongan_mahasiswa_id' => $potongan->id,
                    'tipe' => 'diskon',
                    'nominal_potongan' => $nomPotongan,
                    'keterangan' => 'Potongan: ' . $potongan->nama_potongan . ($potongan->nomor_sk ? " (SK: {$potongan->nomor_sk})" : ''),
                    'diinput_oleh' => auth()->id(),
                ]);

                // Update total potongan pada tagihan
                $newTotalPotongan = (float)PotonganTagihan::where('tagihan_id', $tagihan->id)->sum('nominal_potongan');
                $tagihan->total_potongan = $newTotalPotongan;

                $totalBersihBaru = (float)($tagihan->total_tagihan + $tagihan->total_denda - $newTotalPotongan);
                $newSisa = max(0, $totalBersihBaru - (float)$tagihan->total_bayar);

                // Update status tagihan
                if ($newSisa <= 0) {
                    $tagihan->status = 'lunas';
                } elseif ((float)$tagihan->total_bayar > 0) {
                    $tagihan->status = 'sebagian';
                }
                $tagihan->save();

                // Update nominal Virtual Account aktif
                foreach ($tagihan->virtualAccounts as $va) {
                    $va->nominal = $newSisa;
                    if ($newSisa <= 0) {
                        $va->status = 'dibayar';
                    }
                    $va->save();
                }

                $impactedBills[] = [
                    'tagihan_id' => $tagihan->id,
                    'nomor_tagihan' => $tagihan->nomor_tagihan,
                    'nominal_potongan' => $nomPotongan,
                    'sisa_akhir' => $newSisa,
                    'status_akhir' => $tagihan->status,
                ];
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => "Potongan \"{$potongan->nama_potongan}\" berhasil disimpan dan diterapkan pada " . count($impactedBills) . " tagihan.",
                'data' => [
                    'potongan' => $potongan->load(['potonganTagihan']),
                    'total_nominal_potongan' => $totalNominalSemuaPotongan,
                    'impacted_bills' => $impactedBills,
                ],
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal memproses potongan mahasiswa: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * DELETE /api/v1/sikeu/pembayaran-mahasiswa/potongan/{id}
     * Menghapus potongan mahasiswa dan mereverse nilai tagihan & Virtual Account.
     */
    public function destroy($id)
    {
        $item = PotonganMahasiswa::with(['potonganTagihan.tagihan.virtualAccount'])->findOrFail($id);

        try {
            DB::beginTransaction();

            $potonganTagihans = $item->potonganTagihan;

            foreach ($potonganTagihans as $pt) {
                $tagihan = $pt->tagihan;
                $pt->delete();

                if ($tagihan) {
                    $newTotalPotongan = (float)PotonganTagihan::where('tagihan_id', $tagihan->id)->sum('nominal_potongan');
                    $tagihan->total_potongan = $newTotalPotongan;

                    $totalBersih = (float)($tagihan->total_tagihan + $tagihan->total_denda - $newTotalPotongan);
                    $newSisa = max(0, $totalBersih - (float)$tagihan->total_bayar);

                    // Revert status tagihan
                    if ($newSisa <= 0) {
                        $tagihan->status = 'lunas';
                    } elseif ((float)$tagihan->total_bayar > 0) {
                        $tagihan->status = 'sebagian';
                    } else {
                        $tagihan->status = 'belum_bayar';
                    }
                    $tagihan->save();

                    // Revert active Virtual Accounts
                    foreach ($tagihan->virtualAccounts as $va) {
                        $va->nominal = $newSisa;
                        if ($newSisa > 0 && $va->status === 'dibayar') {
                            $va->status = 'aktif';
                        }
                        $va->save();
                    }
                }
            }

            $item->delete();

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Potongan mahasiswa berhasil dibatalkan dan saldo tagihan telah disesuaikan kembali.',
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal menghapus potongan mahasiswa: ' . $e->getMessage(),
            ], 500);
        }
    }
}
