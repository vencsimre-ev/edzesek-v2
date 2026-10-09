<?php

namespace App\Console\Commands;

use App\Actions\CreateAdminUser;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

class CreateAdmin extends Command
{
    protected $signature = 'app:create-admin';

    protected $description = 'Új, megerősített és jóváhagyott admin létrehozása';

    public function handle(): int
    {
        $input = [
            'name' => $this->ask('Név'),
            'email' => mb_strtolower(trim((string) $this->ask('Emailcím'))),
            'password' => $this->secret('Jelszó'),
            'password_confirmation' => $this->secret('Jelszó megerősítése'),
        ];
        try {
            app(CreateAdminUser::class)->create($input);
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $messages) {
                foreach ($messages as $message) {
                    $this->error($message);
                }
            }

            return self::FAILURE;
        }

        $this->info('A megerősített és jóváhagyott admin létrejött.');

        return self::SUCCESS;
    }
}
