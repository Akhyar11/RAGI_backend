<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Kolom id_feeder yang menjadi IDENTITAS entitas Neo Feeder dibuat unik.
 * Sebelum indeks dibuat, baris duplikat digabung: rujukan dipindah ke baris yang bertahan, lalu duplikat dihapus.
 *
 * Sengaja TIDAK unik (referensi/atribut): siakad_dosen_penugasan.id_feeder (id_registrasi_dosen, berulang per tahun akademik),
 * siakad_mahasiswa.id_feeder_riwayat, siakad_feeder_mappings.feeder_id.
 * Tidak perlu: siakad_kurikulum dan siakad_tahun_akademik sudah unik.
 */
return new class extends Migration
{
    /** @var array<string, list<string>> tabel => kolom identitas */
    private array $identityColumns = [
        'siakad_program_studi' => ['id_feeder'],
        'siakad_mata_kuliah' => ['id_feeder'],
        'siakad_dosen' => ['id_feeder'],
        'siakad_mahasiswa' => ['id_feeder', 'id_feeder_biodata'],
        'siakad_jadwal_kelas' => ['id_feeder'],
        'siakad_dosen_pengampu' => ['id_feeder'],
    ];

    /** @var array<string, list<string>> kolom milik baris duplikat yang dibawa ke baris bertahan bila di sana masih kosong */
    private array $carryColumns = [
        'siakad_program_studi' => ['source_id', 'source_kode_prodi'],
    ];

    /** @var array<string, list<string>> kolom rujukan tanpa foreign key yang tetap harus dipindahkan */
    private array $extraReferenceColumns = [
        'siakad_program_studi' => ['program_studi_id', 'prodi_id'],
    ];

    public function up(): void
    {
        foreach ($this->identityColumns as $table => $columns) {
            if (!Schema::hasTable($table)) {
                continue;
            }
            foreach ($columns as $column) {
                if (!Schema::hasColumn($table, $column)) {
                    continue;
                }
                DB::table($table)->where($column, '')->update([$column => null]);
                $this->deduplicate($table, $column);
            }
        }

        foreach ($this->identityColumns as $table => $columns) {
            if (!Schema::hasTable($table)) {
                continue;
            }
            foreach ($columns as $column) {
                if (!Schema::hasColumn($table, $column) || $this->hasUniqueIndex($table, $column)) {
                    continue;
                }
                Schema::table($table, function (Blueprint $blueprint) use ($table, $column) {
                    $blueprint->unique($column, $this->indexName($table, $column));
                });
            }
        }
    }

    public function down(): void
    {
        // Baris duplikat yang sudah dihapus tidak dapat dipulihkan; hanya indeks yang dilepas.
        foreach ($this->identityColumns as $table => $columns) {
            if (!Schema::hasTable($table)) {
                continue;
            }
            foreach ($columns as $column) {
                $name = $this->indexName($table, $column);
                $exists = collect(Schema::getIndexes($table))->contains(fn (array $index) => $index['name'] === $name);
                if ($exists) {
                    Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropUnique($name));
                }
            }
        }
    }

    private function indexName(string $table, string $column): string
    {
        return "{$table}_{$column}_unique";
    }

    private function hasUniqueIndex(string $table, string $column): bool
    {
        return collect(Schema::getIndexes($table))
            ->contains(fn (array $index) => ($index['unique'] ?? false) && $index['columns'] === [$column]);
    }

    private function deduplicate(string $table, string $column): void
    {
        $values = DB::table($table)->whereNotNull($column)
            ->select($column)->groupBy($column)->havingRaw('count(*) > 1')->pluck($column);

        foreach ($values as $value) {
            DB::transaction(function () use ($table, $column, $value): void {
                $rows = DB::table($table)->where($column, $value)->get();
                $survivor = $this->chooseSurvivor($rows);
                $losers = $rows->filter(fn ($row) => (int) $row->id !== (int) $survivor->id)->values();

                $carried = [];
                foreach ($this->carryColumns[$table] ?? [] as $carryColumn) {
                    if (!property_exists($survivor, $carryColumn) || filled($survivor->{$carryColumn})) {
                        continue;
                    }
                    foreach ($losers as $loser) {
                        if (filled($loser->{$carryColumn})) {
                            $carried[$carryColumn] = $loser->{$carryColumn};
                            break;
                        }
                    }
                }

                $moved = [];
                foreach ($losers as $loser) {
                    foreach ($this->repointReferences($table, (int) $loser->id, (int) $survivor->id) as $ref => $count) {
                        $moved[$ref] = ($moved[$ref] ?? 0) + $count;
                    }
                }

                Log::warning('Migrasi unique id_feeder: duplikat digabung.', [
                    'table' => $table,
                    'column' => $column,
                    'value' => $value,
                    'survivor_id' => $survivor->id,
                    'deleted_rows' => $losers->map(fn ($row) => (array) $row)->all(),
                    'references_moved' => $moved,
                    'carried' => $carried,
                ]);

                DB::table($table)->whereIn('id', $losers->pluck('id')->all())->delete();
                if ($carried !== []) {
                    DB::table($table)->where('id', $survivor->id)->update($carried);
                }
            });
        }
    }

    /** Baris bertahan: tidak soft-delete, lalu aktif, lalu id terkecil. */
    private function chooseSurvivor($rows): object
    {
        return $rows->sort(function ($a, $b) {
            $aDeleted = property_exists($a, 'deleted_at') && $a->deleted_at !== null;
            $bDeleted = property_exists($b, 'deleted_at') && $b->deleted_at !== null;
            if ($aDeleted !== $bDeleted) {
                return $aDeleted <=> $bDeleted;
            }
            $aActive = property_exists($a, 'is_active') ? (int) $a->is_active : 0;
            $bActive = property_exists($b, 'is_active') ? (int) $b->is_active : 0;
            if ($aActive !== $bActive) {
                return $bActive <=> $aActive;
            }

            return (int) $a->id <=> (int) $b->id;
        })->first();
    }

    /**
     * Pindahkan rujukan ke baris $from menuju $to pada semua tabel (foreign key + kolom tambahan tanpa FK).
     *
     * @return array<string, int> "tabel.kolom" => jumlah baris dipindah
     */
    private function repointReferences(string $table, int $from, int $to): array
    {
        $columnsByTable = [];
        foreach (Schema::getTables() as $info) {
            $name = $info['name'];
            if ($name === $table || !Schema::hasTable($name)) {
                continue;
            }
            foreach (Schema::getForeignKeys($name) as $foreignKey) {
                if ($foreignKey['foreign_table'] === $table && $foreignKey['foreign_columns'] === ['id']) {
                    foreach ($foreignKey['columns'] as $column) {
                        $columnsByTable[$name][$column] = true;
                    }
                }
            }
            foreach ($this->extraReferenceColumns[$table] ?? [] as $column) {
                if (Schema::hasColumn($name, $column)) {
                    $columnsByTable[$name][$column] = true;
                }
            }
        }

        $moved = [];
        foreach ($columnsByTable as $name => $columns) {
            foreach (array_keys($columns) as $column) {
                $count = DB::table($name)->where($column, $from)->update([$column => $to]);
                if ($count > 0) {
                    $moved["{$name}.{$column}"] = $count;
                }
            }
        }

        return $moved;
    }
};
