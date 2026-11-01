<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UserAwaitingApproval extends Notification
{
    public function __construct(public User $student) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject('Új felhasználó vár jóváhagyásra')
            ->greeting('Kedves '.$notifiable->name.'!')
            ->line($this->student->name.' ('.$this->student->email.') megerősítette az emailcímét.')
            ->action('Jóváhagyások megnyitása', route('admin.approvals.index'))
            ->salutation(config('app.public_name'));
    }
}
