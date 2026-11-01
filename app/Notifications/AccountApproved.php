<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AccountApproved extends Notification
{
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject('Fiókod jóváhagyva')
            ->greeting('Kedves '.$notifiable->name.'!')
            ->line('Az admin jóváhagyta a fiókodat. Most már használhatod az alkalmazást.')
            ->action('Alkalmazás megnyitása', route('welcome'))
            ->salutation(config('app.public_name'));
    }
}
