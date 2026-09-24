<?php

namespace App\Http\Controllers\Simpeg;

use App\Http\Controllers\Controller;
use App\Models\Simpeg\JabatanFungsionalAkademik;
use Illuminate\Http\Request;

class JabatanFungsionalController extends Controller
{
    public function index(Request $request)
    {
        $query = JabatanFungsionalAkademik::query();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                  ->orWhere('golongan', 'like', "%{$search}%");
            });
        }

        if ($request->filled('golongan')) {
            $query->where('golongan', $request->golongan);
        }

        $sortBy = in_array($request->sort_by, ['nama', 'golongan', 'angka_kredit_min', 'created_at'], true) ? $request->sort_by : 'nama';
        $sortDir = ($request->sort_dir === 'desc' || $request->sort_order === 'desc') ? 'desc' : 'asc';
        $query->orderBy($sortBy, $sortDir);

        if ($request->has('page') || $request->has('limit') || $request->has('per_page')) {
            $perPage = min(100, $request->integer('per_page', $request->integer('limit', 15)));
            $paginated = $query->paginate($perPage);
            return response()->json([
                'status' => 'success',
                'data' => $paginated->items(),
                'meta' => [
                    'current_page' => $paginated->currentPage(),
                    'per_page' => $paginated->perPage(),
                    'total' => $paginated->total(),
                    'last_page' => $paginated->lastPage(),
                    'from' => $paginated->firstItem() ?? 0,
                    'to' => $paginated->lastItem() ?? 0,
                ],
            ]);
        }

        return response()->json([
            'status' => 'success',
            'data' => $query->get(),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'nama' => 'required|string|unique:simpeg_jabatan_fungsional_akademik,nama',
            'angka_kredit_min' => 'nullable|integer',
            'angka_kredit_max' => 'nullable|integer',
            'golongan' => 'required|in:asisten_ahli,lektor,lektor_kepala,guru_besar',
        ]);

        $jafung = JabatanFungsionalAkademik::create($request->all());

        return response()->json([
            'status' => 'success',
            'message' => 'Jabatan Fungsional berhasil dibuat.',
            'data' => $jafung
        ], 201);
    }

    /**
     * GET /api/v1/simpeg/jabatan-fungsional/master/golongan
     * Return distinct golongan values.
     */
    public function getGolongan()
    {
        $golongan = JabatanFungsionalAkademik::select('golongan')
            ->distinct()
            ->orderBy('golongan')
            ->pluck('golongan')
            ->map(fn($g) => [
                'value' => $g,
                'label' => ucwords(str_replace('_', ' ', $g)),
            ]);

        return response()->json([
            'status' => 'success',
            'data' => $golongan,
        ]);
    }
}
