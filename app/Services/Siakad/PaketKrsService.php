<?php

namespace App\Services\Siakad;

use App\Models\Siakad\HakaksesKrs;
use App\Models\Siakad\Kelas;
use App\Models\Siakad\Krs;
use App\Models\Siakad\Mahasiswa;
use App\Models\Siakad\MasterKelas;
use App\Models\Siakad\PaketMkRombel;
use App\Models\Siakad\TahunAkademik;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpResponseException;

class PaketKrsService
{
    public function __construct(protected KrsService $krsService) {}

    /**
     * Cari rombel (master kelas) milik mahasiswa via padanan
     * nama kelas + prodi (+ angkatan bila cocok).
     */
    public function resolveRombel(Mahasiswa $mhs): ?MasterKelas
    {
        if (!$mhs->kelas) {
            return null;
        }
        $query = MasterKelas::where('program_studi_id', $mhs->program_studi_id)
            ->where('nama_kelas', $mhs->kelas)
            ->where('is_active', true);
        $exact = (clone $query)->where('tahun_angkatan', $mhs->angkatan)->first();
        return $exact ?? $query->first();
    }

    /**
     * Gate akses KRS ala wsia_hakakses legacy (delegasi ke KrsService
     * agar satu sumber kebenaran dengan tambah kelas individual).
     */
    public function assertAksesDibuka(Mahasiswa $mhs, int $tahunAkademikId): void
    {
        $this->krsService->assertAksesKrsDibuka($mhs, $tahunAkademikId);
    }

    /**
     * Ambil 1 paket rombel: setiap MK paket di-resolve ke kelas aktual periode
     * berjalan lalu dimasukkan via KrsService::addClass (validasi SKS,
     * prasyarat, bentrok, duplikat tetap berlaku). Hasil dilaporkan per MK.
     *
     * @return array{berhasil: array, gagal: array, dilewati: array}
     */
    public function ambilPaket(Mahasiswa $mhs, int $tahunAkademikId): array
    {
        $this->krsService->assertMahasiswaAktif($mhs);
        $this->assertAksesDibuka($mhs, $tahunAkademikId);

        $rombel = $this->resolveRombel($mhs);
        if (!$rombel) {
            abort(response()->json([
                'status' => 'error',
                'message' => 'Rombel Anda belum dipetakan (kolom kelas kosong). Hubungi BAAK untuk pemetaan rombel.',
            ], 422));
        }

        $paket = PaketMkRombel::with('mataKuliah')
            ->where('master_kelas_id', $rombel->id)
            ->where(function ($q) use ($tahunAkademikId) {
                $q->where('tahun_akademik_id', $tahunAkademikId)->orWhereNull('tahun_akademik_id');
            })
            ->orderBy('semester_anjuran')
            ->get();
        if ($paket->isEmpty()) {
            abort(response()->json([
                'status' => 'error',
                'message' => "Belum ada paket mata kuliah untuk rombel {$rombel->nama_kelas} pada periode ini.",
            ], 422));
        }

        $hasil = ['berhasil' => [], 'gagal' => [], 'dilewati' => []];
        foreach ($paket as $item) {
            $mk = $item->mataKuliah;
            $label = $mk ? "{$mk->kode_mk} — {$mk->nama}" : "MK ID {$item->mata_kuliah_id}";

            if ($mk && $this->krsService->hasLulusMk($mhs->id, (int) $mk->id)) {
                $hasil['dilewati'][] = ['mk' => $label, 'alasan' => 'Sudah lulus/di konversi'];
                continue;
            }

            $kelas = $this->resolveKelasPaket($mhs, $rombel->id, (int) $item->mata_kuliah_id, $tahunAkademikId);
            if (!$kelas) {
                $hasil['gagal'][] = ['mk' => $label, 'alasan' => 'Belum ada kelas dibuka/kuota penuh periode ini'];
                continue;
            }

            try {
                $this->krsService->addClass($mhs, $tahunAkademikId, (int) $kelas->id, (float) ($mhs->ipk ?? 0));
                $hasil['berhasil'][] = ['mk' => $label, 'kelas' => $kelas->nama_kelas];
            } catch (HttpResponseException $e) {
                $hasil['gagal'][] = ['mk' => $label, 'alasan' => $this->pesanGagal($e)];
            }
        }

        return [$rombel, $hasil];
    }

    /**
     * Resolve satu MK paket ke kelas aktual: utamakan kelas yang menempelkan
     * rombel mahasiswa, lalu yang masih berkuota.
     */
    protected function resolveKelasPaket(Mahasiswa $mhs, int $rombelId, int $mkId, int $taId): ?Kelas
    {
        $kandidat = Kelas::with('rombels')
            ->where('tahun_akademik_id', $taId)
            ->where('mata_kuliah_id', $mkId)
            ->where('status', 'aktif')
            ->where(function ($q) use ($mhs) {
                $q->where('program_studi_id', $mhs->program_studi_id)
                    ->orWhereHas('programStudis', fn($pq) => $pq->where('siakad_program_studi.id', $mhs->program_studi_id));
            })
            ->get();
        if ($kandidat->isEmpty()) {
            return null;
        }
        $prioritas = $kandidat->sortByDesc(fn($k) => $k->rombels->contains('id', $rombelId) ? 1 : 0)
            ->values();
        return $prioritas->first(fn($k) => $k->kuota_krs > 0) ?? $prioritas->first();
    }

    protected function pesanGagal(HttpResponseException $e): string
    {
        try {
            $data = json_decode($e->getResponse()->getContent(), true);
            if (is_array($data) && isset($data['message'])) {
                return (string) $data['message'];
            }
        } catch (\Throwable $ignored) {
        }
        return 'Gagal ditambahkan ke KRS.';
    }

    /**
     * Buka/tutup akses KRS massal untuk seluruh mahasiswa satu rombel.
     *
     * @return array{dibuka: int, dikunci: int, total: int}
     */
    public function setAksesRombel(int $masterKelasId, int $tahunAkademikId, bool $isDibuka): array
    {
        $rombel = MasterKelas::findOrFail($masterKelasId);
        $mhsIds = Mahasiswa::where('program_studi_id', $rombel->program_studi_id)
            ->where('kelas', $rombel->nama_kelas)
            ->where('status', 'aktif')
            ->pluck('id');

        $hasil = DB::transaction(function () use ($mhsIds, $tahunAkademikId, $isDibuka) {
            $count = 0;
            foreach ($mhsIds as $mhsId) {
                HakaksesKrs::updateOrCreate(
                    ['mahasiswa_id' => $mhsId, 'tahun_akademik_id' => $tahunAkademikId],
                    ['is_dibuka' => $isDibuka, 'dibuka_at' => $isDibuka ? now() : null]
                );
                $count++;
            }
            return ['dibuka' => $isDibuka ? $count : 0, 'dikunci' => $isDibuka ? 0 : $count, 'total' => $count];
        });

        try {
            \App\Services\AuditLogService::record(
                module: 'SIAKAD',
                action: 'update',
                tableName: 'siakad_hakakses_krs',
                recordId: null,
                newValues: ['master_kelas_id' => $masterKelasId, 'is_dibuka' => $isDibuka, 'hasil' => $hasil]
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal mencatat audit log setAksesRombel: ' . $e->getMessage());
        }

        return $hasil;
    }
}
