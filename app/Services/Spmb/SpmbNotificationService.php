<?php

namespace App\Services\Spmb;

use App\Models\Module;
use App\Models\Sikeu\TagihanMahasiswa;
use App\Models\Sikeu\VirtualAccount;
use App\Models\Spmb\HasilSeleksi;
use App\Models\Spmb\PendaftaranCalonMhs;
use App\Services\Notifications\SystemNotificationService;

class SpmbNotificationService
{
    public const MODULE_CODE = 'spmb';

    public const SOURCE_SYSTEM = 'SPMB';

    public const APPLICATION_URL = '/spmb/dashboard';

    private const VA_ACTIVE = 'aktif';

    private const PAYMENT_PAID = 'lunas';

    public function __construct(private readonly SystemNotificationService $inbox) {}

    public function applicationChanged(PendaftaranCalonMhs $application): void
    {
        if (! $application->wasRecentlyCreated && ! $application->wasChanged('status')) {
            return;
        }

        $content = match ($application->status) {
            PendaftaranCalonMhs::STATUS_SUBMITTED => ['Pendaftaran SPMB berhasil dikirim', 'Pendaftaran Anda menunggu verifikasi administrasi.'],
            PendaftaranCalonMhs::STATUS_VERIFIED,
            PendaftaranCalonMhs::STATUS_LULUS_ADMINISTRASI => ['Berkas pendaftaran telah diverifikasi', 'Pendaftaran Anda lolos verifikasi administrasi.'],
            PendaftaranCalonMhs::STATUS_GAGAL_ADMINISTRASI => ['Pendaftaran belum lolos verifikasi administrasi', $application->catatan_verifikasi ?: 'Silakan hubungi panitia SPMB.'],
            default => null,
        };
        if ($content) {
            $this->send($application, 'application_'.$application->status, ...$content);
        }
    }

    public function resultPublished(HasilSeleksi $result): void
    {
        if (! $result->diumumkan_at || $result->diumumkan_at->isFuture()) {
            return;
        }
        $application = $result->pendaftaranCalonMhs;
        if (! $application) {
            return;
        }
        $decision = match ($result->status) {
            HasilSeleksi::STATUS_LULUS => 'Anda dinyatakan lulus seleksi. Silakan melanjutkan daftar ulang.',
            HasilSeleksi::STATUS_CADANGAN => 'Anda berada dalam daftar cadangan. Pantau pembaruan berikutnya.',
            default => 'Anda dinyatakan tidak lulus seleksi. Silakan hubungi panitia untuk informasi lebih lanjut.',
        };
        $this->send($application, 'result_published', 'Hasil seleksi SPMB telah diumumkan', $decision,
            $result->status.'/'.($result->program_studi_diterima_id ?? 0), HasilSeleksi::class, $result->id);
    }

    public function reenrollmentChanged(HasilSeleksi $result): void
    {
        if (! $result->wasRecentlyCreated && ! $result->wasChanged('status_daftar_ulang')) {
            return;
        }
        $content = match ($result->status_daftar_ulang) {
            HasilSeleksi::STATUS_DAFTAR_ULANG_MENUNGGU_PEMBAYARAN => ['Daftar ulang menunggu pembayaran', 'Tagihan daftar ulang tersedia. Silakan selesaikan pembayaran.'],
            HasilSeleksi::STATUS_DAFTAR_ULANG_LUNAS => ['Daftar ulang SPMB selesai', 'Pembayaran daftar ulang Anda telah dikonfirmasi lunas.'],
            default => null,
        };
        if ($content && $result->pendaftaranCalonMhs) {
            $this->send($result->pendaftaranCalonMhs, 'reenrollment_'.$result->status_daftar_ulang, ...$content);
        }
    }

    public function virtualAccountChanged(VirtualAccount $account): void
    {
        if ($account->status !== self::VA_ACTIVE || (! $account->wasRecentlyCreated && ! $account->wasChanged(['va_number', 'expired_at', 'status']))) {
            return;
        }
        $bill = $account->tagihan;
        if (! $this->isSpmbBill($bill) || $bill->status === self::PAYMENT_PAID) {
            return;
        }
        $this->send($bill->calonMahasiswa, 'billing_va_issued', 'Tagihan dan Virtual Account SPMB tersedia',
            'Tagihan '.$bill->nomor_tagihan.' tersedia. Lihat nomor VA, nominal, dan batas pembayaran di portal.',
            $account->va_number.'/'.$account->expired_at?->getTimestamp(), TagihanMahasiswa::class, $bill->id);
    }

    public function billChanged(TagihanMahasiswa $bill): void
    {
        if ($bill->status !== self::PAYMENT_PAID || (! $bill->wasRecentlyCreated && ! $bill->wasChanged('status')) || ! $this->isSpmbBill($bill)) {
            return;
        }
        $this->send($bill->calonMahasiswa, 'payment_paid', 'Pembayaran SPMB telah lunas',
            'Pembayaran tagihan '.$bill->nomor_tagihan.' telah dikonfirmasi lunas.', '', TagihanMahasiswa::class, $bill->id);
    }

    private function isSpmbBill(?TagihanMahasiswa $bill): bool
    {
        return $bill && $bill->source_system === self::SOURCE_SYSTEM && $bill->calonMahasiswa !== null;
    }

    private function send(PendaftaranCalonMhs $application, string $type, string $title, string $message,
        string $state = '', string $entityType = PendaftaranCalonMhs::class, ?int $entityId = null): void
    {
        $user = $application->user;
        if (! $user) {
            return;
        }
        $entityId ??= $application->id;
        $this->inbox->create($user, [
            'module_id' => Module::query()->where('code', self::MODULE_CODE)->value('id'),
            'type' => $type,
            'title' => $title,
            'message' => $application->no_pendaftaran.': '.$message,
            'url' => self::APPLICATION_URL,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'dedup_key' => hash('sha256', $entityType.'/'.$entityId.'/'.$type.'/'.$state),
        ]);
    }
}
