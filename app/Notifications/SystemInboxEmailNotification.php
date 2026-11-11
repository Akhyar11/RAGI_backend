<?php

namespace App\Notifications;

use App\Models\CoreNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SystemInboxEmailNotification extends Notification implements ShouldQueueAfterCommit
{
    use Queueable;

    public function __construct(private readonly CoreNotification $notification)
    {
        $this->onQueue('notifications');
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject($this->notification->title)
            ->greeting('Halo '.($notifiable->name ?: $notifiable->username))
            ->line($this->notification->message ?? 'Ada pembaruan pada pendaftaran Anda.');

        if ($this->notification->url) {
            $frontendUrl = rtrim((string) config('app.frontend_url', config('app.url')), '/');
            $message->action('Lihat detail', $frontendUrl.$this->notification->url);
        }

        return $message->line('Email ini dikirim otomatis oleh Sistem Terintegrasi Kampus.');
    }
}
