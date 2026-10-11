<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useBootstrapFive();
        // This application uses Sanctum only for first-party session authentication.
        Sanctum::getAccessTokenFromRequestUsing(fn () => null);

        VerifyEmail::toMailUsing(fn ($user, $url) => (new MailMessage)
            ->subject('Emailcím megerősítése')->greeting('Kedves '.$user->name.'!')
            ->line('A regisztráció folytatásához erősítsd meg az emailcímedet.')
            ->action('Emailcím megerősítése', $url)
            ->line('Ha nem te regisztráltál, nincs teendőd.')->salutation('Edzések v2')
        );
        ResetPassword::toMailUsing(fn ($user, $token) => (new MailMessage)
            ->subject('Jelszó visszaállítása')->greeting('Kedves '.$user->name.'!')
            ->line('Jelszó-visszaállítási kérést kaptunk a fiókodhoz.')
            ->action('Jelszó visszaállítása', route('password.reset', ['token' => $token, 'email' => $user->email]))
            ->line('A link '.config('auth.passwords.users.expire').' percig érvényes.')
            ->line('Ha nem te kérted, nincs teendőd.')->salutation('Edzések v2')
        );
    }
}
