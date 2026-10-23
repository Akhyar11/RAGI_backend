<?php

namespace App\Services\Siakad;

use App\Models\Lms\LmsNotifikasi;
use App\Models\Lms\PengumpulanTugas;
use App\Models\Lms\Quiz;
use App\Models\Lms\Tugas;
use App\Models\Siakad\Mahasiswa;
use App\Models\User;
use App\Services\Notification\CampusNotificationService;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Service notifikasi in-app modul LMS (Fase 3A).
 *
 * Tanggung jawab:
 *  - menyimpan & membaca notifikasi milik user (termasuk status read/unread),
 *  - menghasilkan pengingat deadline tugas & kuis untuk mahasiswa yang
 *    terdaftar pada kelas terkait.
 *
 * Notifikasi bersifat personal (disimpan per `user_id`) sehingga tidak
 * membutuhkan permission khusus: setiap user hanya dapat membaca dan mengubah
 * status notifikasi miliknya sendiri.
 */
class LmsNotificationService
{
    /** Jumlah data per halaman (default). */
    public const PER_PAGE = 15;

    /** Batas maksimum data per halaman. */
    public const PER_PAGE_MAX = 100;

    /** Sort default. */
    public const SORT_DEFAULT = 'created_at';

    /**
     * @var array<int, string>
     */
    public const SORTABLE = ['created_at', 'read_at', 'prioritas', 'jenis'];

