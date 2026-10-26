<?php

namespace App\Notifications;

use App\Models\Invitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InvitationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $token, public string $inviterName) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(Invitation $notifiable): MailMessage
    {
        $company = (string) settings('company.name');

        return (new MailMessage)
            ->subject(__('users.invite.mail_subject', ['company' => $company]))
            ->greeting(__('users.invite.mail_greeting', ['name' => $notifiable->name ?: '']))
            ->line(__('users.invite.mail_line', ['inviter' => $this->inviterName, 'company' => $company, 'role' => __('access.roles.'.$notifiable->role)]))
            ->action(__('users.invite.mail_action'), route('invitation.show', ['token' => $this->token]))
            ->line(__('users.invite.mail_expiry', ['days' => Invitation::VALID_DAYS]))
            ->salutation(__('users.invite.mail_salutation'));
    }
}
