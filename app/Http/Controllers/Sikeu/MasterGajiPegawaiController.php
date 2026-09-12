<?php

namespace App\Http\Controllers\Sikeu;

use App\Http\Controllers\Controller;
use App\Models\Sikeu\MasterGajiPegawai;
use App\Models\Simpeg\Pegawai;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class MasterGajiPegawaiController extends Controller
{
    /**
     * GET /api/v1/sikeu/master/gaji-pegawai
     * List all employees with their salary configuration, supporting search, filters & pagination
     */
    public function index(Request $request)
    {
        $perPage = min(100, $request->integer('per_page', 15));
        $search = $request->query('search');
        $jenisPegawai = $request->query('jenis_pegawai');

        $query = Pegawai::query();

        if ($request->filled('search')) {
            $s = $request->query('search');
            $query->where(function ($q) use ($s) {
                $q->where('nama_lengkap', 'like', "%{$s}%")
                  ->orWhere('nip', 'like', "%{$s}%")
                  ->orWhere('email', 'like', "%{$s}%");
            });
        }

        if ($request->filled('jenis_pegawai') && $request->jenis_pegawai !== 'all') {
            $query->where('jenis_pegawai', $request->jenis_pegawai);
        }

        $allowedSort = ['nama_lengkap', 'nip', 'jenis_pegawai', 'created_at'];
        $sortBy = in_array($request->query('sort_by'), $allowedSort) ? $request->query('sort_by') : 'nama_lengkap';
        $sortOrder = strtolower($request->query('sort_order', 'asc')) === 'desc' ? 'desc' : 'asc';
        $query->orderBy($sortBy, $sortOrder);

        $paginated = $query->paginate($perPage);

        $pegawaiIds = collect($paginated->items())->pluck('id');
        $masters = MasterGajiPegawai::whereIn('pegawai_id', $pegawaiIds)->get()->keyBy('pegawai_id');

        $data = collect($paginated->items())->map(function ($p) use ($masters) {
            $master = $masters->get($p->id);
            return [
                'pegawai_id' => $p->id,
                'nama_lengkap' => $p->nama_lengkap,
                'nip' => $p->nip ?? '-',
                'jenis_pegawai' => $p->jenis_pegawai,
                'gaji_pokok' => $master ? (float)$master->gaji_pokok : 4500000,
                'tunjangan_tetap' => $master ? (float)$master->tunjangan_tetap : 1200000,
                'potongan_tetap' => $master ? (float)$master->potongan_tetap : 150000,
                'tarif_transport_harian' => $master ? (float)$master->tarif_transport_harian : 50000,
                'catatan' => $master?->catatan,
                'updated_at' => $master?->updated_at?->toIso8601String(),
            ];
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Data master gaji pegawai berhasil dimuat',
            'data' => $data,
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
                'last_page' => $paginated->lastPage(),
                'from' => $paginated->firstItem(),
                'to' => $paginated->lastItem(),
            ],
            'filters' => [
                'search' => $search,
                'jenis_pegawai' => $jenisPegawai,
                'sort_by' => $sortBy,
                'sort_order' => $sortOrder,
            ],
        ]);
    }

    /**
     * POST /api/sikeu/master/gaji-pegawai
     * Save or update salary configuration for an employee
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'pegawai_id' => 'required|exists:simpeg_pegawai,id',
            'gaji_pokok' => 'required|numeric|min:0',
            'tunjangan_tetap' => 'nullable|numeric|min:0',
            'potongan_tetap' => 'nullable|numeric|min:0',
            'tarif_transport_harian' => 'nullable|numeric|min:0',
            'catatan' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validasi komponen gaji gagal.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $master = MasterGajiPegawai::updateOrCreate(
            ['pegawai_id' => $request->pegawai_id],
            [
                'gaji_pokok' => $request->gaji_pokok,
                'tunjangan_tetap' => $request->tunjangan_tetap ?? 0,
                'potongan_tetap' => $request->potongan_tetap ?? 0,
                'tarif_transport_harian' => $request->tarif_transport_harian ?? 50000,
                'catatan' => $request->catatan,
            ]
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Master komponen gaji pegawai berhasil disimpan.',
            'data' => $master,
        ]);
    }
}
