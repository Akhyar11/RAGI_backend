<?php

namespace App\Models\Sikeu;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DispensasiTagihan extends Model
{
    use HasFactory;

    protected $table = 'sikeu_dispensasi_tagihan';

    protected $fillable = [
        'tagihan_id',
        'mahasiswa_id',
        'tipe_dispensasi',
        'jatuh_tempo_baru',
        'jumlah_cicilan',
        'nominal_per_cicilan',
        'alasan',
        'allow_krs',
        'dokumen_pendukung',
        'status',
        'diajukan_oleh',
        'disetujui_oleh',
        'tanggal_persetujuan',
        'catatan_pimpinan',
        'signature_hash',
    ];

    protected $casts = [
        'allow_krs' => 'boolean',
        'jatuh_tempo_baru' => 'date',
        'nominal_per_cicilan' => 'decimal:2',
        'tanggal_persetujuan' => 'datetime',
    ];

    public function tagihan()
    {
        return $this->belongsTo(TagihanMahasiswa::class, 'tagihan_id');
    }

    public function mahasiswa()
    {
        return $this->belongsTo(\App\Models\Siakad\Mahasiswa::class, 'mahasiswa_id');
    }

    public function tipeTagihanMahasiswa()
    {
        return $this->belongsTo(MahasiswaTipeTagihan::class, 'mahasiswa_id', 'mahasiswa_id');
    }

    /**
     * Ringkasan pembayaran cicilan "beneran": hanya pembayaran sukses yang
     * tercatat SETELAH skema dispensasi disetujui (atau diajukan bila belum
     * disetujui). Pembayaran sebelum dispensasi ada BUKAN cicilan.
     *
     * @return array{count: int, total: float}
     */
    public function cicilanPaymentsSummary(): array
    {
        $this->loadMissing('tagihan.pembayarans');

        $cutoff = $this->tanggal_persetujuan ?? $this->created_at;
        $mark = $cutoff ? \Illuminate\Support\Carbon::parse($cutoff) : null;

        $count = 0;
        $total = 0;
        foreach ($this->tagihan?->pembayarans ?? [] as $p) {
            if ($p->status !== 'success') {
                continue;
            }
            $waktu = $p->waktu_bayar ? \Illuminate\Support\Carbon::parse($p->waktu_bayar) : null;
            if ($mark && $waktu && $waktu->lt($mark)) {
                continue;
            }
            $count++;
            $total += (float) $p->jumlah_bayar;
        }

        return ['count' => $count, 'total' => $total];
    }

    /**
     * Bangun hash tanda tangan digital surat dispensasi.
     * Dipakai saat approval, cetak bukti, dan verifikasi publik QR.
     */
    public static function makeSignatureHash(int $id, $mahasiswaId, $jatuhTempoBaru): string
    {
        if ($jatuhTempoBaru instanceof \DateTimeInterface) {
            $jatuhTempoBaru = $jatuhTempoBaru->format('Y-m-d');
        }
        $secretKey = config('app.key') ?: 'sikeu-signature-salt';
        $raw = hash_hmac('sha256', "DISP-{$id}-{$mahasiswaId}-{$jatuhTempoBaru}", $secretKey);

        return 'SIG-DISP-' . strtoupper(substr($raw, 0, 16));
    }
}
