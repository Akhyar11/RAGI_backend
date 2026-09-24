<?php

namespace App\Services\Sinapra;

use App\Models\PeminjamanRuangan;
use App\Models\Ruangan;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class KalenderRuanganService
{
    /**
     * Peta nama hari Inggris ke Indonesia untuk pencocokan jadwal kuliah SIAKAD.
     */
    private const HARI_MAP = [
        'Monday' => 'Senin',
        'Tuesday' => 'Selasa',
        'Wednesday' => 'Rabu',
        'Thursday' => 'Kamis',
        'Friday' => 'Jumat',
        'Saturday' => 'Sabtu',
        'Sunday' => 'Minggu',
    ];

    /**
     * Mengambil jadwal terpadu ketersediaan ruangan (Peminjaman SINAPRA + Perkuliahan SIAKAD).
     *
     * @param array $filters
     * @return array
     */
    public function getSchedule(array $filters = []): array
    {
        $startDate = !empty($filters['start_date'])
            ? Carbon::parse($filters['start_date'])->startOfDay()
            : Carbon::now()->startOfWeek()->startOfDay();

        $endDate = !empty($filters['end_date'])
            ? Carbon::parse($filters['end_date'])->endOfDay()
            : Carbon::now()->endOfWeek()->endOfDay();

        $ruanganId = !empty($filters['ruangan_id']) ? (int) $filters['ruangan_id'] : null;
        $gedungId = !empty($filters['gedung_id']) ? (int) $filters['gedung_id'] : null;
        $source = $filters['source'] ?? 'semua';

        $events = [];

        // 1. Ambil Event dari Peminjaman SINAPRA
        if ($source === 'semua' || $source === 'sinapra') {
            $peminjamanEvents = $this->getSinapraPeminjamanEvents($startDate, $endDate, $ruanganId, $gedungId);
            $events = array_merge($events, $peminjamanEvents);
        }

        // 2. Ambil Event dari Perkuliahan SIAKAD
        if ($source === 'semua' || $source === 'siakad') {
            $siakadEvents = $this->getSiakadPerkuliahanEvents($startDate, $endDate, $ruanganId, $gedungId);
            $events = array_merge($events, $siakadEvents);
        }

        // Urutkan event berdasarkan tanggal ascending, lalu jam_mulai ascending
        usort($events, function ($a, $b) {
            $dateCmp = strcmp($a['tanggal'], $b['tanggal']);
            if ($dateCmp !== 0) {
                return $dateCmp;
            }
            return strcmp($a['jam_mulai'], $b['jam_mulai']);
        });

        return [
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d'),
            'total' => count($events),
            'events' => $events,
        ];
    }

    /**
     * Mengambil agenda peminjaman ruangan dari modul SINAPRA.
     */
    private function getSinapraPeminjamanEvents(Carbon $startDate, Carbon $endDate, ?int $ruanganId, ?int $gedungId): array
    {
        $query = PeminjamanRuangan::with(['ruangan.gedung', 'user'])
            ->whereDate('tanggal', '>=', $startDate->format('Y-m-d'))
            ->whereDate('tanggal', '<=', $endDate->format('Y-m-d'))
            ->whereIn('status', ['disetujui', 'berlangsung', 'pending']);

        if ($ruanganId) {
            $query->where('ruangan_id', $ruanganId);
        }

        if ($gedungId) {
            $query->whereHas('ruangan', function ($q) use ($gedungId) {
                $q->where('gedung_id', $gedungId);
            });
        }

        $records = $query->get();
        $items = [];

        foreach ($records as $item) {
            $items[] = [
                'id' => 'sinapra-' . $item->id,
                'raw_id' => $item->id,
                'source' => 'sinapra',
                'ruangan_id' => $item->ruangan_id,
                'ruangan_nama' => $item->ruangan?->nama ?? 'Ruangan',
                'gedung_nama' => $item->ruangan?->gedung?->nama ?? 'Gedung',
                'title' => $item->keperluan,
                'tanggal' => Carbon::parse($item->tanggal)->format('Y-m-d'),
                'jam_mulai' => substr((string) $item->jam_mulai, 0, 5),
                'jam_selesai' => substr((string) $item->jam_selesai, 0, 5),
                'penanggung_jawab' => $item->user?->name ?? 'Civitas Kampus',
                'tipe' => 'peminjaman',
                'status' => $item->status,
                'badge_label' => 'Peminjaman SINAPRA',
                'catatan' => $item->catatan_laboran ?? $item->catatan_penolakan,
            ];
        }

        return $items;
    }

    /**
     * Mengambil jadwal perkuliahan aktif dari modul SIAKAD jika tabel tersedia.
     */
    private function getSiakadPerkuliahanEvents(Carbon $startDate, Carbon $endDate, ?int $ruanganId, ?int $gedungId): array
    {
        if (!Schema::hasTable('siakad_kelas')) {
            return [];
        }

        $hasMataKuliah = Schema::hasTable('siakad_mata_kuliah');
        $prodiTable = Schema::hasTable('siakad_program_studi')
            ? 'siakad_program_studi'
            : (Schema::hasTable('spmb_master_program_studi') ? 'spmb_master_program_studi' : null);

        $kelasQuery = DB::table('siakad_kelas')
            ->leftJoin('sinapra_ruangan', 'siakad_kelas.ruangan_id', '=', 'sinapra_ruangan.id')
            ->leftJoin('sinapra_gedung', 'sinapra_ruangan.gedung_id', '=', 'sinapra_gedung.id');

        if ($hasMataKuliah) {
            $kelasQuery->leftJoin('siakad_mata_kuliah', 'siakad_kelas.mata_kuliah_id', '=', 'siakad_mata_kuliah.id');
        }

        if ($prodiTable) {
            $kelasQuery->leftJoin($prodiTable, 'siakad_kelas.program_studi_id', '=', "{$prodiTable}.id");
        }

        $selects = [
            'siakad_kelas.id',
            'siakad_kelas.nama_kelas',
            'siakad_kelas.kode_kelas',
            'siakad_kelas.ruangan_id',
            'siakad_kelas.hari',
            'siakad_kelas.jam_mulai',
            'siakad_kelas.jam_selesai',
            'sinapra_ruangan.nama as ruangan_nama',
            'sinapra_gedung.nama as gedung_nama',
            'sinapra_gedung.id as gedung_id',
        ];

        $selects[] = $hasMataKuliah ? 'siakad_mata_kuliah.nama as mata_kuliah_nama' : DB::raw('NULL as mata_kuliah_nama');
        $selects[] = $prodiTable ? "{$prodiTable}.nama as prodi_nama" : DB::raw('NULL as prodi_nama');

        $kelasQuery->select($selects)
            ->whereNull('siakad_kelas.deleted_at')
            ->whereNotNull('siakad_kelas.ruangan_id')
            ->whereNotNull('siakad_kelas.hari');

        if ($ruanganId) {
            $kelasQuery->where('siakad_kelas.ruangan_id', $ruanganId);
        }

        if ($gedungId) {
            $kelasQuery->where('sinapra_gedung.id', $gedungId);
        }

        $kelasList = $kelasQuery->get();

        if ($kelasList->isEmpty()) {
            return [];
        }

        $items = [];
        $period = CarbonPeriod::create($startDate, $endDate);

        // Petakan kelas ke setiap tanggal dalam rentang yang cocok harinya
        foreach ($period as $date) {
            $dayEnglish = $date->format('l');
            $dayIndo = strtolower(self::HARI_MAP[$dayEnglish] ?? '');

            $matchingKelas = $kelasList->filter(function ($item) use ($dayIndo) {
                return strtolower((string) $item->hari) === $dayIndo;
            });

            foreach ($matchingKelas as $k) {
                $items[] = [
                    'id' => 'siakad-' . $k->id . '-' . $date->format('Y-m-d'),
                    'raw_id' => $k->id,
                    'source' => 'siakad',
                    'ruangan_id' => $k->ruangan_id,
                    'ruangan_nama' => $k->ruangan_nama ?? 'Ruangan',
                    'gedung_nama' => $k->gedung_nama ?? 'Gedung',
                    'title' => ($k->mata_kuliah_nama ?? $k->nama_kelas) . ' (' . $k->kode_kelas . ')',
                    'tanggal' => $date->format('Y-m-d'),
                    'jam_mulai' => substr((string) $k->jam_mulai, 0, 5),
                    'jam_selesai' => substr((string) $k->jam_selesai, 0, 5),
                    'penanggung_jawab' => $k->prodi_nama ?? 'Perkuliahan SIAKAD',
                    'tipe' => 'perkuliahan',
                    'status' => 'terjadwal',
                    'badge_label' => 'Kuliah SIAKAD',
                    'catatan' => 'Jadwal Reguler Semester Aktif',
                ];
            }
        }

        return $items;
    }
}
