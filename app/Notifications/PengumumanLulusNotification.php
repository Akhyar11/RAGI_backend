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
        if ($this->hasilSeleksi->status == 'lulus') {
            $statusMessage = 'SELAMAT! Anda dinyatakan LULUS pada program studi pilihan Anda.';
        } elseif ($this->hasilSeleksi->status == 'cadangan') {
            $statusMessage = 'Anda masuk dalam daftar CADANGAN. Harap menunggu informasi selanjutnya.';
        } else {
            $statusMessage = 'Mohon maaf, Anda dinyatakan TIDAK LULUS. Jangan patah semangat dan coba lagi di gelombang berikutnya.';
        }

        return (new MailMessage)
                    ->subject('Pengumuman Hasil Seleksi SPMB')
                    ->greeting('Halo, ' . $notifiable->name)
                    ->line('Berdasarkan hasil evaluasi dan seleksi yang telah dilakukan, berikut adalah pengumuman hasil ujian Anda:')
                    ->line($statusMessage)
                    ->action('Cek Detail Pengumuman', url('/spmb/pengumuman'))
                    ->line('Terima kasih telah berpartisipasi dalam proses seleksi ini.');
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
