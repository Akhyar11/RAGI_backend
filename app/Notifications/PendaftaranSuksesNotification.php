<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PendaftaranSuksesNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $pendaftaran;

    /**
     * Create a new notification instance.
     */
    public function __construct($pendaftaran)
    {
        $this->pendaftaran = $pendaftaran;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail']; // Menggunakan EMAIL sesuai permintaan
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
                    ->subject('Pendaftaran SPMB Berhasil')
                    ->greeting('Halo, ' . $this->pendaftaran->nama_lengkap)
                    ->line('Selamat! Akun pendaftaran SPMB Anda telah berhasil dibuat.')
                    ->line('Nomor Pendaftaran Anda: ' . $this->pendaftaran->no_pendaftaran)
                    ->action('Login ke Portal', url('/spmb/login'))
                    ->line('Terima kasih telah mendaftar di kampus kami.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'pendaftaran_id' => $this->pendaftaran->id,
            'no_pendaftaran' => $this->pendaftaran->no_pendaftaran,
        ];
    }
}
