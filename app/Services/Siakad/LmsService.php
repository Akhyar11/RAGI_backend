<?php

namespace App\Services\Siakad;

use App\Models\Lms\KelasLmsSetting;
use App\Models\Lms\MateriPertemuan;
use App\Models\Lms\MateriFile;
use App\Models\Lms\Tugas;
use App\Models\Lms\PengumpulanTugas;
use App\Models\Lms\IzinAbsensi;
use App\Models\Siakad\Kelas;
use App\Models\Siakad\Pertemuan;
use App\Models\Siakad\AbsensiMahasiswa;
use App\Models\Siakad\Mahasiswa;
use App\Models\Siakad\Dosen;
use App\Models\Siakad\DosenPengampu;
use App\Models\Siakad\KrsDetail;
use App\Models\Siakad\NilaiKomponenMahasiswa;
use App\Models\SystemSetting;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LmsService
{
    /**
     * Dapatkan disk storage yang aktif (prioritas: kelas -> system setting -> default disk).
     */
    public function resolveDisk(?Kelas $kelas = null): string
    {
        if ($kelas && $kelas->lmsSetting && !empty($kelas->lmsSetting->storage_disk)) {
            $disk = $kelas->lmsSetting->storage_disk;
            if (array_key_exists($disk, config('filesystems.disks', []))) {
                return $disk;
            }
        }

        $configuredDisk = SystemSetting::get('lms_storage_disk');
        if ($configuredDisk && array_key_exists($configuredDisk, config('filesystems.disks', []))) {
            return $configuredDisk;
        }

        return config('filesystems.default', 'local');
    }

    /**
     * Dapatkan overview kelas LMS beserta 16 pertemuan, progres, dan statistik.
     */
    public function getKelasOverview(int $kelasId, ?int $userId = null): array
    {
        $kelas = Kelas::with([
            'mataKuliah',
            'tahunAkademik',
            'programStudi',
            'ruangan',
            'dosenPengampu.dosen',
            'lmsSetting',
            'pertemuans' => function ($q) {
                $q->orderBy('pertemuan_ke')
                  ->withCount(['materiList', 'tugasList', 'absensi']);
            },
        ])->findOrFail($kelasId);

        // Ambil mahasiswa peserta kelas dari KRS disetujui
        $totalMahasiswa = KrsDetail::where('kelas_id', $kelasId)->count();

        // Hitung statistik pertemuan
        $totalPertemuan = $kelas->pertemuans->count();
        $pertemuanTerisi = $kelas->pertemuans->filter(function ($p) {
            return $p->materi_list_count > 0 || $p->absensi_count > 0;
        })->count();

        // Hitung rata-rata kehadiran jika ada absensi
        $totalAbsen = AbsensiMahasiswa::whereIn('pertemuan_id', $kelas->pertemuans->pluck('id'))->count();
        $totalHadir = AbsensiMahasiswa::whereIn('pertemuan_id', $kelas->pertemuans->pluck('id'))
            ->where('status', 'hadir')
            ->count();
        $rataHadirPersen = $totalAbsen > 0 ? round(($totalHadir / $totalAbsen) * 100, 1) : 0;

        // Total tugas aktif
        $totalTugas = Tugas::whereIn('pertemuan_id', $kelas->pertemuans->pluck('id'))->count();

        return [
            'kelas' => $kelas,
            'lms_setting' => $kelas->lmsSetting ?? $this->getDefaultKelasSetting($kelas),
            'progress' => [
                'total' => $totalPertemuan,
                'terisi' => $pertemuanTerisi,
                'persen' => $totalPertemuan > 0 ? round(($pertemuanTerisi / $totalPertemuan) * 100) : 0,
            ],
            'statistik' => [
                'total_mahasiswa' => $totalMahasiswa,
                'total_tugas' => $totalTugas,
                'rata_hadir_persen' => $rataHadirPersen,
            ],
        ];
    }

    /**
     * Dapatkan detail pertemuan LMS (Materi, Tugas, Absensi, dan Izin).
     */
    public function getPertemuanDetail(int $pertemuanId, ?int $userId = null): array
    {
        $pertemuan = Pertemuan::with([
            'kelas.mataKuliah',
            'kelas.lmsSetting',
            'materiList.files',
            'tugasList.komponenPenilaian',
            'tugasList.pengumpulan.mahasiswa',
            'absensi.mahasiswa',
            'izinAbsensiList.mahasiswa',
        ])->findOrFail($pertemuanId);

        $isMahasiswa = false;
        $mahasiswaModel = null;
        if ($userId) {
            $mahasiswaModel = Mahasiswa::where('user_id', $userId)->first();
            $isMahasiswa = $mahasiswaModel !== null;
        }

        // Jika mahasiswa, filter hanya materi dan tugas yang sudah dipublish
        $materiList = $pertemuan->materiList;
        $tugasList = $pertemuan->tugasList;
        if ($isMahasiswa) {
            $materiList = $materiList->where('is_published', true)->values();
            $tugasList = $tugasList->where('is_published', true)->values();
        }

        // Status absensi mahasiswa login
        $myAbsensi = null;
        $myPengumpulan = [];
        $myIzin = null;
        if ($mahasiswaModel) {
            $myAbsensi = $pertemuan->absensi->firstWhere('mahasiswa_id', $mahasiswaModel->id);
            $myIzin = $pertemuan->izinAbsensiList->firstWhere('mahasiswa_id', $mahasiswaModel->id);
            foreach ($tugasList as $tugas) {
                $sub = $tugas->pengumpulan->firstWhere('mahasiswa_id', $mahasiswaModel->id);
                if ($sub) {
                    $myPengumpulan[$tugas->id] = $sub;
                }
            }
        }

        // Summary absensi per pertemuan
        $absensiSummary = [
            'hadir' => $pertemuan->absensi->where('status', 'hadir')->count(),
            'sakit' => $pertemuan->absensi->where('status', 'sakit')->count(),
            'izin'  => $pertemuan->absensi->where('status', 'izin')->count(),
            'alfa'  => $pertemuan->absensi->where('status', 'alfa')->count(),
            'total' => $pertemuan->absensi->count(),
        ];

        // Status token aktif
        $tokenAktif = !empty($pertemuan->token_absensi) &&
            $pertemuan->token_expired_at &&
            now()->lt($pertemuan->token_expired_at);

        return [
            'pertemuan' => $pertemuan,
            'materi_list' => $materiList,
            'tugas_list' => $tugasList,
            'absensi_list' => $pertemuan->absensi,
            'absensi_summary' => $absensiSummary,
            'izin_list' => $pertemuan->izinAbsensiList,
            'token_aktif' => $tokenAktif,
            'token_sisa_detik' => $tokenAktif ? now()->diffInSeconds($pertemuan->token_expired_at) : 0,
            'my_absensi' => $myAbsensi,
            'my_pengumpulan' => $myPengumpulan,
            'my_izin' => $myIzin,
        ];
    }

    /**
     * Buat materi pembelajaran baru.
     */
    public function storeMateri(int $pertemuanId, array $data, ?UploadedFile $file = null): MateriPertemuan
    {
        return DB::transaction(function () use ($pertemuanId, $data, $file) {
            $pertemuan = Pertemuan::with('kelas')->findOrFail($pertemuanId);

            $tipeKontenId = $data['tipe_konten_id'] ?? null;
            $tipeKonten   = $data['tipe_konten'] ?? null;
            if ($tipeKontenId && !$tipeKonten) {
                $ref = DB::table('spmb_master_referensi')->find($tipeKontenId);
                $tipeKonten = $ref?->kode ?? 'teks';
            }

            $materi = MateriPertemuan::create([
                'pertemuan_id'   => $pertemuanId,
                'tipe_konten_id' => $tipeKontenId,
                'judul'          => $data['judul'],
                'deskripsi'      => $data['deskripsi'] ?? null,
                'tipe_konten'    => $tipeKonten ?? 'teks',
                'link_eksternal' => $data['link_eksternal'] ?? null,
                'urutan'         => $data['urutan'] ?? ((MateriPertemuan::where('pertemuan_id', $pertemuanId)->max('urutan') ?? 0) + 1),
                'is_published'   => $data['is_published'] ?? true,
            ]);

            // Jika ada berkas di-upload bersamaan
            if ($file) {
                $this->uploadMateriFile($materi->id, $file);
            }

            return $materi->load('files');
        });
    }

    /**
     * Perbarui data materi pembelajaran.
     */
    public function updateMateri(int $materiId, array $data): MateriPertemuan
    {
        if (isset($data['tipe_konten_id']) && !isset($data['tipe_konten'])) {
            $ref = DB::table('spmb_master_referensi')->find($data['tipe_konten_id']);
            $data['tipe_konten'] = $ref?->kode ?? 'teks';
        }

        $materi = MateriPertemuan::findOrFail($materiId);
        $materi->update($data);

        return $materi->fresh(['files']);
    }

    /**
     * Hapus materi beserta berkas fisiknya.
     */
    public function deleteMateri(int $materiId): bool
    {
        return DB::transaction(function () use ($materiId) {
            $materi = MateriPertemuan::with('files')->findOrFail($materiId);

            foreach ($materi->files as $file) {
                if ($file->file_path && Storage::disk($file->disk)->exists($file->file_path)) {
                    Storage::disk($file->disk)->delete($file->file_path);
                }
                $file->delete();
            }

            return $materi->delete();
        });
    }

    /**
     * Upload lampiran berkas materi tambahan.
     */
    public function uploadMateriFile(int $materiId, UploadedFile $file): MateriFile
    {
        $materi = MateriPertemuan::with('pertemuan.kelas')->findOrFail($materiId);
        $disk = $this->resolveDisk($materi->pertemuan?->kelas);

        $safeName = Str::uuid() . '.' . $file->getClientOriginalExtension();
        $directory = 'siakad/lms/materi/' . date('Y/m');
        $storedPath = $file->storeAs($directory, $safeName, $disk);

        return MateriFile::create([
            'materi_id'      => $materiId,
            'nama_file'      => $file->getClientOriginalName(),
            'file_path'      => $storedPath,
            'disk'           => $disk,
            'ukuran_bytes'   => $file->getSize(),
            'mime_type'      => $file->getClientMimeType(),
        ]);
    }

    /**
     * Hapus satu berkas lampiran materi.
     */
    public function deleteMateriFile(int $fileId): bool
    {
        $file = MateriFile::findOrFail($fileId);

        if ($file->file_path && Storage::disk($file->disk)->exists($file->file_path)) {
            Storage::disk($file->disk)->delete($file->file_path);
        }

        return $file->delete();
    }

    /**
     * Buat penugasan baru pada pertemuan tertentu.
     */
    public function storeTugas(int $pertemuanId, array $data): Tugas
    {
        Pertemuan::findOrFail($pertemuanId);

        return Tugas::create([
            'pertemuan_id'          => $pertemuanId,
            'komponen_penilaian_id' => $data['komponen_penilaian_id'] ?? null,
            'judul'                 => $data['judul'],
            'deskripsi'             => $data['deskripsi'] ?? null,
            'deadline_at'           => $data['deadline_at'] ?? null,
            'maks_nilai'            => $data['maks_nilai'] ?? 100,
            'can_submit_late'       => $data['can_submit_late'] ?? true,
            'is_published'          => $data['is_published'] ?? true,
        ]);
    }

    /**
     * Perbarui data tugas perkuliahan.
     */
    public function updateTugas(int $tugasId, array $data): Tugas
    {
        $tugas = Tugas::findOrFail($tugasId);
        $tugas->update($data);

        return $tugas->fresh();
    }

    /**
     * Hapus penugasan dan seluruh berkas pengumpulan mahasiswa.
     */
    public function deleteTugas(int $tugasId): bool
    {
        return DB::transaction(function () use ($tugasId) {
            $tugas = Tugas::with('pengumpulan')->findOrFail($tugasId);

            foreach ($tugas->pengumpulan as $sub) {
                if ($sub->file_path && Storage::disk($sub->disk)->exists($sub->file_path)) {
                    Storage::disk($sub->disk)->delete($sub->file_path);
                }
                $sub->delete();
            }

            return $tugas->delete();
        });
    }

    /**
     * Simpan pengumpulan tugas oleh mahasiswa.
     */
    public function kumpulkanTugas(int $tugasId, int $userId, array $data, ?UploadedFile $file = null): PengumpulanTugas
    {
        return DB::transaction(function () use ($tugasId, $userId, $data, $file) {
            $mahasiswa = Mahasiswa::where('user_id', $userId)->firstOrFail();
            $mahasiswaId = $mahasiswa->id;

            $tugas = Tugas::with('pertemuan.kelas')->findOrFail($tugasId);
            $disk = $this->resolveDisk($tugas->pertemuan?->kelas);

            $isLate = false;
            if ($tugas->deadline_at && now()->gt($tugas->deadline_at)) {
                if (!$tugas->can_submit_late) {
                    throw ValidationException::withMessages([
                        'deadline_at' => ['Batas waktu pengumpulan tugas ini telah berakhir dan tidak menerima pengumpulan terlambat.'],
                    ]);
                }
                $isLate = true;
            }

            $pengumpulan = PengumpulanTugas::firstOrNew([
                'tugas_id'     => $tugasId,
                'mahasiswa_id' => $mahasiswaId,
            ]);

            // Jika ada file lama dan di-upload file baru, hapus berkas lama
            if ($file) {
                if ($pengumpulan->file_path && Storage::disk($pengumpulan->disk)->exists($pengumpulan->file_path)) {
                    Storage::disk($pengumpulan->disk)->delete($pengumpulan->file_path);
                }

                $safeName = Str::uuid() . '.' . $file->getClientOriginalExtension();
                $directory = 'siakad/lms/pengumpulan/' . date('Y/m');
                $storedPath = $file->storeAs($directory, $safeName, $disk);

                $pengumpulan->file_path = $storedPath;
                $pengumpulan->disk = $disk;
                $pengumpulan->nama_file_asli = $file->getClientOriginalName();
                $pengumpulan->ukuran_bytes = $file->getSize();
                $pengumpulan->mime_type = $file->getClientMimeType();
            }

            $pengumpulan->catatan_mahasiswa = $data['catatan_mahasiswa'] ?? $pengumpulan->catatan_mahasiswa;
            $pengumpulan->is_late = $isLate;
            $pengumpulan->save();

            return $pengumpulan->fresh();
        });
    }

    /**
     * Beri nilai tugas dan otomatis sinkronisasikan ke OBE jika terhubung komponen penilaian.
     */
    public function beriNilaiTugas(int $pengumpulanId, float $nilai, ?string $feedback, int $dosenUserId): PengumpulanTugas
    {
        return DB::transaction(function () use ($pengumpulanId, $nilai, $feedback, $dosenUserId) {
            $pengumpulan = PengumpulanTugas::with('tugas.pertemuan.kelas')->findOrFail($pengumpulanId);
            $tugas = $pengumpulan->tugas;

            if ($nilai < 0 || $nilai > $tugas->maks_nilai) {
                throw ValidationException::withMessages([
                    'nilai' => ["Nilai harus berada di antara 0 dan {$tugas->maks_nilai}."],
                ]);
            }

            $pengumpulan->update([
                'nilai'          => $nilai,
                'feedback_dosen' => $feedback,
                'dinilai_at'     => now(),
                'dinilai_oleh'   => $dosenUserId,
            ]);

            // Sinkronisasi ke siakad_nilai_komponen_mhs jika ada komponen OBE yang terhubung
            if ($tugas->komponen_penilaian_id && $tugas->pertemuan?->kelas_id) {
                $kelasId = $tugas->pertemuan->kelas_id;
                $mahasiswaId = $pengumpulan->mahasiswa_id;

                // Cari KRS Detail mahasiswa untuk kelas ini
                $krsDetail = KrsDetail::where('kelas_id', $kelasId)
                    ->whereHas('krs', function ($q) use ($mahasiswaId) {
                        $q->where('mahasiswa_id', $mahasiswaId);
                    })
                    ->first();

                if ($krsDetail) {
                    NilaiKomponenMahasiswa::updateOrCreate(
                        [
                            'krs_detail_id'         => $krsDetail->id,
                            'komponen_penilaian_id' => $tugas->komponen_penilaian_id,
                        ],
                        [
                            'nilai_angka'           => $nilai,
                            'catatan_feedback'      => $feedback,
                            'diinput_oleh'          => $dosenUserId,
                        ]
                    );
                }
            }

            return $pengumpulan->fresh(['tugas', 'mahasiswa']);
        });
    }

    /**
     * Generate 6-digit token absensi realtime dengan masa aktif berbatas.
     */
    public function generateTokenAbsensi(int $pertemuanId): array
    {
        $pertemuan = Pertemuan::with('kelas.lmsSetting')->findOrFail($pertemuanId);

        $ttlMinutes = (int) SystemSetting::get('lms_token_ttl_minutes', 15);
        if ($ttlMinutes < 1) {
            $ttlMinutes = 15;
        }

        // Generate 6 digit angka acak
        $token = str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
        $expiredAt = now()->addMinutes($ttlMinutes);

        $pertemuan->update([
            'token_absensi'    => $token,
            'token_expired_at' => $expiredAt,
            'status_pertemuan' => 'berlangsung',
        ]);

        return [
            'pertemuan_id' => $pertemuanId,
            'token'      => $token,
            'ttl_menit'  => $ttlMinutes,
            'expired_at' => $expiredAt->toIso8601String(),
            'sisa_detik' => $ttlMinutes * 60,
        ];
    }

    /**
     * Mahasiswa menginputkan token absensi untuk mencatatkan kehadiran.
     */
    public function inputTokenAbsensi(int $pertemuanId, int $userId, string $token): void
    {
        $mahasiswa = Mahasiswa::where('user_id', $userId)->firstOrFail();
        $mahasiswaId = $mahasiswa->id;

        $pertemuan = Pertemuan::with('kelas')->findOrFail($pertemuanId);

        if (empty($pertemuan->token_absensi) || trim($pertemuan->token_absensi) !== trim($token)) {
            throw ValidationException::withMessages([
                'token' => ['Token absensi tidak cocok atau tidak valid.'],
            ]);
        }

        if (!$pertemuan->token_expired_at || now()->gt($pertemuan->token_expired_at)) {
            throw ValidationException::withMessages([
                'token' => ['Token absensi telah kedaluwarsa. Mintalah token baru kepada dosen pengampu.'],
            ]);
        }

        AbsensiMahasiswa::updateOrCreate(
            [
                'pertemuan_id' => $pertemuanId,
                'mahasiswa_id' => $mahasiswaId,
            ],
            [
                'status'  => 'hadir',
                'catatan' => 'Presensi mandiri via token LMS',
            ]
        );
    }

    /**
     * Input absensi massal oleh dosen untuk satu pertemuan.
     */
    public function bulkInputAbsensi(int $pertemuanId, array $absensiData): void
    {
        DB::transaction(function () use ($pertemuanId, $absensiData) {
            Pertemuan::findOrFail($pertemuanId);

            foreach ($absensiData as $item) {
                if (!isset($item['mahasiswa_id'])) {
                    continue;
                }

                $statusKode = $item['status'] ?? null;
                if (!empty($item['status_id']) && !$statusKode) {
                    $ref = DB::table('spmb_master_referensi')->find($item['status_id']);
                    $statusKode = $ref?->kode ?? 'hadir';
                }

                if (!$statusKode) {
                    continue;
                }

                AbsensiMahasiswa::updateOrCreate(
                    [
                        'pertemuan_id' => $pertemuanId,
                        'mahasiswa_id' => $item['mahasiswa_id'],
                    ],
                    [
                        'status'  => $statusKode,
                        'catatan' => $item['catatan'] ?? null,
                    ]
                );
            }
        });
    }

    /**
     * Mahasiswa mengajukan izin atau sakit untuk absensi pertemuan.
     */
    public function ajukanIzin(int $pertemuanId, int $userId, array $data, ?UploadedFile $suratFile = null): IzinAbsensi
    {
        return DB::transaction(function () use ($pertemuanId, $userId, $data, $suratFile) {
            $mahasiswa = Mahasiswa::where('user_id', $userId)->firstOrFail();
            $mahasiswaId = $mahasiswa->id;

            $pertemuan = Pertemuan::with('kelas')->findOrFail($pertemuanId);
            $disk = $this->resolveDisk($pertemuan->kelas);

            $suratPath = null;
            if ($suratFile) {
                $safeName = Str::uuid() . '.' . $suratFile->getClientOriginalExtension();
                $directory = 'siakad/lms/izin/' . date('Y/m');
                $suratPath = $suratFile->storeAs($directory, $safeName, $disk);
            }

            $tipeIzinId = $data['tipe_izin_id'] ?? null;
            $tipeIzin   = $data['tipe_izin'] ?? null;
            if ($tipeIzinId && !$tipeIzin) {
                $ref = DB::table('spmb_master_referensi')->find($tipeIzinId);
                $tipeIzin = $ref?->kode ?? 'izin';
            }

            return IzinAbsensi::updateOrCreate(
                [
                    'pertemuan_id' => $pertemuanId,
                    'mahasiswa_id' => $mahasiswaId,
                ],
                [
                    'tipe_izin_id' => $tipeIzinId,
                    'tipe_izin'    => $tipeIzin ?? 'izin',
                    'alasan'       => $data['alasan'],
                    'surat_path'   => $suratPath,
                    'disk'         => $disk,
                    'status'       => 'pending',
                ]
            );
        });
    }

    /**
     * Dosen memproses (setujui / tolak) pengajuan izin absensi mahasiswa.
     */
    public function prosesIzin(int $izinId, int $statusId, ?string $catatan, int $dosenUserId): IzinAbsensi
    {
        return DB::transaction(function () use ($izinId, $statusId, $catatan, $dosenUserId) {
            $izin = IzinAbsensi::findOrFail($izinId);

            $ref = DB::table('spmb_master_referensi')->find($statusId);
            $statusKode = $ref?->kode ?? 'disetujui';

            $izin->update([
                'status'        => $statusKode,
                'catatan_dosen' => $catatan,
                'diproses_oleh' => $dosenUserId,
                'diproses_at'   => now(),
            ]);

            // Jika disetujui, update record siakad_absensi_mahasiswa ke status sakit/izin
            if ($statusKode === 'disetujui') {
                AbsensiMahasiswa::updateOrCreate(
                    [
                        'pertemuan_id' => $izin->pertemuan_id,
                        'mahasiswa_id' => $izin->mahasiswa_id,
                    ],
                    [
                        'status'  => $izin->tipe_izin === 'sakit' ? 'sakit' : 'izin',
                        'catatan' => 'Disetujui dari pengajuan surat izin: ' . ($catatan ?? '-'),
                    ]
                );
            }

            return $izin->fresh();
        });
    }

    /**
     * Rekapitulasi kehadiran satu kelas lengkap untuk seluruh pertemuan.
     */
    public function getRekapAbsensiKelas(int $kelasId): array
    {
        $kelas = Kelas::with(['lmsSetting', 'pertemuans'])->findOrFail($kelasId);
        $totalPertemuan = $kelas->pertemuans->count();

        // Ambil seluruh mahasiswa yang mengambil kelas ini via KRS Detail
        $krsDetails = KrsDetail::with('krs.mahasiswa')
            ->where('kelas_id', $kelasId)
            ->get();

        $minHadirPersen = $kelas->lmsSetting?->batas_min_hadir_persen ?? 75;

        $rekap = [];
        foreach ($krsDetails as $kd) {
            $mhs = $kd->krs?->mahasiswa;
            if (!$mhs) continue;

            $absensiList = AbsensiMahasiswa::whereIn('pertemuan_id', $kelas->pertemuans->pluck('id'))
                ->where('mahasiswa_id', $mhs->id)
                ->get();

            $hadir = $absensiList->where('status', 'hadir')->count();
            $sakit = $absensiList->where('status', 'sakit')->count();
            $izin  = $absensiList->where('status', 'izin')->count();
            $alfa  = $absensiList->where('status', 'alfa')->count();

            $persenHadir = $totalPertemuan > 0 ? round(($hadir / $totalPertemuan) * 100, 1) : 0;
            $memenuhiSyarat = $persenHadir >= $minHadirPersen;

            $rekap[] = [
                'mahasiswa_id'     => $mhs->id,
                'nim'              => $mhs->nim,
                'nama_lengkap'     => $mhs->nama_lengkap,
                'hadir'            => $hadir,
                'sakit'            => $sakit,
                'izin'             => $izin,
                'alfa'             => $alfa,
                'total_pertemuan'  => $totalPertemuan,
                'persen_kehadiran' => $persenHadir,
                'memenuhi_syarat'  => $memenuhiSyarat,
            ];
        }

        return [
            'kelas_id'               => $kelas->id,
            'total_pertemuan'        => $totalPertemuan,
            'batas_min_hadir_persen' => $minHadirPersen,
            'rekapitulasi'           => $rekap,
        ];
    }

    /**
     * Dapatkan daftar kelas yang diampu dosen atau diambil mahasiswa dengan pagination dan filter.
     */
    public function getMyKelas(
        int $userId,
        int $perPage = 15,
        ?string $search = null,
        string $sortBy = 'created_at',
        string $sortOrder = 'desc'
    ): \Illuminate\Contracts\Pagination\LengthAwarePaginator {
        $query = Kelas::with(['mataKuliah', 'tahunAkademik', 'programStudi', 'dosenPengampu.dosen', 'ruangan']);

        $dosen = Dosen::where('user_id', $userId)->first();
        if ($dosen) {
            $kelasIds = DosenPengampu::where('dosen_id', $dosen->id)->pluck('kelas_id');
            $query->whereIn('id', $kelasIds);
        } else {
            $mahasiswa = Mahasiswa::where('user_id', $userId)->first();
            if ($mahasiswa) {
                $kelasIds = KrsDetail::whereHas('krs', function ($q) use ($mahasiswa) {
                    $q->where('mahasiswa_id', $mahasiswa->id);
                })->pluck('kelas_id');
                $query->whereIn('id', $kelasIds);
            }
        }

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('nama_kelas', 'like', "%{$search}%")
                  ->orWhere('kode_kelas', 'like', "%{$search}%")
                  ->orWhereHas('mataKuliah', function ($mk) use ($search) {
                      $mk->where('nama', 'like', "%{$search}%")
                         ->orWhere('kode_mk', 'like', "%{$search}%");
                  });
            });
        }

        $allowedSorts = ['id', 'nama_kelas', 'kode_kelas', 'created_at', 'updated_at'];
        $sortField = in_array($sortBy, $allowedSorts, true) ? $sortBy : 'created_at';
        $direction = strtolower($sortOrder) === 'asc' ? 'asc' : 'desc';

        return $query->orderBy($sortField, $direction)->paginate($perPage);
    }

    /**
     * Dapatkan daftar seluruh tugas milik mahasiswa yang sedang login dengan pagination.
     */
    public function getMyAllTugas(
        int $userId,
        int $perPage = 15,
        ?string $search = null,
        string $sortBy = 'created_at',
        string $sortOrder = 'desc'
    ): \Illuminate\Contracts\Pagination\LengthAwarePaginator {
        $mahasiswa = Mahasiswa::where('user_id', $userId)->first();
        if (!$mahasiswa) {
            return Tugas::whereRaw('1 = 0')->paginate($perPage);
        }

        $kelasIds = KrsDetail::whereHas('krs', function ($q) use ($mahasiswa) {
            $q->where('mahasiswa_id', $mahasiswa->id);
        })->pluck('kelas_id');

        $query = Tugas::whereHas('pertemuan', function ($q) use ($kelasIds) {
            $q->whereIn('kelas_id', $kelasIds);
        })
        ->where('is_published', true)
        ->with(['pertemuan.kelas.mataKuliah', 'komponenPenilaian', 'pengumpulan' => function ($q) use ($mahasiswa) {
            $q->where('mahasiswa_id', $mahasiswa->id);
        }]);

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('judul', 'like', "%{$search}%")
                  ->orWhere('deskripsi', 'like', "%{$search}%");
            });
        }

        $allowedSorts = ['id', 'judul', 'deadline_at', 'created_at', 'updated_at'];
        $sortField = in_array($sortBy, $allowedSorts, true) ? $sortBy : 'created_at';
        $direction = strtolower($sortOrder) === 'asc' ? 'asc' : 'desc';

        return $query->orderBy($sortField, $direction)->paginate($perPage);
    }

    /**
     * Dapatkan URL unduh aman untuk materi atau pengumpulan tugas.
     */
    public function getDownloadUrl(string $type, int $id, int $userId): array
    {
        if ($type === 'materi') {
            $file = MateriFile::findOrFail($id);
            $disk = $file->disk;
            $path = $file->file_path;
            $filename = $file->nama_file;
        } elseif ($type === 'pengumpulan') {
            $pengumpulan = PengumpulanTugas::findOrFail($id);
            $disk = $pengumpulan->disk;
            $path = $pengumpulan->file_path;
            $filename = $pengumpulan->nama_file_asli ?: 'submission.pdf';
        } else {
            throw ValidationException::withMessages([
                'type' => ['Tipe download tidak dikenal.'],
            ]);
        }

        if (!$path || !Storage::disk($disk)->exists($path)) {
            throw new ModelNotFoundException('Berkas tidak ditemukan pada storage server.');
        }

        // Jika disk mendukung temporaryUrl (seperti S3 / Cloudflare R2)
        try {
            $url = Storage::disk($disk)->temporaryUrl($path, now()->addMinutes(30));
        } catch (\Throwable $e) {
            // Fallback untuk storage lokal
            $url = Storage::disk($disk)->url($path);
        }

        return [
            'nama_file'    => $filename,
            'download_url' => $url,
            'disk'         => $disk,
        ];
    }

    /**
     * Perbarui konfigurasi LMS kelas.
     */
    public function updateKelasLmsSetting(int $kelasId, array $data): KelasLmsSetting
    {
        Kelas::findOrFail($kelasId);

        return KelasLmsSetting::updateOrCreate(
            ['kelas_id' => $kelasId],
            [
                'total_pertemuan'         => $data['total_pertemuan'] ?? 16,
                'metode_absensi'          => $data['metode_absensi'] ?? 'keduanya',
                'batas_min_hadir_persen'  => $data['batas_min_hadir_persen'] ?? 75,
                'can_submit_late'         => $data['can_submit_late'] ?? true,
                'show_nilai_to_mahasiswa' => $data['show_nilai_to_mahasiswa'] ?? true,
                'storage_disk'            => $data['storage_disk'] ?? null,
            ]
        );
    }

    /**
     * Setting bawaan jika kelas belum memiliki konfigurasi khusus.
     */
    protected function getDefaultKelasSetting(Kelas $kelas): array
    {
        return [
            'kelas_id'                => $kelas->id,
            'total_pertemuan'         => 16,
            'metode_absensi'          => 'keduanya',
            'batas_min_hadir_persen'  => 75,
            'can_submit_late'         => true,
            'show_nilai_to_mahasiswa' => true,
            'storage_disk'            => $this->resolveDisk($kelas),
        ];
    }
}
