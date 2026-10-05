<?php

namespace App\Services\Siakad;

use App\Models\Lms\ForumPost;
use App\Models\Lms\ForumTopik;
use App\Models\Siakad\Dosen;
use App\Models\Siakad\Kelas;
use App\Models\Siakad\KrsDetail;
use App\Models\Siakad\Mahasiswa;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ForumService
{
    /**
     * Whitelist kolom yang boleh dipakai sebagai `sort_by` pada daftar topik.
     *
     * Nilai di luar daftar ini diabaikan dan jatuh ke default, bukan error,
     * supaya nama kolom tidak pernah masuk ke query secara bebas.
     *
     * @var array<int, string>
     */
    public const SORT_TOPIK = ['id', 'judul', 'is_pinned', 'created_at'];

    /**
     * Whitelist kolom yang boleh dipakai sebagai `sort_by` pada daftar pesan.
     *
     * @var array<int, string>
     */
    public const SORT_POST = ['id', 'created_at'];

    /**
     * Normalisasi parameter sorting terhadap whitelist kolom.
     *
     * Dipakai controller juga, supaya nilai yang echoed pada `meta.filters`
     * persis sama dengan yang benar-benar dipakai query builder.
     *
     * @param  array<int, string>  $allowed
     * @return array{0: string, 1: string}
     */
    public function normalisasiSort(string $sortBy, string $sortOrder, array $allowed): array
    {
        $field = in_array($sortBy, $allowed, true) ? $sortBy : $allowed[0];
        $direction = strtolower($sortOrder) === 'asc' ? 'asc' : 'desc';

        return [$field, $direction];
    }

    protected function assertAnggotaKelas(int $kelasId, int $userId): void
    {
        $user = User::findOrFail($userId);

        // Pengelola (dosen/admin via gate di controller) lolos bila mengampu atau istimewa.
        $mahasiswaId = Mahasiswa::where('user_id', $userId)->value('id');
        if ($mahasiswaId) {
            $ikut = KrsDetail::where('kelas_id', $kelasId)
                ->whereHas('krs', fn ($q) => $q->where('mahasiswa_id', $mahasiswaId))
                ->exists();
            if (!$ikut) {
                throw ValidationException::withMessages([
                    'kelas' => ['Anda tidak terdaftar pada kelas perkuliahan ini.'],
                ]);
            }

            return;
        }

        $dosenId = Dosen::where('user_id', $userId)->value('id');
        $mengampu = $dosenId
            ? \App\Models\Siakad\DosenPengampu::where('kelas_id', $kelasId)->where('dosen_id', $dosenId)->exists()
            : false;

        if (!$mengampu && !$user->isSuperAdmin() && !$user->hasPermission('siakad.kelas.manage')) {
            throw ValidationException::withMessages([
                'kelas' => ['Anda tidak terdaftar pada kelas perkuliahan ini.'],
            ]);
        }
    }

    protected function namaPenulis(int $userId): string
    {
        $nama = Mahasiswa::where('user_id', $userId)->value('nama_lengkap')
            ?? Dosen::where('user_id', $userId)->value('nama_lengkap')
            ?? User::find($userId)?->name;

        return $nama ?: 'Pengguna';
    }

    /**
     * Daftar topik satu kelas; buat otomatis "Diskusi Umum" bila masih kosong.
     *
     * Paginasi dijalankan di level query builder (satu COUNT + satu SELECT per
     * halaman), bukan dengan mengambil seluruh baris lalu di-slice.
     */
    public function listTopik(
        int $kelasId,
        int $userId,
        int $perPage = 15,
        string $sortBy = 'id',
        string $sortOrder = 'asc'
    ): LengthAwarePaginator {
        $kelas = Kelas::with('mataKuliah')->findOrFail($kelasId);
        $this->assertAnggotaKelas($kelasId, $userId);
        $perPage = min(100, max(1, $perPage));
        [$sortField, $direction] = $this->normalisasiSort($sortBy, $sortOrder, self::SORT_TOPIK);

        $this->pastikanTopikDefault($kelas, $userId);

        // `is_pinned` selalu jadi kunci pertama supaya topik terkunci tidak
        // tenggelam; `id` jadi kunci terakhir sebagai penentu stabil (tie-break).
        $paginator = ForumTopik::where('kelas_id', $kelas->id)
            ->withCount('posts')
            ->orderByDesc('is_pinned')
            ->orderBy($sortField, $direction)
            ->orderBy('id')
            ->paginate($perPage);

        // Ringkas per topik untuk preview daftar. post_terakhir diambil dengan
        // satu query untuk seluruh halaman, bukan satu query per topik.
        $terakhir = ForumPost::whereIn('topik_id', collect($paginator->items())->pluck('id'))
            ->orderBy('id')
            ->get()
            ->groupBy('topik_id')
            ->map(fn ($posts) => $posts->last());

        $paginator->setCollection(
            collect($paginator->items())->map(function (ForumTopik $t) use ($terakhir) {
                $last = $terakhir->get($t->id);

                return [
                    'id' => $t->id,
                    'judul' => $t->judul,
                    'pertemuan_id' => $t->pertemuan_id,
                    'is_pinned' => $t->is_pinned,
                    'total_post' => $t->posts_count ?? 0,
                    'post_terakhir' => $last ? [
                        'nama_penulis' => $last->nama_penulis,
                        'isi' => mb_substr($last->isi, 0, 120),
                        'created_at' => $last->created_at?->toIso8601String(),
                    ] : null,
                ];
            })
        );

        return $paginator;
    }

    /**
     * Pastikan kelas punya minimal satu topik; buat "Diskusi Umum" bila belum ada.
     *
     * Dipisah dari listTopik() agar paginasi tetap berjalan di level query builder
     * (satu COUNT + satu SELECT per halaman, bukan satu COUNT + SELECT semua baris).
     */
    protected function pastikanTopikDefault(Kelas $kelas, int $userId): void
    {
        $ada = ForumTopik::where('kelas_id', $kelas->id)->exists();

        if ($ada) {
            return;
        }

        $topik = ForumTopik::create([
            'kelas_id' => $kelas->id,
            'judul' => 'Diskusi Umum Kelas ' . ($kelas->mataKuliah?->nama ?? $kelas->nama_kelas),
            'dibuat_oleh' => $userId,
        ]);

        try {
            AuditLogService::record(
                module: 'LMS',
                action: 'create',
                tableName: 'lms_forum_topik',
                recordId: $topik->id,
                oldValues: null,
                newValues: $topik->getAttributes()
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Gagal mencatat audit log buat topik forum default: ' . $e->getMessage());
        }
    }

    /**
     * Daftar topik forum dari SELURUH kelas yang bisa diakses user (agregat module-level).
     *
     * Berbeda dengan listTopik() per kelas, method ini:
     * - TIDAK membuat topik "Diskusi Umum" otomatis bila kelas belum punya topik.
     *   Endpoint agregat bersifat read-only, jadi GET tidak boleh menulis data.
     * - Pagination dilakukan di level database.
     *
     * @param  array<int>|null  $kelasIds  null = semua kelas (privileged)
     */
    public function listTopikAggregate(
        ?array $kelasIds,
        int $perPage = 15,
        ?string $search = null,
        string $sortBy = 'id',
        string $sortOrder = 'desc',
        ?int $tahunAkademikId = null,
        ?int $kelasId = null,
        ?bool $isPinned = null
    ): LengthAwarePaginator {
        $query = ForumTopik::with(['kelas.mataKuliah', 'kelas.tahunAkademik'])
            ->withCount('posts');

        if ($kelasIds !== null) {
            $query->whereIn('kelas_id', $kelasIds ?: [0]);
        }

        // Filter kelas tunggal tetap harus intersecting dengan $kelasIds, sehingga
        // user tidak bisa menebak kelas di luar haknya hanya lewat kelas_id.
        if ($kelasId) {
            $query->whereIn('kelas_id', $kelasIds === null ? [$kelasId] : array_values(array_intersect($kelasIds, [$kelasId])));
        }

        if ($tahunAkademikId) {
            $query->whereHas('kelas', function ($q) use ($tahunAkademikId) {
                $q->where('tahun_akademik_id', $tahunAkademikId);
            });
        }

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('judul', 'like', "%{$search}%")
                  ->orWhereHas('kelas', function ($kelas) use ($search) {
                      $kelas->where('nama_kelas', 'like', "%{$search}%")
                            ->orWhere('kode_kelas', 'like', "%{$search}%")
                            ->orWhereHas('mataKuliah', function ($mk) use ($search) {
                                $mk->where('nama', 'like', "%{$search}%")
                                   ->orWhere('kode_mk', 'like', "%{$search}%");
                            });
                  });
            });
        }

        // Filter sematan: null berarti "semua", bukan "tidak bersemat".
        if ($isPinned !== null) {
            $query->where('is_pinned', $isPinned);
        }

        [$sortField, $direction] = $this->normalisasiSort($sortBy, $sortOrder, self::SORT_TOPIK);

        return $query->orderByDesc('is_pinned')
            ->orderBy($sortField, $direction)
            ->orderBy('id')
            ->paginate($perPage);
    }

    public function createTopik(int $kelasId, array $data, int $userId): ForumTopik
    {
        Kelas::findOrFail($kelasId);
        $this->assertAnggotaKelas($kelasId, $userId);

        return ForumTopik::create([
            'kelas_id' => $kelasId,
            'pertemuan_id' => $data['pertemuan_id'] ?? null,
            'judul' => $data['judul'],
            'dibuat_oleh' => $userId,
            'is_pinned' => $data['is_pinned'] ?? false,
        ]);
    }

    public function deleteTopik(int $topikId): bool
    {
        return (bool) ForumTopik::findOrFail($topikId)->delete();
    }

    public function listPost(
        int $topikId,
        int $userId,
        int $perPage = 15,
        string $sortBy = 'id',
        string $sortOrder = 'asc'
    ): LengthAwarePaginator {
        $topik = ForumTopik::findOrFail($topikId);
        $this->assertAnggotaKelas($topik->kelas_id, $userId);

        $perPage = min(100, max(1, $perPage));
        [$sortField, $direction] = $this->normalisasiSort($sortBy, $sortOrder, self::SORT_POST);

        return ForumPost::where('topik_id', $topikId)
            ->whereNull('parent_id')
            ->with('balasan')
            ->orderBy($sortField, $direction)
            ->orderBy('id')
            ->paginate($perPage);
    }

    public function createPost(int $topikId, array $data, int $userId): ForumPost
    {
        $topik = ForumTopik::findOrFail($topikId);
        $this->assertAnggotaKelas($topik->kelas_id, $userId);

        if (!empty($data['parent_id'])) {
            $parent = ForumPost::findOrFail($data['parent_id']);
            if ((int) $parent->topik_id !== (int) $topikId || $parent->parent_id !== null) {
                throw ValidationException::withMessages([
                    'parent_id' => ['Balasan hanya satu level dan harus dalam topik yang sama.'],
                ]);
            }
        }

        return ForumPost::create([
            'topik_id' => $topikId,
            'parent_id' => $data['parent_id'] ?? null,
            'user_id' => $userId,
            'nama_penulis' => $this->namaPenulis($userId),
            'isi' => $data['isi'],
        ]);
    }

    /**
     * Hapus satu pesan beserta balasannya.
     *
     * Otorisasi pemilik vs pengelola kelas sudah ditangani ForumPostPolicy di
     * controller, jadi service ini murni operasi persist.
     */
    public function deletePost(int $postId): bool
    {
        return (bool) ForumPost::findOrFail($postId)->delete();
    }
}
