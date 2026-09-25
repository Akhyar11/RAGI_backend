<?php

namespace App\Http\Controllers\API\Siakad;

use App\Http\Controllers\Controller;
use App\Models\Siakad\Dosen;
use App\Models\Siakad\Mahasiswa;
use App\Models\Siakad\PaCatatan;
use App\Models\Siakad\PaLaporan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
     * Rekap bimbingan: komposisi status mahasiswa bimbingan per dosen
     * (aktif, cuti, mangkir, dropout/keluar, lulus) + butuh penanganan khusus.
     * Kaprodi/admin: seluruh dosen (filter prodi); dosen: dirinya sendiri.
     */
    public function rekap(Request $request)
    {
        $request->validate([
            'dosen_id' => 'nullable|exists:siakad_dosen,id',
            'program_studi_id' => 'nullable|exists:siakad_program_studi,id',
        ]);

        $user = $request->user();
        $ownDosen = Dosen::where('user_id', $user?->id)->first();

        $dosenQuery = Dosen::with('programStudi');
        if (!$this->isPrivileged($user)) {
            $dosenQuery->where('id', $ownDosen?->id ?? -1);
        } elseif ($request->filled('dosen_id')) {
            $dosenQuery->where('id', $request->dosen_id);
        }
        if ($request->filled('program_studi_id')) {
            $dosenQuery->where('program_studi_id', $request->program_studi_id);
        }
        $dosens = $dosenQuery->orderBy('nama_lengkap')->get();

        $data = $dosens->map(function ($d) {
            $base = Mahasiswa::where('dosen_wali_id', $d->id);
            $counts = [
                'aktif' => (clone $base)->where('status', 'aktif')->count(),
                'cuti' => (clone $base)->where('status', 'cuti')->count(),
                'mangkir' => (clone $base)->where('status', 'mangkir')->count(),
                'keluar' => (clone $base)->whereIn('status', ['dropout'])->count(),
                'lulus' => (clone $base)->where('status', 'lulus')->count(),
            ];
            $counts['total'] = array_sum($counts);
            $khusus = PaCatatan::where('dosen_id', $d->id)
                ->where('butuh_penanganan_khusus', true)
                ->where('status_tindak_lanjut', '!=', 'selesai')
                ->count();
            $totalCatatan = PaCatatan::where('dosen_id', $d->id)->count();
            $terakhir = PaCatatan::where('dosen_id', $d->id)->latest('id')->first();
            $laporan = PaLaporan::where('dosen_id', $d->id)->latest('id')->first();

            return [
                'dosen_id' => $d->id,
                'nama_lengkap' => $d->nama_lengkap,
                'nidn' => $d->nidn,
                'program_studi' => $d->programStudi?->nama,
                'komposisi' => $counts,
                'butuh_khusus_aktif' => $khusus,
                'total_bimbingan' => $totalCatatan,
                'terakhir_bimbingan_at' => $terakhir?->tanggal_bimbingan ?? $terakhir?->created_at,
                'belum_bimbingan' => $totalCatatan === 0 && $counts['total'] > 0,
                'laporan_terakhir' => $laporan ? [
                    'tahun_akademik_id' => $laporan->tahun_akademik_id,
                    'status' => $laporan->status,
                    'updated_at' => $laporan->updated_at,
                ] : null,
            ];
        });

        return response()->json(['status' => 'success', 'data' => $data]);
    }

    /** Daftar mahasiswa bimbingan + flag masalah (tunggakan, KRS belum disetujui, IPK rendah). */
    public function advisees(Request $request)
    {
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
        $list = $query->orderBy('nama_lengkap')->get();

        $data = $list->map(function ($m) use ($dosen) {
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

        return response()->json(['status' => 'success', 'data' => $data]);
    }

    public function listCatatan(Request $request)
    {
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
            $query->where('dosen_id', $own?->id ?? -1);
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
        $validated = $request->validate([
            'mahasiswa_id' => 'required|exists:siakad_mahasiswa,id',
            'dosen_id' => 'nullable|exists:siakad_dosen,id',
            'kategori' => 'required|in:akademik,krs,khs,keuangan,pribadi,lainnya',
            'isi' => 'required|string',
            'kesimpulan' => 'nullable|string',
            'tanggal_bimbingan' => 'nullable|date',
            'butuh_penanganan_khusus' => 'nullable|boolean',
            'status_tindak_lanjut' => 'nullable|in:dipantau,diproses,selesai',
            'tahun_akademik_id' => 'nullable|exists:siakad_tahun_akademik,id',
        ]);

        $user = $request->user();
        $dosen = Dosen::where('user_id', $user?->id)->first();
        if (!$dosen && !$this->isPrivileged($user)) {
            return response()->json(['status' => 'error', 'message' => 'Akun Anda tidak terhubung ke data dosen.'], 403);
        }

        $mhs = Mahasiswa::findOrFail($validated['mahasiswa_id']);
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
            return response()->json(['status' => 'error', 'message' => 'Tentukan dosen PA untuk catatan ini.'], 422);
        }

        $catatan = PaCatatan::create(array_merge($validated, [
            'dosen_id' => $dosenId,
            'butuh_penanganan_khusus' => $request->boolean('butuh_penanganan_khusus', false),
            'status_tindak_lanjut' => $validated['status_tindak_lanjut'] ?? 'dipantau',
            'dibuat_oleh' => $user?->id,
        ]));

        return response()->json(['status' => 'success', 'message' => 'Catatan bimbingan tersimpan', 'data' => $catatan->load(['mahasiswa', 'dosen'])], 201);
    }

    public function updateCatatan(Request $request, $id)
    {
        $catatan = PaCatatan::findOrFail($id);
        $user = $request->user();
        $own = Dosen::where('user_id', $user?->id)->first();
        if (!$this->isPrivileged($user) && (int) $catatan->dosen_id !== (int) ($own?->id ?? -1)) {
            return response()->json(['status' => 'error', 'message' => 'Catatan ini milik PA lain.'], 403);
        }

        $validated = $request->validate([
            'kategori' => 'sometimes|in:akademik,krs,khs,keuangan,pribadi,lainnya',
            'isi' => 'sometimes|string',
            'kesimpulan' => 'nullable|string',
            'tanggal_bimbingan' => 'nullable|date',
            'butuh_penanganan_khusus' => 'nullable|boolean',
            'status_tindak_lanjut' => 'nullable|in:dipantau,diproses,selesai',
        ]);
        $catatan->update($validated);

        return response()->json(['status' => 'success', 'message' => 'Catatan diperbarui', 'data' => $catatan->fresh()->load(['mahasiswa'])]);
    }

    public function destroyCatatan($id, Request $request)
    {
        $catatan = PaCatatan::findOrFail($id);
        $user = $request->user();
        $own = Dosen::where('user_id', $user?->id)->first();
        if (!$this->isPrivileged($user) && (int) $catatan->dosen_id !== (int) ($own?->id ?? -1)) {
            return response()->json(['status' => 'error', 'message' => 'Catatan ini milik PA lain.'], 403);
        }
        $catatan->delete();

        return response()->json(['status' => 'success', 'message' => 'Catatan dihapus']);
    }

    public function getLaporan(Request $request)
    {
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
        $laporan = PaLaporan::findOrFail($id);
        $user = $request->user();
        $own = Dosen::where('user_id', $user?->id)->first();
        if (!$this->isPrivileged($user) && (int) $laporan->dosen_id !== (int) ($own?->id ?? -1)) {
            return response()->json(['status' => 'error', 'message' => 'Aktivitas bimbingan milik PA lain.'], 403);
        }
        $laporan->delete();

        return response()->json(['status' => 'success', 'message' => 'Aktivitas bimbingan berhasil dihapus']);
    }

    /**
     * Cetak Laporan PDF / Print Preview Laporan Aktivitas Bimbingan (Model aktifitas_bimbingan_pdf.php Indonusa)
     */
    public function cetakLaporanPdf(Request $request)
    {
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
}
