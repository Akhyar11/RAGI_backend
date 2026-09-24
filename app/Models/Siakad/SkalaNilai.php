<?php

namespace App\Models\Siakad;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SkalaNilai extends Model
{
    use SoftDeletes;

    protected $table = 'siakad_skala_nilai';

    protected $fillable = [
        'program_studi_id',
        'nilai_huruf',
        'bobot_indeks',
        'batas_bawah',
        'batas_atas',
        'is_lulus',
        'keterangan',
        'is_active',
    ];

    protected $casts = [
        'bobot_indeks' => 'decimal:2',
        'batas_bawah' => 'decimal:2',
        'batas_atas' => 'decimal:2',
        'is_lulus' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function programStudi()
    {
        return $this->belongsTo(ProgramStudi::class, 'program_studi_id');
    }

    /**
     * Cari nilai huruf & bobot indeks berdasarkan nilai angka (0 - 100).
     */
    public static function konversiNilai(float $nilaiAngka, ?int $prodiId = null): ?self
    {
        $query = static::where('is_active', true)
            ->where('batas_bawah', '<=', $nilaiAngka)
            ->where('batas_atas', '>=', $nilaiAngka);

        if ($prodiId) {
            $khusus = (clone $query)->where('program_studi_id', $prodiId)->orderBy('bobot_indeks', 'desc')->first();
            if ($khusus) {
                return $khusus;
            }
        }

        return $query->whereNull('program_studi_id')->orderBy('bobot_indeks', 'desc')->first();
    }
}
