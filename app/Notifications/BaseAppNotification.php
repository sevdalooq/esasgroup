<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Uygulama içi (database) + e-posta bildirimlerinin ortak tabanı.
 * Mobil push (FCM) kanalı ileride buraya eklenecek.
 */
abstract class BaseAppNotification extends Notification
{
    use Queueable;

    abstract public function title(): string;

    abstract public function body(): string;

    /** Bildirimin ilgili olduğu kayıt (frontend yönlendirmesi için) */
    public function payload(): array
    {
        return [];
    }

    public function via(object $notifiable): array
    {
        $channels = ['database'];
        $email = method_exists($notifiable, 'routeNotificationForMail')
            ? $notifiable->routeNotificationForMail($this)
            : ($notifiable->email ?? null);

        if ($email) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Esas Grup – ' . $this->title())
            ->greeting('Merhaba ' . ($notifiable->name ?? $notifiable->full_name ?? ''))
            ->line($this->body())
            ->salutation('Esas Grup Yönetim Sistemi');
    }

    public function toArray(object $notifiable): array
    {
        return array_merge([
            'title' => $this->title(),
            'body' => $this->body(),
            'kind' => static::class,
        ], $this->payload());
    }
}
