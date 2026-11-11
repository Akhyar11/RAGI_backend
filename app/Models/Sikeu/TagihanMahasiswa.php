<?php

namespace App\Models\Sikeu;

use App\Models\Siakad\Mahasiswa;
use App\Models\Spmb\MasterTahunAkademik;
use App\Models\Spmb\PendaftaranCalonMhs;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TagihanMahasiswa extends Model
{
    use HasFactory;

    /** Tipe referensi tagihan biaya daftar ulang SPMB. */
    public const TIPE_SPMB_DAFTAR_ULANG = 'spmb_daftar_ulang';

    /** Tipe referensi tagihan biaya pendaftaran SPMB. */
    public const TIPE_SPMB_PENDAFTARAN = 'spmb_pendaftaran';

    protected $table = 'sikeu_tagihan_mahasiswa';

    protected $fillable = [
        'mahasiswa_id',
        'calon_mahasiswa_id',
        'tipe_referensi',
        'tahun_akademik_id',
        'semester',
        'nomor_tagihan',
        'h2h_billing_id',
        'h2h_id_tagihan',
        'h2h_custid',
        'total_tagihan',
        'total_potongan',
        'total_denda',
        'total_bayar',
        'status',
        'requires_approval',
        'status_approval',
        'disetujui_oleh',
        'tanggal_approval',
        'catatan_approval',
        'source_system',
        'payment_gateway_config_id',
        'payment_gateway_name',
        'payment_gateway_environment',
        'koreksi_alokasi_id',
        'jatuh_tempo',
    ];

    protected $casts = [
        'payment_gateway_config_id' => 'integer',
        'semester' => 'integer',        'total_tagihan' => 'decimal:2',
        'total_potongan' => 'decimal:2',
        'total_denda' => 'decimal:2',
        'total_bayar' => 'decimal:2',
        'requires_approval' => 'boolean',
        'tanggal_approval' => 'datetime',
        'jatuh_tempo' => 'date',
    ];

    public function mahasiswa()
    {
        return $this->belongsTo(Mahasiswa::class, 'mahasiswa_id');
    }

    public function paymentGateway()
    {
        return $this->belongsTo(PaymentGatewayConfig::class, 'payment_gateway_config_id');
    }

    public function scopeSpmbStage(Builder $query, int $applicationId, bool $reenrollment = false): Builder
    {
        return $query->where('calon_mahasiswa_id', $applicationId)->where('source_system', 'SPMB')
            ->where(function (Builder $builder) use ($reenrollment): void {
                $builder->where('tipe_referensi', $reenrollment ? self::TIPE_SPMB_DAFTAR_ULANG : self::TIPE_SPMB_PENDAFTARAN);
                if (! $reenrollment) {
                    $builder->orWhereNull('tipe_referensi');
                }
            });
    }

    public function checkouts()
    {
        return $this->hasMany(PaymentCheckout::class, 'tagihan_id');
    }

    public function calonMahasiswa()
    {
        return $this->belongsTo(PendaftaranCalonMhs::class, 'calon_mahasiswa_id');
    }

    public function tahunAkademik()
    {
        return $this->belongsTo(MasterTahunAkademik::class, 'tahun_akademik_id');
    }

    public function tipeTagihanMahasiswa()
    {
        return $this->belongsTo(MahasiswaTipeTagihan::class, 'mahasiswa_id', 'mahasiswa_id');
    }

    public function detailTagihan()
    {
        return $this->hasMany(DetailTagihan::class, 'tagihan_id');
    }

    public function details()
    {
        return $this->hasMany(DetailTagihan::class, 'tagihan_id');
    }

    public function potonganTagihan()
    {
        return $this->hasMany(PotonganTagihan::class, 'tagihan_id');
    }

    public function dendaTagihan()
    {
        return $this->hasMany(DendaTagihan::class, 'tagihan_id');
    }

    public function virtualAccount()
    {
        return $this->hasOne(VirtualAccount::class, 'tagihan_id');
    }

    public function virtualAccounts()
    {
        return $this->hasMany(VirtualAccount::class, 'tagihan_id');
    }

    public function pembayarans()
    {
        return $this->hasMany(Pembayaran::class, 'tagihan_id');
    }

    public function dispensasi()
    {
        return $this->hasMany(DispensasiTagihan::class, 'tagihan_id');
    }

    public function dispensasis()
    {
        return $this->hasMany(DispensasiTagihan::class, 'tagihan_id');
    }
}
