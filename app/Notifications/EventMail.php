<?php

namespace App\Notifications;

use App\Enums\NotificationEvent;
use App\Support\Push\PushMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Email di un singolo avviso (modalità "subito") */
class EventMail extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public NotificationEvent $event, public PushMessage $message, public bool $urgent = false) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject(($this->urgent ? '[!] ' : '').$this->message->title.' · '.settings('company.name'))
            ->greeting($this->message->title);
        if ($this->message->body !== '') {
            $mail->line($this->message->body);
        }

        return $mail
            ->action(__('notifications.mail.open'), url($this->message->url))
            ->line(__('notifications.mail.why', ['event' => $this->event->label()]))
            ->line(__('notifications.mail.change', ['url' => route('notifications.preferences')]))
            ->salutation(__('notifications.mail.salutation'));
    }
}
