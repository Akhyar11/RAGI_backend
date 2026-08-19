<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TagihanVaNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $pembayaran;

    /**
     * Create a new notification instance.
     */
    public function __construct($pembayaran)
    {
        $this->pembayaran = $pembayaran;
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
        return (new MailMessage)
                    ->subject('Tagihan Pembayaran Pendaftaran SPMB')
                    ->greeting('Halo, ' . $notifiable->name)
                    ->line('Anda telah memilih gelombang pendaftaran. Berikut adalah detail tagihan Anda:')
                    ->line('Kode Bayar / VA: ' . $this->pembayaran->va_number)
                    ->line('Jumlah Tagihan: Rp ' . number_format($this->pembayaran->jumlah_tagihan, 0, ',', '.'))
                    ->line('Batas Pembayaran: ' . $this->pembayaran->expired_at->format('d M Y H:i'))
                    ->action('Cara Pembayaran', url('/spmb/panduan-bayar'))
                    ->line('Harap segera melakukan pembayaran sebelum batas waktu berakhir.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'pembayaran_id' => $this->pembayaran->id,
            'va_number' => $this->pembayaran->va_number,
        ];
    }
}