    /**
     * Daftar notifikasi milik user dengan filter & pagination.
     *
     * @param  array<string, mixed>  $filters
     */
    public function index(User $user, array $filters = []): LengthAwarePaginator
    {
        $perPage = (int) ($filters['per_page'] ?? self::PER_PAGE);
        $perPage = max(1, min(self::PER_PAGE_MAX, $perPage));

        $query = LmsNotifikasi::query()
            ->where('user_id', $user->id)
            ->with('kelas:id,kode_kelas,nama_kelas');

        if (!empty($filters['jenis']) && in_array($filters['jenis'], LmsNotifikasi::JENIS, true)) {
            $query->where('jenis', $filters['jenis']);
        }

        if (isset($filters['is_read']) && $filters['is_read'] !== null && $filters['is_read'] !== '') {
            $query->where('is_read', filter_var($filters['is_read'], FILTER_VALIDATE_BOOLEAN));
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('judul', 'like', "%{$search}%")
                    ->orWhere('pesan', 'like', "%{$search}%");
            });
        }

        $sortBy = $filters['sort_by'] ?? self::SORT_DEFAULT;
        if (!in_array($sortBy, self::SORTABLE, true)) {
            $sortBy = self::SORT_DEFAULT;
        }
        $sortOrder = strtolower((string) ($filters['sort_order'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';

        return $query->orderBy($sortBy, $sortOrder)->paginate($perPage);
    }

    /**
     * Jumlah notifikasi yang belum dibaca.
     */
    public function unreadCount(User $user): int
    {
        return LmsNotifikasi::query()
            ->where('user_id', $user->id)
            ->where('is_read', false)
            ->count();
    }

    /**
     * Tandai satu notifikasi milik user sebagai sudah dibaca.
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException bila notifikasi
     *         tidak ada atau bukan milik user tersebut.
     */
    public function markAsRead(User $user, int $notifikasiId): LmsNotifikasi
    {
        $notifikasi = LmsNotifikasi::query()
            ->where('user_id', $user->id)
            ->findOrFail($notifikasiId);

        if (!$notifikasi->is_read) {
            $notifikasi->forceFill([
                'is_read' => true,
                'read_at' => now(),
            ])->save();
        }

        return $notifikasi;
    }

    /**
     * Tandai seluruh notifikasi milik user sebagai sudah dibaca.
     *
     * @return int jumlah notifikasi yang baru ditandai terbaca
     */
    public function markAllAsRead(User $user): int
    {
        return LmsNotifikasi::query()
            ->where('user_id', $user->id)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now(),
                'updated_at' => now(),
            ]);
    }

    /**
     * Simpan satu notifikasi untuk user.
     *
     * Bila `dedup_key` diisi, notifikasi dengan key yang sama tidak akan
     * dibuat dua kali (berguna untuk pengingat deadline yang dijalankan
     * berkala).
     *
     * @param  array<string, mixed>  $options
     */
    public function push(
        int $userId,
        string $jenis,
        string $judul,
        ?string $pesan = null,
        array $options = []
    ): ?LmsNotifikasi {
        $attributes = [
            'user_id' => $userId,
            'jenis' => $jenis,
            'judul' => $judul,
            'pesan' => $pesan,
            'rute' => $options['rute'] ?? null,
            'kelas_id' => $options['kelas_id'] ?? null,
            'entitas_type' => $options['entitas_type'] ?? null,
            'entitas_id' => $options['entitas_id'] ?? null,
            'prioritas' => $options['prioritas'] ?? LmsNotifikasi::PRIORITAS_NORMAL,
            'is_read' => false,
            'read_at' => null,
            'dedup_key' => $options['dedup_key'] ?? null,
        ];

        if (!empty($attributes['dedup_key'])) {
            $existing = LmsNotifikasi::query()
                ->where('user_id', $userId)
                ->where('dedup_key', $attributes['dedup_key'])
                ->first();

            if ($existing) {
                return $existing;
            }
        }

        return LmsNotifikasi::query()->create($attributes);
    }

    /**
     * Simpan satu notifikasi untuk banyak user sekaligus.
     *
     * @param  iterable<int>  $userIds
     * @param  array<string, mixed>  $options
     * @return int jumlah notifikasi yang benar-benar dibuat
     */
    public function pushMany(
        iterable $userIds,
        string $jenis,
        string $judul,
        ?string $pesan = null,
        array $options = []
    ): int {
        $created = 0;

        foreach (array_unique(array_map('intval', is_array($userIds) ? $userIds : iterator_to_array($userIds))) as $userId) {
            if ($userId <= 0) {
                continue;
            }

            $before = LmsNotifikasi::query()->where('user_id', $userId)->count();
            $this->push($userId, $jenis, $judul, $pesan, $options);
            $after = LmsNotifikasi::query()->where('user_id', $userId)->count();

            if ($after > $before) {
                $created++;
            }
        }

        return $created;
    }

    /**
     * Kirim pengingat deadline tugas ke mahasiswa yang belum mengumpulkan.
     *
     * @param  int  $jumlahJam  rentang pengingat ke depan (jam)
     * @return array{tugas:int, notifikasi:int, outbound:int}
     */
    public function pushDeadlineTugas(int $jumlahJam = 24): array
    {
        $now = now();
        $batas = $now->copy()->addHours(max(1, $jumlahJam));

        $daftarTugas = Tugas::query()
            ->where('is_published', true)
            ->whereNotNull('deadline_at')
            ->whereBetween('deadline_at', [$now, $batas])
            ->with('pertemuan:id,kelas_id')
            ->get();

        $jumlahNotifikasi = 0;
        $jumlahTugas = 0;
        $jumlahOutbound = 0;

        foreach ($daftarTugas as $tugas) {
            $kelasId = $tugas->pertemuan?->kelas_id;
            if (!$kelasId) {
                continue;
            }

            $daftarMahasiswa = $this->mahasiswaBelumMengumpulkan($tugas->id, (int) $kelasId);
            if ($daftarMahasiswa->isEmpty()) {
                continue;
            }

            $jumlahTugas++;
            $deadline = Carbon::parse($tugas->deadline_at);
            $judul = 'Tugas segera jatuh tempo: '.$tugas->judul;
            $pesan = 'Batas pengumpulan tugas ini pada '.$deadline->format('d M Y H:i').'.';

            $jumlahNotifikasi += $this->pushMany(
                $daftarMahasiswa->pluck('user_id')->all(),
                LmsNotifikasi::JENIS_DEADLINE_TUGAS,
                $judul,
                $pesan,
                [
                    'rute' => '/lms/tugas/'.$tugas->id,
                    'kelas_id' => $kelasId,
                    'entitas_type' => Tugas::class,
                    'entitas_id' => $tugas->id,
                    'prioritas' => LmsNotifikasi::PRIORITAS_TINGGI,
                    'dedup_key' => 'deadline_tugas:'.$tugas->id.':'.$deadline->format('Ymd'),
                ]
            );

            $jumlahOutbound += $this->kirimKanalOutbound($daftarMahasiswa, $judul, $pesan);
        }

        return ['tugas' => $jumlahTugas, 'notifikasi' => $jumlahNotifikasi, 'outbound' => $jumlahOutbound];
    }

    /**
     * Kirim pengingat penutupan kuis/tryout ke peserta yang belum mengerjakan.
     *
     * @param  int  $jumlahJam  rentang pengingat ke depan (jam)
     * @return array{quiz:int, notifikasi:int, outbound:int}
     */
    public function pushDeadlineQuiz(int $jumlahJam = 24): array
    {
        $now = now();
        $batas = $now->copy()->addHours(max(1, $jumlahJam));

        $daftarQuiz = Quiz::query()
            ->where('is_published', true)
            ->where('is_archived', false)
            ->whereNotNull('ditutup_at')
            ->whereBetween('ditutup_at', [$now, $batas])
            ->with(['pertemuan:id,kelas_id'])
            ->get();

        $jumlahNotifikasi = 0;
        $jumlahQuiz = 0;
        $jumlahOutbound = 0;

        foreach ($daftarQuiz as $quiz) {
            $kelasId = $quiz->kelas_id ?: $quiz->pertemuan?->kelas_id;
            $daftarMahasiswa = $this->pesertaQuiz($quiz, (int) $kelasId);

            if ($daftarMahasiswa->isEmpty()) {
                continue;
            }

            $jumlahQuiz++;
            $ditutup = Carbon::parse($quiz->ditutup_at);
            $judul = 'Kuis segera ditutup: '.$quiz->judul;
            $pesan = 'Kuis ini ditutup pada '.$ditutup->format('d M Y H:i').'.';

            $jumlahNotifikasi += $this->pushMany(
                $daftarMahasiswa->pluck('user_id')->all(),
                LmsNotifikasi::JENIS_DEADLINE_QUIZ,
                $judul,
                $pesan,
                [
                    'rute' => '/lms/quiz/'.$quiz->id,
                    'kelas_id' => $kelasId ?: null,
                    'entitas_type' => Quiz::class,
                    'entitas_id' => $quiz->id,
                    'prioritas' => LmsNotifikasi::PRIORITAS_TINGGI,
                    'dedup_key' => 'deadline_quiz:'.$quiz->id.':'.$ditutup->format('Ymd'),
                ]
            );

            $jumlahOutbound += $this->kirimKanalOutbound($daftarMahasiswa, $judul, $pesan);
        }

        return ['quiz' => $jumlahQuiz, 'notifikasi' => $jumlahNotifikasi, 'outbound' => $jumlahOutbound];
    }

    /**
     * Kirim pengingat ke kanal outbound global (email / WhatsApp).
     *
     * Memakai `CampusNotificationService` yang sudah ada — TIDAK membuat kanal
     * baru. Kanal bersifat opsional (default nonaktif) dan dinyalakan lewat
     * config `lms.notifikasi.kirim_email` / `lms.notifikasi.kirim_whatsapp`.
     *
     * @param  Collection<int, Mahasiswa>  $daftarMahasiswa
     * @return int jumlah pesan outbound yang dikirim
     */
    private function kirimKanalOutbound(Collection $daftarMahasiswa, string $judul, string $pesan): int
    {
        $kirimEmail = (bool) config('lms.notifikasi.kirim_email', false);
        $kirimWhatsApp = (bool) config('lms.notifikasi.kirim_whatsapp', false);

        if (! $kirimEmail && ! $kirimWhatsApp) {
            return 0;
        }

        $terkirim = 0;

        foreach ($daftarMahasiswa as $mahasiswa) {
            $user = $mahasiswa->user;
            if (! $user) {
                continue;
            }

            if ($kirimEmail && $user->email) {
                CampusNotificationService::sendEmail($user->email, $judul, $pesan);
                $terkirim++;
            }

            if ($kirimWhatsApp && $user->phone) {
                CampusNotificationService::sendWhatsApp($user->phone, $judul.' — '.$pesan);
                $terkirim++;
            }
        }

        return $terkirim;
    }

    /**
     * Daftar mahasiswa yang terdaftar pada kelas dan belum mengumpulkan tugas.
     *
     * @return Collection<int, Mahasiswa>
     */
    private function mahasiswaBelumMengumpulkan(int $tugasId, int $kelasId): Collection
    {
        $sudahMengumpulkan = PengumpulanTugas::query()
            ->where('tugas_id', $tugasId)
            ->pluck('mahasiswa_id')
            ->all();

        return Mahasiswa::query()
            ->with('user:id,name,email,phone')
            ->whereHas('krs.krsDetails', fn ($q) => $q->where('kelas_id', $kelasId))
            ->when(
                !empty($sudahMengumpulkan),
                fn ($q) => $q->whereNotIn('id', $sudahMengumpulkan)
            )
            ->whereNotNull('user_id')
            ->get();
    }

    /**
     * Daftar mahasiswa peserta kuis/tryout yang belum pernah menyelesaikan.
     *
     * @return Collection<int, Mahasiswa>
     */
    private function pesertaQuiz(Quiz $quiz, int $kelasId): Collection
    {
        $pesertaMahasiswaIds = $quiz->tipe === Quiz::TIPE_TRYOUT
            ? $quiz->tryoutPeserta()->pluck('mahasiswa_id')
            : ($kelasId > 0
                ? Mahasiswa::query()
                    ->whereHas('krs.krsDetails', fn ($q) => $q->where('kelas_id', $kelasId))
                    ->pluck('id')
                : collect());

        if ($pesertaMahasiswaIds->isEmpty()) {
            return collect();
        }

        $sudahSelesai = $quiz->attempts()
            ->where('status', 'selesai')
            ->whereIn('mahasiswa_id', $pesertaMahasiswaIds)
            ->pluck('mahasiswa_id')
            ->all();

        return Mahasiswa::query()
            ->with('user:id,name,email,phone')
            ->whereIn('id', $pesertaMahasiswaIds)
            ->when(
                !empty($sudahSelesai),
                fn ($q) => $q->whereNotIn('id', $sudahSelesai)
            )
            ->whereNotNull('user_id')
            ->get();
    }
}
