<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PengumumanLulusNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $hasilSeleksi;

    /**
     * Create a new notification instance.
     */
    public function __construct($hasilSeleksi)
    {
        $this->hasilSeleksi = $hasilSeleksi;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $statusMessage = '';
        if ($this->hasilSeleksi->status === \App\Models\Spmb\HasilSeleksi::STATUS_LULUS) {
            $statusMessage = 'SELAMAT! Anda dinyatakan LULUS seleksi administrasi SPMB pada program studi pilihan Anda.';
        } elseif ($this->hasilSeleksi->status === \App\Models\Spmb\HasilSeleksi::STATUS_CADANGAN) {
            $statusMessage = 'Anda masuk dalam daftar CADANGAN. Harap menunggu informasi selanjutnya.';
        } else {
            $statusMessage = 'Mohon maaf, Anda dinyatakan TIDAK LULUS pada periode ini. Anda dapat mencoba kembali pada gelombang berikutnya.';
        }

        $frontendUrl = config('app.frontend_url', 'http://localhost:3000');
        $message = (new MailMessage)
                    ->subject('Pengumuman Hasil Seleksi SPMB')
                    ->greeting('Halo, ' . ($notifiable->name ?? $this->hasilSeleksi->pendaftaran->nama_lengkap ?? 'Calon Mahasiswa'))
                    ->line('Berikut hasil seleksi administrasi SPMB Anda:')
                    ->line($statusMessage);

        if ($this->hasilSeleksi->status === \App\Models\Spmb\HasilSeleksi::STATUS_LULUS) {
            $message->line('Silakan login ke portal untuk melanjutkan proses daftar ulang sesuai jadwal yang tersedia.')
                ->action('Lanjutkan Daftar Ulang', url($frontendUrl . '/spmb/daftar-ulang'));
        } else {
            $message->action('Cek Pengumuman', url($frontendUrl . '/spmb/dashboard'));
        }

        return $message->line('Terima kasih telah mengikuti proses penerimaan mahasiswa baru.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'hasil_seleksi_id' => $this->hasilSeleksi->id,
            'status' => $this->hasilSeleksi->status,
        ];
    }
}
