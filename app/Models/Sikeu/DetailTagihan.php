<?php

namespace App\Models\Sikeu;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DetailTagihan extends Model
{
    use HasFactory;

    protected $table = 'sikeu_detail_tagihan';

    protected $fillable = [
        'tagihan_id',
        'master_biaya_id',
        'nominal',
        'potongan',
        'nominal_bersih',
        'terbayar',
        'keterangan',
    ];

    protected $casts = [
        'nominal' => 'decimal:2',
        'potongan' => 'decimal:2',
        'nominal_bersih' => 'decimal:2',
        'terbayar' => 'decimal:2',
    ];

    public function tagihan()
    {
        return $this->belongsTo(TagihanMahasiswa::class, 'tagihan_id');
    }

    public function masterBiaya()
    {
        return $this->belongsTo(MasterBiaya::class, 'master_biaya_id');
    }

    /**
     * Sisa per komponen = nominal bersih dikurangi porsi yang sudah terbayar.
     */
    public function getSisaAttribute(): float
    {
        return max(0, (float) $this->nominal_bersih - (float) $this->terbayar);
    }

    /**
     * Alokasikan pembayaran ke rincian secara FIFO (detail tertua dulu).
     * Mengembalikan sisa nominal yang tidak tertampung.
     */
    public static function alokasikan(TagihanMahasiswa $tagihan, float $nominal): float
    {
        $remaining = $nominal;
        $details = $tagihan->relationLoaded('details')
            ? $tagihan->details->sortBy('id')
            : static::where('tagihan_id', $tagihan->id)->orderBy('id')->get();

        foreach ($details as $detail) {
            if ($remaining <= 0) {
                break;
            }
            $capacity = max(0, (float) $detail->nominal_bersih - (float) $detail->terbayar);
            $fill = min($remaining, $capacity);
            if ($fill > 0) {
                $detail->terbayar = (float) $detail->terbayar + $fill;
                $detail->save();
                $remaining -= $fill;
            }
        }

        return $remaining;
    }

    /**
     * Kurangi alokasi pembayaran dari rincian secara LIFO (detail terbaru dulu,
     * dipakai saat koreksi/pengalihan keluar). Mengembalikan sisa yang tidak terkurangi.
     */
    public static function kurangi(TagihanMahasiswa $tagihan, float $nominal): float
    {
        $remaining = $nominal;
        $details = $tagihan->relationLoaded('details')
            ? $tagihan->details->sortByDesc('id')
            : static::where('tagihan_id', $tagihan->id)->orderBy('id', 'desc')->get();

        foreach ($details as $detail) {
            if ($remaining <= 0) {
                break;
            }
            $take = min($remaining, (float) $detail->terbayar);
            if ($take > 0) {
                $detail->terbayar = (float) $detail->terbayar - $take;
                $detail->save();
                $remaining -= $take;
            }
        }

        return $remaining;
    }
}
