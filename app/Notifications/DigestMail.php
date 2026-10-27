<?php

namespace App\Notifications;

use App\Models\UserNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Riepilogo giornaliero degli avvisi in modalità "riepilogo" */
class DigestMail extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  list<array{title: string, body: string|null, url: string|null, at: string}>  $items
     */
    public function __construct(public array $items, public int $more = 0) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $count = count($this->items) + $this->more;
        $mail = (new MailMessage)
            ->subject(trans_choice('notifications.digest.subject', $count, ['count' => $count, 'company' => settings('company.name')]))
            ->greeting(trans_choice('notifications.digest.greeting', $count, ['count' => $count]));
        foreach ($this->items as $item) {
            $mail->line('**'.$item['title'].'** · '.$item['at'].($item['body'] ? "  \n".$item['body'] : ''));
        }
        if ($this->more > 0) {
            $mail->line(trans_choice('notifications.digest.more', $this->more, ['count' => $this->more]));
        }

        return $mail->action(__('notifications.digest.open'), route('notifications.index'))
            ->line(__('notifications.mail.change', ['url' => route('notifications.preferences')]))
            ->salutation(__('notifications.mail.salutation'));
    }

    /**
     * Righe del riepilogo dagli avvisi.
     *
     * @param  iterable<UserNotification>  $notifications
     * @return list<array{title: string, body: string|null, url: string|null, at: string}>
     */
    public static function fromNotifications(iterable $notifications): array
    {
        $items = [];
        foreach ($notifications as $n) {
            /** @var UserNotification $n */
            $items[] = ['title' => $n->title, 'body' => $n->body, 'url' => $n->url, 'at' => $n->created_at->translatedFormat('j M H:i')];
        }

        return $items;
    }
}
